<?php

declare(strict_types=1);

/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\Deck;
use App\Entity\DeckVersion;
use App\Entity\Event;
use App\Entity\EventDeckEntry;
use App\Entity\EventDeckRegistration;
use App\Entity\EventEngagement;
use App\Entity\EventStaff;
use App\Entity\EventTag;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Enum\DeckStatus;
use App\Enum\EngagementState;
use App\Enum\EventVisibility;
use App\Enum\ParticipationMode;
use App\Form\EventFormType;
use App\Message\CancelEventBorrowsMessage;
use App\Message\FinishEventBorrowsMessage;
use App\Message\StartEndingPhaseMessage;
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventDeckEntryRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Repository\EventStaffRepository;
use App\Repository\EventTagRepository;
use App\Repository\UserRepository;
use App\Service\BorrowService;
use App\Service\EventNotificationService;
use App\Service\StaffCustodyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F3.1 — Create a new event
 * @see docs/features.md F3.3 — Event detail view
 * @see docs/features.md F3.4 — Register participation to an event
 * @see docs/features.md F3.5 — Assign event staff team
 * @see docs/features.md F3.7 — Register played deck for event
 * @see docs/features.md F3.9 — Edit an event
 * @see docs/features.md F3.10 — Cancel an event
 * @see docs/features.md F3.13 — Player engagement states
 * @see docs/features.md F4.8 — Staff-delegated lending
 * @see docs/features.md F3.21 — Clear deck selection on withdrawal
 * @see docs/features.md F4.9 — Staff deck custody tracking
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
#[Route('/event')]
#[IsGranted('ROLE_USER')]
class EventController extends AbstractAppController
{
    /**
     * @see docs/features.md F3.1 — Create a new event
     */
    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function new(Request $request, EntityManagerInterface $em, EventTagRepository $tagRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $event = new Event();
        $event->setTimezone($user->getTimezone());

        $form = $this->createForm(EventFormType::class, $event, [
            'event_timezone' => $event->getTimezone(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setOrganizer($user);
            $event->setFormat('Expanded');
            $this->applyTagsFromForm($event, $form, $tagRepository);
            $em->persist($event);
            $em->flush();

            $this->addFlash('success', 'app.flash.event.created', ['%name%' => $event->getName()]);

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/new.html.twig', [
            'form' => $form,
            'existingTagNames' => $this->collectExistingTagNames($tagRepository),
            'initialTagNames' => $this->collectEventTagNames($event),
        ]);
    }

    /**
     * @see docs/features.md F3.3 — Event detail view
     * @see docs/features.md F3.7 — Register played deck for event
     * @see docs/features.md F3.11 — Event visibility
     */
    #[Route('/{id}', name: 'app_event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Event $event,
        BorrowRepository $borrowRepository,
        DeckRepository $deckRepository,
        EventDeckEntryRepository $entryRepository,
        EventDeckRegistrationRepository $registrationRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Draft/Private events: only visible to organizer, staff, invited users, and admins
        if (EventVisibility::Public !== $event->getVisibility()
            && !$event->isOrganizerOrStaff($user)
            && !$this->isGranted('ROLE_ADMIN')) {
            $engagement = $event->getEngagementFor($user);
            if (null === $engagement || null === $engagement->getInvitedBy()) {
                throw $this->createAccessDeniedException();
            }
        }

        $userEngagement = $event->getEngagementFor($user);
        $isParticipant = null !== $userEngagement;

        // Build registration map first — needed for both "Your Decks" table and playability filter
        $deckRegistrationMap = [];
        foreach ($registrationRepository->findByEventAndOwner($event, $user) as $reg) {
            $deckId = $reg->getDeck()->getId();
            if (null !== $deckId) {
                $deckRegistrationMap[$deckId] = [
                    'registered' => true,
                    'delegated' => $reg->isDelegateToStaff(),
                    'registrationId' => $reg->getId(),
                    'staffReceivedAt' => $reg->getStaffReceivedAt(),
                    'staffReturnedAt' => $reg->getStaffReturnedAt(),
                ];
            }
        }

        $playableOwnDecks = [];
        $currentDeckEntry = null;
        $canChangeDeck = false;

        $deckBorrowBlockMap = [];
        $deckPendingBorrowCountMap = [];

        if ($isParticipant) {
            $playableOwnDecks = array_filter(
                $deckRepository->findByOwner($user),
                static fn (Deck $deck): bool => DeckStatus::Retired !== $deck->getStatus()
                    && null !== $deck->getCurrentVersion(),
            );
            $playableOwnDecks = array_values($playableOwnDecks);

            // Build borrow conflict maps for own decks at this event
            foreach ($playableOwnDecks as $deck) {
                $deckId = $deck->getId();
                if (null === $deckId) {
                    continue;
                }

                if (null !== $borrowRepository->findBlockingBorrowForDeckAtEvent($deck, $event)) {
                    $deckBorrowBlockMap[$deckId] = true;
                }

                $pendingCount = \count($borrowRepository->findAllPendingBorrowsForDeckAtEvent($deck, $event));
                if ($pendingCount > 0) {
                    $deckPendingBorrowCountMap[$deckId] = $pendingCount;
                }
            }

            $currentDeckEntry = $entryRepository->findOneByEventAndPlayer($event, $user);
            $canChangeDeck = !$currentDeckEntry || $event->getDate() > new \DateTimeImmutable();
        }

        // Delegation: show owner's decks with version (eligible for registration)
        $ownedDecksWithVersion = array_filter(
            $deckRepository->findByOwner($user),
            static fn (Deck $deck): bool => null !== $deck->getCurrentVersion()
                && DeckStatus::Retired !== $deck->getStatus()
                && $deck->isEventRegisterable(),
        );
        $ownedDecksWithVersion = array_values($ownedDecksWithVersion);

        $isStaff = $event->isOrganizerOrStaff($user);

        $delegatedRegistrations = $isStaff ? $registrationRepository->findDelegatedByEvent($event) : [];

        // Ending phase banner data
        $endingPhaseLentBorrows = 0;
        $endingPhaseOwnerStats = ['inCustody' => 0, 'stillOut' => 0];
        $endingPhaseGlobalStats = ['returned' => 0, 'stillOut' => 0];

        if (null !== $event->getEndingPhaseAt() && null === $event->getFinishedAt()) {
            // Count user's lent borrows as borrower
            $lentBorrows = $borrowRepository->findLentBorrowsByEvent($event);
            foreach ($lentBorrows as $borrow) {
                if ($borrow->getBorrower()->getId() === $user->getId()) {
                    ++$endingPhaseLentBorrows;
                }
            }

            // Owner stats: decks in custody vs still out
            $userId = $user->getId();
            foreach ($lentBorrows as $borrow) {
                if ($borrow->getDeck()->getOwnerOrFail()->getId() === $userId) {
                    ++$endingPhaseOwnerStats['stillOut'];
                }
            }
            $custodyBorrows = $borrowRepository->findInCustodyBorrowsByEvent($event);
            foreach ($custodyBorrows as $borrow) {
                if ($borrow->getDeck()->getOwnerOrFail()->getId() === $userId) {
                    ++$endingPhaseOwnerStats['inCustody'];
                }
            }

            // Global stats for organizer/staff
            if ($isStaff) {
                $endingPhaseGlobalStats['stillOut'] = \count($lentBorrows);
                $endingPhaseGlobalStats['returned'] = \count($custodyBorrows);
            }
        }

        return $this->render('event/show.html.twig', [
            'event' => $event,
            'isOrganizer' => $event->getOrganizer()->getId() === $user->getId(),
            'isStaff' => $isStaff,
            'userEngagement' => $userEngagement,
            'isParticipant' => $isParticipant,
            'eventBorrows' => $borrowRepository->findByEventForUser($event, $user),
            'playableOwnDecks' => $playableOwnDecks,
            'currentDeckEntry' => $currentDeckEntry,
            'canChangeDeck' => $canChangeDeck,
            'deckBorrowBlockMap' => $deckBorrowBlockMap,
            'deckPendingBorrowCountMap' => $deckPendingBorrowCountMap,
            'ownedDecksWithVersion' => $ownedDecksWithVersion,
            'deckRegistrationMap' => $deckRegistrationMap,
            'delegatedBorrows' => $isStaff ? $borrowRepository->findDelegatedBorrowsByEvent($event) : [],
            'delegatedRegistrations' => $delegatedRegistrations,
            'hasResults' => null !== $event->getFinishedAt() ? $entryRepository->hasResults($event) : false,
            'endingPhaseLentBorrows' => $endingPhaseLentBorrows,
            'endingPhaseOwnerStats' => $endingPhaseOwnerStats,
            'endingPhaseGlobalStats' => $endingPhaseGlobalStats,
        ]);
    }

    /**
     * Browse decks available to borrow for this event.
     *
     * @see docs/features.md F4.1 — Request to borrow a deck
     */
    #[Route('/{id}/decks', name: 'app_event_decks', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function availableDecks(Event $event, DeckRepository $deckRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt() || null !== $event->getEndingPhaseAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_browse_cancelled');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $userEngagement = $event->getEngagementFor($user);

        if (null === $userEngagement) {
            $this->addFlash('warning', 'app.flash.event.register_to_browse');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/available_decks.html.twig', [
            'event' => $event,
            'availableDecks' => $deckRepository->findAvailableForEvent($event, $user),
        ]);
    }

    /**
     * @see docs/features.md F3.7 — Register played deck for event
     */
    #[Route('/{id}/select-deck', name: 'app_event_select_deck', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function selectDeck(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        DeckRepository $deckRepository,
        BorrowRepository $borrowRepository,
        BorrowService $borrowService,
        EventDeckEntryRepository $entryRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('select-deck-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null === $event->getEngagementFor($user)) {
            $this->addFlash('warning', 'app.flash.event.must_be_participant');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt()) {
            $this->addFlash('warning', 'app.flash.event.selection_not_available');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $deckId = $request->getPayload()->getInt('deck_id');
        $existingEntry = $entryRepository->findOneByEventAndPlayer($event, $user);

        // Allow first selection even after event start; block changes only when already selected
        if (null !== $existingEntry && $event->getDate() <= new \DateTimeImmutable()) {
            $this->addFlash('warning', 'app.flash.event.selection_locked');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (0 === $deckId) {
            if (null !== $existingEntry) {
                $em->remove($existingEntry);
                $em->flush();
            }

            $this->addFlash('success', 'app.flash.event.selection_cleared');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $deck = $deckRepository->find($deckId);

        if (null === $deck) {
            $this->addFlash('danger', 'app.flash.deck_not_found');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $currentVersion = $this->resolvePlayableDeckVersion($deck, $user, $event, $borrowRepository);

        if (null === $currentVersion) {
            $this->addFlash('danger', 'app.flash.event.deck_not_available');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Cancel pending borrows for own deck when owner selects it for themselves
        if ($deck->getOwnerOrFail()->getId() === $user->getId()) {
            $pendingBorrows = $borrowRepository->findAllPendingBorrowsForDeckAtEvent($deck, $event);

            if ([] !== $pendingBorrows && '1' !== $request->getPayload()->getString('confirm_cancel_borrows')) {
                $this->addFlash('warning', 'app.flash.event.pending_borrows_not_confirmed');

                return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
            }

            foreach ($pendingBorrows as $pendingBorrow) {
                $borrowService->cancel($pendingBorrow, $user);
            }
        }

        if (null !== $existingEntry) {
            $em->remove($existingEntry);
            $em->flush();
        }

        $entry = new EventDeckEntry();
        $entry->setEvent($event);
        $entry->setPlayer($user);
        $entry->setDeckVersion($currentVersion);

        $em->persist($entry);
        $em->flush();

        $this->addFlash('success', 'app.flash.event.playing_with', ['%name%' => $deck->getName()]);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.9 — Edit an event
     */
    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function edit(Event $event, Request $request, EntityManagerInterface $em, EventNotificationService $notificationService, EventTagRepository $tagRepository): Response
    {
        $this->denyAccessUnlessOrganizer($event);

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_edit_cancelled');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $form = $this->createForm(EventFormType::class, $event, [
            'event_timezone' => $event->getTimezone(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setFormat('Expanded');
            $this->applyTagsFromForm($event, $form, $tagRepository);
            $em->flush();

            $notificationService->notifyEventUpdated($event);

            $this->addFlash('success', 'app.flash.event.updated', ['%name%' => $event->getName()]);

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
            'existingTagNames' => $this->collectExistingTagNames($tagRepository),
            'initialTagNames' => $this->collectEventTagNames($event),
        ]);
    }

    /**
     * @see docs/features.md F3.10 — Cancel an event
     */
    #[Route('/{id}/cancel', name: 'app_event_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function cancel(Event $event, Request $request, EntityManagerInterface $em, MessageBusInterface $messageBus, EventNotificationService $notificationService): Response
    {
        $this->denyAccessUnlessOrganizer($event);

        if (!$this->isCsrfTokenValid('cancel-event-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'app.flash.event.already_cancelled');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $event->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $messageBus->dispatch(new CancelEventBorrowsMessage((int) $event->getId()));
        $notificationService->notifyEventCancelled($event);

        $this->addFlash('success', 'app.flash.event.cancelled', ['%name%' => $event->getName()]);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * Initiate handover: organizer picks a target user. The target must
     * accept on the event page before the transfer takes effect.
     *
     * @see docs/features.md F3.23 — Organizer handover
     */
    #[Route('/{id}/transfer/initiate', name: 'app_event_transfer_initiate', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function transferInitiate(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        EventNotificationService $notificationService,
    ): Response {
        $this->denyAccessUnlessOrganizer($event);

        if (!$this->isCsrfTokenValid('event-transfer-initiate-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $targetQuery = trim($request->getPayload()->getString('target'));

        if ('' === $targetQuery) {
            $this->addFlash('warning', 'app.flash.event.transfer.target_required');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $target = $userRepository->findByMultiField($targetQuery);

        /** @var User $currentOrganizer */
        $currentOrganizer = $this->getUser();

        if (null === $target || $target->getId() === $currentOrganizer->getId()) {
            $this->addFlash('warning', 'app.flash.event.transfer.target_invalid');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $event->requestTransferTo($target);
        $em->flush();

        $notificationService->notifyTransferRequested($event, $target, $currentOrganizer);

        $this->addFlash('success', 'app.flash.event.transfer.initiated', ['%name%' => $target->getScreenName()]);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * Current organizer cancels a pending handover.
     *
     * @see docs/features.md F3.23 — Organizer handover
     */
    #[Route('/{id}/transfer/cancel', name: 'app_event_transfer_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function transferCancel(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOrganizer($event);

        if (!$this->isCsrfTokenValid('event-transfer-cancel-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (!$event->hasPendingTransfer()) {
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $event->clearPendingTransfer();
        $em->flush();

        $this->addFlash('success', 'app.flash.event.transfer.cancelled');

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * Target accepts the handover — they become the new organizer.
     *
     * @see docs/features.md F3.23 — Organizer handover
     */
    #[Route('/{id}/transfer/accept', name: 'app_event_transfer_accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function transferAccept(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        EventNotificationService $notificationService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessTransferTarget($event, $user);

        if (!$this->isCsrfTokenValid('event-transfer-accept-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $previousOrganizer = $event->getOrganizer();
        $event->setOrganizer($user);
        $event->clearPendingTransfer();
        $em->flush();

        $notificationService->notifyTransferAccepted($event, $previousOrganizer, $user);

        $this->addFlash('success', 'app.flash.event.transfer.accepted', ['%name%' => $event->getName()]);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * Target declines the handover — the previous organizer stays.
     *
     * @see docs/features.md F3.23 — Organizer handover
     */
    #[Route('/{id}/transfer/decline', name: 'app_event_transfer_decline', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function transferDecline(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        EventNotificationService $notificationService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessTransferTarget($event, $user);

        if (!$this->isCsrfTokenValid('event-transfer-decline-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $organizer = $event->getOrganizer();
        $event->clearPendingTransfer();
        $em->flush();

        $notificationService->notifyTransferDeclined($event, $organizer, $user);

        $this->addFlash('success', 'app.flash.event.transfer.declined');

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_event_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('event-delete-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $event->setDeletedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'app.flash.event.deleted');

        return $this->redirectToRoute('app_event_list');
    }

    /**
     * @see docs/features.md F3.20 — Mark event as finished
     * @see docs/features.md F4.6 — Overdue tracking
     */
    #[Route('/{id}/finish', name: 'app_event_finish', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function finish(Event $event, Request $request, EntityManagerInterface $em, MessageBusInterface $messageBus): Response
    {
        $this->denyAccessUnlessOrganizer($event);

        if (!$this->isCsrfTokenValid('finish-event-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_finish_cancelled');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getFinishedAt()) {
            $this->addFlash('warning', 'app.flash.event.already_finished');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // If ending phase was not started, cancel pre-handoff borrows now
        if (null === $event->getEndingPhaseAt()) {
            $messageBus->dispatch(new CancelEventBorrowsMessage((int) $event->getId()));
        }

        $event->setFinishedAt(new \DateTimeImmutable());
        $em->flush();

        $messageBus->dispatch(new FinishEventBorrowsMessage((int) $event->getId()));

        $this->addFlash('success', 'app.flash.event.finished', ['%name%' => $event->getName()]);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F4.6 — Overdue tracking
     */
    #[Route('/{id}/ending-phase', name: 'app_event_ending_phase', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function startEndingPhase(Event $event, Request $request, EntityManagerInterface $em, MessageBusInterface $messageBus): Response
    {
        $this->denyAccessUnlessOrganizer($event);

        if (!$this->isCsrfTokenValid('ending-phase-event-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_ending_phase_cancelled');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getFinishedAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_ending_phase_finished');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getEndingPhaseAt()) {
            $this->addFlash('warning', 'app.flash.event.already_ending_phase');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $event->setEndingPhaseAt(new \DateTimeImmutable());
        $em->flush();

        $messageBus->dispatch(new StartEndingPhaseMessage((int) $event->getId()));

        $this->addFlash('success', 'app.flash.event.ending_phase_started', ['%name%' => $event->getName()]);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.4 — Register participation to an event
     * @see docs/features.md F3.21 — Clear deck selection on withdrawal
     */
    #[Route('/{id}/participate', name: 'app_event_participate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function participate(Event $event, Request $request, EntityManagerInterface $em, EventDeckEntryRepository $entryRepository): Response
    {
        if (!$this->isCsrfTokenValid('participate-event-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_register_cancelled');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $modeValue = $request->getPayload()->getString('mode');
        $mode = ParticipationMode::tryFrom($modeValue);

        if (null === $mode) {
            $this->addFlash('danger', 'app.flash.event.invalid_mode');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $state = ParticipationMode::Playing === $mode
            ? EngagementState::RegisteredPlaying
            : EngagementState::RegisteredSpectating;

        $engagement = $event->getEngagementFor($user);

        // Invitation-only guard: only invited users (or organizer/staff) can register as player
        if ($event->isInvitationOnly()
            && ParticipationMode::Playing === $mode
            && !$event->isOrganizerOrStaff($user)
            && (null === $engagement || null === $engagement->getInvitedBy())) {
            $this->addFlash('warning', 'app.flash.event.invitation_only_player');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Clear deck entry when switching from Playing to Spectating
        if (null !== $engagement
            && ParticipationMode::Spectating === $mode
            && ParticipationMode::Playing === $engagement->getParticipationMode()) {
            $deckEntry = $entryRepository->findOneByEventAndPlayer($event, $user);
            if (null !== $deckEntry) {
                $em->remove($deckEntry);
                $this->addFlash('info', 'app.flash.event.deck_selection_cleared');
            }
        }

        if (null === $engagement) {
            $engagement = new EventEngagement();
            $engagement->setEvent($event);
            $engagement->setUser($user);
            $em->persist($engagement);
        }

        $engagement->setState($state);
        $engagement->setParticipationMode($mode);
        $em->flush();

        $flashKey = ParticipationMode::Playing === $mode ? 'app.flash.event.registered_as_player' : 'app.flash.event.registered_as_spectator';
        $this->addFlash('success', $flashKey);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.13 — Player engagement states
     */
    #[Route('/{id}/interested', name: 'app_event_interested', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function interested(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('interested-event-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_register_cancelled');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $engagement = $event->getEngagementFor($user);

        if (null !== $engagement) {
            $this->addFlash('info', 'app.flash.event.already_engaged');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $engagement = new EventEngagement();
        $engagement->setEvent($event);
        $engagement->setUser($user);
        $engagement->setState(EngagementState::Interested);
        $em->persist($engagement);
        $em->flush();

        $this->addFlash('success', 'app.flash.event.marked_interested');

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.13 — Player engagement states
     */
    #[Route('/{id}/invite', name: 'app_event_invite', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function invite(Event $event, Request $request, EntityManagerInterface $em, UserRepository $userRepository, EventNotificationService $notificationService): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$event->isOrganizerOrStaff($currentUser)) {
            throw $this->createAccessDeniedException('Only organizers or staff can invite participants.');
        }

        if (!$this->isCsrfTokenValid('invite-event-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_invite_ended');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $userQuery = $request->getPayload()->getString('user_query');
        $targetUser = $userRepository->findByMultiField($userQuery);

        if (null === $targetUser) {
            $this->addFlash('warning', 'app.flash.event.user_not_found', ['%name%' => $userQuery]);

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $existingEngagement = $event->getEngagementFor($targetUser);

        if (null !== $existingEngagement) {
            $this->addFlash('info', 'app.flash.event.user_already_engaged', ['%name%' => $targetUser->getScreenName()]);

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $engagement = new EventEngagement();
        $engagement->setEvent($event);
        $engagement->setUser($targetUser);
        $engagement->setState(EngagementState::Invited);
        $engagement->setInvitedBy($currentUser);
        $em->persist($engagement);
        $em->flush();

        $notificationService->notifyUserInvited($event, $targetUser);

        $this->addFlash('success', 'app.flash.event.user_invited', ['%name%' => $targetUser->getScreenName()]);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.4 — Register participation to an event
     * @see docs/features.md F3.21 — Clear deck selection on withdrawal
     */
    #[Route('/{id}/withdraw', name: 'app_event_withdraw', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function withdraw(Event $event, Request $request, EntityManagerInterface $em, EventDeckEntryRepository $entryRepository): Response
    {
        if (!$this->isCsrfTokenValid('withdraw-event-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_withdraw_cancelled');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $deckEntry = $entryRepository->findOneByEventAndPlayer($event, $user);
        if (null !== $deckEntry) {
            $em->remove($deckEntry);
            $this->addFlash('info', 'app.flash.event.deck_selection_cleared');
        }

        $engagement = $event->getEngagementFor($user);

        if (null !== $engagement) {
            $em->remove($engagement);
        }

        $em->flush();

        $this->addFlash('success', 'app.flash.event.withdrawn');

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.5 — Assign event staff team
     */
    #[Route('/{id}/assign-staff', name: 'app_event_assign_staff', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function assignStaff(Event $event, Request $request, EntityManagerInterface $em, UserRepository $userRepository, EventNotificationService $notificationService): Response
    {
        $this->denyAccessUnlessOrganizer($event);

        if (!$this->isCsrfTokenValid('assign-staff-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_assign_staff_cancelled');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $userQuery = $request->getPayload()->getString('user_query');
        $targetUser = $userRepository->findByMultiField($userQuery);

        if (null === $targetUser) {
            $this->addFlash('warning', 'app.flash.event.user_not_found', ['%name%' => $userQuery]);

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $screenName = $targetUser->getScreenName();

        if ($targetUser->getId() === $event->getOrganizer()->getId()) {
            $this->addFlash('warning', 'app.flash.event.organizer_cannot_be_staff');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getStaffFor($targetUser)) {
            $this->addFlash('warning', 'app.flash.event.already_staff', ['%name%' => $screenName]);

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $staff = new EventStaff();
        $staff->setEvent($event);
        $staff->setUser($targetUser);
        $staff->setAssignedBy($currentUser);

        $em->persist($staff);
        $em->flush();

        $notificationService->notifyStaffAssigned($event, $targetUser);

        $this->addFlash('success', 'app.flash.event.staff_added', ['%name%' => $screenName]);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.5 — Assign event staff team
     */
    #[Route('/{id}/remove-staff/{staffId}', name: 'app_event_remove_staff', methods: ['POST'], requirements: ['id' => '\d+', 'staffId' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function removeStaff(Event $event, int $staffId, Request $request, EntityManagerInterface $em, EventStaffRepository $staffRepository): Response
    {
        $this->denyAccessUnlessOrganizer($event);

        if (!$this->isCsrfTokenValid('remove-staff-'.$staffId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_remove_staff_cancelled');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $staffMember = $staffRepository->find($staffId);

        if (null === $staffMember || $staffMember->getEvent()->getId() !== $event->getId()) {
            throw $this->createNotFoundException('Staff member not found for this event.');
        }

        $em->remove($staffMember);
        $em->flush();

        $this->addFlash('success', 'app.flash.event.staff_removed', ['%name%' => $staffMember->getUser()->getScreenName()]);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * Toggle deck registration (available) for an event.
     *
     * @see docs/features.md F4.8 — Staff-delegated lending
     */
    #[Route('/{id}/toggle-registration', name: 'app_event_toggle_registration', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleDeckRegistration(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        DeckRepository $deckRepository,
        BorrowRepository $borrowRepository,
        EventDeckRegistrationRepository $registrationRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('toggle-registration-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt() || null !== $event->getEndingPhaseAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_change_registration');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $deckId = $request->getPayload()->getInt('deck_id');
        $deck = $deckRepository->find($deckId);

        if (null === $deck) {
            $this->addFlash('danger', 'app.flash.deck_not_found');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if ($deck->getOwnerOrFail()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'app.flash.event.own_decks_only_registration');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (!$deck->isEventRegisterable()) {
            $this->addFlash('danger', 'app.flash.event.standard_deck_not_registerable');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);

        if (null === $registration) {
            $registration = new EventDeckRegistration();
            $registration->setEvent($event);
            $registration->setDeck($deck);
            $registration->setDelegateToStaff(false);
            if (!$deck->isPublic()) {
                $deck->setPublic(true);
            }
            $em->persist($registration);
            $em->flush();

            $this->addFlash('success', 'app.flash.event.deck_registered', ['%name%' => $deck->getName()]);
        } else {
            // Guard: cannot unregister if there is an active borrow for this deck at this event
            $activeBorrow = $borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event);
            if (null !== $activeBorrow) {
                $this->addFlash('warning', 'app.flash.event.deck_cannot_unregister', ['%name%' => $deck->getName()]);

                return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
            }

            $em->remove($registration);
            $em->flush();

            $this->addFlash('success', 'app.flash.event.deck_unregistered', ['%name%' => $deck->getName()]);
        }

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * Toggle staff delegation for a registered deck at an event.
     *
     * @see docs/features.md F4.8 — Staff-delegated lending
     */
    #[Route('/{id}/toggle-delegation', name: 'app_event_toggle_delegation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleDeckDelegation(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        DeckRepository $deckRepository,
        EventDeckRegistrationRepository $registrationRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('toggle-delegation-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt() || null !== $event->getEndingPhaseAt()) {
            $this->addFlash('warning', 'app.flash.event.cannot_change_delegation');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $deckId = $request->getPayload()->getInt('deck_id');
        $deck = $deckRepository->find($deckId);

        if (null === $deck) {
            $this->addFlash('danger', 'app.flash.deck_not_found');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if ($deck->getOwnerOrFail()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'app.flash.event.own_decks_only_delegation');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);

        if (null === $registration) {
            $this->addFlash('warning', 'app.flash.event.deck_must_register', ['%name%' => $deck->getName()]);

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Cannot revoke delegation while the deck is physically with staff (F4.14)
        if ($registration->isDelegateToStaff() && $registration->hasStaffReceived() && !$registration->hasStaffReturned()) {
            $this->addFlash('danger', 'app.flash.event.cannot_revoke_delegation_in_custody', ['%name%' => $deck->getName()]);

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Cannot enable delegation when the organizer hasn't accepted custody for this event.
        if (!$registration->isDelegateToStaff() && !$event->isAllowCustody()) {
            $this->addFlash('warning', 'app.flash.event.delegation_not_allowed');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $registration->setDelegateToStaff(!$registration->isDelegateToStaff());
        $em->flush();

        $flashKey = $registration->isDelegateToStaff() ? 'app.flash.event.delegation_enabled' : 'app.flash.event.delegation_disabled';
        $this->addFlash('success', $flashKey, ['%name%' => $deck->getName()]);

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * Owner confirms handing a delegated deck to event staff.
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    #[Route('/{id}/custody/{registrationId}/owner-handover', name: 'app_event_custody_owner_handover', methods: ['POST'], requirements: ['id' => '\d+', 'registrationId' => '\d+'])]
    public function ownerHandover(
        Event $event,
        int $registrationId,
        Request $request,
        EventDeckRegistrationRepository $registrationRepository,
        StaffCustodyService $custodyService,
    ): Response {
        if (!$this->isCsrfTokenValid('custody-owner-handover-'.$registrationId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $registration = $registrationRepository->find($registrationId);

        if (null === $registration || $registration->getEvent()->getId() !== $event->getId()) {
            throw $this->createNotFoundException('Registration not found for this event.');
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $custodyService->confirmOwnerHandover($registration, $user);
            $this->addFlash('success', 'app.flash.event.custody_handed_over', ['%name%' => $registration->getDeck()->getName()]);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (AccessDeniedHttpException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * Staff confirms returning a delegated deck to the owner.
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    #[Route('/{id}/custody/{registrationId}/staff-return', name: 'app_event_custody_staff_return', methods: ['POST'], requirements: ['id' => '\d+', 'registrationId' => '\d+'])]
    public function staffReturn(
        Event $event,
        int $registrationId,
        Request $request,
        EventDeckRegistrationRepository $registrationRepository,
        StaffCustodyService $custodyService,
    ): Response {
        if (!$this->isCsrfTokenValid('custody-staff-return-'.$registrationId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $registration = $registrationRepository->find($registrationId);

        if (null === $registration || $registration->getEvent()->getId() !== $event->getId()) {
            throw $this->createNotFoundException('Registration not found for this event.');
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $custodyService->confirmStaffReturn($registration, $user);
            $this->addFlash('success', 'app.flash.event.custody_returned_to_owner', ['%name%' => $registration->getDeck()->getName()]);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (AccessDeniedHttpException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * Owner reclaims a delegated deck directly (closes custody + all active borrows).
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    #[Route('/{id}/custody/{registrationId}/owner-reclaim', name: 'app_event_custody_owner_reclaim', methods: ['POST'], requirements: ['id' => '\d+', 'registrationId' => '\d+'])]
    public function ownerReclaim(
        Event $event,
        int $registrationId,
        Request $request,
        EventDeckRegistrationRepository $registrationRepository,
        StaffCustodyService $custodyService,
    ): Response {
        if (!$this->isCsrfTokenValid('custody-owner-reclaim-'.$registrationId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $registration = $registrationRepository->find($registrationId);

        if (null === $registration || $registration->getEvent()->getId() !== $event->getId()) {
            throw $this->createNotFoundException('Registration not found for this event.');
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $custodyService->ownerReclaimDeck($registration, $user);
            $this->addFlash('success', 'app.flash.event.custody_reclaimed', ['%name%' => $registration->getDeck()->getName()]);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (AccessDeniedHttpException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F4.12 — Walk-up lending (direct lend)
     */
    #[Route('/{id}/walk-up', name: 'app_event_walk_up', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function walkUp(Event $event): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$event->isOrganizerOrStaff($user)) {
            throw $this->createAccessDeniedException('Only organizers or staff can initiate walk-up lending.');
        }

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt() || null !== $event->getEndingPhaseAt()) {
            $this->addFlash('warning', 'app.flash.event.walkup_not_available');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/walk_up.html.twig', [
            'event' => $event,
        ]);
    }

    /**
     * @see docs/features.md F4.12 — Walk-up lending (direct lend)
     */
    #[Route('/{id}/walk-up', name: 'app_event_walk_up_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function walkUpSubmit(Event $event, Request $request, BorrowService $borrowService, DeckRepository $deckRepository, UserRepository $userRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$event->isOrganizerOrStaff($user)) {
            throw $this->createAccessDeniedException('Only organizers or staff can initiate walk-up lending.');
        }

        if (!$this->isCsrfTokenValid('walk-up-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_walk_up', ['id' => $event->getId()]);
        }

        $deckId = $request->getPayload()->getInt('deck_id');
        $borrowerId = $request->getPayload()->getInt('borrower_id');

        $deck = $deckRepository->find($deckId);
        if (null === $deck) {
            $this->addFlash('danger', 'app.flash.deck_not_found');

            return $this->redirectToRoute('app_event_walk_up', ['id' => $event->getId()]);
        }

        $borrower = $userRepository->find($borrowerId);
        if (null === $borrower) {
            $this->addFlash('danger', 'app.flash.borrower_not_found');

            return $this->redirectToRoute('app_event_walk_up', ['id' => $event->getId()]);
        }

        try {
            $borrowService->createWalkUpBorrow($deck, $borrower, $event, $user);
            $this->addFlash('success', 'app.flash.event.walkup_success', ['%deck%' => $deck->getName(), '%borrower%' => $borrower->getScreenName()]);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_event_walk_up', ['id' => $event->getId()]);
        }

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * Resolves the deck version to use for the given deck, checking ownership
     * or an active borrow at the event. Returns null if the deck is not playable.
     *
     * @see docs/features.md F3.7 — Register played deck for event
     */
    private function resolvePlayableDeckVersion(
        Deck $deck,
        User $user,
        Event $event,
        BorrowRepository $borrowRepository,
    ): ?DeckVersion {
        // Own deck: must not be lent, retired, or committed to a borrower at this event
        if ($deck->getOwnerOrFail()->getId() === $user->getId()) {
            if (DeckStatus::Lent === $deck->getStatus() || DeckStatus::Retired === $deck->getStatus()) {
                return null;
            }

            // Block if an approved/lent/overdue borrow exists for this deck at this event
            if (null !== $borrowRepository->findBlockingBorrowForDeckAtEvent($deck, $event)) {
                return null;
            }

            return $deck->getCurrentVersion();
        }

        // Borrowed deck: must have an approved or lent borrow at this event
        $borrow = $borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event);

        if (null === $borrow) {
            return null;
        }

        if ($borrow->getBorrower()->getId() !== $user->getId()) {
            return null;
        }

        if (BorrowStatus::Approved !== $borrow->getStatus() && BorrowStatus::Lent !== $borrow->getStatus()) {
            return null;
        }

        return $borrow->getDeckVersion();
    }

    private function denyAccessUnlessOrganizer(Event $event): void
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($event->getOrganizer()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You are not the organizer of this event.');
        }
    }

    /**
     * @see docs/features.md F3.23 — Organizer handover
     */
    private function denyAccessUnlessTransferTarget(Event $event, User $user): void
    {
        $target = $event->getPendingTransferTo();

        if (null === $target || $target->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You are not the recipient of a pending transfer for this event.');
        }
    }

    /**
     * @see docs/features.md F3.12 — Event tags
     *
     * @param \Symfony\Component\Form\FormInterface<Event> $form
     */
    private function applyTagsFromForm(Event $event, \Symfony\Component\Form\FormInterface $form, EventTagRepository $tagRepository): void
    {
        /** @var string|null $tagsJson */
        $tagsJson = $form->get('tagsInput')->getData();

        if (null === $tagsJson || '' === $tagsJson) {
            $event->setTags([]);

            return;
        }

        $decoded = json_decode($tagsJson, true);

        if (!\is_array($decoded)) {
            $event->setTags([]);

            return;
        }

        /** @var list<string> $names */
        $names = array_values(array_filter($decoded, 'is_string'));

        $event->setTags($tagRepository->resolveByNames($names));
    }

    /**
     * @see docs/features.md F3.12 — Event tags
     *
     * @return list<string>
     */
    private function collectExistingTagNames(EventTagRepository $tagRepository): array
    {
        return array_map(static fn (EventTag $tag): string => $tag->getName(), $tagRepository->findAllOrderedByName());
    }

    /**
     * @see docs/features.md F3.12 — Event tags
     *
     * @return list<string>
     */
    private function collectEventTagNames(Event $event): array
    {
        $names = [];

        foreach ($event->getTags() as $tag) {
            $names[] = $tag->getName();
        }

        return $names;
    }
}

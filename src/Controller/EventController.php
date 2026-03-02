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
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Enum\DeckStatus;
use App\Enum\EngagementState;
use App\Enum\ParticipationMode;
use App\Form\EventFormType;
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventDeckEntryRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Repository\EventRepository;
use App\Repository\EventStaffRepository;
use App\Repository\UserRepository;
use App\Service\BorrowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F3.1 — Create a new event
 * @see docs/features.md F3.2 — Event listing
 * @see docs/features.md F3.3 — Event detail view
 * @see docs/features.md F3.4 — Register participation to an event
 * @see docs/features.md F3.5 — Assign event staff team
 * @see docs/features.md F3.7 — Register played deck for event
 * @see docs/features.md F3.9 — Edit an event
 * @see docs/features.md F3.10 — Cancel an event
 * @see docs/features.md F4.8 — Staff-delegated lending
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 */
#[Route('/event')]
#[IsGranted('ROLE_USER')]
class EventController extends AbstractController
{
    /**
     * @see docs/features.md F3.1 — Create a new event
     */
    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $event = new Event();
        $event->setTimezone($user->getTimezone());

        $form = $this->createForm(EventFormType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setOrganizer($user);
            $event->setFormat('Expanded');
            $em->persist($event);
            $em->flush();

            $this->addFlash('success', \sprintf('Event "%s" created.', $event->getName()));

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * @see docs/features.md F3.3 — Event detail view
     * @see docs/features.md F3.7 — Register played deck for event
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

        $userEngagement = $event->getEngagementFor($user);
        $isParticipant = null !== $userEngagement;

        $playableOwnDecks = [];
        $currentDeckEntry = null;
        $canChangeDeck = false;

        if ($isParticipant) {
            $playableOwnDecks = array_filter(
                $deckRepository->findByOwner($user),
                static fn (Deck $deck): bool => DeckStatus::Lent !== $deck->getStatus()
                    && DeckStatus::Retired !== $deck->getStatus()
                    && null !== $deck->getCurrentVersion(),
            );
            $playableOwnDecks = array_values($playableOwnDecks);

            $currentDeckEntry = $entryRepository->findOneByEventAndPlayer($event, $user);
            $canChangeDeck = !$currentDeckEntry || $event->getDate() > new \DateTimeImmutable();
        }

        // Delegation: show owner's decks with version (eligible for registration)
        $ownedDecksWithVersion = array_filter(
            $deckRepository->findByOwner($user),
            static fn (Deck $deck): bool => null !== $deck->getCurrentVersion()
                && DeckStatus::Retired !== $deck->getStatus(),
        );
        $ownedDecksWithVersion = array_values($ownedDecksWithVersion);

        $deckRegistrationMap = [];
        foreach ($registrationRepository->findByEventAndOwner($event, $user) as $reg) {
            $deckId = $reg->getDeck()->getId();
            if (null !== $deckId) {
                $deckRegistrationMap[$deckId] = [
                    'registered' => true,
                    'delegated' => $reg->isDelegateToStaff(),
                ];
            }
        }

        return $this->render('event/show.html.twig', [
            'event' => $event,
            'isOrganizer' => $event->getOrganizer()->getId() === $user->getId(),
            'isStaff' => $event->isOrganizerOrStaff($user),
            'userEngagement' => $userEngagement,
            'isParticipant' => $isParticipant,
            'eventBorrows' => $borrowRepository->findByEventForUser($event, $user),
            'playableOwnDecks' => $playableOwnDecks,
            'currentDeckEntry' => $currentDeckEntry,
            'canChangeDeck' => $canChangeDeck,
            'ownedDecksWithVersion' => $ownedDecksWithVersion,
            'deckRegistrationMap' => $deckRegistrationMap,
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

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt()) {
            $this->addFlash('warning', 'Decks cannot be browsed for a cancelled or finished event.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $userEngagement = $event->getEngagementFor($user);

        if (null === $userEngagement) {
            $this->addFlash('warning', 'Register as a participant to browse and borrow decks.');

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
        EventDeckEntryRepository $entryRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('select-deck-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null === $event->getEngagementFor($user)) {
            $this->addFlash('warning', 'You must be a participant to select a deck.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt()) {
            $this->addFlash('warning', 'Deck selection is not available for cancelled or finished events.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $deckId = $request->getPayload()->getInt('deck_id');
        $existingEntry = $entryRepository->findOneByEventAndPlayer($event, $user);

        // Allow first selection even after event start; block changes only when already selected
        if (null !== $existingEntry && $event->getDate() <= new \DateTimeImmutable()) {
            $this->addFlash('warning', 'Deck selection cannot be changed after the event has started.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (0 === $deckId) {
            if (null !== $existingEntry) {
                $em->remove($existingEntry);
                $em->flush();
            }

            $this->addFlash('success', 'Deck selection cleared.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $deck = $deckRepository->find($deckId);

        if (null === $deck) {
            $this->addFlash('danger', 'Deck not found.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $currentVersion = $this->resolvePlayableDeckVersion($deck, $user, $event, $borrowRepository);

        if (null === $currentVersion) {
            $this->addFlash('danger', 'This deck is not available for selection.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
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

        $this->addFlash('success', \sprintf('Playing with "%s".', $deck->getName()));

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.9 — Edit an event
     */
    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function edit(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOrganizer($event);

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'A cancelled event cannot be edited.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $form = $this->createForm(EventFormType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setFormat('Expanded');
            $em->flush();

            $this->addFlash('success', \sprintf('Event "%s" updated.', $event->getName()));

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    /**
     * @see docs/features.md F3.10 — Cancel an event
     */
    #[Route('/{id}/cancel', name: 'app_event_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function cancel(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOrganizer($event);

        if (!$this->isCsrfTokenValid('cancel-event-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'This event is already cancelled.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $event->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', \sprintf('Event "%s" has been cancelled.', $event->getName()));

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.4 — Register participation to an event
     */
    #[Route('/{id}/participate', name: 'app_event_participate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function participate(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('participate-event-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'Cannot register for a cancelled event.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $modeValue = $request->getPayload()->getString('mode');
        $mode = ParticipationMode::tryFrom($modeValue);

        if (null === $mode) {
            $this->addFlash('danger', 'Invalid participation mode.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $state = ParticipationMode::Playing === $mode
            ? EngagementState::RegisteredPlaying
            : EngagementState::RegisteredSpectating;

        $engagement = $event->getEngagementFor($user);

        if (null === $engagement) {
            $engagement = new EventEngagement();
            $engagement->setEvent($event);
            $engagement->setUser($user);
            $em->persist($engagement);
        }

        $engagement->setState($state);
        $engagement->setParticipationMode($mode);
        $em->flush();

        $label = ParticipationMode::Playing === $mode ? 'player' : 'spectator';
        $this->addFlash('success', \sprintf('You are now registered as a %s.', $label));

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.4 — Register participation to an event
     */
    #[Route('/{id}/withdraw', name: 'app_event_withdraw', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function withdraw(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('withdraw-event-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'Cannot withdraw from a cancelled event.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $engagement = $event->getEngagementFor($user);

        if (null !== $engagement) {
            $em->remove($engagement);
            $em->flush();
        }

        $this->addFlash('success', 'You have withdrawn from this event.');

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.5 — Assign event staff team
     */
    #[Route('/{id}/assign-staff', name: 'app_event_assign_staff', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function assignStaff(Event $event, Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessOrganizer($event);

        if (!$this->isCsrfTokenValid('assign-staff-'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'Cannot assign staff to a cancelled event.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $userQuery = $request->getPayload()->getString('user_query');
        $targetUser = $userRepository->findByMultiField($userQuery);

        if (null === $targetUser) {
            $this->addFlash('warning', \sprintf('User "%s" not found.', $userQuery));

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $screenName = $targetUser->getScreenName();

        if ($targetUser->getId() === $event->getOrganizer()->getId()) {
            $this->addFlash('warning', 'The organizer cannot be assigned as staff.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getStaffFor($targetUser)) {
            $this->addFlash('warning', \sprintf('"%s" is already a staff member.', $screenName));

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $staff = new EventStaff();
        $staff->setEvent($event);
        $staff->setUser($targetUser);
        $staff->setAssignedBy($currentUser);

        $em->persist($staff);
        $em->flush();

        $this->addFlash('success', \sprintf('"%s" has been added to the staff team.', $screenName));

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
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt()) {
            $this->addFlash('warning', 'Cannot remove staff from a cancelled event.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $staffMember = $staffRepository->find($staffId);

        if (null === $staffMember || $staffMember->getEvent()->getId() !== $event->getId()) {
            throw $this->createNotFoundException('Staff member not found for this event.');
        }

        $em->remove($staffMember);
        $em->flush();

        $this->addFlash('success', \sprintf('"%s" has been removed from the staff team.', $staffMember->getUser()->getScreenName()));

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
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt()) {
            $this->addFlash('warning', 'Cannot change registration for a cancelled or finished event.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $deckId = $request->getPayload()->getInt('deck_id');
        $deck = $deckRepository->find($deckId);

        if (null === $deck) {
            $this->addFlash('danger', 'Deck not found.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if ($deck->getOwner()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'You can only manage registration for your own decks.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);

        if (null === $registration) {
            $registration = new EventDeckRegistration();
            $registration->setEvent($event);
            $registration->setDeck($deck);
            $registration->setDelegateToStaff(false);
            $em->persist($registration);
            $em->flush();

            $this->addFlash('success', \sprintf('"%s" registered for this event.', $deck->getName()));
        } else {
            // Guard: cannot unregister if there is an active borrow for this deck at this event
            $activeBorrow = $borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event);
            if (null !== $activeBorrow) {
                $this->addFlash('warning', \sprintf('Cannot unregister "%s" — it has an active borrow at this event.', $deck->getName()));

                return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
            }

            $em->remove($registration);
            $em->flush();

            $this->addFlash('success', \sprintf('"%s" unregistered from this event.', $deck->getName()));
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
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt()) {
            $this->addFlash('warning', 'Cannot change delegation for a cancelled or finished event.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $deckId = $request->getPayload()->getInt('deck_id');
        $deck = $deckRepository->find($deckId);

        if (null === $deck) {
            $this->addFlash('danger', 'Deck not found.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if ($deck->getOwner()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'You can only manage delegation for your own decks.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);

        if (null === $registration) {
            $this->addFlash('warning', \sprintf('"%s" must be registered before enabling delegation.', $deck->getName()));

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $registration->setDelegateToStaff(!$registration->isDelegateToStaff());
        $em->flush();

        $label = $registration->isDelegateToStaff() ? 'enabled' : 'disabled';
        $this->addFlash('success', \sprintf('Staff delegation %s for "%s".', $label, $deck->getName()));

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    /**
     * @see docs/features.md F3.2 — Event listing
     */
    #[Route('', name: 'app_event_list', methods: ['GET'])]
    public function list(EventRepository $eventRepository): Response
    {
        return $this->render('event/list.html.twig', [
            'events' => $eventRepository->findUpcoming(20),
        ]);
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

        if (null !== $event->getCancelledAt() || null !== $event->getFinishedAt()) {
            $this->addFlash('warning', 'Walk-up lending is not available for cancelled or finished events.');

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
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_event_walk_up', ['id' => $event->getId()]);
        }

        $deckId = $request->getPayload()->getInt('deck_id');
        $borrowerId = $request->getPayload()->getInt('borrower_id');

        $deck = $deckRepository->find($deckId);
        if (null === $deck) {
            $this->addFlash('danger', 'Deck not found.');

            return $this->redirectToRoute('app_event_walk_up', ['id' => $event->getId()]);
        }

        $borrower = $userRepository->find($borrowerId);
        if (null === $borrower) {
            $this->addFlash('danger', 'Borrower not found.');

            return $this->redirectToRoute('app_event_walk_up', ['id' => $event->getId()]);
        }

        try {
            $borrowService->createWalkUpBorrow($deck, $borrower, $event, $user);
            $this->addFlash('success', \sprintf('"%s" lent to %s.', $deck->getName(), $borrower->getScreenName()));
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
        // Own deck: must not be lent or retired, and must have a current version
        if ($deck->getOwner()->getId() === $user->getId()) {
            if (DeckStatus::Lent === $deck->getStatus() || DeckStatus::Retired === $deck->getStatus()) {
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
}

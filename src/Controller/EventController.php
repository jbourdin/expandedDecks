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

use App\Entity\Event;
use App\Entity\EventEngagement;
use App\Entity\EventStaff;
use App\Entity\User;
use App\Enum\EngagementState;
use App\Enum\ParticipationMode;
use App\Form\EventFormType;
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\EventStaffRepository;
use App\Repository\UserRepository;
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
 * @see docs/features.md F3.9 — Edit an event
 * @see docs/features.md F3.10 — Cancel an event
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
     */
    #[Route('/{id}', name: 'app_event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Event $event, BorrowRepository $borrowRepository, DeckRepository $deckRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $userEngagement = $event->getEngagementFor($user);
        $isParticipant = null !== $userEngagement;

        $availableDecks = [];
        if ($isParticipant && null === $event->getCancelledAt() && null === $event->getFinishedAt()) {
            $availableDecks = $deckRepository->findAvailableForEvent($event, $user);
        }

        return $this->render('event/show.html.twig', [
            'event' => $event,
            'isOrganizer' => $event->getOrganizer()->getId() === $user->getId(),
            'userEngagement' => $userEngagement,
            'isParticipant' => $isParticipant,
            'eventBorrows' => $borrowRepository->findByEvent($event),
            'availableDecks' => $availableDecks,
        ]);
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
     * @see docs/features.md F3.2 — Event listing
     */
    #[Route('', name: 'app_event_list', methods: ['GET'])]
    public function list(EventRepository $eventRepository): Response
    {
        return $this->render('event/list.html.twig', [
            'events' => $eventRepository->findUpcoming(20),
        ]);
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

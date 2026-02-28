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
use App\Entity\User;
use App\Form\EventFormType;
use App\Repository\EventRepository;
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
 * @see docs/features.md F3.9 — Edit an event
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
    public function show(Event $event): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('event/show.html.twig', [
            'event' => $event,
            'isOrganizer' => $event->getOrganizer()->getId() === $user->getId(),
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

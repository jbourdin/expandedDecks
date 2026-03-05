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
use App\Enum\EventVisibility;
use App\Repository\EventDeckEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F3.17 — Tournament Results
 */
#[Route('/event')]
class TournamentResultsController extends AbstractAppController
{
    /**
     * @see docs/features.md F3.17 — Tournament Results
     */
    #[Route('/{id}/results', name: 'app_event_results', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function results(Event $event, EventDeckEntryRepository $entryRepository): Response
    {
        if (null !== $event->getCancelledAt()) {
            throw $this->createNotFoundException();
        }

        if (null === $event->getFinishedAt()) {
            $this->addFlash('warning', 'app.flash.results.not_finished');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $user = $this->getUser();
        $isAuthenticated = $user instanceof User;

        if (EventVisibility::Public !== $event->getVisibility()) {
            if (!$isAuthenticated || !$event->isOrganizerOrStaff($user)) {
                throw $this->createAccessDeniedException();
            }
        }

        $entries = $entryRepository->findByEventOrderedByPlacement($event);

        return $this->render('event/results.html.twig', [
            'event' => $event,
            'entries' => $entries,
            'isAuthenticated' => $isAuthenticated,
            'isOrganizerOrStaff' => $isAuthenticated && $event->isOrganizerOrStaff($user),
        ]);
    }

    /**
     * @see docs/features.md F3.17 — Tournament Results
     */
    #[Route('/{id}/results/edit', name: 'app_event_results_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Event $event,
        Request $request,
        EventDeckEntryRepository $entryRepository,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$event->isOrganizerOrStaff($user)) {
            throw $this->createAccessDeniedException();
        }

        if (null !== $event->getCancelledAt() || null === $event->getFinishedAt()) {
            $this->addFlash('warning', 'app.flash.results.not_finished');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $entries = $entryRepository->findByEventOrderedByPlacement($event);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('results-edit-'.$event->getId(), $request->getPayload()->getString('_token'))) {
                $this->addFlash('danger', 'app.flash.invalid_token');

                return $this->redirectToRoute('app_event_results_edit', ['id' => $event->getId()]);
            }

            /** @var array<string, array{placement?: string, match_record?: string}> $results */
            $results = $request->getPayload()->all('results');
            $errors = [];

            foreach ($entries as $entry) {
                $entryId = (string) $entry->getId();

                if (!isset($results[$entryId])) {
                    continue;
                }

                $placementValue = trim($results[$entryId]['placement'] ?? '');
                $matchRecordValue = trim($results[$entryId]['match_record'] ?? '');

                if ('' !== $placementValue) {
                    $placement = filter_var($placementValue, \FILTER_VALIDATE_INT);
                    if (false === $placement || $placement < 1) {
                        $errors[$entryId] = 'Placement must be a positive integer.';

                        continue;
                    }
                    $entry->setFinalPlacement($placement);
                } else {
                    $entry->setFinalPlacement(null);
                }

                if ('' !== $matchRecordValue) {
                    if (!preg_match('/^\d{1,2}-\d{1,2}-\d{1,2}$/', $matchRecordValue)) {
                        $errors[$entryId] = 'Match record must be in W-L-T format (e.g. 3-1-0).';

                        continue;
                    }
                    $entry->setMatchRecord($matchRecordValue);
                } else {
                    $entry->setMatchRecord(null);
                }
            }

            if ([] === $errors) {
                $em->flush();
                $this->addFlash('success', 'app.flash.results.saved');

                return $this->redirectToRoute('app_event_results', ['id' => $event->getId()]);
            }

            return $this->render('event/results_edit.html.twig', [
                'event' => $event,
                'entries' => $entries,
                'errors' => $errors,
            ]);
        }

        return $this->render('event/results_edit.html.twig', [
            'event' => $event,
            'entries' => $entries,
            'errors' => [],
        ]);
    }
}

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

use App\Entity\User;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public event listing, accessible without authentication.
 *
 * @see docs/features.md F3.2 — Event listing
 * @see docs/features.md F3.15 — Event discovery
 */
class EventListController extends AbstractController
{
    private const VALID_SCOPES = ['all', 'public', 'staffing'];

    /**
     * @see docs/features.md F3.11 — Event visibility
     * @see docs/features.md F3.15 — Event discovery
     * @see docs/features.md F7.1 — Dashboard (scope=staffing)
     */
    #[Route('/event', name: 'app_event_list', methods: ['GET'], priority: 10)]
    public function list(Request $request, EventRepository $eventRepository): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $scope = $request->query->getString('scope', 'all');

        if (!\in_array($scope, self::VALID_SCOPES, true)) {
            $scope = 'all';
        }

        // Anonymous users can only see public events
        if (null === $user) {
            $scope = 'public';
        }

        $events = match ($scope) {
            'public' => $eventRepository->findPublicUpcoming(),
            'staffing' => $eventRepository->findUpcomingByOrganizerOrStaff($user ?? throw new \LogicException()),
            default => $eventRepository->findVisibleUpcoming($user),
        };

        return $this->render('event/list.html.twig', [
            'events' => $events,
            'scope' => $scope,
        ]);
    }

    /**
     * Legacy discover route — redirects to event list with public scope.
     */
    #[Route('/events/discover', name: 'app_event_discover', methods: ['GET'])]
    public function discover(): Response
    {
        return $this->redirectToRoute('app_event_list', ['scope' => 'public'], Response::HTTP_MOVED_PERMANENTLY);
    }
}

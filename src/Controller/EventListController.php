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

use App\Entity\EventTag;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\EventTagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public event listing, accessible without authentication.
 *
 * @see docs/features.md F3.2 — Event listing
 * @see docs/features.md F3.12 — Event tags
 * @see docs/features.md F3.15 — Event discovery
 */
class EventListController extends AbstractController
{
    private const VALID_SCOPES = ['all', 'public', 'staffing'];

    /**
     * @see docs/features.md F3.11 — Event visibility
     * @see docs/features.md F3.12 — Event tags
     * @see docs/features.md F3.15 — Event discovery
     * @see docs/features.md F7.1 — Dashboard (scope=staffing)
     */
    #[Route('/event', name: 'app_event_list', methods: ['GET'], priority: 10)]
    public function list(
        Request $request,
        EventRepository $eventRepository,
        EventTagRepository $tagRepository,
    ): Response {
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

        $tagSlug = trim($request->query->getString('tag'));
        $activeTag = '' !== $tagSlug ? $tagRepository->findOneBySlug($tagSlug) : null;

        if (null !== $activeTag) {
            $events = match ($scope) {
                'public' => $eventRepository->findPublicUpcomingByTag($activeTag),
                default => $eventRepository->findVisibleUpcomingByTag($user, $activeTag),
            };
        } else {
            $events = match ($scope) {
                'public' => $eventRepository->findPublicUpcoming(),
                'staffing' => $eventRepository->findUpcomingByOrganizerOrStaff($user ?? throw new \LogicException()),
                default => $eventRepository->findVisibleUpcoming($user),
            };
        }

        return $this->render('event/list.html.twig', [
            'events' => $events,
            'scope' => $scope,
            'allTags' => $tagRepository->findAllOrderedByName(),
            'activeTag' => $activeTag,
            'icalUrl' => $this->generateIcalUrl($activeTag),
        ]);
    }

    /**
     * Public list of upcoming events tagged with a given slug.
     *
     * @see docs/features.md F3.12 — Event tags
     * @see docs/features.md F3.16 — Public iCal feed
     */
    #[Route('/event/tag/{slug}', name: 'app_event_tag', methods: ['GET'], requirements: ['slug' => '[a-z0-9][a-z0-9-]*'], priority: 10)]
    public function tag(
        string $slug,
        EventRepository $eventRepository,
        EventTagRepository $tagRepository,
    ): Response {
        $tag = $tagRepository->findOneBySlug($slug);

        if (null === $tag) {
            throw $this->createNotFoundException();
        }

        /** @var User|null $user */
        $user = $this->getUser();

        $events = null !== $user
            ? $eventRepository->findVisibleUpcomingByTag($user, $tag)
            : $eventRepository->findPublicUpcomingByTag($tag);

        return $this->render('event/list.html.twig', [
            'events' => $events,
            'scope' => 'public',
            'allTags' => $tagRepository->findAllOrderedByName(),
            'activeTag' => $tag,
            'icalUrl' => $this->generateIcalUrl($tag),
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

    private function generateIcalUrl(?EventTag $tag): string
    {
        if (null !== $tag) {
            return $this->generateUrl('app_event_tag_ical', ['slug' => $tag->getSlug()]);
        }

        return $this->generateUrl('app_event_list_ical');
    }
}

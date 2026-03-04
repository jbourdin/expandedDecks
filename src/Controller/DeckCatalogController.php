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
use App\Repository\ArchetypeRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
 */
class DeckCatalogController extends AbstractController
{
    private const int PER_PAGE = 12;

    #[Route('/deck', name: 'app_deck_list', methods: ['GET'], priority: 10)]
    public function list(
        Request $request,
        DeckRepository $deckRepository,
        ArchetypeRepository $archetypeRepository,
        EventRepository $eventRepository,
        UserRepository $userRepository,
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));

        $filters = [
            'search' => $request->query->getString('q'),
            'archetype' => $request->query->getString('archetype'),
        ];

        // Resolve owner filter + display name (guard against empty string before getInt)
        $ownerName = '';
        if ('' !== $request->query->getString('owner')) {
            $ownerId = $request->query->getInt('owner');
            if ($ownerId > 0) {
                $filters['owner'] = $ownerId;
                $owner = $userRepository->find($ownerId);
                if (null !== $owner) {
                    $ownerName = $owner->getScreenName();
                }

                // When the owner filter matches the logged-in user, show all their
                // decks (including private ones) by skipping the public constraint.
                $currentUser = $this->getUser();
                if ($currentUser instanceof User && $currentUser->getId() === $ownerId) {
                    $filters['selfOwner'] = true;
                }
            }
        }

        // Resolve event filter + display name (guard against empty string before getInt)
        $eventName = '';
        if ('' !== $request->query->getString('event')) {
            $eventId = $request->query->getInt('event');
            if ($eventId > 0) {
                $filters['event'] = $eventId;
                $event = $eventRepository->find($eventId);
                if (null !== $event) {
                    $eventName = $event->getName();
                }
            }
        }

        // Resolve archetype filter + display name
        $archetypeName = '';
        $archetypeSlug = $filters['archetype'];
        if ('' !== $archetypeSlug) {
            $archetype = $archetypeRepository->findOneBy(['slug' => $archetypeSlug]);
            if (null !== $archetype) {
                $archetypeName = $archetype->getName();
            }
        }

        $qb = $deckRepository->createCatalogQueryBuilder($filters);
        $qb->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('deck/list.html.twig', [
            'decks' => $paginator,
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'archetypeName' => $archetypeName,
            'eventName' => $eventName,
            'ownerName' => $ownerName,
        ]);
    }
}

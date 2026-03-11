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
use App\Entity\DeckCard;
use App\Entity\User;
use App\Enum\DeckEventStatus;
use App\Enum\DeckStatus;
use App\Repository\BorrowRepository;
use App\Repository\EventDeckEntryRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Repository\EventRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F2.3 — Detail view
 * @see docs/features.md F2.14 — Deck event status overview
 * @see docs/features.md F4.5 — Borrow history
 */
class DeckShowController extends AbstractController
{
    #[Route('/deck/{short_tag}', name: 'app_deck_show', methods: ['GET'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}'])]
    public function show(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        BorrowRepository $borrowRepository,
        EventRepository $eventRepository,
        EventDeckEntryRepository $eventDeckEntryRepository,
        EventDeckRegistrationRepository $eventDeckRegistrationRepository,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        // Access control: public decks are visible to everyone
        if (!$deck->isPublic()) {
            if (null === $user) {
                throw $this->createAccessDeniedException();
            }

            $isOwnerOrAdmin = $deck->getOwner()->getId() === $user->getId()
                || $this->isGranted('ROLE_ADMIN');

            if (!$isOwnerOrAdmin) {
                // Check if user is organizer/staff of any event where this deck is registered
                $hasStaffAccess = false;
                foreach ($deck->getEventRegistrations() as $registration) {
                    if ($registration->getEvent()->isOrganizerOrStaff($user)) {
                        $hasStaffAccess = true;
                        break;
                    }
                }

                if (!$hasStaffAccess) {
                    throw $this->createAccessDeniedException();
                }
            }
        }

        $groupedCards = [];
        $currentVersion = $deck->getCurrentVersion();

        if (null !== $currentVersion) {
            foreach ($currentVersion->getCards() as $card) {
                $groupedCards[$card->getCardType()][] = $card;
            }

            // Sort within each group: quantity desc, name asc
            foreach ($groupedCards as &$cards) {
                usort($cards, static function (DeckCard $a, DeckCard $b): int {
                    if ($a->getQuantity() !== $b->getQuantity()) {
                        return $b->getQuantity() - $a->getQuantity();
                    }

                    return strcmp($a->getCardName(), $b->getCardName());
                });
            }
            unset($cards);
        }

        // Ensure consistent section order
        $orderedGroups = [];
        foreach (['pokemon', 'trainer', 'energy'] as $section) {
            if (isset($groupedCards[$section])) {
                $orderedGroups[$section] = $groupedCards[$section];
            }
        }

        $isOwner = null !== $user && $deck->getOwner()->getId() === $user->getId();

        // Anonymous users get empty borrow data
        $deckBorrows = [];
        $eligibleEvents = [];
        $eventStatusOverview = [];

        if (null !== $user) {
            $deckBorrows = $borrowRepository->findByDeckForUser($deck, $user);

            // Only show eligible events if deck is not retired, user is not owner, and deck has a version
            if (!$isOwner && DeckStatus::Retired !== $deck->getStatus() && null !== $currentVersion) {
                $candidates = $eventRepository->findEligibleForBorrow($user, $deck);

                // Filter out events with same-day conflicts
                foreach ($candidates as $candidate) {
                    if (null === $borrowRepository->findBlockingBorrowForDeckAtEvent($deck, $candidate)
                        && [] === $borrowRepository->findBlockingBorrowsOnSameDay($deck, $candidate)) {
                        $eligibleEvents[] = $candidate;
                    }
                }
            }

            if ($isOwner) {
                $upcomingEvents = $eventRepository->findUpcomingByEngagement($user);
                foreach ($upcomingEvents as $event) {
                    if (null !== $eventDeckEntryRepository->findOneByEventAndDeck($event, $deck)) {
                        $status = DeckEventStatus::Played;
                    } elseif (null !== $borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event)) {
                        $status = DeckEventStatus::ActivelyBorrowed;
                    } elseif (null !== ($registration = $eventDeckRegistrationRepository->findOneByEventAndDeck($event, $deck))) {
                        $status = $registration->isDelegateToStaff()
                            ? DeckEventStatus::DelegatedToStaff
                            : DeckEventStatus::Registered;
                    } else {
                        $status = DeckEventStatus::NotRegistered;
                    }

                    $eventStatusOverview[] = [
                        'event' => $event,
                        'status' => $status,
                    ];
                }
            }
        }

        return $this->render('deck/show.html.twig', [
            'deck' => $deck,
            'groupedCards' => $orderedGroups,
            'isOwner' => $isOwner,
            'deckBorrows' => $deckBorrows,
            'eligibleEvents' => $eligibleEvents,
            'eventStatusOverview' => $eventStatusOverview,
            'versionCount' => $deck->getVersions()->count(),
        ]);
    }
}

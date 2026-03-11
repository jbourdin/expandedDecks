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
use App\Entity\User;
use App\Repository\BorrowRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F5.12 — Deck show activity pagination
 */
#[IsGranted('ROLE_USER')]
class DeckBorrowHistoryController extends AbstractController
{
    private const int PER_PAGE = 20;

    #[Route('/deck/{short_tag}/borrows', name: 'app_deck_borrow_history', methods: ['GET'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}'])]
    public function __invoke(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        Request $request,
        BorrowRepository $borrowRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $this->denyAccessUnlessCanViewDeck($deck, $user);

        $page = max(1, $request->query->getInt('page', 1));

        $queryBuilder = $borrowRepository->createDeckForUserQueryBuilder($deck, $user);
        $queryBuilder->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($queryBuilder, fetchJoinCollection: true);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('deck/borrow_history.html.twig', [
            'deck' => $deck,
            'borrows' => $paginator,
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    private function denyAccessUnlessCanViewDeck(Deck $deck, User $user): void
    {
        if ($deck->isPublic()) {
            return;
        }

        if ($deck->getOwner()->getId() === $user->getId() || $this->isGranted('ROLE_ADMIN')) {
            return;
        }

        foreach ($deck->getEventRegistrations() as $registration) {
            if ($registration->getEvent()->isOrganizerOrStaff($user)) {
                return;
            }
        }

        throw $this->createAccessDeniedException();
    }
}

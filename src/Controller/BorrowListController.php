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
use App\Enum\BorrowStatus;
use App\Repository\BorrowRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F4.5 — Borrow history
 * @see docs/features.md F4.10 — Owner borrow inbox
 */
#[IsGranted('ROLE_USER')]
class BorrowListController extends AbstractController
{
    private const int PER_PAGE = 20;

    /**
     * @see docs/features.md F4.5 — Borrow history
     */
    #[Route('/borrows', name: 'app_borrow_list', methods: ['GET'])]
    public function borrows(Request $request, BorrowRepository $borrowRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $status = $this->resolveStatusFilter($request);
        $page = max(1, $request->query->getInt('page', 1));

        $qb = $borrowRepository->createBorrowerQueryBuilder($user, $status);
        $qb->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('borrow/list.html.twig', [
            'borrows' => $paginator,
            'currentStatus' => $status,
            'statuses' => BorrowStatus::cases(),
            'pageTitle' => 'My Borrows',
            'mode' => 'borrows',
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * @see docs/features.md F4.10 — Owner borrow inbox
     */
    #[Route('/lends', name: 'app_lend_list', methods: ['GET'])]
    public function lends(Request $request, BorrowRepository $borrowRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $status = $this->resolveStatusFilter($request);
        $page = max(1, $request->query->getInt('page', 1));

        $qb = $borrowRepository->createDeckOwnerQueryBuilder($user, $status);
        $qb->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('borrow/list.html.twig', [
            'borrows' => $paginator,
            'currentStatus' => $status,
            'statuses' => BorrowStatus::cases(),
            'pageTitle' => 'My Lends',
            'mode' => 'lends',
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    private function resolveStatusFilter(Request $request): ?BorrowStatus
    {
        $statusParam = $request->query->getString('status');

        if ('' === $statusParam) {
            return null;
        }

        return BorrowStatus::tryFrom($statusParam);
    }
}

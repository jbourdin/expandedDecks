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
    /**
     * @see docs/features.md F4.5 — Borrow history
     */
    #[Route('/borrows', name: 'app_borrow_list', methods: ['GET'])]
    public function borrows(Request $request, BorrowRepository $borrowRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $status = $this->resolveStatusFilter($request);

        return $this->render('borrow/list.html.twig', [
            'borrows' => $borrowRepository->findAllByBorrower($user, $status),
            'currentStatus' => $status,
            'statuses' => BorrowStatus::cases(),
            'pageTitle' => 'My Borrows',
            'mode' => 'borrows',
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

        return $this->render('borrow/list.html.twig', [
            'borrows' => $borrowRepository->findAllByDeckOwner($user, $status),
            'currentStatus' => $status,
            'statuses' => BorrowStatus::cases(),
            'pageTitle' => 'My Lends',
            'mode' => 'lends',
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

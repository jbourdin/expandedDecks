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
use App\Repository\EventDeckRegistrationRepository;
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
     * @see docs/features.md F7.1 — Dashboard (scope=managed)
     */
    #[Route('/lends', name: 'app_lend_list', methods: ['GET'])]
    public function lends(Request $request, BorrowRepository $borrowRepository, EventDeckRegistrationRepository $registrationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $status = $this->resolveStatusFilter($request);
        $scope = $request->query->getString('scope');

        if ('managed' === $scope) {
            return $this->renderManaged($request, $borrowRepository, $registrationRepository, $user, $status);
        }

        $inboxMode = null === $status || !$status->isTerminal();

        if ($inboxMode) {
            return $this->renderInbox($borrowRepository, $user, $status);
        }

        return $this->renderHistory($request, $borrowRepository, $user, $status);
    }

    /**
     * Managed mode: borrows at events where the user is organizer or staff.
     *
     * @see docs/features.md F7.1 — Dashboard
     */
    private function renderManaged(Request $request, BorrowRepository $borrowRepository, EventDeckRegistrationRepository $registrationRepository, User $user, ?BorrowStatus $status): Response
    {
        $inboxMode = null === $status || !$status->isTerminal();

        if ($inboxMode) {
            $borrows = $borrowRepository->findActiveManagedBorrows($user, $status);
            $eventGroups = $this->groupByEvent($borrows);
            $custodyMap = $this->buildCustodyMap($borrows, $registrationRepository);

            return $this->render('borrow/list.html.twig', [
                'currentStatus' => $status,
                'statuses' => BorrowStatus::cases(),
                'pageTitle' => 'Managed Borrows',
                'mode' => 'managed',
                'inboxMode' => true,
                'eventGroups' => $eventGroups,
                'custodyMap' => $custodyMap,
                'totalItems' => 0,
                'currentPage' => 1,
                'totalPages' => 1,
                'borrows' => [],
                'scope' => 'managed',
            ]);
        }

        $page = max(1, $request->query->getInt('page', 1));

        $qb = $borrowRepository->createManagedQueryBuilder($user, $status);
        $qb->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('borrow/list.html.twig', [
            'borrows' => $paginator,
            'currentStatus' => $status,
            'statuses' => BorrowStatus::cases(),
            'pageTitle' => 'Managed Borrows',
            'mode' => 'managed',
            'inboxMode' => false,
            'eventGroups' => [],
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'scope' => 'managed',
        ]);
    }

    /**
     * Inbox mode: active borrows grouped by event.
     */
    private function renderInbox(BorrowRepository $borrowRepository, User $user, ?BorrowStatus $status): Response
    {
        if (null === $status) {
            $borrows = $borrowRepository->findActiveBorrowsForOwner($user);
        } else {
            /** @var list<\App\Entity\Borrow> $borrows */
            $borrows = $borrowRepository->createDeckOwnerQueryBuilder($user, $status)
                ->resetDQLPart('orderBy')
                ->orderBy('e.date', 'ASC')
                ->addOrderBy('b.requestedAt', 'DESC')
                ->getQuery()
                ->getResult();
        }

        $eventGroups = $this->groupByEvent($borrows);

        return $this->render('borrow/list.html.twig', [
            'currentStatus' => $status,
            'statuses' => BorrowStatus::cases(),
            'pageTitle' => 'My Lends',
            'mode' => 'lends',
            'inboxMode' => true,
            'eventGroups' => $eventGroups,
            'totalItems' => 0,
            'currentPage' => 1,
            'totalPages' => 1,
            'borrows' => [],
        ]);
    }

    /**
     * History mode: terminal borrows with pagination.
     */
    private function renderHistory(Request $request, BorrowRepository $borrowRepository, User $user, BorrowStatus $status): Response
    {
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
            'inboxMode' => false,
            'eventGroups' => [],
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * @param list<\App\Entity\Borrow> $borrows
     *
     * @return list<array{event: \App\Entity\Event, borrows: list<\App\Entity\Borrow>}>
     */
    private function groupByEvent(array $borrows): array
    {
        /** @var array<int, array{event: \App\Entity\Event, borrows: list<\App\Entity\Borrow>}> $groups */
        $groups = [];

        foreach ($borrows as $borrow) {
            $eventId = (int) $borrow->getEvent()->getId();

            if (!isset($groups[$eventId])) {
                $groups[$eventId] = [
                    'event' => $borrow->getEvent(),
                    'borrows' => [],
                ];
            }

            $groups[$eventId]['borrows'][] = $borrow;
        }

        return array_values($groups);
    }

    /**
     * Build a map of borrow ID → whether staff has physical custody of the deck.
     * Only relevant for delegated borrows; non-delegated borrows are not included.
     *
     * @param list<\App\Entity\Borrow> $borrows
     *
     * @return array<int, bool>
     */
    private function buildCustodyMap(array $borrows, EventDeckRegistrationRepository $registrationRepository): array
    {
        $map = [];

        foreach ($borrows as $borrow) {
            if (!$borrow->isDelegatedToStaff()) {
                continue;
            }

            $registration = $registrationRepository->findOneByEventAndDeck(
                $borrow->getEvent(),
                $borrow->getDeck(),
            );

            $map[(int) $borrow->getId()] = null !== $registration && $registration->hasStaffReceived();
        }

        return $map;
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

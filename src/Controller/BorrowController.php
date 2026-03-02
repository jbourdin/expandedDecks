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

use App\Entity\Borrow;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Service\BorrowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F4.1 — Request to borrow a deck
 * @see docs/features.md F4.2 — Approve / deny a borrow request
 * @see docs/features.md F4.3 — Confirm deck hand-off (lend)
 * @see docs/features.md F4.4 — Confirm deck return
 * @see docs/features.md F4.7 — Cancel a borrow request
 * @see docs/features.md F4.8 — Staff-delegated lending
 */
#[Route('/borrow')]
#[IsGranted('ROLE_USER')]
class BorrowController extends AbstractController
{
    /**
     * @see docs/features.md F4.1 — Request to borrow a deck
     * @see docs/features.md F4.8 — Staff-delegated lending
     */
    #[Route('/{id}', name: 'app_borrow_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Borrow $borrow): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isOwner = $borrow->getDeck()->getOwner()->getId() === $user->getId();
        $isBorrower = $borrow->getBorrower()->getId() === $user->getId();
        $isDelegatedStaff = $borrow->isDelegatedToStaff() && $borrow->getEvent()->isOrganizerOrStaff($user);

        return $this->render('borrow/show.html.twig', [
            'borrow' => $borrow,
            'canApprove' => ($isOwner || $isDelegatedStaff) && BorrowStatus::Pending === $borrow->getStatus(),
            'canDeny' => ($isOwner || $isDelegatedStaff) && BorrowStatus::Pending === $borrow->getStatus(),
            'canHandOff' => ($isOwner || $isDelegatedStaff) && BorrowStatus::Approved === $borrow->getStatus(),
            'canReturn' => ($isOwner || $isDelegatedStaff) && \in_array($borrow->getStatus(), [BorrowStatus::Lent, BorrowStatus::Overdue], true),
            'canCancel' => ($isBorrower || $isOwner) && $borrow->isCancellable(),
            'canReturnToOwner' => ($isOwner || $isDelegatedStaff) && BorrowStatus::Returned === $borrow->getStatus() && $borrow->isDelegatedToStaff(),
        ]);
    }

    /**
     * @see docs/features.md F4.1 — Request to borrow a deck
     */
    #[Route('/request', name: 'app_borrow_request', methods: ['POST'])]
    public function request(Request $request, BorrowService $borrowService, DeckRepository $deckRepository, EventRepository $eventRepository): Response
    {
        $eventId = $request->getPayload()->getInt('event_id');
        $deckId = $request->getPayload()->getInt('deck_id');
        $redirectTo = $request->getPayload()->getString('redirect_to');

        $event = $eventRepository->find($eventId);
        if (null === $event) {
            throw $this->createNotFoundException('Event not found.');
        }

        if (!$this->isCsrfTokenValid('borrow-request-'.$eventId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->buildBorrowRedirect($redirectTo, $eventId, $deckId);
        }

        $deck = $deckRepository->find($deckId);
        if (null === $deck) {
            $this->addFlash('danger', 'Deck not found.');

            return $this->buildBorrowRedirect($redirectTo, $eventId, $deckId);
        }

        /** @var User $user */
        $user = $this->getUser();
        $notes = $request->getPayload()->getString('notes');

        try {
            $borrowService->requestBorrow($deck, $user, $event, '' !== $notes ? $notes : null);
            $this->addFlash('success', \sprintf('Borrow request for "%s" submitted.', $deck->getName()));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->buildBorrowRedirect($redirectTo, $eventId, $deckId);
    }

    /**
     * @see docs/features.md F4.2 — Approve / deny a borrow request
     */
    #[Route('/{id}/approve', name: 'app_borrow_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(Borrow $borrow, Request $request, BorrowService $borrowService): Response
    {
        if (!$this->isCsrfTokenValid('approve-borrow-'.$borrow->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $borrowService->approve($borrow, $user);
            $this->addFlash('success', 'Borrow request approved.');
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
    }

    /**
     * @see docs/features.md F4.2 — Approve / deny a borrow request
     */
    #[Route('/{id}/deny', name: 'app_borrow_deny', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deny(Borrow $borrow, Request $request, BorrowService $borrowService): Response
    {
        if (!$this->isCsrfTokenValid('deny-borrow-'.$borrow->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $borrowService->deny($borrow, $user);
            $this->addFlash('success', 'Borrow request denied.');
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
    }

    /**
     * @see docs/features.md F4.3 — Confirm deck hand-off (lend)
     */
    #[Route('/{id}/hand-off', name: 'app_borrow_hand_off', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function handOff(Borrow $borrow, Request $request, BorrowService $borrowService): Response
    {
        if (!$this->isCsrfTokenValid('hand-off-borrow-'.$borrow->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $borrowService->handOff($borrow, $user);
            $this->addFlash('success', 'Deck has been handed off.');
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
    }

    /**
     * @see docs/features.md F4.4 — Confirm deck return
     */
    #[Route('/{id}/return', name: 'app_borrow_return', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function confirmReturn(Borrow $borrow, Request $request, BorrowService $borrowService): Response
    {
        if (!$this->isCsrfTokenValid('return-borrow-'.$borrow->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $borrowService->confirmReturn($borrow, $user);
            $this->addFlash('success', 'Deck return confirmed.');
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
    }

    /**
     * @see docs/features.md F4.7 — Cancel a borrow request
     */
    #[Route('/{id}/cancel', name: 'app_borrow_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(Borrow $borrow, Request $request, BorrowService $borrowService): Response
    {
        if (!$this->isCsrfTokenValid('cancel-borrow-'.$borrow->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $borrowService->cancel($borrow, $user);
            $this->addFlash('success', 'Borrow has been cancelled.');
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
    }

    /**
     * @see docs/features.md F4.8 — Staff-delegated lending
     */
    #[Route('/{id}/return-to-owner', name: 'app_borrow_return_to_owner', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function returnToOwner(Borrow $borrow, Request $request, BorrowService $borrowService): Response
    {
        if (!$this->isCsrfTokenValid('return-to-owner-borrow-'.$borrow->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $borrowService->returnToOwner($borrow, $user);
            $this->addFlash('success', 'Deck returned to owner.');
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_borrow_show', ['id' => $borrow->getId()]);
    }

    private function buildBorrowRedirect(string $redirectTo, int $eventId, int $deckId): Response
    {
        if ('deck' === $redirectTo) {
            return $this->redirectToRoute('app_deck_show', ['id' => $deckId]);
        }

        if ('event_decks' === $redirectTo) {
            return $this->redirectToRoute('app_event_decks', ['id' => $eventId]);
        }

        return $this->redirectToRoute('app_event_show', ['id' => $eventId]);
    }
}

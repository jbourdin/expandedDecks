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

use App\Entity\BannedCard;
use App\Form\BannedCardFormType;
use App\Repository\BannedCardRepository;
use App\Service\BannedCardEnricher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin CRUD for the banned card list (soft-delete + reactivate).
 *
 * @see docs/features.md F6.5 — Banned card list management
 * @see docs/features.md F6.14 — Banned cards public page
 */
#[Route('/admin/banned-card')]
#[IsGranted('ROLE_ADMIN')]
class AdminBannedCardController extends AbstractAppController
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly BannedCardRepository $bannedCardRepository,
        private readonly BannedCardEnricher $cardEnricher,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_banned_card_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $view = $request->query->getString('view', 'active');
        if (!\in_array($view, ['active', 'history'], true)) {
            $view = 'active';
        }

        $cards = 'history' === $view
            ? $this->bannedCardRepository->findDeletedOrderedByDeletionDate()
            : $this->bannedCardRepository->findActiveOrderedByEffectiveDate();

        return $this->render('admin/banned_card/list.html.twig', [
            'cards' => $cards,
            'currentView' => $view,
        ]);
    }

    #[Route('/new', name: 'app_admin_banned_card_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $card = new BannedCard();

        $form = $this->createForm(BannedCardFormType::class, $card);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $this->bannedCardRepository->findOneIncludingDeleted(
                $card->getSetCode(),
                $card->getCardNumber(),
            );

            if ($existing instanceof BannedCard) {
                $existing->setCardName($card->getCardName());
                $existing->setEffectiveDate($card->getEffectiveDate());
                $existing->setSourceUrl($card->getSourceUrl());
                $existing->setExplanation($card->getExplanation());
                $existing->setDeletedAt(null);
                $this->cardEnricher->enrich($existing);

                $this->entityManager->flush();

                $this->addFlash('success', 'app.admin.banned_card.flash.reactivated');

                return $this->redirectToRoute('app_admin_banned_card_edit', ['id' => $existing->getId()]);
            }

            $this->cardEnricher->enrich($card);
            $this->entityManager->persist($card);
            $this->entityManager->flush();

            $this->addFlash('success', 'app.admin.banned_card.flash.created');

            return $this->redirectToRoute('app_admin_banned_card_edit', ['id' => $card->getId()]);
        }

        return $this->render('admin/banned_card/form.html.twig', [
            'form' => $form,
            'card' => $card,
            'isEdit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_banned_card_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, BannedCard $card): Response
    {
        $form = $this->createForm(BannedCardFormType::class, $card);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->cardEnricher->enrich($card);
            $this->entityManager->flush();

            $this->addFlash('success', 'app.admin.banned_card.flash.updated');

            return $this->redirectToRoute('app_admin_banned_card_edit', ['id' => $card->getId()]);
        }

        return $this->render('admin/banned_card/form.html.twig', [
            'form' => $form,
            'card' => $card,
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_banned_card_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, BannedCard $card): Response
    {
        if (!$this->isCsrfTokenValid('banned-card-delete-'.$card->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_banned_card_edit', ['id' => $card->getId()]);
        }

        $card->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->addFlash('success', 'app.admin.banned_card.flash.deleted');

        return $this->redirectToRoute('app_admin_banned_card_list');
    }

    #[Route('/{id}/restore', name: 'app_admin_banned_card_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(Request $request, BannedCard $card): Response
    {
        if (!$this->isCsrfTokenValid('banned-card-restore-'.$card->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_banned_card_list', ['view' => 'history']);
        }

        $card->setDeletedAt(null);
        $this->entityManager->flush();

        $this->addFlash('success', 'app.admin.banned_card.flash.restored');

        return $this->redirectToRoute('app_admin_banned_card_edit', ['id' => $card->getId()]);
    }
}

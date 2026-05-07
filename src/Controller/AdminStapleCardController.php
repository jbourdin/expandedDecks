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

use App\Constants\StapleCardBucket;
use App\Entity\StapleCard;
use App\Form\StapleCardCreateData;
use App\Form\StapleCardCreateFormType;
use App\Form\StapleCardFormType;
use App\Repository\StapleCardRepository;
use App\Service\StapleCardEnricher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin CRUD for staple cards (one row per CardIdentity).
 *
 * @see docs/features.md F6.15 — Staple cards
 */
#[Route('/admin/staple-cards')]
#[IsGranted('ROLE_ARCHETYPE_EDITOR')]
class AdminStapleCardController extends AbstractAppController
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly StapleCardRepository $stapleCardRepository,
        private readonly StapleCardEnricher $enricher,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_staple_card_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $view = $request->query->getString('view', 'active');
        if (!\in_array($view, ['active', 'history'], true)) {
            $view = 'active';
        }

        $bucket = $request->query->getString('bucket', StapleCardBucket::POKEMON);
        if (!StapleCardBucket::isValid($bucket)) {
            $bucket = StapleCardBucket::POKEMON;
        }

        if ('history' === $view) {
            $cards = $this->stapleCardRepository->findDeletedOrderedByDeletionDate();
        } else {
            $cards = $this->stapleCardRepository->findActiveByBucket($bucket);
        }

        return $this->render('admin/staple_card/list.html.twig', [
            'cards' => $cards,
            'currentView' => $view,
            'currentBucket' => $bucket,
            'buckets' => StapleCardBucket::ORDER,
        ]);
    }

    #[Route('/new', name: 'app_admin_staple_card_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $data = new StapleCardCreateData();
        $form = $this->createForm(StapleCardCreateFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $parsed = self::parseCode($data->code);
            if (null === $parsed) {
                $this->addFlash('danger', 'app.admin.staple_card.flash.invalid_code');

                return $this->redirectToRoute('app_admin_staple_card_new');
            }
            [$setCode, $cardNumber] = $parsed;

            $staple = $this->enricher->createFromCode($setCode, $cardNumber, $data->hotness, $data->note);
            if (null === $staple) {
                $this->addFlash('danger', 'app.admin.staple_card.flash.not_found');

                return $this->redirectToRoute('app_admin_staple_card_new');
            }

            $this->addFlash('success', 'app.admin.staple_card.flash.created');

            return $this->redirectToRoute('app_admin_staple_card_list', ['bucket' => $staple->getBucket()]);
        }

        return $this->render('admin/staple_card/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_staple_card_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, StapleCard $card): Response
    {
        $form = $this->createForm(StapleCardFormType::class, $card);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'app.admin.staple_card.flash.updated');

            return $this->redirectToRoute('app_admin_staple_card_edit', ['id' => $card->getId()]);
        }

        return $this->render('admin/staple_card/form.html.twig', [
            'form' => $form,
            'card' => $card,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_staple_card_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, StapleCard $card): Response
    {
        if (!$this->isCsrfTokenValid('staple-card-delete-'.$card->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_staple_card_edit', ['id' => $card->getId()]);
        }

        $card->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->addFlash('success', 'app.admin.staple_card.flash.deleted');

        return $this->redirectToRoute('app_admin_staple_card_list', ['bucket' => $card->getBucket()]);
    }

    #[Route('/{id}/restore', name: 'app_admin_staple_card_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(Request $request, StapleCard $card): Response
    {
        if (!$this->isCsrfTokenValid('staple-card-restore-'.$card->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_staple_card_list', ['view' => 'history']);
        }

        $card->setDeletedAt(null);
        $this->entityManager->flush();

        $this->addFlash('success', 'app.admin.staple_card.flash.restored');

        return $this->redirectToRoute('app_admin_staple_card_edit', ['id' => $card->getId()]);
    }

    /**
     * Persist staple ordering for a single bucket. Expects a JSON array of staple IDs in display order.
     */
    #[Route('/reorder/{bucket}', name: 'app_admin_staple_card_reorder', methods: ['POST'])]
    public function reorder(Request $request, string $bucket): JsonResponse
    {
        if (!StapleCardBucket::isValid($bucket)) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_bucket'], 400);
        }

        /** @var list<int> $ids */
        $ids = json_decode($request->getContent(), true) ?? [];

        foreach ($ids as $position => $id) {
            $staple = $this->stapleCardRepository->find($id);
            if ($staple instanceof StapleCard && $staple->getBucket() === $bucket) {
                $staple->setPosition($position);
            }
        }
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Parses a code like "LOR-093" / "LOR 093" / "LOR_093" into [setCode, cardNumber].
     *
     * @return array{0: string, 1: string}|null
     */
    private static function parseCode(string $code): ?array
    {
        $code = trim($code);
        if (1 === preg_match('/^([A-Za-z0-9]+)[\s\-_]+([A-Za-z0-9]+)$/', $code, $matches)) {
            return [strtoupper($matches[1]), $matches[2]];
        }

        return null;
    }
}

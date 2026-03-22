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

use App\Message\EnrichDeckVersionMessage;
use App\Repository\BannedCardRepository;
use App\Repository\DeckVersionRepository;
use App\Service\BannedCardsSyncService;
use App\Service\EnrichmentFlushService;
use App\Service\Mosaic\MosaicRedispatchService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/technical')]
#[IsGranted('ROLE_TECHNICAL_ADMIN')]
class AdminTechnicalController extends AbstractAppController
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly DeckVersionRepository $deckVersionRepository,
        private readonly BannedCardRepository $bannedCardRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly BannedCardsSyncService $bannedCardsSyncService,
        private readonly MosaicRedispatchService $mosaicRedispatchService,
        private readonly EnrichmentFlushService $enrichmentFlushService,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_technical_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $pendingEnrichments = $this->deckVersionRepository->findNotEnriched();
        $pendingMosaics = $this->deckVersionRepository->findEnrichedWithoutMosaic();
        $bannedCardCount = $this->bannedCardRepository->count();

        return $this->render('admin/technical/dashboard.html.twig', [
            'pendingEnrichments' => $pendingEnrichments,
            'pendingMosaics' => $pendingMosaics,
            'bannedCardCount' => $bannedCardCount,
        ]);
    }

    #[Route('/enrich-retry', name: 'app_admin_technical_enrich_retry', methods: ['POST'])]
    public function enrichRetry(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-enrich-retry', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $versions = $this->deckVersionRepository->findNotEnriched();

        if ([] === $versions) {
            $this->addFlash('info', 'app.admin.technical.enrich.none_pending');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        foreach ($versions as $version) {
            /** @var int $id */
            $id = $version->getId();
            $this->messageBus->dispatch(new EnrichDeckVersionMessage($id));
        }

        $this->addFlash('success', 'app.admin.technical.enrich.dispatched', ['%count%' => \count($versions)]);

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    /**
     * @see docs/features.md F6.6 — Visual deck list (card mosaic)
     */
    #[Route('/mosaic-generate', name: 'app_admin_technical_mosaic_generate', methods: ['POST'])]
    public function mosaicGenerate(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-mosaic-generate', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $count = $this->mosaicRedispatchService->redispatch();

        if (0 === $count) {
            $this->addFlash('info', 'app.admin.technical.mosaic.none_pending');
        } else {
            $this->addFlash('success', 'app.admin.technical.mosaic.dispatched', ['%count%' => $count]);
        }

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    #[Route('/flush-reenrich', name: 'app_admin_technical_flush_reenrich', methods: ['POST'])]
    public function flushAndReenrich(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-flush-reenrich', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $this->enrichmentFlushService->flush();

        $versions = $this->deckVersionRepository->findNotEnriched();

        foreach ($versions as $version) {
            /** @var int $id */
            $id = $version->getId();
            $this->messageBus->dispatch(new EnrichDeckVersionMessage($id));
        }

        $this->addFlash('warning', 'app.admin.technical.flush_reenrich.success', ['%count%' => \count($versions)]);

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    #[Route('/banned-cards-sync', name: 'app_admin_technical_banned_cards_sync', methods: ['POST'])]
    public function bannedCardsSync(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-banned-cards-sync', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $result = $this->bannedCardsSyncService->sync();

        if ($result->success) {
            $this->addFlash('success', 'app.admin.technical.banned_cards.synced');
        } else {
            $this->addFlash('danger', 'app.admin.technical.banned_cards.failed');
        }

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }
}

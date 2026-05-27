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

use App\Enum\SyncMode;
use App\Message\BuildSetMappingsMessage;
use App\Message\EnrichDeckVersionMessage;
use App\Message\SyncTcgdexSeriesMessage;
use App\Repository\BannedCardRepository;
use App\Repository\DeckVersionRepository;
use App\Repository\TcgdexSetMappingRepository;
use App\Service\BannedCardEnricher;
use App\Service\BannedCardsSyncService;
use App\Service\CardIdentity\CardIdentitySignatureRebuilder;
use App\Service\CardReenrichService;
use App\Service\EnrichmentFlushService;
use App\Service\Mosaic\MosaicRedispatchService;
use App\Service\Search\SearchIndexer;
use App\Service\Sprite\SpriteMappingSyncService;
use App\Service\Tcgdex\TcgdexApiThrottle;
use App\Service\Tcgdex\TcgdexSyncStatusService;
use App\Twig\Runtime\MenuRuntime;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
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
        private readonly BannedCardEnricher $bannedCardEnricher,
        private readonly MosaicRedispatchService $mosaicRedispatchService,
        private readonly EnrichmentFlushService $enrichmentFlushService,
        private readonly TcgdexSetMappingRepository $setMappingRepository,
        private readonly CardReenrichService $cardReenrichService,
        private readonly TcgdexSyncStatusService $syncStatusService,
        private readonly TcgdexApiThrottle $tcgdexApiThrottle,
        private readonly \App\Repository\StapleCardRepository $stapleCardRepository,
        private readonly \App\Service\StapleCardEnricher $stapleCardEnricher,
        private readonly SearchIndexer $searchIndexer,
        private readonly \App\Service\Deck\DeckCardSortBackfillService $sortBackfillService,
        private readonly CardIdentitySignatureRebuilder $signatureRebuilder,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_technical_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $pendingEnrichments = $this->deckVersionRepository->findNotEnriched();
        $pendingMosaics = $this->deckVersionRepository->findEnrichedWithoutMosaic();
        $bannedCards = $this->bannedCardRepository->findActiveOrderedByEffectiveDate();
        $bannedCardCount = 0;
        foreach ($bannedCards as $bannedCard) {
            $bannedCardCount += $bannedCard->getPrintings()->count();
        }
        $stapleCardCount = \count($this->stapleCardRepository->findAllActive());
        $setMappingCount = $this->setMappingRepository->count();

        return $this->render('admin/technical/dashboard.html.twig', [
            'pendingEnrichments' => $pendingEnrichments,
            'pendingMosaics' => $pendingMosaics,
            'bannedCardCount' => $bannedCardCount,
            'stapleCardCount' => $stapleCardCount,
            'setMappingCount' => $setMappingCount,
            'syncQueueDepth' => $this->syncStatusService->getQueueDepth(),
            'syncLastCompleted' => $this->syncStatusService->getLastSyncTimestamp(),
            'syncInCooldown' => $this->tcgdexApiThrottle->isInCooldown(),
            'pendingSortOrderBackfill' => $this->sortBackfillService->countPending(),
        ]);
    }

    /**
     * @see docs/features.md F2.28 — Preserve imported list order
     */
    #[Route('/sort-order-backfill', name: 'app_admin_technical_sort_order_backfill', methods: ['POST'])]
    public function sortOrderBackfill(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-sort-order-backfill', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $count = $this->sortBackfillService->redispatch();

        if (0 === $count) {
            $this->addFlash('info', 'app.admin.technical.sort_order_backfill.none_pending');
        } else {
            $this->addFlash('success', 'app.admin.technical.sort_order_backfill.dispatched', ['%count%' => $count]);
        }

        return $this->redirectToRoute('app_admin_technical_dashboard');
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

    #[Route('/reenrich-card', name: 'app_admin_technical_reenrich_card', methods: ['POST'])]
    public function reenrichCard(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-reenrich-card', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $setCode = trim($request->getPayload()->getString('set_code'));
        $cardNumber = trim($request->getPayload()->getString('card_number'));

        if ('' === $setCode || '' === $cardNumber) {
            $this->addFlash('warning', 'app.admin.technical.reenrich_card.empty');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $count = $this->cardReenrichService->reenrich($setCode, $cardNumber);

        if (0 === $count) {
            $this->addFlash('info', 'app.admin.technical.reenrich_card.no_match', [
                '%set_code%' => $setCode,
                '%card_number%' => $cardNumber,
            ]);
        } else {
            $this->addFlash('success', 'app.admin.technical.reenrich_card.dispatched', [
                '%set_code%' => $setCode,
                '%card_number%' => $cardNumber,
                '%count%' => $count,
            ]);
        }

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

    #[Route('/set-mappings-rebuild', name: 'app_admin_technical_set_mappings_rebuild', methods: ['POST'])]
    public function setMappingsRebuild(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-set-mappings-rebuild', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $this->setMappingRepository->truncate();
        $this->messageBus->dispatch(new BuildSetMappingsMessage());

        $this->addFlash('success', 'app.admin.technical.set_mappings.dispatched');

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    /**
     * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
     */
    #[Route('/sprite-mapping-rebuild', name: 'app_admin_technical_sprite_mapping_rebuild', methods: ['POST'])]
    public function spriteMappingRebuild(Request $request, SpriteMappingSyncService $syncService): Response
    {
        if (!$this->isCsrfTokenValid('technical-sprite-mapping-rebuild', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        try {
            $result = $syncService->sync();
            $this->addFlash('success', 'app.admin.technical.sprite_mapping.synced', [
                '%inserted%' => $result['inserted'],
                '%updated%' => $result['updated'],
                '%total%' => $result['total'],
            ]);
        } catch (\RuntimeException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    /**
     * @see docs/features.md F6.13 — Incremental TCGdex database sync
     */
    #[Route('/tcgdex-sync-insert', name: 'app_admin_technical_tcgdex_sync_insert', methods: ['POST'])]
    public function tcgdexSyncInsert(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-tcgdex-sync-insert', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $this->messageBus->dispatch(new SyncTcgdexSeriesMessage(SyncMode::Insert));
        $this->addFlash('success', 'app.admin.technical.tcgdex_sync.dispatched_insert');

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    /**
     * @see docs/features.md F6.13 — Incremental TCGdex database sync
     */
    #[Route('/tcgdex-sync-update', name: 'app_admin_technical_tcgdex_sync_update', methods: ['POST'])]
    public function tcgdexSyncUpdate(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-tcgdex-sync-update', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $this->messageBus->dispatch(new SyncTcgdexSeriesMessage(SyncMode::Update));
        $this->addFlash('success', 'app.admin.technical.tcgdex_sync.dispatched_update');

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

    #[Route('/banned-cards-enrich', name: 'app_admin_technical_banned_cards_enrich', methods: ['POST'])]
    public function bannedCardsEnrich(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-banned-cards-enrich', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $force = $request->getPayload()->getBoolean('force');
        [$linked, $unresolved] = $this->bannedCardEnricher->enrichAllActive($force);

        $this->addFlash('success', 'app.admin.technical.banned_cards.enriched', [
            '%linked%' => $linked,
            '%total%' => $linked + \count($unresolved),
        ]);

        if ([] !== $unresolved) {
            $this->addFlash('warning', 'app.admin.technical.banned_cards.enrich_unresolved', [
                '%cards%' => implode(', ', $unresolved),
            ]);
        }

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    #[Route('/staple-cards-enrich', name: 'app_admin_technical_staple_cards_enrich', methods: ['POST'])]
    public function stapleCardsEnrich(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-staple-cards-enrich', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $force = $request->getPayload()->getBoolean('force');
        [$linked, $unresolved] = $this->stapleCardEnricher->enrichAllActive($force);

        $this->addFlash('success', 'app.admin.technical.staple_cards.enriched', [
            '%linked%' => $linked,
            '%total%' => $linked + \count($unresolved),
        ]);

        if ([] !== $unresolved) {
            $this->addFlash('warning', 'app.admin.technical.staple_cards.enrich_unresolved', [
                '%cards%' => implode(', ', $unresolved),
            ]);
        }

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    /**
     * Rebuilds every Pokemon CardIdentity's attack signature against the
     * damage-aware format. Cross-era reprints whose damage values differ are
     * split off into find-or-created clone identities; printings move with
     * them. See {@see CardIdentitySignatureRebuilder} for the split rules.
     *
     * @see docs/features.md F6.10 — Card identity and printing model
     */
    #[Route('/card-identity-signature-rebuild', name: 'app_admin_technical_card_identity_signature_rebuild', methods: ['POST'])]
    public function cardIdentitySignatureRebuild(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-card-identity-signature-rebuild', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $result = $this->signatureRebuilder->rebuild();

        $this->addFlash('success', 'app.admin.technical.card_identity_signature.rebuilt', [
            '%updated%' => $result->updatedInPlace,
            '%split%' => $result->splitAsPrimary,
            '%clones%' => $result->clonesCreated,
            '%reused%' => $result->reusedExistingTarget,
            '%repointed%' => $result->printingsRepointed,
        ]);

        if (0 !== $result->printingsMissingTcgdexData || 0 !== $result->skippedNoTcgdexData) {
            $this->addFlash('warning', 'app.admin.technical.card_identity_signature.missing_data', [
                '%printings%' => $result->printingsMissingTcgdexData,
                '%identities%' => $result->skippedNoTcgdexData,
            ]);
        }

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    /**
     * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
     */
    #[Route('/search-reindex', name: 'app_admin_technical_search_reindex', methods: ['POST'])]
    public function searchReindex(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-search-reindex', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        if (!$this->searchIndexer->isHealthy()) {
            $this->addFlash('warning', 'app.admin.technical.search.unreachable');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $counts = $this->searchIndexer->reindexAll();
        $total = array_sum($counts);

        $this->addFlash('success', 'app.admin.technical.search.success', [
            '%total%' => $total,
            '%indexes%' => \count($counts),
        ]);

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    #[Route('/clear-cache', name: 'app_admin_technical_clear_cache', methods: ['POST'])]
    public function clearCache(Request $request, MenuRuntime $menuRuntime): Response
    {
        if (!$this->isCsrfTokenValid('technical-clear-cache', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $menuRuntime->invalidateCache();
        $this->addFlash('success', 'app.admin.technical.clear_cache.done');

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    /**
     * Clear the entire application cache pool.
     */
    #[Route('/clear-app-cache', name: 'app_admin_technical_clear_app_cache', methods: ['POST'])]
    public function clearAppCache(Request $request, CacheItemPoolInterface $cache): Response
    {
        if (!$this->isCsrfTokenValid('technical-clear-app-cache', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $cache->clear();
        $this->addFlash('success', 'app.admin.technical.clear_app_cache.done');

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    /**
     * Delete a specific key from the application cache pool.
     */
    #[Route('/clear-cache-key', name: 'app_admin_technical_clear_cache_key', methods: ['POST'])]
    public function clearCacheKey(Request $request, CacheInterface $cache): Response
    {
        if (!$this->isCsrfTokenValid('technical-clear-cache-key', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $key = trim($request->getPayload()->getString('cache_key'));

        if ('' === $key) {
            $this->addFlash('warning', 'app.admin.technical.clear_cache_key.empty');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $cache->delete($key);
        $this->addFlash('success', 'app.admin.technical.clear_cache_key.done', ['%key%' => $key]);

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }
}

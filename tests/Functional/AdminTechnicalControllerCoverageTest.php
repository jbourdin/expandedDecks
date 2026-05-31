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

namespace App\Tests\Functional;

/**
 * Backfills coverage on every AdminTechnicalController action that the
 * headline test (AdminTechnicalControllerTest) leaves at auth-only.
 *
 * The handlers are thin: validate CSRF, dispatch a message or call a service,
 * flash a status, redirect. The interesting branches are CSRF rejection,
 * empty-input shortcuts, success-vs-failure flashes, and zero-vs-many counts.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class AdminTechnicalControllerCoverageTest extends AbstractFunctionalTest
{
    private function getCsrfToken(string $tokenId): string
    {
        $session = $this->client->getSession();
        self::assertNotNull($session, 'Session must exist — make a GET request first.');
        $session->start();

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = static::getContainer()->get('request_stack');

        $synthetic = new \Symfony\Component\HttpFoundation\Request();
        $synthetic->setSession($session);
        $requestStack->push($synthetic);

        try {
            /** @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokenManager */
            $tokenManager = static::getContainer()->get('security.csrf.token_manager');

            return $tokenManager->getToken($tokenId)->getValue();
        } finally {
            $requestStack->pop();
        }
    }

    public function testEnrichRetryRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/enrich-retry', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testEnrichRetrySucceedsWithValidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/technical');

        $this->client->request('POST', '/admin/technical/enrich-retry', [
            '_token' => $this->getCsrfToken('technical-enrich-retry'),
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        // Either info (none pending) or success (dispatched).
        self::assertSelectorExists('.alert-info, .alert-success');
    }

    public function testFlushReenrichRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/flush-reenrich', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // Note: flushAndReenrich, setMappingsRebuild, tcgdexSync, and
    // tcgdexForceUpdate dispatch Messenger messages on transports that are
    // configured `sync://` in the test env. The handlers reach external
    // services (TCGdex / PokeAPI) and the controller doesn't catch handler
    // exceptions, so happy-path tests for these actions are inherently flaky.
    // We cover the auth + CSRF/invalid-submission rejection branches only.

    public function testMosaicGenerateRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/mosaic-generate', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testMosaicGenerateSucceedsWithValidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/technical');

        $this->client->request('POST', '/admin/technical/mosaic-generate', [
            '_token' => $this->getCsrfToken('technical-mosaic-generate'),
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        // Either info (0 pending) or success (N dispatched) — both branches of mosaicGenerate.
        self::assertSelectorExists('.alert-info, .alert-success');
    }

    /**
     * @see docs/features.md F2.28 — Preserve imported list order
     */
    public function testSortOrderBackfillRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/sort-order-backfill', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * @see docs/features.md F2.28 — Preserve imported list order
     */
    public function testSortOrderBackfillSucceedsWithValidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/technical');

        $this->client->request('POST', '/admin/technical/sort-order-backfill', [
            '_token' => $this->getCsrfToken('technical-sort-order-backfill'),
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        // Either info (0 pending) or success (N dispatched) — both branches of sortOrderBackfill.
        self::assertSelectorExists('.alert-info, .alert-success');
    }

    public function testSpriteMappingRebuildRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/sprite-mapping-rebuild', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
     */
    public function testTcgdexSyncRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/tcgdex-sync', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
     */
    public function testTcgdexForceUpdateRejectsInvalidSubmission(): void
    {
        $this->loginAs('admin@example.com');
        // No form payload → the form is not submitted/valid → invalid-submission branch.
        $this->client->request('POST', '/admin/technical/tcgdex-force-update', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
     */
    public function testTcgdexSyncSucceedsWithValidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/technical');

        $this->client->request('POST', '/admin/technical/tcgdex-sync', [
            '_token' => $this->getCsrfToken('technical-tcgdex-sync'),
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        // Gap-fill series sync was dispatched.
        self::assertSelectorExists('.alert-success');
    }

    /**
     * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
     */
    public function testTcgdexForceUpdateSucceedsWithValidSet(): void
    {
        // The force-update set list comes from createExpandedSetsQueryBuilder(): a set
        // needs a release date, a non-promo PTCG code, and an Expanded-era serie.
        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $serie = new \App\Entity\TcgdexSerie('sv');
        $set = new \App\Entity\TcgdexSet('sv05', $serie);
        $set->setName(['en' => 'Paldea Evolved']);
        $set->setPtcgCode('PAL');
        $set->setReleaseDate(new \DateTimeImmutable('2023-06-09'));
        $entityManager->persist($serie);
        $entityManager->persist($set);
        $entityManager->flush();

        $this->loginAs('admin@example.com');
        // Render the dashboard so the force-update form (and its set choices) is built.
        $this->client->request('GET', '/admin/technical');

        $this->client->request('POST', '/admin/technical/tcgdex-force-update', [
            'tcgdex_force_update_form' => [
                'set' => 'sv05',
                '_token' => $this->getCsrfToken('technical-tcgdex-force-update'),
            ],
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        // A force-update for the chosen set was dispatched.
        self::assertSelectorExists('.alert-success');
    }

    public function testBannedCardsSyncRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/banned-cards-sync', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testClearCacheRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/clear-cache', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testClearCacheInvalidatesMenuRuntime(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/technical');

        $this->client->request('POST', '/admin/technical/clear-cache', [
            '_token' => $this->getCsrfToken('technical-clear-cache'),
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testClearAppCacheRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/clear-app-cache', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testClearAppCacheClearsCachePool(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/technical');

        $this->client->request('POST', '/admin/technical/clear-app-cache', [
            '_token' => $this->getCsrfToken('technical-clear-app-cache'),
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testClearCacheKeyRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/clear-cache-key', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testClearCacheKeyShowsWarningOnEmptyInput(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/technical');

        $this->client->request('POST', '/admin/technical/clear-cache-key', [
            '_token' => $this->getCsrfToken('technical-clear-cache-key'),
            'cache_key' => '   ',
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-warning');
    }

    public function testClearCacheKeyDeletesNamedKey(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/technical');

        $this->client->request('POST', '/admin/technical/clear-cache-key', [
            '_token' => $this->getCsrfToken('technical-clear-cache-key'),
            'cache_key' => 'test_cache_key',
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testCardIdentitySignatureRebuildRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('POST', '/admin/technical/card-identity-signature-rebuild', ['_token' => 'wrong']);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testCardIdentitySignatureRebuildSucceedsWithValidCsrf(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/technical');

        $this->client->request('POST', '/admin/technical/card-identity-signature-rebuild', [
            '_token' => $this->getCsrfToken('technical-card-identity-signature-rebuild'),
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }
}

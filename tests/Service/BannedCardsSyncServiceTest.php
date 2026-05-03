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

namespace App\Tests\Service;

use App\Entity\BannedCard;
use App\Entity\BannedCardPrinting;
use App\Repository\BannedCardPrintingRepository;
use App\Repository\BannedCardRepository;
use App\Service\BannedCardEnricher;
use App\Service\BannedCardSeedData;
use App\Service\BannedCardsSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @see docs/features.md F6.5 — Banned card list management
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardsSyncServiceTest extends TestCase
{
    private function buildHtml(string $expandedSection): string
    {
        return '<html><body><h2>Standard</h2><ul><li><a>SomeCard</a> (Scarlet &amp; Violet, 1/100)</li></ul>'
            .'<h2>Expanded</h2>'.$expandedSection
            .'</body></html>';
    }

    private function buildSeedStub(): BannedCardSeedData
    {
        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findActiveOrderedByEffectiveDate')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        return new BannedCardSeedData($bannedCardRepo, $entityManager);
    }

    private function buildEnricherStub(): BannedCardEnricher
    {
        $apiClient = $this->createStub(\App\Service\Tcgdex\TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);

        $identityResolver = $this->createStub(\App\Service\CardIdentity\CardIdentityResolver::class);

        $cardPrintingRepository = $this->createStub(\App\Repository\CardPrintingRepository::class);
        $cardPrintingRepository->method('findFirstBySetCodeAndCardNumber')->willReturn(null);

        $bannedCardPrintingRepository = $this->createStub(BannedCardPrintingRepository::class);
        $bannedCardPrintingRepository->method('findAllOrderedBySetAndNumber')->willReturn([]);

        $bannedCardRepository = $this->createStub(BannedCardRepository::class);
        $bannedCardRepository->method('findActiveOrderedByEffectiveDate')->willReturn([]);
        $bannedCardRepository->method('findOneByCardIdentity')->willReturn(null);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        return new BannedCardEnricher(
            $apiClient,
            $identityResolver,
            $cardPrintingRepository,
            $bannedCardPrintingRepository,
            $bannedCardRepository,
            $entityManager,
        );
    }

    public function testSyncAddsNewBannedPrintings(): void
    {
        $html = $this->buildHtml(
            '<ul>'
            .'<li><a href="#">Archeops</a> (<em>Black &amp; White—Noble Victories</em>, 67/101; <em>Black &amp; White—Dark Explorers</em>, 110/108)</li>'
            .'<li><a href="#">Ghetsis</a> (<em>Black &amp; White—Plasma Freeze</em>, 101/116 and 115/116)</li>'
            .'</ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $printingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $printingRepo->method('findOneBySetCodeAndCardNumber')->willReturn(null);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findActiveOrderedByEffectiveDate')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $printingRepo, $entityManager, $this->buildEnricherStub(), $this->buildSeedStub());
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(4, $result->added);
        self::assertSame(0, $result->removed);
        self::assertSame(0, $result->unchanged);
    }

    public function testSyncSkipsExistingPrintings(): void
    {
        $html = $this->buildHtml(
            '<ul><li><a href="#">Archeops</a> (<em>Black &amp; White—Noble Victories</em>, 67/101)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $existingParent = new BannedCard();
        $existingParent->setCardName('Archeops');
        $existingPrinting = new BannedCardPrinting();
        $existingPrinting->setSetCode('NVI');
        $existingPrinting->setCardNumber('67');
        $existingParent->addPrinting($existingPrinting);

        $printingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $printingRepo->method('findOneBySetCodeAndCardNumber')
            ->willReturnCallback(static fn (string $setCode, string $cardNumber): ?BannedCardPrinting => 'NVI' === $setCode && '67' === $cardNumber ? $existingPrinting : null);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findActiveOrderedByEffectiveDate')->willReturn([$existingParent]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $printingRepo, $entityManager, $this->buildEnricherStub(), $this->buildSeedStub());
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(0, $result->added);
        self::assertSame(0, $result->removed);
        self::assertSame(1, $result->unchanged);
    }

    public function testSyncSoftDeletesParentsWithNoMatchingPrintings(): void
    {
        $html = $this->buildHtml(
            '<ul><li><a href="#">Archeops</a> (<em>Black &amp; White—Noble Victories</em>, 67/101)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $archeopsParent = new BannedCard();
        $archeopsParent->setCardName('Archeops');
        $archeopsPrinting = new BannedCardPrinting();
        $archeopsPrinting->setSetCode('NVI');
        $archeopsPrinting->setCardNumber('67');
        $archeopsParent->addPrinting($archeopsPrinting);

        $oldParent = new BannedCard();
        $oldParent->setCardName('Old Banned Card');
        $oldPrinting = new BannedCardPrinting();
        $oldPrinting->setSetCode('XY');
        $oldPrinting->setCardNumber('999');
        $oldParent->addPrinting($oldPrinting);

        $printingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $printingRepo->method('findOneBySetCodeAndCardNumber')
            ->willReturnCallback(static fn (string $setCode, string $cardNumber): ?BannedCardPrinting => 'NVI' === $setCode && '67' === $cardNumber ? $archeopsPrinting : null);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findActiveOrderedByEffectiveDate')->willReturn([$archeopsParent, $oldParent]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $printingRepo, $entityManager, $this->buildEnricherStub(), $this->buildSeedStub());
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(0, $result->added);
        self::assertSame(1, $result->removed);
        self::assertSame(1, $result->unchanged);
        self::assertNull($archeopsParent->getDeletedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $oldParent->getDeletedAt());
    }

    public function testSyncReactivatesSoftDeletedParents(): void
    {
        $html = $this->buildHtml(
            '<ul><li><a href="#">Archeops</a> (<em>Black &amp; White—Noble Victories</em>, 67/101)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $existingParent = new BannedCard();
        $existingParent->setCardName('Archeops');
        $existingParent->setDeletedAt(new \DateTimeImmutable('2024-01-01'));
        $existingPrinting = new BannedCardPrinting();
        $existingPrinting->setSetCode('NVI');
        $existingPrinting->setCardNumber('67');
        $existingParent->addPrinting($existingPrinting);

        $printingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $printingRepo->method('findOneBySetCodeAndCardNumber')
            ->willReturnCallback(static fn (string $setCode, string $cardNumber): ?BannedCardPrinting => 'NVI' === $setCode && '67' === $cardNumber ? $existingPrinting : null);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findActiveOrderedByEffectiveDate')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $printingRepo, $entityManager, $this->buildEnricherStub(), $this->buildSeedStub());
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(1, $result->added);
        self::assertSame(0, $result->unchanged);
        self::assertNull($existingParent->getDeletedAt());
    }

    public function testSyncFailsWhenNoExpandedSection(): void
    {
        $html = '<html><body><h2>Standard</h2><ul><li>Card (Set, 1/100)</li></ul></body></html>';

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $printingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $printingRepo, $entityManager, $this->buildEnricherStub(), $this->buildSeedStub());
        $result = $service->sync();

        self::assertFalse($result->success);
        self::assertNotNull($result->error);
    }

    public function testSyncMatchesExpandedFormatHeading(): void
    {
        $html = '<html><body><h2>Standard</h2><h2>Expanded Format</h2>'
            .'<ul><li><a href="#">Archeops</a> (<em>Black &amp; White—Noble Victories</em>, 67/101)</li></ul></body></html>';

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $printingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $printingRepo->method('findOneBySetCodeAndCardNumber')->willReturn(null);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findActiveOrderedByEffectiveDate')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $printingRepo, $entityManager, $this->buildEnricherStub(), $this->buildSeedStub());
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(1, $result->added);
    }

    public function testSyncWarnsOnUnknownSetName(): void
    {
        $html = $this->buildHtml(
            '<ul><li><a href="#">TestCard</a> (<em>Unknown Future Set</em>, 42/100)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $printingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $printingRepo->method('findOneBySetCodeAndCardNumber')->willReturn(null);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findActiveOrderedByEffectiveDate')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $printingRepo, $entityManager, $this->buildEnricherStub(), $this->buildSeedStub());
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(0, $result->added);
        self::assertNotEmpty($result->warnings);
        self::assertStringContainsString('Unknown set', $result->warnings[0]);
    }

    public function testSyncSkipsActiveParentsWithEmptyPrintingsDuringSoftDelete(): void
    {
        // Parent with an empty printings collection must be skipped during the
        // soft-delete pass — otherwise findActiveOrderedByEffectiveDate could
        // return a transient row that gets mistakenly soft-deleted as "all
        // printings missing".
        $html = $this->buildHtml(
            '<ul><li><a href="#">Archeops</a> (<em>Black &amp; White—Noble Victories</em>, 67/101)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $emptyParent = new BannedCard();
        $emptyParent->setCardName('Empty Placeholder');

        $printingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $printingRepo->method('findOneBySetCodeAndCardNumber')->willReturn(null);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findActiveOrderedByEffectiveDate')->willReturn([$emptyParent]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $printingRepo, $entityManager, $this->buildEnricherStub(), $this->buildSeedStub());
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(0, $result->removed);
        self::assertNull($emptyParent->getDeletedAt());
    }

    public function testSyncReusesParentForRepeatedIdentityViaInLoopCache(): void
    {
        // Two distinct (setCode, cardNumber) entries that, after enrichment,
        // resolve to the same CardIdentity. The first creates a placeholder
        // parent, the second must find that parent through the in-memory cache
        // (the DB lookup still returns null since flush is deferred). Both
        // printings end up under the same parent — the duplicate placeholder
        // is dropped.
        $html = $this->buildHtml(
            '<ul><li><a href="#">Unown</a> (<em>Sun &amp; Moon—Lost Thunder</em>, 90/214 and 91/214)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $identity = $this->createStub(\App\Entity\CardIdentity::class);
        $identity->method('getId')->willReturn(42);
        $identity->method('getName')->willReturn('Unown');

        $local = $this->createStub(\App\Entity\CardPrinting::class);
        $local->method('getCardIdentity')->willReturn($identity);

        $printingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $printingRepo->method('findOneBySetCodeAndCardNumber')->willReturn(null);

        // findOneByCardIdentity always returns null — the in-loop cache is the
        // only thing that prevents creating two parents for the same identity.
        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findActiveOrderedByEffectiveDate')->willReturn([]);
        $bannedCardRepo->method('findOneByCardIdentity')->willReturn(null);

        $cardPrintingRepository = $this->createStub(\App\Repository\CardPrintingRepository::class);
        $cardPrintingRepository->method('findFirstBySetCodeAndCardNumber')->willReturn($local);

        $apiClient = $this->createStub(\App\Service\Tcgdex\TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);

        $identityResolver = $this->createStub(\App\Service\CardIdentity\CardIdentityResolver::class);

        $enricher = new BannedCardEnricher(
            $apiClient,
            $identityResolver,
            $cardPrintingRepository,
            $printingRepo,
            $bannedCardRepo,
            $this->createStub(EntityManagerInterface::class),
        );

        // Track parent removals — the duplicate placeholder must be dropped.
        $removed = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });
        $entityManager->expects(self::once())->method('flush');

        $service = new BannedCardsSyncService(
            $httpClient,
            $bannedCardRepo,
            $printingRepo,
            $entityManager,
            $enricher,
            $this->buildSeedStub(),
        );

        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(2, $result->added);
        self::assertCount(1, $removed, 'second placeholder parent should be removed after merge');
    }

    public function testSyncParsesCardNameWithEmTagInAltAttribute(): void
    {
        $html = $this->buildHtml(
            '<ul><li>'
            .'<a rel="imagepopup" href="https://www.pokemon.com/us/pokemon-tcg/pokemon-cards/xy-series/xy6/77/" alt="Shaymin-<em>EX</em>">Shaymin-<em>EX</em></a>'
            .' (<em>XY—Roaring Skies</em>, 77/108, 77a/108, and 106/108)'
            .'</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $printingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $printingRepo->method('findOneBySetCodeAndCardNumber')->willReturn(null);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findActiveOrderedByEffectiveDate')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $printingRepo, $entityManager, $this->buildEnricherStub(), $this->buildSeedStub());
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(3, $result->added);
    }
}

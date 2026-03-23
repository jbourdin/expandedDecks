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
use App\Repository\BannedCardRepository;
use App\Service\BannedCardsSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @see docs/features.md F6.5 — Banned card list management
 */
class BannedCardsSyncServiceTest extends TestCase
{
    private function buildHtml(string $expandedSection): string
    {
        return '<html><body><h2>Standard</h2><ul><li><a>SomeCard</a> (Scarlet &amp; Violet, 1/100)</li></ul>'
            .'<h2>Expanded</h2>'.$expandedSection
            .'</body></html>';
    }

    public function testSyncAddsNewBannedCards(): void
    {
        $html = $this->buildHtml(
            '<ul>'
            .'<li><a href="#">Archeops</a> (<em>Black &amp; White—Noble Victories</em>, 67/101; <em>Black &amp; White—Dark Explorers</em>, 110/108)</li>'
            .'<li><a href="#">Ghetsis</a> (<em>Black &amp; White—Plasma Freeze</em>, 101/116 and 115/116)</li>'
            .'</ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(4))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(4, $result->added);
        self::assertSame(0, $result->removed);
        self::assertSame(0, $result->unchanged);
    }

    public function testSyncSkipsExistingCards(): void
    {
        $html = $this->buildHtml(
            '<ul><li><a href="#">Archeops</a> (<em>Black &amp; White—Noble Victories</em>, 67/101)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $existing = new BannedCard();
        $existing->setCardName('Archeops');
        $existing->setSetCode('NVI');
        $existing->setCardNumber('67');

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')
            ->willReturnCallback(static fn (string $setCode, string $cardNumber): ?BannedCard => 'NVI' === $setCode && '67' === $cardNumber ? $existing : null);
        $bannedCardRepo->method('findAll')->willReturn([$existing]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(0, $result->added);
        self::assertSame(0, $result->removed);
        self::assertSame(1, $result->unchanged);
    }

    public function testSyncRemovesUnbannedCards(): void
    {
        $html = $this->buildHtml(
            '<ul><li><a href="#">Archeops</a> (<em>Black &amp; White—Noble Victories</em>, 67/101)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $archeops = new BannedCard();
        $archeops->setCardName('Archeops');
        $archeops->setSetCode('NVI');
        $archeops->setCardNumber('67');

        $oldCard = new BannedCard();
        $oldCard->setCardName('OldBannedCard');
        $oldCard->setSetCode('XY');
        $oldCard->setCardNumber('999');

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')
            ->willReturnCallback(static fn (string $setCode, string $cardNumber): ?BannedCard => 'NVI' === $setCode && '67' === $cardNumber ? $archeops : null);
        $bannedCardRepo->method('findAll')->willReturn([$archeops, $oldCard]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('remove')->with($oldCard);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(0, $result->added);
        self::assertSame(1, $result->removed);
        self::assertSame(1, $result->unchanged);
    }

    public function testSyncHandlesMultiplePrintingsOfSameCard(): void
    {
        $html = $this->buildHtml(
            '<ul><li><a href="#">Unown</a> (<em>Sun &amp; Moon—Lost Thunder</em>, 90/214 and 91/214)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist');

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(2, $result->added);
    }

    public function testSyncParsesCardNameWithEmTagInAltAttribute(): void
    {
        $html = $this->buildHtml(
            '<ul><li>'
            .'<a rel="imagepopup" href="https://www.pokemon.com/us/pokemon-tcg/pokemon-cards/xy-series/xy6/77/" target="_self" src="https://www.pokemon.com/static-assets/content-assets/cms2/img/cards/web/XY6/XY6_EN_77.png" alt="Shaymin-<em>EX</em>">Shaymin-<em>EX</em></a>'
            .' (<em>XY—Roaring Skies</em>, 77/108, 77a/108, and 106/108)'
            .'</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))->method('persist')
            ->willReturnCallback(static function (BannedCard $card) use (&$persisted): void {
                $persisted[] = $card->getCardName();
            });

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(3, $result->added);
        self::assertSame(['Shaymin-EX', 'Shaymin-EX', 'Shaymin-EX'], $persisted);
    }

    public function testSyncFailsWhenNoExpandedSection(): void
    {
        $html = '<html><body><h2>Standard</h2><ul><li>Card (Set, 1/100)</li></ul></body></html>';

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertFalse($result->success);
        self::assertNotNull($result->error);
    }

    public function testSyncMatchesExpandedFormatHeading(): void
    {
        // Some pages use ">Expanded Format<" instead of ">Expanded<"
        $html = '<html><body><h2>Standard</h2><h2>Expanded Format</h2>'
            .'<ul><li><a href="#">Archeops</a> (<em>Black &amp; White—Noble Victories</em>, 67/101)</li></ul></body></html>';

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(1, $result->added);
    }

    public function testSyncWarnsWhenNoUlFoundAfterExpandedSection(): void
    {
        $html = '<html><body><h2>Expanded</h2><p>No list here</p></body></html>';

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(0, $result->added);
        self::assertContains('Could not find a <ul> list in the Expanded section.', $result->warnings);
    }

    public function testSyncWarnsOnUnknownSetName(): void
    {
        $html = $this->buildHtml(
            '<ul><li><a href="#">TestCard</a> (<em>Unknown Future Set</em>, 42/100)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(0, $result->added);
        self::assertNotEmpty($result->warnings);
        self::assertStringContainsString('Unknown set', $result->warnings[0]);
    }

    public function testSyncWarnsWhenNoSetInfoInParentheses(): void
    {
        $html = $this->buildHtml(
            '<ul><li><a href="#">CardWithoutSetInfo</a></li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(0, $result->added);
        self::assertStringContainsString('No set info found', $result->warnings[0]);
    }

    public function testSyncWarnsWhenNoCommaInSetGroup(): void
    {
        $html = $this->buildHtml(
            '<ul><li><a href="#">BadEntry</a> (Black &amp; White—Noble Victories)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(0, $result->added);
        self::assertStringContainsString('Could not parse set group', $result->warnings[0]);
    }

    public function testSyncNormalizesDashVariantsInSetName(): void
    {
        // Use en-dash (–) instead of em-dash (—) in set name
        $html = $this->buildHtml(
            '<ul><li><a href="#">Archeops</a> (<em>Black &amp; White–Noble Victories</em>, 67/101)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');

        $service = new BannedCardsSyncService($httpClient, $bannedCardRepo, $entityManager);
        $result = $service->sync();

        self::assertTrue($result->success);
        self::assertSame(1, $result->added);
    }
}

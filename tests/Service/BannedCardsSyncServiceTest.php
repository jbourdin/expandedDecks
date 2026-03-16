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
}

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

namespace App\Tests\Command;

use App\Command\BannedCardsSyncCommand;
use App\Entity\BannedCard;
use App\Repository\BannedCardRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @see docs/features.md F6.5 — Banned card list management
 */
class BannedCardsSyncCommandTest extends TestCase
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

        $bannedCardRepo = $this->createMock(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        // Archeops: NVI 67 + DEX 110, Ghetsis: PLF 101 + PLF 115 = 4 entries
        $em->expects(self::exactly(4))->method('persist');
        $em->expects(self::once())->method('flush');

        $command = new BannedCardsSyncCommand($httpClient, $bannedCardRepo, $em);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('4 added', $tester->getDisplay());
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

        $bannedCardRepo = $this->createMock(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')
            ->with('NVI', '67')
            ->willReturn($existing);
        $bannedCardRepo->method('findAll')->willReturn([$existing]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $command = new BannedCardsSyncCommand($httpClient, $bannedCardRepo, $em);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('0 added', $tester->getDisplay());
        self::assertStringContainsString('1 unchanged', $tester->getDisplay());
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

        $bannedCardRepo = $this->createMock(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')
            ->willReturnCallback(static fn (string $setCode, string $cardNumber): ?BannedCard => 'NVI' === $setCode && '67' === $cardNumber ? $archeops : null);
        $bannedCardRepo->method('findAll')->willReturn([$archeops, $oldCard]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('remove')->with($oldCard);

        $command = new BannedCardsSyncCommand($httpClient, $bannedCardRepo, $em);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('1 removed', $tester->getDisplay());
    }

    public function testSyncHandlesMultiplePrintingsOfSameCard(): void
    {
        // Unown has two different card numbers in the same set — both are separate banned cards
        $html = $this->buildHtml(
            '<ul><li><a href="#">Unown</a> (<em>Sun &amp; Moon—Lost Thunder</em>, 90/214 and 91/214)</li></ul>',
        );

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createMock(BannedCardRepository::class);
        $bannedCardRepo->method('findOneBySetCodeAndNumber')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('persist');

        $command = new BannedCardsSyncCommand($httpClient, $bannedCardRepo, $em);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('2 added', $tester->getDisplay());
    }

    public function testSyncFailsWhenNoExpandedSection(): void
    {
        $html = '<html><body><h2>Standard</h2><ul><li>Card (Set, 1/100)</li></ul></body></html>';

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createMock(BannedCardRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $command = new BannedCardsSyncCommand($httpClient, $bannedCardRepo, $em);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
    }
}

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
        return '<html><body><h2>Standard</h2><ul><li><a>SomeCard</a></li></ul>'
            .'<h2>Expanded</h2>'.$expandedSection
            .'</body></html>';
    }

    public function testSyncAddsNewBannedCards(): void
    {
        $html = $this->buildHtml('<ul><li><a href="#">Archeops</a> (<em>BW</em>)</li><li><a href="#">Ghetsis</a> (<em>PLF</em>)</li></ul>');

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createMock(BannedCardRepository::class);
        $bannedCardRepo->method('findOneByName')->willReturn(null);
        $bannedCardRepo->method('findAll')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('persist');
        $em->expects(self::once())->method('flush');

        $command = new BannedCardsSyncCommand($httpClient, $bannedCardRepo, $em);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('2 added', $tester->getDisplay());
        self::assertStringContainsString('0 removed', $tester->getDisplay());
    }

    public function testSyncSkipsExistingCards(): void
    {
        $html = $this->buildHtml('<ul><li><a href="#">Archeops</a></li></ul>');

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $existing = new BannedCard();
        $existing->setCardName('Archeops');

        $bannedCardRepo = $this->createMock(BannedCardRepository::class);
        $bannedCardRepo->method('findOneByName')
            ->with('Archeops')
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
        $html = $this->buildHtml('<ul><li><a href="#">Archeops</a></li></ul>');

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $archeops = new BannedCard();
        $archeops->setCardName('Archeops');

        $oldCard = new BannedCard();
        $oldCard->setCardName('OldBannedCard');

        $bannedCardRepo = $this->createMock(BannedCardRepository::class);
        $bannedCardRepo->method('findOneByName')
            ->willReturnCallback(static fn (string $name): ?BannedCard => 'Archeops' === $name ? $archeops : null);
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

    public function testSyncFailsWhenNoExpandedSection(): void
    {
        $html = '<html><body><h2>Standard</h2><ul><li>Card</li></ul></body></html>';

        $httpClient = new MockHttpClient([new MockResponse($html)]);

        $bannedCardRepo = $this->createMock(BannedCardRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $command = new BannedCardsSyncCommand($httpClient, $bannedCardRepo, $em);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
    }
}

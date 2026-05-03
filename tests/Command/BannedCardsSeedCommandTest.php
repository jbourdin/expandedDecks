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

use App\Command\BannedCardsSeedCommand;
use App\Entity\BannedCard;
use App\Entity\BannedCardPrinting;
use App\Repository\BannedCardRepository;
use App\Service\BannedCardSeedData;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * BannedCardSeedData is final readonly so we exercise it through a real
 * instance backed by a stubbed repository.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardsSeedCommandTest extends TestCase
{
    public function testCommandSucceedsAndReportsCounts(): void
    {
        $card = new BannedCard();
        $card->setCardName('Archeops');
        $printing = new BannedCardPrinting();
        $printing->setSetCode('NVI');
        $printing->setCardNumber('67');
        $card->addPrinting($printing);

        $repository = $this->createStub(BannedCardRepository::class);
        $repository->method('findActiveOrderedByEffectiveDate')->willReturn([$card]);

        $seedData = new BannedCardSeedData($repository, $this->createStub(EntityManagerInterface::class));

        $tester = new CommandTester(new BannedCardsSeedCommand($seedData));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('1 card(s) updated', $display);
        self::assertStringContainsString('0 skipped', $display);
    }

    public function testCommandHandlesEmptyResult(): void
    {
        $repository = $this->createStub(BannedCardRepository::class);
        $repository->method('findActiveOrderedByEffectiveDate')->willReturn([]);

        $seedData = new BannedCardSeedData($repository, $this->createStub(EntityManagerInterface::class));

        $tester = new CommandTester(new BannedCardsSeedCommand($seedData));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('0 card(s) updated', $tester->getDisplay());
    }
}

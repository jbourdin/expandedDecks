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

use App\Command\BannedCardsEnrichCommand;
use App\Entity\BannedCard;
use App\Entity\BannedCardPrinting;
use App\Repository\BannedCardPrintingRepository;
use App\Repository\BannedCardRepository;
use App\Repository\CardPrintingRepository;
use App\Service\BannedCardEnricher;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Tcgdex\TcgdexApiClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * BannedCardEnricher is final readonly so we drive the command with a real
 * enricher backed by stubbed dependencies — the command's job is to translate
 * the [linked, unresolved] tuple into console output.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardsEnrichCommandTest extends TestCase
{
    public function testCommandReportsLinkedAndUnresolvedCounts(): void
    {
        $enricher = $this->buildEnricher(
            printings: [
                $this->buildBannedPrinting('LOT', '90', 'Card A'),
                $this->buildBannedPrinting('PHF', '99', 'Card B'),
            ],
        );

        $tester = new CommandTester(new BannedCardsEnrichCommand($enricher));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('Enriched 2', $display);
        // Both printings unresolved (no local hit, no API hit).
        self::assertStringContainsString('Linked 0 / 2', $display);
        self::assertStringContainsString('Could not resolve 2', $display);
        self::assertStringContainsString('Card A (LOT 90)', $display);
    }

    public function testCommandWithEmptyEnrichmentSucceeds(): void
    {
        $enricher = $this->buildEnricher(printings: []);

        $tester = new CommandTester(new BannedCardsEnrichCommand($enricher));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('Enriched 0', $display);
        self::assertStringContainsString('Linked 0 / 0', $display);
    }

    public function testCommandAcceptsForceFlag(): void
    {
        $enricher = $this->buildEnricher(printings: []);

        $tester = new CommandTester(new BannedCardsEnrichCommand($enricher));
        $tester->execute(['--force' => true]);

        self::assertSame(0, $tester->getStatusCode());
    }

    /**
     * @param list<BannedCardPrinting> $printings
     */
    private function buildEnricher(array $printings): BannedCardEnricher
    {
        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);

        $identityResolver = $this->createStub(CardIdentityResolver::class);

        $cardPrintingRepository = $this->createStub(CardPrintingRepository::class);
        $cardPrintingRepository->method('findFirstBySetCodeAndCardNumber')->willReturn(null);

        $bannedCardPrintingRepository = $this->createStub(BannedCardPrintingRepository::class);
        $bannedCardPrintingRepository->method('findAllOrderedBySetAndNumber')->willReturn($printings);

        $bannedCardRepository = $this->createStub(BannedCardRepository::class);
        $bannedCardRepository->method('findOneByCardIdentity')->willReturn(null);

        return new BannedCardEnricher(
            $apiClient,
            $identityResolver,
            $cardPrintingRepository,
            $bannedCardPrintingRepository,
            $bannedCardRepository,
            $this->createStub(EntityManagerInterface::class),
        );
    }

    private function buildBannedPrinting(string $setCode, string $cardNumber, string $cardName): BannedCardPrinting
    {
        $parent = new BannedCard();
        $parent->setCardName($cardName);

        $printing = new BannedCardPrinting();
        $printing->setSetCode($setCode);
        $printing->setCardNumber($cardNumber);
        $parent->addPrinting($printing);

        return $printing;
    }
}

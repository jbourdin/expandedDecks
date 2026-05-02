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
use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Service\BannedCardGrouper;
use App\Service\BannedCardPrintingLinker;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardGrouperTest extends TestCase
{
    private function buildLinkerStub(): BannedCardPrintingLinker
    {
        $stub = $this->createStub(\App\Repository\CardPrintingRepository::class);
        $stub->method('findFirstBySetCodeAndCardNumber')->willReturn(null);

        $tcgdex = $this->createStub(\App\Repository\TcgdexSetRepository::class);
        $tcgdex->method('findByPtcgCode')->willReturn(null);

        return new BannedCardPrintingLinker($stub, $tcgdex);
    }

    private function makeBannedCard(
        string $name,
        string $setCode,
        string $cardNumber,
        ?CardPrinting $printing = null,
        ?\DateTimeImmutable $effectiveDate = null,
        ?string $sourceUrl = null,
        ?string $explanation = null,
    ): BannedCard {
        $card = new BannedCard();
        $card->setCardName($name);
        $card->setSetCode($setCode);
        $card->setCardNumber($cardNumber);
        if (null !== $printing) {
            $card->setCardPrinting($printing);
        }
        if (null !== $effectiveDate) {
            $card->setEffectiveDate($effectiveDate);
        }
        if (null !== $sourceUrl) {
            $card->setSourceUrl($sourceUrl);
        }
        if (null !== $explanation) {
            $card->setExplanation($explanation);
        }

        return $card;
    }

    private function makePrinting(CardIdentity $identity, int $rarityTier, bool $withParsableTcgdexId = true): CardPrinting
    {
        $printing = new CardPrinting();
        $printing->setCardIdentity($identity);
        $printing->setRarityTier($rarityTier);
        // Default tcgdexId has a dash so the linker's PokemonTCG.io fallback can
        // build a URL. Pass false to opt out (used by "no image anywhere" tests).
        $tcgdexId = $withParsableTcgdexId
            ? 'tcgdex-'.bin2hex(random_bytes(4))
            : 'noimage'.bin2hex(random_bytes(4));
        $printing->setTcgdexId($tcgdexId);

        return $printing;
    }

    public function testGroupsByCardIdentityWhenLinked(): void
    {
        $identity = new CardIdentity();
        $printingA = $this->makePrinting($identity, 3);
        $printingB = $this->makePrinting($identity, 5);

        $cards = [
            $this->makeBannedCard('Archeops', 'NVI', '67', $printingA),
            $this->makeBannedCard('Archeops', 'DEX', '110', $printingB),
        ];

        $grouper = new BannedCardGrouper($this->buildLinkerStub());
        $groups = $grouper->group($cards, 'en');

        self::assertCount(1, $groups);
        self::assertSame('Archeops', $groups[0]->cardName);
        self::assertCount(2, $groups[0]->printings);
    }

    public function testRepresentativeIsLowestRarityTier(): void
    {
        $identity = new CardIdentity();
        $rarePrinting = $this->makePrinting($identity, 5);
        $rarePrinting->setImageUrl('https://example/rare/high.webp');
        $commonPrinting = $this->makePrinting($identity, 1);
        $commonPrinting->setImageUrl('https://example/common/high.webp');

        $rareBan = $this->makeBannedCard('Archeops', 'NVI', '67', $rarePrinting);
        $commonBan = $this->makeBannedCard('Archeops', 'DEX', '110', $commonPrinting);

        $grouper = new BannedCardGrouper($this->buildLinkerStub());
        $groups = $grouper->group([$rareBan, $commonBan], 'en');

        self::assertCount(1, $groups);
        self::assertSame($commonBan, $groups[0]->representative, 'Lowest-tier (cheapest) printing should illustrate the group.');
        self::assertSame('https://example/common/high.webp', $groups[0]->imageUrl);
    }

    public function testRepresentativePrefersLowestRarityWithImage(): void
    {
        $identity = new CardIdentity();
        // Cheap printing whose tcgdexId is non-parsable so no fallback URL applies.
        $cheapNoImage = $this->makePrinting($identity, 1, withParsableTcgdexId: false);
        $rareWithImage = $this->makePrinting($identity, 5);
        $rareWithImage->setImageUrl('https://example/rare/high.webp');

        $cheapBan = $this->makeBannedCard('Archeops', 'NVI', '67', $cheapNoImage);
        $rareBan = $this->makeBannedCard('Archeops', 'DEX', '110', $rareWithImage);

        $grouper = new BannedCardGrouper($this->buildLinkerStub());
        $groups = $grouper->group([$cheapBan, $rareBan], 'en');

        self::assertCount(1, $groups);
        self::assertSame($rareBan, $groups[0]->representative, 'Should skip the cheap printing without a resolvable image and use the rarer one that has one.');
        self::assertSame('https://example/rare/high.webp', $groups[0]->imageUrl);
    }

    public function testRepresentativeFallsBackToLowestTierWhenNoneHaveImage(): void
    {
        $identity = new CardIdentity();
        $printingA = $this->makePrinting($identity, 5, withParsableTcgdexId: false);
        $printingB = $this->makePrinting($identity, 1, withParsableTcgdexId: false);

        $a = $this->makeBannedCard('Archeops', 'NVI', '67', $printingA);
        $b = $this->makeBannedCard('Archeops', 'DEX', '110', $printingB);

        $grouper = new BannedCardGrouper($this->buildLinkerStub());
        $groups = $grouper->group([$a, $b], 'en');

        self::assertCount(1, $groups);
        // No image anywhere — fall back to the lowest tier.
        self::assertSame($b, $groups[0]->representative);
        self::assertNull($groups[0]->imageUrl);
    }

    public function testUnlinkedRowsAreNotGroupedByNameAlone(): void
    {
        // Two functionally-distinct cards that share a name (e.g. Unown HAND vs
        // Unown DAMAGE) without any linked CardPrinting must NOT be merged.
        $cards = [
            $this->makeBannedCard('Unown', 'LOT', '89'),
            $this->makeBannedCard('Unown', 'LOT', '91'),
            $this->makeBannedCard('Lysandre\'s Trump Card', 'PHF', '99'),
        ];

        $grouper = new BannedCardGrouper($this->buildLinkerStub());
        $groups = $grouper->group($cards, 'en');

        self::assertCount(3, $groups, 'Each unlinked row must stand on its own to avoid false grouping by name.');
    }

    public function testUnlinkedRowsCollapseOnceLinkedToSameIdentity(): void
    {
        $identity = new CardIdentity();
        $printing = $this->makePrinting($identity, 3);

        $cards = [
            $this->makeBannedCard('Forest of Giant Plants', 'AOR', '74', $printing),
            $this->makeBannedCard('Forest of Giant Plants', 'AOR', '75a', $printing),
        ];

        $grouper = new BannedCardGrouper($this->buildLinkerStub());
        $groups = $grouper->group($cards, 'en');

        self::assertCount(1, $groups);
        self::assertCount(2, $groups[0]->printings);
    }

    public function testEffectiveDateIsEarliestInGroup(): void
    {
        $identity = new CardIdentity();
        $printing = $this->makePrinting($identity, 3);

        $oldBan = $this->makeBannedCard(
            'Archeops',
            'NVI',
            '67',
            $printing,
            new \DateTimeImmutable('2014-08-20'),
        );
        $newBan = $this->makeBannedCard(
            'Archeops',
            'DEX',
            '110',
            $printing,
            new \DateTimeImmutable('2018-09-01'),
        );

        $grouper = new BannedCardGrouper($this->buildLinkerStub());
        $groups = $grouper->group([$newBan, $oldBan], 'en');

        self::assertCount(1, $groups);
        self::assertSame('2014-08-20', $groups[0]->effectiveDate?->format('Y-m-d'));
    }

    public function testFirstNonEmptyExplanationWins(): void
    {
        $identity = new CardIdentity();
        $printing = $this->makePrinting($identity, 3);

        $a = $this->makeBannedCard('Archeops', 'NVI', '67', $printing);
        $b = $this->makeBannedCard(
            'Archeops',
            'DEX',
            '110',
            $printing,
            explanation: 'Locks evolution Pokemon.',
        );

        $grouper = new BannedCardGrouper($this->buildLinkerStub());
        $groups = $grouper->group([$a, $b], 'en');

        self::assertCount(1, $groups);
        self::assertSame('Locks evolution Pokemon.', $groups[0]->explanation);
    }

    public function testGroupsAreSortedByEffectiveDateDescThenName(): void
    {
        $identity = new CardIdentity();
        $printing = $this->makePrinting($identity, 3);

        $oldCard = $this->makeBannedCard('A Card', 'AAA', '1', $printing, new \DateTimeImmutable('2020-01-01'));
        $recentBCard = $this->makeBannedCard('B Card', 'BBB', '1', null, new \DateTimeImmutable('2024-01-01'));
        $recentACard = $this->makeBannedCard('A Different Card', 'CCC', '1', null, new \DateTimeImmutable('2024-01-01'));

        $grouper = new BannedCardGrouper($this->buildLinkerStub());
        $groups = $grouper->group([$oldCard, $recentBCard, $recentACard], 'en');

        self::assertCount(3, $groups);
        self::assertSame('A Different Card', $groups[0]->cardName);
        self::assertSame('B Card', $groups[1]->cardName);
        self::assertSame('A Card', $groups[2]->cardName);
    }

    public function testPrintingsAreSortedBySetCodeAndNumber(): void
    {
        $identity = new CardIdentity();
        $printing = $this->makePrinting($identity, 3);

        $cards = [
            $this->makeBannedCard('Archeops', 'NVI', '67', $printing),
            $this->makeBannedCard('Archeops', 'DEX', '110', $printing),
            $this->makeBannedCard('Archeops', 'DEX', '109', $printing),
        ];

        $grouper = new BannedCardGrouper($this->buildLinkerStub());
        $groups = $grouper->group($cards, 'en');

        self::assertCount(1, $groups);
        $printings = $groups[0]->printings;
        self::assertSame('DEX', $printings[0]->getSetCode());
        self::assertSame('109', $printings[0]->getCardNumber());
        self::assertSame('DEX', $printings[1]->getSetCode());
        self::assertSame('110', $printings[1]->getCardNumber());
        self::assertSame('NVI', $printings[2]->getSetCode());
    }
}

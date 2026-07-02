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

use App\Entity\CardPrinting;
use App\Entity\StapleCard;
use App\Entity\StapleCardPrinting;
use App\Entity\TcgdexCard;
use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use App\Repository\TcgdexSetRepository;
use App\Service\StapleCardImageResolver;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.15 — Staple cards
 */
class StapleCardImageResolverTest extends TestCase
{
    public function testReturnsNullWhenStapleHasNoRepresentativeAndNoPrintings(): void
    {
        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        self::assertNull($resolver->resolveForStaple(new StapleCard()));
    }

    public function testRepresentativePrintingDirectImageUrlWins(): void
    {
        $printing = $this->buildCardPrinting(imageUrl: 'https://images.example.com/foo.png');

        $staple = new StapleCard();
        $staple->setRepresentativePrinting($printing);

        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://images.example.com/foo.png',
            $resolver->resolveForStaple($staple),
        );
    }

    public function testRepresentativePrintingDirectImageUrlIsNormalizedForTcgdexCdn(): void
    {
        $printing = $this->buildCardPrinting(
            imageUrl: 'https://assets.tcgdex.net/en/sm/sm3.5/7/high.webp',
        );

        $staple = new StapleCard();
        $staple->setRepresentativePrinting($printing);

        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        // Dotted set ID gets stripped to match TCGdex CDN paths.
        self::assertSame(
            'https://assets.tcgdex.net/en/sm/sm35/7/high.webp',
            $resolver->resolveForStaple($staple),
        );
    }

    public function testRepresentativePrintingFallsThroughToTcgdexCdnFromTcgdexCard(): void
    {
        $printing = $this->buildCardPrinting(
            imageUrl: null,
            tcgdexCard: $this->buildTcgdexCard('sm', 'sm11.5', '42'),
        );

        $staple = new StapleCard();
        $staple->setRepresentativePrinting($printing);

        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://assets.tcgdex.net/fr/sm/sm115/42/high.webp',
            $resolver->resolveForStaple($staple, 'fr'),
        );
    }

    public function testRepresentativePrintingFallsThroughToTcgdexCdnFromParsedTcgdexId(): void
    {
        $printing = $this->buildCardPrinting(
            imageUrl: null,
            tcgdexCard: null,
            tcgdexId: 'swsh4-100',
        );

        $staple = new StapleCard();
        $staple->setRepresentativePrinting($printing);

        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://assets.tcgdex.net/en/swsh/swsh4/100/high.webp',
            $resolver->resolveForStaple($staple),
        );
    }

    public function testRepresentativePrintingFallsThroughToPokemontcgioWhenSerieGuessFails(): void
    {
        // Set ID prefix doesn't match any guessable serie -> CDN is null,
        // PokemonTCG.io takes over.
        $printing = $this->buildCardPrinting(
            imageUrl: null,
            tcgdexCard: null,
            tcgdexId: 'mc1-3',
        );

        $staple = new StapleCard();
        $staple->setRepresentativePrinting($printing);

        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://images.pokemontcg.io/mc1/3_hires.png',
            $resolver->resolveForStaple($staple),
        );
    }

    public function testRepresentativePrintingFinalFallbackUsesUpstreamSetCodeViaTcgdexSet(): void
    {
        // No dash -> parseTcgdexId returns null -> CDN + pokemontcg.io fail,
        // so the resolver falls back to the upstream set-code lookup.
        $printingNoTcgdex = $this->buildCardPrinting(
            imageUrl: null,
            tcgdexCard: null,
            tcgdexId: 'nodash',
        );

        $staple = new StapleCard();
        $staple->setRepresentativePrinting($printingNoTcgdex);
        $staple->addPrinting($this->buildStaplePrinting('LOT', '90', $printingNoTcgdex));

        $set = $this->buildTcgdexSet('sm', 'sm11.5');
        $resolver = new StapleCardImageResolver($this->buildSetRepository($set));

        self::assertSame(
            'https://assets.tcgdex.net/en/sm/sm115/90/high.webp',
            $resolver->resolveForStaple($staple),
        );
    }

    public function testRepresentativePrintingFailsThenWalksChildPrintingsByRarity(): void
    {
        // Representative resolves to nothing.
        $representative = $this->buildCardPrinting(
            imageUrl: null,
            tcgdexCard: null,
            tcgdexId: 'nodash',
        );

        // Two child printings: high-rarity tier first in collection, but the
        // resolver should pick the lowest rarity tier.
        $rare = $this->buildCardPrinting(
            imageUrl: 'https://images.example.com/rare.png',
            rarityTier: 5,
        );
        $common = $this->buildCardPrinting(
            imageUrl: 'https://images.example.com/common.png',
            rarityTier: 1,
        );

        $staple = new StapleCard();
        $staple->setRepresentativePrinting($representative);
        $staple->addPrinting($this->buildStaplePrinting('LOT', '90', $rare));
        $staple->addPrinting($this->buildStaplePrinting('LOT', '91', $common));

        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://images.example.com/common.png',
            $resolver->resolveForStaple($staple),
        );
    }

    public function testChildWithoutCardPrintingFallsBackToUpstreamSetCode(): void
    {
        $set = $this->buildTcgdexSet('xy', 'xy7');

        $staple = new StapleCard();
        // StapleCardPrinting with null cardPrinting -> directly uses setCode lookup.
        $staple->addPrinting($this->buildStaplePrinting('PHF', '99', null));

        $resolver = new StapleCardImageResolver($this->buildSetRepository($set));

        self::assertSame(
            'https://assets.tcgdex.net/en/xy/xy7/99/high.webp',
            $resolver->resolveForStaple($staple),
        );
    }

    public function testChildWithoutCardPrintingReturnsNullWhenSetCodeUnknown(): void
    {
        $staple = new StapleCard();
        $staple->addPrinting($this->buildStaplePrinting('UNKNOWN', '1', null));

        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        self::assertNull($resolver->resolveForStaple($staple));
    }

    public function testNullPrintingsSortedAfterPrintingsWithCardPrintingByMaxIntTier(): void
    {
        // A child without CardPrinting has rarity tier PHP_INT_MAX in the sort,
        // so a child with a CardPrinting wins even when its tier is the default 6.
        $rare = $this->buildCardPrinting(
            imageUrl: 'https://images.example.com/rare.png',
            rarityTier: 6,
        );

        $staple = new StapleCard();
        $staple->addPrinting($this->buildStaplePrinting('LOT', '90', null));
        $staple->addPrinting($this->buildStaplePrinting('LOT', '91', $rare));

        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://images.example.com/rare.png',
            $resolver->resolveForStaple($staple),
        );
    }

    public function testNonTcgdexCdnImageUrlIsReturnedUnchanged(): void
    {
        // normalizeTcgdexCdnUrl only mutates URLs starting with the TCGdex CDN base.
        $printing = $this->buildCardPrinting(
            imageUrl: 'https://images.pokemontcg.io/sm115/42_hires.png',
        );

        $staple = new StapleCard();
        $staple->setRepresentativePrinting($printing);

        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://images.pokemontcg.io/sm115/42_hires.png',
            $resolver->resolveForStaple($staple),
        );
    }

    public function testEmptyImageUrlSkipsDirectAndFallsThrough(): void
    {
        $printing = $this->buildCardPrinting(
            imageUrl: '',
            tcgdexCard: $this->buildTcgdexCard('bw', 'bw1', '10'),
        );

        $staple = new StapleCard();
        $staple->setRepresentativePrinting($printing);

        $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://assets.tcgdex.net/en/bw/bw1/10/high.webp',
            $resolver->resolveForStaple($staple),
        );
    }

    public function testGuessSerieIdSupportsAllKnownPrefixes(): void
    {
        $cases = [
            ['sv8-1', 'sv', 'sv8'],
            ['swsh10-2', 'swsh', 'swsh10'],
            ['sm9-3', 'sm', 'sm9'],
            ['xy3-4', 'xy', 'xy3'],
            ['bw5-6', 'bw', 'bw5'],
        ];

        foreach ($cases as [$tcgdexId, $expectedSerie, $expectedSet]) {
            $printing = $this->buildCardPrinting(
                imageUrl: null,
                tcgdexCard: null,
                tcgdexId: $tcgdexId,
            );

            $staple = new StapleCard();
            $staple->setRepresentativePrinting($printing);

            $resolver = new StapleCardImageResolver($this->buildSetRepository(null));

            $expected = \sprintf(
                'https://assets.tcgdex.net/en/%s/%s/%s/high.webp',
                $expectedSerie,
                $expectedSet,
                explode('-', $tcgdexId)[1],
            );

            self::assertSame($expected, $resolver->resolveForStaple($staple), "tcgdexId $tcgdexId");
        }
    }

    private function buildSetRepository(?TcgdexSet $set): TcgdexSetRepository
    {
        $repository = $this->createStub(TcgdexSetRepository::class);
        $repository->method('findByPtcgCode')->willReturn($set);

        return $repository;
    }

    private function buildCardPrinting(
        ?string $imageUrl = null,
        ?TcgdexCard $tcgdexCard = null,
        string $tcgdexId = 'sm1-1',
        int $rarityTier = 6,
    ): CardPrinting {
        $printing = $this->createStub(CardPrinting::class);
        $printing->method('getImageUrl')->willReturn($imageUrl);
        $printing->method('getTcgdexCard')->willReturn($tcgdexCard);
        $printing->method('getTcgdexId')->willReturn($tcgdexId);
        $printing->method('getRarityTier')->willReturn($rarityTier);

        return $printing;
    }

    private function buildTcgdexCard(string $serieId, string $setId, string $localId): TcgdexCard
    {
        $set = $this->buildTcgdexSet($serieId, $setId);

        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getSet')->willReturn($set);
        $tcgdexCard->method('getLocalId')->willReturn($localId);

        return $tcgdexCard;
    }

    private function buildTcgdexSet(string $serieId, string $setId): TcgdexSet
    {
        $serie = $this->createStub(TcgdexSerie::class);
        $serie->method('getId')->willReturn($serieId);

        $set = $this->createStub(TcgdexSet::class);
        $set->method('getId')->willReturn($setId);
        $set->method('getSerie')->willReturn($serie);

        return $set;
    }

    private function buildStaplePrinting(string $setCode, string $cardNumber, ?CardPrinting $cardPrinting): StapleCardPrinting
    {
        $printing = new StapleCardPrinting();
        $printing->setSetCode($setCode);
        $printing->setCardNumber($cardNumber);
        $printing->setCardPrinting($cardPrinting);

        return $printing;
    }
}

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
use App\Entity\CardPrinting;
use App\Entity\TcgdexCard;
use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use App\Repository\TcgdexSetRepository;
use App\Service\BannedCardImageResolver;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardImageResolverTest extends TestCase
{
    public function testReturnsNullWhenBanHasNoRepresentativeAndNoPrintings(): void
    {
        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        self::assertNull($resolver->resolveForBan(new BannedCard()));
    }

    public function testRepresentativePrintingDirectImageUrlWins(): void
    {
        $printing = $this->buildCardPrinting(imageUrl: 'https://images.example.com/foo.png');

        $ban = new BannedCard();
        $ban->setRepresentativePrinting($printing);

        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://images.example.com/foo.png',
            $resolver->resolveForBan($ban),
        );
    }

    public function testRepresentativePrintingDirectImageUrlIsNormalizedForTcgdexCdn(): void
    {
        $printing = $this->buildCardPrinting(
            imageUrl: 'https://assets.tcgdex.net/en/sm/sm3.5/7/high.webp',
        );

        $ban = new BannedCard();
        $ban->setRepresentativePrinting($printing);

        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        // Dotted set ID gets stripped to match TCGdex CDN paths.
        self::assertSame(
            'https://assets.tcgdex.net/en/sm/sm35/7/high.webp',
            $resolver->resolveForBan($ban),
        );
    }

    public function testRepresentativePrintingFallsThroughToTcgdexCdnFromTcgdexCard(): void
    {
        $printing = $this->buildCardPrinting(
            imageUrl: null,
            tcgdexCard: $this->buildTcgdexCard('sm', 'sm11.5', '42'),
        );

        $ban = new BannedCard();
        $ban->setRepresentativePrinting($printing);

        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://assets.tcgdex.net/fr/sm/sm115/42/high.webp',
            $resolver->resolveForBan($ban, 'fr'),
        );
    }

    public function testRepresentativePrintingFallsThroughToTcgdexCdnFromParsedTcgdexId(): void
    {
        $printing = $this->buildCardPrinting(
            imageUrl: null,
            tcgdexCard: null,
            tcgdexId: 'swsh4-100',
        );

        $ban = new BannedCard();
        $ban->setRepresentativePrinting($printing);

        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://assets.tcgdex.net/en/swsh/swsh4/100/high.webp',
            $resolver->resolveForBan($ban),
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

        $ban = new BannedCard();
        $ban->setRepresentativePrinting($printing);

        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://images.pokemontcg.io/mc1/3_hires.png',
            $resolver->resolveForBan($ban),
        );
    }

    public function testRepresentativePrintingFinalFallbackUsesUpstreamSetCodeViaTcgdexSet(): void
    {
        $printing = $this->buildCardPrinting(
            imageUrl: null,
            tcgdexCard: null,
            tcgdexId: 'no-dash-here', // dash makes parse succeed; value irrelevant since serie guess fails
        );
        // Force every direct lookup to fail so we hit the upstream-set-code branch.
        $printingNoTcgdex = $this->buildCardPrinting(
            imageUrl: null,
            tcgdexCard: null,
            tcgdexId: 'nodash', // no dash -> parseTcgdexId returns null -> CDN + pokemontcg.io fail
        );

        $ban = new BannedCard();
        $ban->setRepresentativePrinting($printingNoTcgdex);
        $ban->addPrinting($this->buildBannedPrinting('LOT', '90', $printingNoTcgdex));

        $set = $this->buildTcgdexSet('sm', 'sm11.5');
        $resolver = new BannedCardImageResolver($this->buildSetRepository($set));

        self::assertSame(
            'https://assets.tcgdex.net/en/sm/sm115/90/high.webp',
            $resolver->resolveForBan($ban),
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

        $ban = new BannedCard();
        $ban->setRepresentativePrinting($representative);
        $ban->addPrinting($this->buildBannedPrinting('LOT', '90', $rare));
        $ban->addPrinting($this->buildBannedPrinting('LOT', '91', $common));

        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://images.example.com/common.png',
            $resolver->resolveForBan($ban),
        );
    }

    public function testChildWithoutCardPrintingFallsBackToUpstreamSetCode(): void
    {
        $set = $this->buildTcgdexSet('xy', 'xy7');

        $ban = new BannedCard();
        // BannedCardPrinting with null cardPrinting -> directly uses setCode lookup.
        $ban->addPrinting($this->buildBannedPrinting('PHF', '99', null));

        $resolver = new BannedCardImageResolver($this->buildSetRepository($set));

        self::assertSame(
            'https://assets.tcgdex.net/en/xy/xy7/99/high.webp',
            $resolver->resolveForBan($ban),
        );
    }

    public function testChildWithoutCardPrintingReturnsNullWhenSetCodeUnknown(): void
    {
        $ban = new BannedCard();
        $ban->addPrinting($this->buildBannedPrinting('UNKNOWN', '1', null));

        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        self::assertNull($resolver->resolveForBan($ban));
    }

    public function testNullPrintingsSortedAfterPrintingsWithCardPrintingByMaxIntTier(): void
    {
        // A child without CardPrinting has rarity tier PHP_INT_MAX in the sort,
        // so a child with a CardPrinting wins even when its tier is the default 6.
        $rare = $this->buildCardPrinting(
            imageUrl: 'https://images.example.com/rare.png',
            rarityTier: 6,
        );

        $ban = new BannedCard();
        $ban->addPrinting($this->buildBannedPrinting('LOT', '90', null));
        $ban->addPrinting($this->buildBannedPrinting('LOT', '91', $rare));

        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://images.example.com/rare.png',
            $resolver->resolveForBan($ban),
        );
    }

    public function testNonTcgdexCdnImageUrlIsReturnedUnchanged(): void
    {
        // normalizeTcgdexCdnUrl only mutates URLs starting with the TCGdex CDN base.
        $printing = $this->buildCardPrinting(
            imageUrl: 'https://images.pokemontcg.io/sm115/42_hires.png',
        );

        $ban = new BannedCard();
        $ban->setRepresentativePrinting($printing);

        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://images.pokemontcg.io/sm115/42_hires.png',
            $resolver->resolveForBan($ban),
        );
    }

    public function testEmptyImageUrlSkipsDirectAndFallsThrough(): void
    {
        $printing = $this->buildCardPrinting(
            imageUrl: '',
            tcgdexCard: $this->buildTcgdexCard('bw', 'bw1', '10'),
        );

        $ban = new BannedCard();
        $ban->setRepresentativePrinting($printing);

        $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

        self::assertSame(
            'https://assets.tcgdex.net/en/bw/bw1/10/high.webp',
            $resolver->resolveForBan($ban),
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

            $ban = new BannedCard();
            $ban->setRepresentativePrinting($printing);

            $resolver = new BannedCardImageResolver($this->buildSetRepository(null));

            $expected = \sprintf(
                'https://assets.tcgdex.net/en/%s/%s/%s/high.webp',
                $expectedSerie,
                $expectedSet,
                explode('-', $tcgdexId)[1],
            );

            self::assertSame($expected, $resolver->resolveForBan($ban), "tcgdexId $tcgdexId");
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

    private function buildBannedPrinting(string $setCode, string $cardNumber, ?CardPrinting $cardPrinting): BannedCardPrinting
    {
        $printing = new BannedCardPrinting();
        $printing->setSetCode($setCode);
        $printing->setCardNumber($cardNumber);
        $printing->setCardPrinting($cardPrinting);

        return $printing;
    }
}

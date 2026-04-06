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
use App\Entity\TcgdexCard;
use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use App\Service\CardImageResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class CardImageResolverTest extends TestCase
{
    private CardImageResolver $resolver;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->resolver = new CardImageResolver($this->entityManager, $this->logger);
    }

    public function testReturnsFalseWhenImageUrlIsNull(): void
    {
        $printing = $this->createStub(CardPrinting::class);
        $printing->method('getImageUrl')->willReturn(null);

        self::assertFalse($this->resolver->downloadImage($printing));
    }

    public function testReturnsFalseWhenImageUrlIsEmpty(): void
    {
        $printing = $this->createStub(CardPrinting::class);
        $printing->method('getImageUrl')->willReturn('');

        self::assertFalse($this->resolver->downloadImage($printing));
    }

    public function testBuildFallbackUrlsGeneratesDotRemovedTcgdexUrlForDottedSetId(): void
    {
        $serie = $this->createStub(TcgdexSerie::class);
        $serie->method('getId')->willReturn('sm');

        $set = $this->createStub(TcgdexSet::class);
        $set->method('getId')->willReturn('sm11.5');
        $set->method('getSerie')->willReturn($serie);

        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getSet')->willReturn($set);
        $tcgdexCard->method('getLocalId')->willReturn('42');

        $printing = $this->createStub(CardPrinting::class);
        $printing->method('getTcgdexCard')->willReturn($tcgdexCard);

        $urls = $this->invokeBuildFallbackUrls($printing);

        self::assertCount(2, $urls);
        self::assertSame('https://assets.tcgdex.net/en/sm/sm115/42/high.webp', $urls[0]);
        self::assertSame('https://images.pokemontcg.io/sm115/42_hires.png', $urls[1]);
    }

    public function testBuildFallbackUrlsGeneratesPokemontcgIoUrlFromTcgdexCard(): void
    {
        $serie = $this->createStub(TcgdexSerie::class);
        $serie->method('getId')->willReturn('bw');

        $set = $this->createStub(TcgdexSet::class);
        $set->method('getId')->willReturn('bw1');
        $set->method('getSerie')->willReturn($serie);

        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getSet')->willReturn($set);
        $tcgdexCard->method('getLocalId')->willReturn('10');

        $printing = $this->createStub(CardPrinting::class);
        $printing->method('getTcgdexCard')->willReturn($tcgdexCard);

        $urls = $this->invokeBuildFallbackUrls($printing);

        // Non-dotted set: only pokemontcg.io fallback
        self::assertCount(1, $urls);
        self::assertSame('https://images.pokemontcg.io/bw1/10_hires.png', $urls[0]);
    }

    public function testBuildFallbackUrlsGeneratesPokemontcgIoUrlFromTcgdexIdWithoutTcgdexCard(): void
    {
        $printing = $this->createStub(CardPrinting::class);
        $printing->method('getTcgdexCard')->willReturn(null);
        $printing->method('getTcgdexId')->willReturn('xy2-11');

        $urls = $this->invokeBuildFallbackUrls($printing);

        self::assertCount(1, $urls);
        self::assertSame('https://images.pokemontcg.io/xy2/11_hires.png', $urls[0]);
    }

    public function testBuildFallbackUrlsReturnsEmptyWhenNoTcgdexCardAndNoDashInTcgdexId(): void
    {
        $printing = $this->createStub(CardPrinting::class);
        $printing->method('getTcgdexCard')->willReturn(null);
        $printing->method('getTcgdexId')->willReturn('nodash');

        $urls = $this->invokeBuildFallbackUrls($printing);

        self::assertSame([], $urls);
    }

    public function testBuildFallbackUrlsRemovesDotsFromSetIdForPokemontcgIoUrl(): void
    {
        $serie = $this->createStub(TcgdexSerie::class);
        $serie->method('getId')->willReturn('sm');

        $set = $this->createStub(TcgdexSet::class);
        $set->method('getId')->willReturn('sm3.5');
        $set->method('getSerie')->willReturn($serie);

        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getSet')->willReturn($set);
        $tcgdexCard->method('getLocalId')->willReturn('7');

        $printing = $this->createStub(CardPrinting::class);
        $printing->method('getTcgdexCard')->willReturn($tcgdexCard);

        $urls = $this->invokeBuildFallbackUrls($printing);

        // Both fallback URLs should have dots removed from set ID
        self::assertSame('https://assets.tcgdex.net/en/sm/sm35/7/high.webp', $urls[0]);
        self::assertSame('https://images.pokemontcg.io/sm35/7_hires.png', $urls[1]);
    }

    /**
     * Invoke the private buildFallbackUrls method via reflection.
     *
     * @return list<string>
     */
    private function invokeBuildFallbackUrls(CardPrinting $printing): array
    {
        $reflection = new \ReflectionMethod(CardImageResolver::class, 'buildFallbackUrls');

        /* @var list<string> */
        return $reflection->invoke($this->resolver, $printing);
    }
}

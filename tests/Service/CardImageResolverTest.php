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

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Entity\TcgdexCard;
use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use App\Service\CardImageResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class CardImageResolverTest extends TestCase
{
    private CardImageResolver $resolver;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->resolver = new CardImageResolver($this->entityManager, $this->logger);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
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

    public function testDownloadImageReturnsPrimaryUrlContentsOnSuccess(): void
    {
        $path = $this->writeTempFile('primary-bytes');

        // No tcgdex card + no dash in tcgdexId → no HTTP fallbacks would be tried even
        // if the primary failed, isolating this test from the network.
        $printing = $this->buildPrinting($path, 'Pikachu');

        self::assertSame('primary-bytes', $this->resolver->downloadImage($printing));
    }

    public function testDownloadImageUsesLowResolutionUrlWhenRequested(): void
    {
        $directory = sys_get_temp_dir().'/'.uniqid('cir-low-', true);
        mkdir($directory);
        file_put_contents($directory.'/low.webp', 'low-res-bytes');
        $this->tempFiles[] = $directory.'/low.webp';

        // The resolver swaps `/high.webp` → `/low.webp` in the primary URL when
        // resolution=low. Setting a primary URL that only resolves under low.webp
        // proves the swap happened.
        $printing = $this->buildPrinting($directory.'/high.webp', 'Pikachu');

        $result = $this->resolver->downloadImage($printing, 'low');

        // Clean up the directory after the assertion runs but before the test exits.
        rmdir($directory);

        self::assertSame('low-res-bytes', $result);
    }

    public function testDownloadImageFallsBackToSiblingPrintingWhenAllFallbacksFail(): void
    {
        $siblingPath = $this->writeTempFile('sibling-bytes');

        $identity = new CardIdentity();
        $identity->setName('Pikachu');
        $identity->setCategory('pokemon');

        $primary = new CardPrinting();
        $primary->setTcgdexId('nodash'); // no dash → buildFallbackUrls returns []
        $primary->setImageUrl('/nonexistent/primary.webp');
        $primary->setCardIdentity($identity);
        $identity->addPrinting($primary);

        $sibling = new CardPrinting();
        $sibling->setTcgdexId('also-nodash');
        $sibling->setImageUrl($siblingPath);
        $sibling->setCardIdentity($identity);
        $identity->addPrinting($sibling);

        // The persistFallbackUrl branch should call flush() once to save the rewritten primary URL.
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $resolver = new CardImageResolver($entityManager, new NullLogger());

        self::assertSame('sibling-bytes', $resolver->downloadImage($primary));
        // The primary's URL is overwritten to point at the sibling that actually worked.
        self::assertSame($siblingPath, $primary->getImageUrl());
    }

    public function testDownloadImageSkipsSiblingWithMissingUrlAndReturnsFalseWhenNothingResolves(): void
    {
        $identity = new CardIdentity();
        $identity->setName('Phantom');
        $identity->setCategory('pokemon');

        $primary = new CardPrinting();
        $primary->setTcgdexId('nodash'); // no dash → no HTTP fallbacks
        $primary->setImageUrl('/nonexistent/primary.webp');
        $primary->setCardIdentity($identity);
        $identity->addPrinting($primary);

        // Sibling with no image URL — must be skipped (the `continue` branch in tryFromSiblingPrinting).
        $emptySibling = new CardPrinting();
        $emptySibling->setTcgdexId('nodash');
        $emptySibling->setImageUrl(null);
        $emptySibling->setCardIdentity($identity);
        $identity->addPrinting($emptySibling);

        // No flush expected — nothing resolved.
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $resolver = new CardImageResolver($entityManager, new NullLogger());

        self::assertFalse($resolver->downloadImage($primary));
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

    private function buildPrinting(string $imageUrl, string $cardName): CardPrinting
    {
        $identity = new CardIdentity();
        $identity->setName($cardName);
        $identity->setCategory('pokemon');

        $printing = new CardPrinting();
        $printing->setTcgdexId('nodash');
        $printing->setImageUrl($imageUrl);
        $printing->setCardIdentity($identity);
        $identity->addPrinting($printing);

        return $printing;
    }

    private function writeTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cir-');
        \assert(false !== $path);
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }
}

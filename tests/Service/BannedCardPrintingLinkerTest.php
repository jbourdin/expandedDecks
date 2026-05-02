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
use App\Repository\CardPrintingRepository;
use App\Repository\TcgdexSetRepository;
use App\Service\BannedCardPrintingLinker;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardPrintingLinkerTest extends TestCase
{
    private function makeLinker(): BannedCardPrintingLinker
    {
        $cardPrintingRepo = $this->createStub(CardPrintingRepository::class);
        $cardPrintingRepo->method('findFirstBySetCodeAndCardNumber')->willReturn(null);

        $tcgdexSetRepo = $this->createStub(TcgdexSetRepository::class);
        $tcgdexSetRepo->method('findByPtcgCode')->willReturn(null);

        return new BannedCardPrintingLinker($cardPrintingRepo, $tcgdexSetRepo);
    }

    private function makeBannedCard(string $setCode, string $cardNumber, ?CardPrinting $printing = null): BannedCard
    {
        $card = new BannedCard();
        $card->setCardName('Test');
        $card->setSetCode($setCode);
        $card->setCardNumber($cardNumber);
        if (null !== $printing) {
            $card->setCardPrinting($printing);
        }

        return $card;
    }

    private function makePrinting(string $tcgdexId, ?string $imageUrl = null): CardPrinting
    {
        $printing = new CardPrinting();
        $printing->setCardIdentity(new CardIdentity());
        $printing->setTcgdexId($tcgdexId);
        if (null !== $imageUrl) {
            $printing->setImageUrl($imageUrl);
        }

        return $printing;
    }

    public function testStoredImageUrlIsReturnedWhenPresent(): void
    {
        $printing = $this->makePrinting('xy7-74', 'https://assets.tcgdex.net/en/xy/xy7/74/high.webp');
        $card = $this->makeBannedCard('AOR', '74', $printing);

        $url = $this->makeLinker()->resolveImageUrl($card);

        self::assertSame('https://assets.tcgdex.net/en/xy/xy7/74/high.webp', $url);
    }

    public function testStoredImageUrlHasDotsStrippedFromSetIdSegment(): void
    {
        // TCGdex CDN serves "/sm/sm35/45/" even though the set id is "sm3.5".
        $printing = $this->makePrinting('sm3.5-45', 'https://assets.tcgdex.net/en/sm/sm3.5/45/high.webp');
        $card = $this->makeBannedCard('SLG', '45', $printing);

        $url = $this->makeLinker()->resolveImageUrl($card);

        self::assertSame('https://assets.tcgdex.net/en/sm/sm35/45/high.webp', $url);
    }

    public function testNonTcgdexUrlsAreNotRewritten(): void
    {
        $printing = $this->makePrinting('sm3.5-45', 'https://images.pokemontcg.io/sm35/45_hires.png');
        $card = $this->makeBannedCard('SLG', '45', $printing);

        $url = $this->makeLinker()->resolveImageUrl($card);

        self::assertSame('https://images.pokemontcg.io/sm35/45_hires.png', $url);
    }

    public function testFilenameDotIsPreserved(): void
    {
        // The trailing "high.webp" must stay untouched even though it contains a dot.
        $printing = $this->makePrinting('sm3.5-45', 'https://assets.tcgdex.net/en/sm/sm3.5/45/high.webp');
        $card = $this->makeBannedCard('SLG', '45', $printing);

        $url = $this->makeLinker()->resolveImageUrl($card);

        self::assertNotNull($url);
        self::assertStringEndsWith('/high.webp', $url);
    }

    public function testTcgdexCdnFallbackBuiltFromPrintingStripsDots(): void
    {
        // No imageUrl on the printing → linker uses tcgdex_id-derived fallback.
        $printing = $this->makePrinting('sm3.5-45');
        $card = $this->makeBannedCard('SLG', '45', $printing);

        $url = $this->makeLinker()->resolveImageUrl($card);

        self::assertNotNull($url);
        self::assertStringContainsString('/sm35/', $url);
        self::assertStringNotContainsString('/sm3.5/', $url);
    }
}

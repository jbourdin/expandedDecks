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

namespace App\Tests\Service\CardIdentity;

use App\Service\CardIdentity\RarityTierMapper;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class RarityTierMapperTest extends TestCase
{
    private RarityTierMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new RarityTierMapper();
    }

    public function testCommonReturnsTier1(): void
    {
        self::assertSame(1, $this->mapper->map('Common'));
    }

    public function testNoneReturnsTier1(): void
    {
        self::assertSame(1, $this->mapper->map('None'));
    }

    public function testUncommonReturnsTier2(): void
    {
        self::assertSame(2, $this->mapper->map('Uncommon'));
    }

    public function testOneDiamondReturnsTier2(): void
    {
        self::assertSame(2, $this->mapper->map('One Diamond'));
    }

    public function testRareReturnsTier3(): void
    {
        self::assertSame(3, $this->mapper->map('Rare'));
    }

    public function testRareHoloReturnsTier3(): void
    {
        self::assertSame(3, $this->mapper->map('Rare Holo'));
    }

    public function testHoloRareVReturnsTier4(): void
    {
        self::assertSame(4, $this->mapper->map('Holo Rare V'));
    }

    public function testUltraRareReturnsTier5(): void
    {
        self::assertSame(5, $this->mapper->map('Ultra Rare'));
    }

    public function testSecretRareReturnsTier6(): void
    {
        self::assertSame(6, $this->mapper->map('Secret Rare'));
    }

    public function testNullRarityReturnsUnknownTier(): void
    {
        self::assertSame(RarityTierMapper::UNKNOWN_TIER, $this->mapper->map(null));
    }

    public function testEmptyRarityReturnsUnknownTier(): void
    {
        self::assertSame(RarityTierMapper::UNKNOWN_TIER, $this->mapper->map(''));
    }

    public function testUnmappedRarityReturnsUnknownTier(): void
    {
        self::assertSame(RarityTierMapper::UNKNOWN_TIER, $this->mapper->map('Super Duper Rare'));
    }

    public function testUnreliableRaritySetReturnsUnknownTier(): void
    {
        // sma (Shiny Vault) marks all cards as Common, but should return UNKNOWN
        self::assertSame(RarityTierMapper::UNKNOWN_TIER, $this->mapper->map('Common', 'sma'));
    }

    public function testPromoSetReturnsUnknownTier(): void
    {
        self::assertSame(RarityTierMapper::UNKNOWN_TIER, $this->mapper->map('Rare', 'svp'));
    }

    public function testTrainerKitReturnsUnknownTier(): void
    {
        self::assertSame(RarityTierMapper::UNKNOWN_TIER, $this->mapper->map('Common', 'tk-xy-b'));
    }

    public function testReliableSetUsesActualRarity(): void
    {
        self::assertSame(3, $this->mapper->map('Rare', 'swsh9'));
    }

    public function testNullSetIdUsesActualRarity(): void
    {
        self::assertSame(3, $this->mapper->map('Rare', null));
    }

    public function testTrainerGalleryPrefixBumpsTier(): void
    {
        // TG-prefixed cards are premium — bump from tier 3 (Rare) to tier 5
        self::assertSame(5, $this->mapper->map('Rare', 'swsh9', 'TG01'));
    }

    public function testGalarianGalleryPrefixBumpsTier(): void
    {
        self::assertSame(5, $this->mapper->map('Rare', 'swsh12', 'GG05'));
    }

    public function testPremiumPrefixDoesNotBumpAlreadyHighTier(): void
    {
        // Ultra Rare (tier 5) should NOT be bumped to tier 5 (already at minimum)
        self::assertSame(5, $this->mapper->map('Ultra Rare', 'swsh9', 'TG01'));
    }

    public function testSecretRareCardNumberBumpsTier(): void
    {
        // Card number 200 in a set with 180 official cards → secret rare
        self::assertSame(5, $this->mapper->map('Rare', 'swsh9', '200', 180));
    }

    public function testCardNumberWithinOfficialCountIsNotBumped(): void
    {
        self::assertSame(3, $this->mapper->map('Rare', 'swsh9', '100', 180));
    }

    public function testCardNumberAtExactOfficialCountIsNotBumped(): void
    {
        self::assertSame(3, $this->mapper->map('Rare', 'swsh9', '180', 180));
    }

    public function testNullOfficialCountDoesNotBumpNumericCard(): void
    {
        self::assertSame(3, $this->mapper->map('Rare', 'swsh9', '200', null));
    }

    public function testNonNumericCardNumberIsNotBumpedByOfficialCount(): void
    {
        // Card number "TG01" is caught by prefix, not by numeric comparison
        // But a card like "SV084" with letters is not affected by official count
        self::assertSame(3, $this->mapper->map('Rare', 'swsh9', 'SV084', 180));
    }
}

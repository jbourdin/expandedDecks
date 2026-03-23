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

use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Tcgdex\TcgdexCard;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class CardIdentityResolverTest extends TestCase
{
    public function testComputeAbilitySignatureSortsAlphabetically(): void
    {
        $card = $this->createTcgdexCard(abilities: ['Zeta Potential', 'Alpha Glow']);

        self::assertSame('Alpha Glow,Zeta Potential', CardIdentityResolver::computeAbilitySignature($card));
    }

    public function testComputeAbilitySignatureWithSingleAbility(): void
    {
        $card = $this->createTcgdexCard(abilities: ['Fusion Strike System']);

        self::assertSame('Fusion Strike System', CardIdentityResolver::computeAbilitySignature($card));
    }

    public function testComputeAbilitySignatureReturnsEmptyForNoAbilities(): void
    {
        $card = $this->createTcgdexCard(abilities: []);

        self::assertSame('', CardIdentityResolver::computeAbilitySignature($card));
    }

    public function testComputeAttackSignatureSortsAlphabetically(): void
    {
        $card = $this->createTcgdexCard(attacks: ['Shadow Mist', 'Astral Barrage']);

        self::assertSame('Astral Barrage,Shadow Mist', CardIdentityResolver::computeAttackSignature($card));
    }

    public function testComputeAttackSignatureWithSingleAttack(): void
    {
        $card = $this->createTcgdexCard(attacks: ['Techno Blast']);

        self::assertSame('Techno Blast', CardIdentityResolver::computeAttackSignature($card));
    }

    public function testComputeAttackSignatureReturnsEmptyForNoAttacks(): void
    {
        $card = $this->createTcgdexCard(attacks: []);

        self::assertSame('', CardIdentityResolver::computeAttackSignature($card));
    }

    public function testComputeAttackSignatureWithThreeAttacks(): void
    {
        $card = $this->createTcgdexCard(attacks: ['Cross Fusion Strike', 'Max Miracle', 'Astral Barrage']);

        self::assertSame('Astral Barrage,Cross Fusion Strike,Max Miracle', CardIdentityResolver::computeAttackSignature($card));
    }

    /**
     * @param list<string> $abilities
     * @param list<string> $attacks
     */
    private function createTcgdexCard(array $abilities = [], array $attacks = []): TcgdexCard
    {
        return new TcgdexCard(
            id: 'test-001',
            name: 'Test Card',
            category: 'Pokemon',
            trainerType: null,
            imageUrl: null,
            isExpandedLegal: true,
            hp: 100,
            abilities: $abilities,
            attacks: $attacks,
        );
    }
}

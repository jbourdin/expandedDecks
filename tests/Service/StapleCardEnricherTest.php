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

use App\Constants\RuleboxType;
use App\Constants\StapleCardBucket;
use App\Entity\CardIdentity;
use App\Repository\CardPrintingRepository;
use App\Repository\StapleCardPrintingRepository;
use App\Repository\StapleCardRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\StapleCardEnricher;
use App\Service\Tcgdex\TcgdexApiClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Coverage focused on the bucket-assignment priority rule — the critical
 * staple-specific logic where Ace Spec wins over the type-based buckets.
 *
 * @see docs/features.md F6.15 — Staple cards
 */
class StapleCardEnricherTest extends TestCase
{
    public function testComputeBucketForPokemon(): void
    {
        $identity = $this->makeIdentity(category: 'pokemon');

        self::assertSame(StapleCardBucket::POKEMON, $this->makeEnricher()->computeBucketFor($identity));
    }

    public function testComputeBucketForEnergy(): void
    {
        $identity = $this->makeIdentity(category: 'energy');

        self::assertSame(StapleCardBucket::ENERGY, $this->makeEnricher()->computeBucketFor($identity));
    }

    public function testComputeBucketForSupporter(): void
    {
        $identity = $this->makeIdentity(category: 'trainer', trainerType: 'Supporter');

        self::assertSame(StapleCardBucket::SUPPORTER, $this->makeEnricher()->computeBucketFor($identity));
    }

    public function testComputeBucketForItem(): void
    {
        $identity = $this->makeIdentity(category: 'trainer', trainerType: 'Item');

        self::assertSame(StapleCardBucket::ITEM, $this->makeEnricher()->computeBucketFor($identity));
    }

    public function testComputeBucketForTool(): void
    {
        $identity = $this->makeIdentity(category: 'trainer', trainerType: 'Tool');

        self::assertSame(StapleCardBucket::TOOL, $this->makeEnricher()->computeBucketFor($identity));
    }

    public function testComputeBucketForStadium(): void
    {
        $identity = $this->makeIdentity(category: 'trainer', trainerType: 'Stadium');

        self::assertSame(StapleCardBucket::STADIUM, $this->makeEnricher()->computeBucketFor($identity));
    }

    public function testAceSpecWinsOverTrainerSubtype(): void
    {
        // Ace Specs in modern S&V are typically Items by trainerType, but they should land
        // in the Ace Spec bucket — that's the priority rule.
        $identity = $this->makeIdentity(
            category: 'trainer',
            trainerType: 'Item',
            ruleboxType: RuleboxType::ACE_SPEC,
        );

        self::assertSame(StapleCardBucket::ACE_SPEC, $this->makeEnricher()->computeBucketFor($identity));
    }

    public function testAceSpecWinsOverEnergy(): void
    {
        // Edge case: an Ace Spec special energy. ruleboxType wins over category=energy.
        $identity = $this->makeIdentity(
            category: 'energy',
            trainerType: null,
            ruleboxType: RuleboxType::ACE_SPEC,
        );

        self::assertSame(StapleCardBucket::ACE_SPEC, $this->makeEnricher()->computeBucketFor($identity));
    }

    public function testUnknownTrainerTypeFallsBackToItem(): void
    {
        $identity = $this->makeIdentity(category: 'trainer', trainerType: 'UnknownType');

        // Defensive default — most Trainer cards are Items, and an unrecognized subtype
        // surfacing as Item flags it for editor review without crashing.
        self::assertSame(StapleCardBucket::ITEM, $this->makeEnricher()->computeBucketFor($identity));
    }

    public function testNullIdentityFallsBackToPokemon(): void
    {
        // Defensive default for placeholder rows that haven't been enriched yet — should
        // be transient state; the actual bucket is recomputed once the identity is known.
        self::assertSame(StapleCardBucket::POKEMON, $this->makeEnricher()->computeBucketFor(null));
    }

    private function makeEnricher(): StapleCardEnricher
    {
        return new StapleCardEnricher(
            $this->createStub(TcgdexApiClient::class),
            $this->createStub(CardIdentityResolver::class),
            $this->createStub(CardPrintingRepository::class),
            $this->createStub(StapleCardPrintingRepository::class),
            $this->createStub(StapleCardRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
    }

    private function makeIdentity(string $category = 'pokemon', ?string $trainerType = null, ?string $ruleboxType = null): CardIdentity
    {
        $identity = new CardIdentity();
        $identity->setName('Test Card');
        $identity->setCategory($category);
        $identity->setTrainerType($trainerType);
        $identity->setRuleboxType($ruleboxType);

        return $identity;
    }
}

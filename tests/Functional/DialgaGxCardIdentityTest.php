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

namespace App\Tests\Functional;

use App\Entity\CardIdentity;
use App\Repository\CardIdentityRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Tcgdex\TcgdexCard;

/**
 * Regression guard for the Dialga GX duplicate-identity bug.
 *
 * Dialga GX exists in two mechanically-identical printings — Metal type (Forbidden Light)
 * and Dragon type (Ultra Prism) — with the same name, HP, abilities, and attacks. Before
 * `pokemon_type` joined the identity key, both collapsed into a single CardIdentity row;
 * this functional test asserts they now resolve to two distinct identities and that
 * subsequent printings of the same type still share an identity rather than re-splitting.
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 * @see migrations/Version20260526230542.php — pokemon_type column + identity split
 */
class DialgaGxCardIdentityTest extends AbstractFunctionalTest
{
    private const string CARD_NAME = 'Dialga GX';
    private const int CARD_HP = 180;

    /** Real Dialga GX abilities/attacks per TCGdex; order matches the physical card. */
    private const array ABILITIES = [];
    private const array ATTACKS = ['Shred', 'Overclock', 'Timeless GX'];

    public function testMetalAndDragonResolveToDistinctIdentities(): void
    {
        $resolver = $this->getResolver();
        $repository = $this->getRepository();

        $metalPrinting = $resolver->resolveFromTcgdexCard($this->buildDialgaDto('sm6-125', ['Metal']));
        $dragonPrinting = $resolver->resolveFromTcgdexCard($this->buildDialgaDto('sm5-100', ['Dragon']));

        $metalIdentity = $metalPrinting->getCardIdentity();
        $dragonIdentity = $dragonPrinting->getCardIdentity();

        self::assertNotSame(
            $metalIdentity->getId(),
            $dragonIdentity->getId(),
            'Metal and Dragon Dialga GX must resolve to separate CardIdentity rows.',
        );
        self::assertSame('Metal', $metalIdentity->getPokemonType());
        self::assertSame('Dragon', $dragonIdentity->getPokemonType());

        // Both identities share every other identifying field — only pokemon_type differs.
        self::assertSame(self::CARD_NAME, $metalIdentity->getName());
        self::assertSame(self::CARD_NAME, $dragonIdentity->getName());
        self::assertSame(self::CARD_HP, $metalIdentity->getHp());
        self::assertSame(self::CARD_HP, $dragonIdentity->getHp());
        self::assertSame($metalIdentity->getAttackSignature(), $dragonIdentity->getAttackSignature());

        $foundByMetal = $repository->findBySignature(
            self::CARD_NAME,
            'pokemon',
            self::CARD_HP,
            $metalIdentity->getAbilitySignature(),
            $metalIdentity->getAttackSignature(),
            'Metal',
        );
        self::assertInstanceOf(CardIdentity::class, $foundByMetal);
        self::assertSame($metalIdentity->getId(), $foundByMetal->getId());
    }

    public function testSecondMetalPrintingReusesTheSameIdentity(): void
    {
        $resolver = $this->getResolver();

        $firstMetal = $resolver->resolveFromTcgdexCard($this->buildDialgaDto('sm6-125', ['Metal']));
        $secondMetal = $resolver->resolveFromTcgdexCard($this->buildDialgaDto('sm6-138', ['Metal']));

        self::assertSame(
            $firstMetal->getCardIdentity()->getId(),
            $secondMetal->getCardIdentity()->getId(),
            'Same-type Dialga GX printings must share a single CardIdentity (pokemon_type alone does not over-split).',
        );
        self::assertNotSame(
            $firstMetal->getId(),
            $secondMetal->getId(),
            'But each TCGdex ID still gets its own CardPrinting row.',
        );
    }

    public function testMultiTypePokemonGetsSortedTypeSignature(): void
    {
        // Defensive coverage for hypothetical dual-type printings: the signature is sorted
        // alphabetically (matching computePokemonTypeSignature's contract), so ["Water", "Fire"]
        // and ["Fire", "Water"] collapse to the same identity rather than splitting on JSON order.
        $resolver = $this->getResolver();

        $firstOrder = $resolver->resolveFromTcgdexCard($this->buildDialgaDto('sm-test-1', ['Water', 'Fire']));
        $reversedOrder = $resolver->resolveFromTcgdexCard($this->buildDialgaDto('sm-test-2', ['Fire', 'Water']));

        self::assertSame('Fire,Water', $firstOrder->getCardIdentity()->getPokemonType());
        self::assertSame(
            $firstOrder->getCardIdentity()->getId(),
            $reversedOrder->getCardIdentity()->getId(),
        );
    }

    /**
     * @param list<string> $types
     */
    private function buildDialgaDto(string $tcgdexId, array $types): TcgdexCard
    {
        return new TcgdexCard(
            id: $tcgdexId,
            name: self::CARD_NAME,
            category: 'Pokemon',
            trainerType: null,
            imageUrl: null,
            isExpandedLegal: true,
            hp: self::CARD_HP,
            abilities: self::ABILITIES,
            attacks: self::ATTACKS,
            types: $types,
            rarity: 'Rare Holo GX',
        );
    }

    private function getResolver(): CardIdentityResolver
    {
        /** @var CardIdentityResolver $resolver */
        $resolver = static::getContainer()->get(CardIdentityResolver::class);

        return $resolver;
    }

    private function getRepository(): CardIdentityRepository
    {
        /** @var CardIdentityRepository $repository */
        $repository = static::getContainer()->get(CardIdentityRepository::class);

        return $repository;
    }
}

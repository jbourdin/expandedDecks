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

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Repository\CardIdentityRepository;
use App\Repository\CardPrintingRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\CardIdentity\RarityTierMapper;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Service\Tcgdex\TcgdexCard;
use Doctrine\ORM\EntityManagerInterface;
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
     * Covers resolveFromTcgdexCard when printing already exists (returns early).
     */
    public function testResolveFromTcgdexCardReturnsExistingPrinting(): void
    {
        $existingPrinting = new CardPrinting();

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn($existingPrinting);

        $identityRepository = $this->createStub(CardIdentityRepository::class);
        $apiClient = $this->createStub(TcgdexApiClient::class);
        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $tcgdexCard = $this->createTcgdexCard();

        $result = $resolver->resolveFromTcgdexCard($tcgdexCard);

        self::assertSame($existingPrinting, $result);
    }

    /**
     * Covers resolveFromTcgdexCard when no existing printing — creates new identity and printing.
     */
    public function testResolveFromTcgdexCardCreatesNewPrintingAndIdentity(): void
    {
        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);
        $identityRepository->method('findBySignature')->willReturn(null);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $rarityTierMapper->method('map')->willReturn(3);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeastOnce())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $tcgdexCard = new TcgdexCard(
            id: 'swsh9-123',
            name: 'Arceus VSTAR',
            category: 'Pokemon',
            trainerType: null,
            imageUrl: 'https://example.com/image.webp',
            isExpandedLegal: true,
            hp: 280,
            abilities: ['Star Birth'],
            attacks: ['Trinity Nova'],
            rarity: 'Rare',
            setReleaseDate: '2022-02-25',
            setCode: 'BRS',
            cardNumber: '123',
        );

        $result = $resolver->resolveFromTcgdexCard($tcgdexCard);

        self::assertSame('swsh9-123', $result->getTcgdexId());
        self::assertSame('BRS', $result->getSetCode());
        self::assertSame('123', $result->getCardNumber());
        self::assertSame('https://example.com/image.webp', $result->getImageUrl());
        self::assertTrue($result->isExpandedLegal());
        self::assertSame(3, $result->getRarityTier());
        self::assertSame('Arceus VSTAR', $result->getCardIdentity()->getName());
        self::assertSame('pokemon', $result->getCardIdentity()->getCategory());
        self::assertSame(280, $result->getCardIdentity()->getHp());
    }

    /**
     * Covers resolveFromTcgdexCard reusing an existing identity (found by signature).
     */
    public function testResolveFromTcgdexCardReusesExistingIdentity(): void
    {
        $existingIdentity = new CardIdentity();
        $existingIdentity->setName('Pikachu');
        $existingIdentity->setCategory('pokemon');

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);
        $identityRepository->method('findBySignature')->willReturn($existingIdentity);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $rarityTierMapper->method('map')->willReturn(1);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $tcgdexCard = $this->createTcgdexCard();

        $result = $resolver->resolveFromTcgdexCard($tcgdexCard);

        self::assertSame($existingIdentity, $result->getCardIdentity());
    }

    /**
     * Covers expandPrintings: filters out printings with non-matching names.
     */
    public function testExpandPrintingsFiltersNonMatchingNames(): void
    {
        $identity = new CardIdentity();
        $identity->setName('Pikachu');
        $identity->setCategory('pokemon');
        $identity->setHp(60);
        $identity->setAbilitySignature('');
        $identity->setAttackSignature('Thunder Shock');

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: 'xy1-42',
                name: 'Pikachu',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: 'https://example.com/pikachu.webp',
                isExpandedLegal: true,
                hp: 60,
                attacks: ['Thunder Shock'],
            ),
            new TcgdexCard(
                id: 'xy1-43',
                name: 'Pikachu EX',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: 'https://example.com/pikachu-ex.webp',
                isExpandedLegal: true,
                hp: 130,
                attacks: ['Thunder Shock', 'Mega Bolt'],
            ),
        ]);

        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $rarityTierMapper->method('map')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $resolver->expandPrintings($identity);

        // Only the exact-name matching Pikachu should have been added (1 printing)
        self::assertCount(1, $identity->getPrintings());
    }

    /**
     * Covers expandPrintings: Pokemon HP/signature mismatch filters out non-matching printings.
     */
    public function testExpandPrintingsFiltersPokemonByHpAndSignature(): void
    {
        $identity = new CardIdentity();
        $identity->setName('Pikachu');
        $identity->setCategory('Pokemon');
        $identity->setHp(60);
        $identity->setAbilitySignature('');
        $identity->setAttackSignature('Thunder Shock');

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findAllPrintingsByName')->willReturn([
            // Same name and HP but different attacks — should be filtered out
            new TcgdexCard(
                id: 'bw1-115',
                name: 'Pikachu',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: 'https://example.com/different-attacks.webp',
                isExpandedLegal: true,
                hp: 60,
                attacks: ['Iron Tail'],
            ),
            // Same name but different HP — should be filtered out
            new TcgdexCard(
                id: 'sm1-50',
                name: 'Pikachu',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: 'https://example.com/different-hp.webp',
                isExpandedLegal: true,
                hp: 70,
                attacks: ['Thunder Shock'],
            ),
        ]);

        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $resolver->expandPrintings($identity);

        // No printings should have been added
        self::assertCount(0, $identity->getPrintings());
    }

    /**
     * Covers expandPrintings: UniqueConstraintViolationException is caught and entities are detached.
     */
    public function testExpandPrintingsCatchesUniqueConstraintViolation(): void
    {
        $identity = new CardIdentity();
        $identity->setName("Boss's Orders");
        $identity->setCategory('trainer');
        $identity->setHp(0);
        $identity->setAbilitySignature('');
        $identity->setAttackSignature('');

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: 'swsh9-132',
                name: "Boss's Orders",
                category: 'Trainer',
                trainerType: 'Supporter',
                imageUrl: 'https://example.com/boss.webp',
                isExpandedLegal: true,
            ),
        ]);

        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $rarityTierMapper->method('map')->willReturn(3);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush')
            ->willThrowException(new \Doctrine\DBAL\Exception\UniqueConstraintViolationException(
                new \Doctrine\DBAL\Driver\PDO\Exception('Duplicate entry', '23000'),
                null,
            ));
        $entityManager->expects(self::once())->method('detach');

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        // Should not throw — the exception is caught
        $resolver->expandPrintings($identity);

        // No assertion needed: if no exception is thrown, the test passes
        self::assertTrue(true);
    }

    /**
     * Covers expandPrintings: skips printings with empty ID.
     */
    public function testExpandPrintingsSkipsEmptyId(): void
    {
        $identity = new CardIdentity();
        $identity->setName('Nest Ball');
        $identity->setCategory('trainer');
        $identity->setHp(0);
        $identity->setAbilitySignature('');
        $identity->setAttackSignature('');

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: '',
                name: 'Nest Ball',
                category: 'Trainer',
                trainerType: 'Item',
                imageUrl: 'https://example.com/nest.webp',
                isExpandedLegal: true,
            ),
        ]);

        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $resolver->expandPrintings($identity);

        self::assertCount(0, $identity->getPrintings());
    }

    /**
     * Covers the trainerType backfill on an existing identity: when the existing identity
     * has null trainerType but the new TcgdexCard provides one, it should be set.
     *
     * @see docs/features.md F6.10 — Card identity and printing model
     */
    public function testResolveFromTcgdexCardBackfillsTrainerTypeOnExistingIdentity(): void
    {
        $existingIdentity = new CardIdentity();
        $existingIdentity->setName("Boss's Orders");
        $existingIdentity->setCategory('trainer');
        $existingIdentity->setTrainerType(null);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);
        $identityRepository->method('findBySignature')->willReturn($existingIdentity);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $rarityTierMapper->method('map')->willReturn(3);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $tcgdexCard = new TcgdexCard(
            id: 'swsh9-132',
            name: "Boss's Orders",
            category: 'Trainer',
            trainerType: 'Supporter',
            imageUrl: 'https://example.com/boss.webp',
            isExpandedLegal: true,
        );

        $result = $resolver->resolveFromTcgdexCard($tcgdexCard);

        self::assertSame($existingIdentity, $result->getCardIdentity());
        self::assertSame('Supporter', $existingIdentity->getTrainerType());
    }

    /**
     * Covers the trainerType backfill no-op: when the existing identity already has a trainerType,
     * it should not be overwritten.
     *
     * @see docs/features.md F6.10 — Card identity and printing model
     */
    public function testResolveFromTcgdexCardDoesNotOverwriteExistingTrainerType(): void
    {
        $existingIdentity = new CardIdentity();
        $existingIdentity->setName("Boss's Orders");
        $existingIdentity->setCategory('trainer');
        $existingIdentity->setTrainerType('Supporter');

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);
        $identityRepository->method('findBySignature')->willReturn($existingIdentity);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $rarityTierMapper->method('map')->willReturn(3);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $tcgdexCard = new TcgdexCard(
            id: 'swsh9-132',
            name: "Boss's Orders",
            category: 'Trainer',
            trainerType: 'Item',
            imageUrl: 'https://example.com/boss.webp',
            isExpandedLegal: true,
        );

        $resolver->resolveFromTcgdexCard($tcgdexCard);

        // Should keep the original Supporter, not overwrite with Item
        self::assertSame('Supporter', $existingIdentity->getTrainerType());
    }

    /**
     * Covers createPrinting with an invalid setReleaseDate format (ignored).
     */
    public function testResolveFromTcgdexCardHandlesInvalidReleaseDate(): void
    {
        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);
        $identityRepository->method('findBySignature')->willReturn(null);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $rarityTierMapper->method('map')->willReturn(1);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $tcgdexCard = new TcgdexCard(
            id: 'test-001',
            name: 'Test Card',
            category: 'Trainer',
            trainerType: null,
            imageUrl: null,
            isExpandedLegal: true,
            setReleaseDate: 'not-a-valid-date',
        );

        $result = $resolver->resolveFromTcgdexCard($tcgdexCard);

        self::assertNull($result->getSetReleaseDate());
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

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

use App\Constants\RuleboxType;
use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Repository\CardIdentityRepository;
use App\Repository\CardPrintingRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\CardIdentity\RarityTierMapper;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Service\Tcgdex\TcgdexCard;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $card = $this->createTcgdexCard(
            attacks: ['Shadow Mist', 'Astral Barrage'],
            attackDamages: [120, 200],
        );

        self::assertSame('Astral Barrage|200,Shadow Mist|120', CardIdentityResolver::computeAttackSignature($card));
    }

    public function testComputeAttackSignatureWithSingleAttack(): void
    {
        $card = $this->createTcgdexCard(attacks: ['Techno Blast'], attackDamages: [80]);

        self::assertSame('Techno Blast|80', CardIdentityResolver::computeAttackSignature($card));
    }

    public function testComputeAttackSignatureReturnsEmptyForNoAttacks(): void
    {
        $card = $this->createTcgdexCard(attacks: []);

        self::assertSame('', CardIdentityResolver::computeAttackSignature($card));
    }

    public function testComputeAttackSignatureWithThreeAttacks(): void
    {
        $card = $this->createTcgdexCard(
            attacks: ['Cross Fusion Strike', 'Max Miracle', 'Astral Barrage'],
            attackDamages: [150, null, 250],
        );

        self::assertSame(
            'Astral Barrage|250,Cross Fusion Strike|150,Max Miracle|',
            CardIdentityResolver::computeAttackSignature($card),
        );
    }

    public function testComputeAttackSignatureDistinguishesByDamage(): void
    {
        $card20 = $this->createTcgdexCard(attacks: ['Bite'], attackDamages: [20]);
        $card30 = $this->createTcgdexCard(attacks: ['Bite'], attackDamages: [30]);

        self::assertNotSame(
            CardIdentityResolver::computeAttackSignature($card20),
            CardIdentityResolver::computeAttackSignature($card30),
            'Same attack name with different damage must produce distinct signatures (cross-era reprint disambiguation).',
        );
    }

    public function testComputeAttackSignatureHandlesStringDamage(): void
    {
        $card = $this->createTcgdexCard(attacks: ['Thunderpunch'], attackDamages: ['30+']);

        self::assertSame('Thunderpunch|30+', CardIdentityResolver::computeAttackSignature($card));
    }

    public function testComputePokemonTypeSignatureSingleType(): void
    {
        $card = $this->createTcgdexCard(types: ['Metal']);

        self::assertSame('Metal', CardIdentityResolver::computePokemonTypeSignature($card));
    }

    public function testComputePokemonTypeSignatureSortsDualType(): void
    {
        $card = $this->createTcgdexCard(types: ['Water', 'Fire']);

        self::assertSame('Fire,Water', CardIdentityResolver::computePokemonTypeSignature($card));
    }

    public function testComputePokemonTypeSignatureReturnsEmptyForNoTypes(): void
    {
        $card = $this->createTcgdexCard(types: []);

        self::assertSame('', CardIdentityResolver::computePokemonTypeSignature($card));
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
        $identity->setAttackSignature('Thunder Shock|30');

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
                attackDamages: [30],
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
                attackDamages: [30, 50],
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
        $identity->setAttackSignature('Thunder Shock|30');

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
                attackDamages: [40],
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
                attackDamages: [30],
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

    public function testDetectRuleboxTypeReturnsAceSpecForAceSpecRarity(): void
    {
        $card = new TcgdexCard(
            id: 'sv5-198',
            name: 'Hero\'s Cape',
            category: 'Trainer',
            trainerType: 'Tool',
            imageUrl: null,
            isExpandedLegal: true,
            rarity: 'ACE SPEC Rare',
        );

        self::assertSame(RuleboxType::ACE_SPEC, CardIdentityResolver::detectRuleboxType($card));
    }

    public function testDetectRuleboxTypeReturnsNullForRegularRarity(): void
    {
        $card = new TcgdexCard(
            id: 'sv5-001',
            name: 'Pikachu',
            category: 'Pokemon',
            trainerType: null,
            imageUrl: null,
            isExpandedLegal: true,
            rarity: 'Common',
        );

        self::assertNull(CardIdentityResolver::detectRuleboxType($card));
    }

    public function testDetectRuleboxTypeReturnsNullForNullRarity(): void
    {
        $card = new TcgdexCard(
            id: 'sv5-001',
            name: 'Pikachu',
            category: 'Pokemon',
            trainerType: null,
            imageUrl: null,
            isExpandedLegal: true,
        );

        self::assertNull(CardIdentityResolver::detectRuleboxType($card));
    }

    public function testResolveFromTcgdexCardSetsRuleboxTypeOnNewIdentity(): void
    {
        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);
        $identityRepository->method('findBySignature')->willReturn(null);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $rarityTierMapper->method('map')->willReturn(6);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $tcgdexCard = new TcgdexCard(
            id: 'sv5-180',
            name: 'Unfair Stamp',
            category: 'Trainer',
            trainerType: 'Item',
            imageUrl: null,
            isExpandedLegal: true,
            rarity: 'ACE SPEC Rare',
        );

        $result = $resolver->resolveFromTcgdexCard($tcgdexCard);

        self::assertSame(RuleboxType::ACE_SPEC, $result->getCardIdentity()->getRuleboxType());
    }

    public function testResolveFromTcgdexCardBackfillsRuleboxTypeOnExistingIdentity(): void
    {
        $existingIdentity = new CardIdentity();
        $existingIdentity->setName('Unfair Stamp');
        $existingIdentity->setCategory('trainer');
        $existingIdentity->setTrainerType('Item');
        $existingIdentity->setRuleboxType(null);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findByTcgdexId')->willReturn(null);

        $identityRepository = $this->createStub(CardIdentityRepository::class);
        $identityRepository->method('findBySignature')->willReturn($existingIdentity);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $rarityTierMapper = $this->createStub(RarityTierMapper::class);
        $rarityTierMapper->method('map')->willReturn(6);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $resolver = new CardIdentityResolver(
            $identityRepository,
            $printingRepository,
            $apiClient,
            $rarityTierMapper,
            $entityManager,
        );

        $tcgdexCard = new TcgdexCard(
            id: 'sv5-180',
            name: 'Unfair Stamp',
            category: 'Trainer',
            trainerType: 'Item',
            imageUrl: null,
            isExpandedLegal: true,
            rarity: 'ACE SPEC Rare',
        );

        $resolver->resolveFromTcgdexCard($tcgdexCard);

        self::assertSame(RuleboxType::ACE_SPEC, $existingIdentity->getRuleboxType());
    }

    public function testResolveFromTcgdexCardDoesNotOverwriteExistingRuleboxType(): void
    {
        $existingIdentity = new CardIdentity();
        $existingIdentity->setName('Unfair Stamp');
        $existingIdentity->setCategory('trainer');
        $existingIdentity->setTrainerType('Item');
        $existingIdentity->setRuleboxType(RuleboxType::ACE_SPEC);

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

        // Same card resolved later via a non-Ace-Spec printing (would yield null detection)
        $tcgdexCard = new TcgdexCard(
            id: 'sv5-181',
            name: 'Unfair Stamp',
            category: 'Trainer',
            trainerType: 'Item',
            imageUrl: null,
            isExpandedLegal: true,
            rarity: 'Rare',
        );

        $resolver->resolveFromTcgdexCard($tcgdexCard);

        self::assertSame(RuleboxType::ACE_SPEC, $existingIdentity->getRuleboxType());
    }

    /**
     * Future-cases coverage for {@see CardIdentityResolver::detectRuleboxType()}.
     *
     * As of issue #532 PR-1, only ACE_SPEC is detected. This test enumerates every other
     * rulebox type listed in {@see RuleboxType} with realistic example cards, so that when
     * detection logic is extended in follow-up PRs, this test becomes the green target.
     *
     * Unskip when extending detection. Some classic-Mega cases may require extending the
     * TcgdexCard struct with `stage` / `evolveFrom` fields to detect reliably (see the
     * RuleboxType docblock for canonical detection signals per type — `stage = "MEGA"`
     * is the strongest signal for POKEMON_MEGA_CLASSIC, but the struct does not currently
     * carry it).
     *
     * Priority-order trap (already documented in RuleboxType): "Mega Charizard X ex" must
     * resolve to POKEMON_MEGA, not POKEMON_EX, even though it ends in " ex". Same for
     * classic — "M Charizard EX" must resolve to POKEMON_MEGA_CLASSIC, not POKEMON_EX_CLASSIC.
     */
    #[DataProvider('provideRuleboxDetectionCases')]
    public function testDetectRuleboxTypeFutureCases(string $cardName, ?string $rarity, ?string $expectedRuleboxType): void
    {
        self::markTestSkipped('Detection beyond ACE_SPEC is implemented in follow-up PRs (issue #532).');

        $card = new TcgdexCard(
            id: 'test-001',
            name: $cardName,
            category: 'Pokemon',
            trainerType: null,
            imageUrl: null,
            isExpandedLegal: true,
            rarity: $rarity,
        );

        self::assertSame($expectedRuleboxType, CardIdentityResolver::detectRuleboxType($card));
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string, 2: ?string}>
     */
    public static function provideRuleboxDetectionCases(): iterable
    {
        // ── Modern Mega ── prefix "Mega ", coexists with " ex" suffix; check BEFORE POKEMON_EX
        yield 'Modern Mega ex — Mega Charizard X ex' => ['Mega Charizard X ex', 'Double rare', RuleboxType::POKEMON_MEGA];

        // ── Classic Mega ── prefix "M " (single M + space); coexists with " EX" suffix.
        // The strongest signal would be `stage = "MEGA"` on the persisted entity, but the
        // TcgdexCard struct does not carry stage today. Detection from name prefix is the
        // current fallback — verify against false-positives like "Magneton" / "Marowak"
        // which start with M but have no space after.
        yield 'Classic Mega EX — M Charizard EX' => ['M Charizard EX', 'Ultra Rare', RuleboxType::POKEMON_MEGA_CLASSIC];
        yield 'Classic Mega EX — M Rayquaza EX' => ['M Rayquaza EX', 'Ultra Rare', RuleboxType::POKEMON_MEGA_CLASSIC];

        // ── Modern plain ex ── name suffix " ex" (lowercase, S&V era), no Mega prefix
        yield 'Modern ex — Charizard ex' => ['Charizard ex', 'Double rare', RuleboxType::POKEMON_EX];
        yield 'Modern ex — Dialga ex' => ['Dialga ex', 'Double rare', RuleboxType::POKEMON_EX];
        yield 'Modern ex — Iron Valiant ex' => ['Iron Valiant ex', 'Double rare', RuleboxType::POKEMON_EX];

        // ── Classic plain EX ── name contains "-EX" or " EX" (Ruby & Sapphire era), no M prefix
        yield 'Classic EX with hyphen — Charizard-EX' => ['Charizard-EX', 'Ultra Rare', RuleboxType::POKEMON_EX_CLASSIC];
        yield 'Classic EX with hyphen — Dialga-EX' => ['Dialga-EX', 'Ultra Rare', RuleboxType::POKEMON_EX_CLASSIC];
        yield 'Classic EX with hyphen — Latias-EX' => ['Latias-EX', 'Ultra Rare', RuleboxType::POKEMON_EX_CLASSIC];

        // ── V / VMAX / VSTAR ── name suffix
        yield 'V — Charizard V' => ['Charizard V', 'Holo Rare V', RuleboxType::POKEMON_V];
        yield 'V — Medicham V' => ['Medicham V', 'Holo Rare V', RuleboxType::POKEMON_V];
        yield 'VMAX — Charizard VMAX' => ['Charizard VMAX', 'Holo Rare VMAX', RuleboxType::POKEMON_VMAX];
        yield 'VSTAR — Arceus VSTAR' => ['Arceus VSTAR', 'Holo Rare VSTAR', RuleboxType::POKEMON_VSTAR];

        // ── GX ── name suffix " GX"
        yield 'GX — Charizard GX' => ['Charizard GX', 'Ultra Rare', RuleboxType::POKEMON_GX];
        yield 'GX — Dialga GX' => ['Dialga GX', 'Ultra Rare', RuleboxType::POKEMON_GX];

        // ── G ── name suffix " G" (Team Galactic, Platinum era)
        yield 'G — Dialga G' => ['Dialga G', 'Rare', RuleboxType::POKEMON_G];

        // ── BREAK ── name suffix " BREAK"
        yield 'BREAK — Chesnaught BREAK' => ['Chesnaught BREAK', 'Rare', RuleboxType::POKEMON_BREAK];

        // ── Radiant ── name prefix "Radiant " (NOT a suffix)
        yield 'Radiant — Radiant Charizard' => ['Radiant Charizard', 'Shiny rare', RuleboxType::POKEMON_RADIANT];
        yield 'Radiant — Radiant Greninja' => ['Radiant Greninja', 'Shiny rare', RuleboxType::POKEMON_RADIANT];

        // ── Prism Star ── name suffix " ◇" (diamond / prism-star symbol).
        // Verify TCGdex encoding before implementing — the symbol may render as
        // a different unicode codepoint or be omitted entirely from name fields.
        yield 'Prism Star — Latias ◇' => ['Latias ◇', 'Rare', RuleboxType::PRISM_STAR];

        // ── Ace Spec ── rarity = "ACE SPEC Rare". Already detected today; included here so
        // the future-cases suite is self-contained for regression coverage.
        yield 'Ace Spec — Unfair Stamp' => ['Unfair Stamp', 'ACE SPEC Rare', RuleboxType::ACE_SPEC];
        yield 'Ace Spec — Brilliant Blender' => ['Brilliant Blender', 'ACE SPEC Rare', RuleboxType::ACE_SPEC];

        // ── Negative cases ── must return null. These guard against name-pattern false-positives.
        yield 'Plain Pokemon — Pikachu' => ['Pikachu', 'Common', null];
        yield 'Plain Pokemon — Dialga base form' => ['Dialga', 'Rare', null];
        yield 'False-positive guard — Marowak (M, no space)' => ['Marowak', 'Common', null];
        yield 'False-positive guard — Magneton (M, no space)' => ['Magneton', 'Common', null];
        yield 'False-positive guard — Goldeen (G, no space, name does not end in space-G)' => ['Goldeen', 'Common', null];
    }

    /**
     * @param list<string>          $abilities
     * @param list<string>          $attacks
     * @param list<int|string|null> $attackDamages
     * @param list<string>          $types
     */
    private function createTcgdexCard(
        array $abilities = [],
        array $attacks = [],
        array $attackDamages = [],
        array $types = [],
    ): TcgdexCard {
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
            attackDamages: $attackDamages,
            types: $types,
        );
    }
}

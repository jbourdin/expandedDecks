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
use App\Entity\CardPrinting;
use App\Entity\StapleCard;
use App\Entity\StapleCardPrinting;
use App\Repository\CardPrintingRepository;
use App\Repository\StapleCardPrintingRepository;
use App\Repository\StapleCardRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\StapleCardEnricher;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Service\Tcgdex\TcgdexCard as TcgdexCardDto;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Coverage of the staple-specific enrichment flows:
 *   - Bucket priority rule (Ace Spec wins over the type-based buckets).
 *   - `createFromCode` editor entry point (idempotent restore + repositioning).
 *   - `enrichPrinting` + `enrichAllActive` mirroring the BannedCardEnricher machinery.
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

    public function testCreateFromCodeReturnsNullWhenTcgdexNotFound(): void
    {
        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $enricher = $this->buildEnricher(apiClient: $apiClient, entityManager: $entityManager);

        self::assertNull($enricher->createFromCode('UNKNOWN', '0', 5, null));
    }

    public function testCreateFromCodeCreatesNewStapleAndPersists(): void
    {
        $identity = $this->makeIdentity(name: 'Iono', category: 'trainer', trainerType: 'Supporter');
        $cardPrinting = new CardPrinting();
        $cardPrinting->setCardIdentity($identity);
        $cardPrinting->setTcgdexId('paf-80');
        $identity->addPrinting($cardPrinting);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn($this->buildTcgdexCardDto());

        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $identityResolver->method('resolveFromTcgdexCard')->willReturn($cardPrinting);

        $stapleRepo = $this->createStub(StapleCardRepository::class);
        $stapleRepo->method('findOneByCardIdentity')->willReturn(null);
        $stapleRepo->method('findMaxPositionInBucket')->willReturn(-1);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects(self::once())->method('flush');

        $enricher = $this->buildEnricher(
            apiClient: $apiClient,
            identityResolver: $identityResolver,
            stapleCardRepository: $stapleRepo,
            entityManager: $entityManager,
        );

        $staple = $enricher->createFromCode('PAF', '80', 9, 'meta defining draw');

        self::assertNotNull($staple);
        self::assertSame($identity, $staple->getCardIdentity());
        self::assertSame('Iono', $staple->getCardName());
        self::assertSame(StapleCardBucket::SUPPORTER, $staple->getBucket());
        self::assertSame(9, $staple->getHotness());
        self::assertSame(0, $staple->getPosition(), 'first row in an empty bucket appends at position 0');
        self::assertSame($cardPrinting, $staple->getRepresentativePrinting());
        self::assertContains($staple, $persisted);
    }

    public function testCreateFromCodeRestoresAndPreservesPositionWhenBucketUnchanged(): void
    {
        $identity = $this->makeIdentity(name: 'Iono', category: 'trainer', trainerType: 'Supporter');
        $cardPrinting = new CardPrinting();
        $cardPrinting->setCardIdentity($identity);
        $cardPrinting->setTcgdexId('paf-80');
        $identity->addPrinting($cardPrinting);

        $existingStaple = new StapleCard();
        $existingStaple->setCardIdentity($identity);
        $existingStaple->setCardName('Iono');
        $existingStaple->setBucket(StapleCardBucket::SUPPORTER);
        $existingStaple->setHotness(3);
        $existingStaple->setPosition(7);
        $existingStaple->setDeletedAt(new \DateTimeImmutable());

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn($this->buildTcgdexCardDto());

        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $identityResolver->method('resolveFromTcgdexCard')->willReturn($cardPrinting);

        $stapleRepo = $this->createMock(StapleCardRepository::class);
        $stapleRepo->method('findOneByCardIdentity')->willReturn($existingStaple);
        // Bucket unchanged → no findMaxPositionInBucket lookup, position is preserved.
        $stapleRepo->expects(self::never())->method('findMaxPositionInBucket');

        // syncChildPrintings can still persist new StapleCardPrinting children for siblings discovered on
        // the identity — that's expected. We only assert flush() ran exactly once.
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $enricher = $this->buildEnricher(
            apiClient: $apiClient,
            identityResolver: $identityResolver,
            stapleCardRepository: $stapleRepo,
            entityManager: $entityManager,
        );

        $result = $enricher->createFromCode('PAF', '80', 8, 'updated note');

        self::assertSame($existingStaple, $result);
        self::assertNull($result->getDeletedAt(), 'soft-deleted row is restored');
        self::assertSame(7, $result->getPosition(), 'unchanged bucket keeps the existing position');
        self::assertSame(8, $result->getHotness());
        self::assertSame('updated note', $result->getNote());
    }

    public function testCreateFromCodeRepositionsWhenBucketChanges(): void
    {
        // Existing row was POKEMON; the editor's input + identity now categorize it as SUPPORTER.
        $identity = $this->makeIdentity(name: 'Iono', category: 'trainer', trainerType: 'Supporter');
        $cardPrinting = new CardPrinting();
        $cardPrinting->setCardIdentity($identity);
        $cardPrinting->setTcgdexId('paf-80');
        $identity->addPrinting($cardPrinting);

        $existingStaple = new StapleCard();
        $existingStaple->setCardIdentity($identity);
        $existingStaple->setCardName('Iono');
        $existingStaple->setBucket(StapleCardBucket::POKEMON);
        $existingStaple->setPosition(2);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn($this->buildTcgdexCardDto());

        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $identityResolver->method('resolveFromTcgdexCard')->willReturn($cardPrinting);

        $stapleRepo = $this->createStub(StapleCardRepository::class);
        $stapleRepo->method('findOneByCardIdentity')->willReturn($existingStaple);
        $stapleRepo->method('findMaxPositionInBucket')->willReturn(4);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $enricher = $this->buildEnricher(
            apiClient: $apiClient,
            identityResolver: $identityResolver,
            stapleCardRepository: $stapleRepo,
            entityManager: $entityManager,
        );

        $result = $enricher->createFromCode('PAF', '80', 7, null);

        self::assertNotNull($result);
        self::assertSame(StapleCardBucket::SUPPORTER, $result->getBucket());
        self::assertSame(5, $result->getPosition(), 'bucket changed → appended at maxPosition+1');
    }

    public function testEnrichPrintingNoOpsWhenAlreadyLinked(): void
    {
        $existing = $this->createStub(CardPrinting::class);

        $printing = $this->buildStaplePrinting('LOT', '90', $existing);

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->expects(self::never())->method('findCard');

        $enricher = $this->buildEnricher(apiClient: $apiClient);

        self::assertTrue($enricher->enrichPrinting($printing));
        self::assertSame($existing, $printing->getCardPrinting());
    }

    public function testEnrichPrintingHitsLocalRepositoryFirst(): void
    {
        $local = $this->createStub(CardPrinting::class);

        $repo = $this->createStub(CardPrintingRepository::class);
        $repo->method('findFirstBySetCodeAndCardNumber')->willReturn($local);

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->expects(self::never())->method('findCard');

        $printing = $this->buildStaplePrinting('LOT', '90', null);

        $enricher = $this->buildEnricher(cardPrintingRepository: $repo, apiClient: $apiClient);

        self::assertTrue($enricher->enrichPrinting($printing));
        self::assertSame($local, $printing->getCardPrinting());
    }

    public function testEnrichPrintingFallsBackToTcgdexApi(): void
    {
        $tcgdexCard = $this->buildTcgdexCardDto(imageUrl: 'https://images.example.com/foo.png');
        $resolved = $this->buildResolvedPrintingWithMutableImageUrl(initialImageUrl: null);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn($tcgdexCard);

        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $identityResolver->method('resolveFromTcgdexCard')->willReturn($resolved);

        $printing = $this->buildStaplePrinting('LOT', '90', null);

        $enricher = $this->buildEnricher(apiClient: $apiClient, identityResolver: $identityResolver);

        self::assertTrue($enricher->enrichPrinting($printing));
        self::assertSame($resolved, $printing->getCardPrinting());
        self::assertSame('https://images.example.com/foo.png', $resolved->getImageUrl());
    }

    public function testEnrichPrintingDoesNotOverwriteExistingImageUrl(): void
    {
        $tcgdexCard = $this->buildTcgdexCardDto(imageUrl: 'https://images.example.com/new.png');
        $resolved = $this->buildResolvedPrintingWithMutableImageUrl(initialImageUrl: 'https://images.example.com/old.png');

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn($tcgdexCard);

        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $identityResolver->method('resolveFromTcgdexCard')->willReturn($resolved);

        $printing = $this->buildStaplePrinting('LOT', '90', null);

        $enricher = $this->buildEnricher(apiClient: $apiClient, identityResolver: $identityResolver);

        $enricher->enrichPrinting($printing);

        self::assertSame('https://images.example.com/old.png', $resolved->getImageUrl());
    }

    public function testEnrichPrintingFallsBackToAliasLookup(): void
    {
        $tcgdexCard = $this->buildTcgdexCardDto(imageUrl: null);
        $resolved = $this->createStub(CardPrinting::class);

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->expects(self::once())->method('findCard')->willReturn(null);
        $apiClient->expects(self::once())->method('findCardByNameInAliasedSet')->willReturn($tcgdexCard);

        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $identityResolver->method('resolveFromTcgdexCard')->willReturn($resolved);

        $staple = new StapleCard();
        $staple->setCardName('Some Card');
        $printing = $this->buildStaplePrinting('LOT', '90', null);
        $staple->addPrinting($printing);

        $enricher = $this->buildEnricher(apiClient: $apiClient, identityResolver: $identityResolver);

        self::assertTrue($enricher->enrichPrinting($printing));
        self::assertSame($resolved, $printing->getCardPrinting());
    }

    public function testEnrichPrintingReturnsFalseWhenAllSourcesMiss(): void
    {
        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);

        $staple = new StapleCard();
        $staple->setCardName('Phantom Card');
        $printing = $this->buildStaplePrinting('UNKNOWN', '0', null);
        $staple->addPrinting($printing);

        $enricher = $this->buildEnricher(apiClient: $apiClient);

        self::assertFalse($enricher->enrichPrinting($printing));
        self::assertNull($printing->getCardPrinting());
    }

    public function testEnrichAllActiveLinksUnresolvedAndRecomputesActiveStaples(): void
    {
        $identity = $this->makeIdentity(name: 'Iono', category: 'trainer', trainerType: 'Supporter');
        $linkedPrinting = new CardPrinting();
        $linkedPrinting->setCardIdentity($identity);
        $linkedPrinting->setTcgdexId('paf-80');
        $identity->addPrinting($linkedPrinting);

        // First call resolves locally; second misses everywhere so it shows up as unresolved.
        $cardPrintingRepo = $this->createStub(CardPrintingRepository::class);
        $cardPrintingRepo->method('findFirstBySetCodeAndCardNumber')->willReturnOnConsecutiveCalls($linkedPrinting, null);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);

        $staple1 = new StapleCard();
        $staple1->setCardName('Iono');
        $staple1->setBucket(StapleCardBucket::POKEMON); // will be recomputed to SUPPORTER
        $staple1->setPosition(2);
        $printing1 = $this->buildStaplePrinting('PAF', '80', null);
        $staple1->addPrinting($printing1);

        $staple2 = new StapleCard();
        $staple2->setCardName('Lost Card');
        $printing2 = $this->buildStaplePrinting('UNK', '99', null);
        $staple2->addPrinting($printing2);

        $staplePrintingRepo = $this->createStub(StapleCardPrintingRepository::class);
        $staplePrintingRepo->method('findAllOrderedBySetAndNumber')->willReturn([$printing1, $printing2]);
        $staplePrintingRepo->method('findOneBySetCodeAndCardNumber')->willReturn(null);

        // After reparent, the canonical staple for $identity is $staple1 — it should appear in findAllActive.
        $stapleRepo = $this->createStub(StapleCardRepository::class);
        $stapleRepo->method('findOneByCardIdentity')->willReturn(null);
        $stapleRepo->method('findAllActive')->willReturn([$staple1]);
        $stapleRepo->method('findMaxPositionInBucket')->willReturn(4);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $enricher = $this->buildEnricher(
            apiClient: $apiClient,
            cardPrintingRepository: $cardPrintingRepo,
            stapleCardPrintingRepository: $staplePrintingRepo,
            stapleCardRepository: $stapleRepo,
            entityManager: $entityManager,
        );

        [$linked, $unresolved] = $enricher->enrichAllActive();

        self::assertSame(1, $linked);
        self::assertSame(['Lost Card (UNK 99)'], $unresolved);
        // The active staple's bucket was recomputed from the now-known identity.
        self::assertSame(StapleCardBucket::SUPPORTER, $staple1->getBucket());
        self::assertSame(5, $staple1->getPosition(), 'bucket changed → appended at maxPosition+1');
        // The editor's StapleCardPrinting got its representative pulled by the seed path.
        self::assertSame($linkedPrinting, $staple1->getRepresentativePrinting());
    }

    public function testEnrichAllActiveForceModeResetsExistingLink(): void
    {
        $stale = $this->createStub(CardPrinting::class);

        $printing = $this->buildStaplePrinting('LOT', '90', $stale);
        $staple = new StapleCard();
        $staple->setCardName('Pikachu');
        $staple->addPrinting($printing);

        $staplePrintingRepo = $this->createStub(StapleCardPrintingRepository::class);
        $staplePrintingRepo->method('findAllOrderedBySetAndNumber')->willReturn([$printing]);

        $stapleRepo = $this->createStub(StapleCardRepository::class);
        $stapleRepo->method('findAllActive')->willReturn([$staple]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);

        $enricher = $this->buildEnricher(
            apiClient: $apiClient,
            stapleCardPrintingRepository: $staplePrintingRepo,
            stapleCardRepository: $stapleRepo,
        );

        [$linked, $unresolved] = $enricher->enrichAllActive(force: true);

        self::assertSame(0, $linked);
        self::assertSame(['Pikachu (LOT 90)'], $unresolved);
        self::assertNull($printing->getCardPrinting(), 'force mode should reset existing link before re-enriching');
    }

    public function testEnrichAllActiveForceModeAlsoDropsCachedRepresentativePrintingOnIdentifiedStaples(): void
    {
        $identity = $this->makeIdentity(name: 'Iono', category: 'trainer', trainerType: 'Supporter');
        $stale = new CardPrinting();
        $stale->setCardIdentity($identity);
        $stale->setTcgdexId('paf-80');

        // The staple already has its identity resolved — the active-staple loop will run for it.
        $staple = new StapleCard();
        $staple->setCardName('Iono');
        $staple->setBucket(StapleCardBucket::SUPPORTER);
        $staple->setCardIdentity($identity);
        $staple->setRepresentativePrinting($stale);

        $staplePrintingRepo = $this->createStub(StapleCardPrintingRepository::class);
        $staplePrintingRepo->method('findAllOrderedBySetAndNumber')->willReturn([]);

        $stapleRepo = $this->createStub(StapleCardRepository::class);
        $stapleRepo->method('findAllActive')->willReturn([$staple]);

        $enricher = $this->buildEnricher(
            stapleCardPrintingRepository: $staplePrintingRepo,
            stapleCardRepository: $stapleRepo,
        );

        $enricher->enrichAllActive(force: true);

        self::assertNull(
            $staple->getRepresentativePrinting(),
            'force mode drops the cached canonical art so it is re-derived from the freshly enriched printings',
        );
    }

    private function buildEnricher(
        ?TcgdexApiClient $apiClient = null,
        ?CardIdentityResolver $identityResolver = null,
        ?CardPrintingRepository $cardPrintingRepository = null,
        ?StapleCardPrintingRepository $stapleCardPrintingRepository = null,
        ?StapleCardRepository $stapleCardRepository = null,
        ?EntityManagerInterface $entityManager = null,
    ): StapleCardEnricher {
        if (null === $apiClient) {
            $apiClient = $this->createStub(TcgdexApiClient::class);
            $apiClient->method('findCard')->willReturn(null);
            $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);
        }
        if (null === $identityResolver) {
            $identityResolver = $this->createStub(CardIdentityResolver::class);
        }
        if (null === $cardPrintingRepository) {
            $cardPrintingRepository = $this->createStub(CardPrintingRepository::class);
            $cardPrintingRepository->method('findFirstBySetCodeAndCardNumber')->willReturn(null);
        }
        if (null === $stapleCardPrintingRepository) {
            $stapleCardPrintingRepository = $this->createStub(StapleCardPrintingRepository::class);
            $stapleCardPrintingRepository->method('findAllOrderedBySetAndNumber')->willReturn([]);
            $stapleCardPrintingRepository->method('findOneBySetCodeAndCardNumber')->willReturn(null);
        }
        if (null === $stapleCardRepository) {
            $stapleCardRepository = $this->createStub(StapleCardRepository::class);
            $stapleCardRepository->method('findOneByCardIdentity')->willReturn(null);
            $stapleCardRepository->method('findAllActive')->willReturn([]);
            $stapleCardRepository->method('findMaxPositionInBucket')->willReturn(-1);
        }
        if (null === $entityManager) {
            $entityManager = $this->createStub(EntityManagerInterface::class);
        }

        return new StapleCardEnricher(
            $apiClient,
            $identityResolver,
            $cardPrintingRepository,
            $stapleCardPrintingRepository,
            $stapleCardRepository,
            $entityManager,
        );
    }

    private function makeEnricher(): StapleCardEnricher
    {
        return $this->buildEnricher();
    }

    private function makeIdentity(string $name = 'Test Card', string $category = 'pokemon', ?string $trainerType = null, ?string $ruleboxType = null): CardIdentity
    {
        $identity = new CardIdentity();
        $identity->setName($name);
        $identity->setCategory($category);
        $identity->setTrainerType($trainerType);
        $identity->setRuleboxType($ruleboxType);

        return $identity;
    }

    private function buildStaplePrinting(string $setCode, string $cardNumber, ?CardPrinting $cardPrinting): StapleCardPrinting
    {
        $printing = new StapleCardPrinting();
        $printing->setSetCode($setCode);
        $printing->setCardNumber($cardNumber);
        $printing->setCardPrinting($cardPrinting);

        return $printing;
    }

    private function buildTcgdexCardDto(?string $imageUrl = null): TcgdexCardDto
    {
        return new TcgdexCardDto(
            id: 'paf-80',
            name: 'Iono',
            category: 'Trainer',
            trainerType: 'Supporter',
            imageUrl: $imageUrl,
            isExpandedLegal: true,
        );
    }

    private function buildResolvedPrintingWithMutableImageUrl(?string $initialImageUrl): CardPrinting
    {
        // We need both getImageUrl (initially returns $initialImageUrl) and
        // setImageUrl (mutates state). A real CardPrinting works for this.
        $resolved = new CardPrinting();
        $resolved->setTcgdexId('paf-80');
        if (null !== $initialImageUrl) {
            $resolved->setImageUrl($initialImageUrl);
        }

        return $resolved;
    }
}

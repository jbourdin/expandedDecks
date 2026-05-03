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
use App\Entity\BannedCardPrinting;
use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Repository\BannedCardPrintingRepository;
use App\Repository\BannedCardRepository;
use App\Repository\CardPrintingRepository;
use App\Service\BannedCardEnricher;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Service\Tcgdex\TcgdexCard as TcgdexCardDto;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardEnricherTest extends TestCase
{
    public function testEnrichPrintingNoOpsWhenAlreadyLinked(): void
    {
        $existing = $this->createStub(CardPrinting::class);

        $printing = $this->buildBannedPrinting('LOT', '90', $existing);

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

        $printing = $this->buildBannedPrinting('LOT', '90', null);

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

        $printing = $this->buildBannedPrinting('LOT', '90', null);

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

        $printing = $this->buildBannedPrinting('LOT', '90', null);

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

        $ban = new BannedCard();
        $ban->setCardName('Some Card');
        $printing = $this->buildBannedPrinting('LOT', '90', null);
        $ban->addPrinting($printing);

        $enricher = $this->buildEnricher(apiClient: $apiClient, identityResolver: $identityResolver);

        self::assertTrue($enricher->enrichPrinting($printing));
        self::assertSame($resolved, $printing->getCardPrinting());
    }

    public function testEnrichPrintingReturnsFalseWhenAllSourcesMiss(): void
    {
        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);

        $ban = new BannedCard();
        $ban->setCardName('Phantom Card');
        $printing = $this->buildBannedPrinting('UNKNOWN', '0', null);
        $ban->addPrinting($printing);

        $enricher = $this->buildEnricher(apiClient: $apiClient);

        self::assertFalse($enricher->enrichPrinting($printing));
        self::assertNull($printing->getCardPrinting());
    }

    public function testEnrichAllActiveCollectsLinkedAndUnresolvedAndFlushesOnce(): void
    {
        $linkedPrinting = $this->createStub(CardPrinting::class);
        $linkedPrinting->method('getCardIdentity')->willReturn($this->buildIdentity(1, 'Pikachu'));

        $repo = $this->createStub(CardPrintingRepository::class);
        $repo->method('findFirstBySetCodeAndCardNumber')->willReturnOnConsecutiveCalls($linkedPrinting, null);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);

        $ban1 = new BannedCard();
        $ban1->setCardName('Pikachu');
        $printing1 = $this->buildBannedPrinting('LOT', '90', null);
        $ban1->addPrinting($printing1);

        $ban2 = new BannedCard();
        $ban2->setCardName('Lost Card');
        $printing2 = $this->buildBannedPrinting('PHF', '99', null);
        $ban2->addPrinting($printing2);

        $bannedPrintingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $bannedPrintingRepo->method('findAllOrderedBySetAndNumber')->willReturn([$printing1, $printing2]);

        $bannedRepo = $this->createStub(BannedCardRepository::class);
        $bannedRepo->method('findOneByCardIdentity')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $enricher = $this->buildEnricher(
            apiClient: $apiClient,
            cardPrintingRepository: $repo,
            bannedCardPrintingRepository: $bannedPrintingRepo,
            bannedCardRepository: $bannedRepo,
            entityManager: $entityManager,
        );

        [$linked, $unresolved] = $enricher->enrichAllActive();

        self::assertSame(1, $linked);
        self::assertSame(['Lost Card (PHF 99)'], $unresolved);
    }

    public function testEnrichAllActiveForceModeResetsExistingLink(): void
    {
        $stale = $this->createStub(CardPrinting::class);

        $printing = $this->buildBannedPrinting('LOT', '90', $stale);
        $ban = new BannedCard();
        $ban->setCardName('Pikachu');
        $ban->addPrinting($printing);

        $bannedPrintingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $bannedPrintingRepo->method('findAllOrderedBySetAndNumber')->willReturn([$printing]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);

        $enricher = $this->buildEnricher(
            apiClient: $apiClient,
            bannedCardPrintingRepository: $bannedPrintingRepo,
        );

        [$linked, $unresolved] = $enricher->enrichAllActive(force: true);

        self::assertSame(0, $linked);
        self::assertSame(['Pikachu (LOT 90)'], $unresolved);
        self::assertNull($printing->getCardPrinting(), 'force mode should reset existing link before re-enriching');
    }

    public function testReparentPromotesCurrentParentWhenNoCanonicalExists(): void
    {
        $identity = $this->buildIdentity(42, 'Pikachu');

        $cardPrinting = $this->createStub(CardPrinting::class);
        $cardPrinting->method('getCardIdentity')->willReturn($identity);

        $local = $cardPrinting; // local hit returns the stub
        $repo = $this->createStub(CardPrintingRepository::class);
        $repo->method('findFirstBySetCodeAndCardNumber')->willReturn($local);

        $ban = new BannedCard();
        $ban->setCardName(''); // placeholder name (not yet enriched)
        $printing = $this->buildBannedPrinting('LOT', '90', null);
        $ban->addPrinting($printing);

        $bannedPrintingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $bannedPrintingRepo->method('findAllOrderedBySetAndNumber')->willReturn([$printing]);

        $bannedRepo = $this->createStub(BannedCardRepository::class);
        $bannedRepo->method('findOneByCardIdentity')->willReturn(null);

        $enricher = $this->buildEnricher(
            cardPrintingRepository: $repo,
            bannedCardPrintingRepository: $bannedPrintingRepo,
            bannedCardRepository: $bannedRepo,
        );

        $enricher->enrichAllActive();

        self::assertSame($identity, $ban->getCardIdentity());
        self::assertSame('Pikachu', $ban->getCardName());
    }

    public function testReparentInLoopCacheReusesParentForSameIdentity(): void
    {
        // Regression for the unique-constraint trap: two consecutive printings
        // for the same identity must reuse the parent created in the same loop
        // (DB lookup still empty since flush happens at the end).
        $identity = $this->buildIdentity(42, 'Pikachu');

        $local = $this->createStub(CardPrinting::class);
        $local->method('getCardIdentity')->willReturn($identity);

        $repo = $this->createStub(CardPrintingRepository::class);
        $repo->method('findFirstBySetCodeAndCardNumber')->willReturn($local);

        // Two distinct parents (placeholders); the second one should end up
        // empty after reparenting and get removed.
        $parent1 = new BannedCard();
        $parent1->setCardName('');
        $printing1 = $this->buildBannedPrinting('LOT', '90', null);
        $parent1->addPrinting($printing1);

        $parent2 = new BannedCard();
        $parent2->setCardName('');
        $printing2 = $this->buildBannedPrinting('LOT', '91', null);
        $parent2->addPrinting($printing2);

        $bannedPrintingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $bannedPrintingRepo->method('findAllOrderedBySetAndNumber')->willReturn([$printing1, $printing2]);

        // bannedCardRepository::findOneByCardIdentity returns null both times —
        // the in-loop cache is what makes the second one find the parent.
        $bannedRepo = $this->createStub(BannedCardRepository::class);
        $bannedRepo->method('findOneByCardIdentity')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($parent2);
        $entityManager->expects(self::once())->method('flush');

        $enricher = $this->buildEnricher(
            cardPrintingRepository: $repo,
            bannedCardPrintingRepository: $bannedPrintingRepo,
            bannedCardRepository: $bannedRepo,
            entityManager: $entityManager,
        );

        $enricher->enrichAllActive();

        // Both printings now live under parent1.
        self::assertSame($parent1, $printing1->getBannedCard());
        self::assertSame($parent1, $printing2->getBannedCard());
        self::assertSame('Pikachu', $parent1->getCardName());
        self::assertCount(2, $parent1->getPrintings());
        self::assertCount(0, $parent2->getPrintings());
    }

    public function testReparentFillsEmptyNameOnExistingCanonicalParent(): void
    {
        $identity = $this->buildIdentity(42, 'Pikachu');

        $local = $this->createStub(CardPrinting::class);
        $local->method('getCardIdentity')->willReturn($identity);

        $repo = $this->createStub(CardPrintingRepository::class);
        $repo->method('findFirstBySetCodeAndCardNumber')->willReturn($local);

        $canonical = new BannedCard();
        $canonical->setCardName(''); // empty -> should get filled
        $canonical->setCardIdentity($identity);
        $printing = $this->buildBannedPrinting('LOT', '90', null);
        $canonical->addPrinting($printing);

        $bannedPrintingRepo = $this->createStub(BannedCardPrintingRepository::class);
        $bannedPrintingRepo->method('findAllOrderedBySetAndNumber')->willReturn([$printing]);

        // canonical IS the currentParent -> the second branch fires
        $bannedRepo = $this->createStub(BannedCardRepository::class);
        $bannedRepo->method('findOneByCardIdentity')->willReturn($canonical);

        $enricher = $this->buildEnricher(
            cardPrintingRepository: $repo,
            bannedCardPrintingRepository: $bannedPrintingRepo,
            bannedCardRepository: $bannedRepo,
        );

        $enricher->enrichAllActive();

        self::assertSame('Pikachu', $canonical->getCardName());
    }

    private function buildEnricher(
        ?TcgdexApiClient $apiClient = null,
        ?CardIdentityResolver $identityResolver = null,
        ?CardPrintingRepository $cardPrintingRepository = null,
        ?BannedCardPrintingRepository $bannedCardPrintingRepository = null,
        ?BannedCardRepository $bannedCardRepository = null,
        ?EntityManagerInterface $entityManager = null,
    ): BannedCardEnricher {
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
        if (null === $bannedCardPrintingRepository) {
            $bannedCardPrintingRepository = $this->createStub(BannedCardPrintingRepository::class);
            $bannedCardPrintingRepository->method('findAllOrderedBySetAndNumber')->willReturn([]);
        }
        if (null === $bannedCardRepository) {
            $bannedCardRepository = $this->createStub(BannedCardRepository::class);
            $bannedCardRepository->method('findOneByCardIdentity')->willReturn(null);
        }
        if (null === $entityManager) {
            $entityManager = $this->createStub(EntityManagerInterface::class);
        }

        return new BannedCardEnricher(
            $apiClient,
            $identityResolver,
            $cardPrintingRepository,
            $bannedCardPrintingRepository,
            $bannedCardRepository,
            $entityManager,
        );
    }

    private function buildBannedPrinting(string $setCode, string $cardNumber, ?CardPrinting $cardPrinting): BannedCardPrinting
    {
        $printing = new BannedCardPrinting();
        $printing->setSetCode($setCode);
        $printing->setCardNumber($cardNumber);
        $printing->setCardPrinting($cardPrinting);

        return $printing;
    }

    private function buildTcgdexCardDto(?string $imageUrl): TcgdexCardDto
    {
        return new TcgdexCardDto(
            id: 'sm-1',
            name: 'Pikachu',
            category: 'Pokemon',
            trainerType: null,
            imageUrl: $imageUrl,
            isExpandedLegal: true,
        );
    }

    private function buildIdentity(int $id, string $name): CardIdentity
    {
        $identity = $this->createStub(CardIdentity::class);
        $identity->method('getId')->willReturn($id);
        $identity->method('getName')->willReturn($name);

        return $identity;
    }

    private function buildResolvedPrintingWithMutableImageUrl(?string $initialImageUrl): CardPrinting
    {
        // We need both getImageUrl (initially returns $initialImageUrl) and
        // setImageUrl (mutates state). A real CardPrinting works for this.
        $resolved = new CardPrinting();
        $resolved->setTcgdexId('sm-1');
        if (null !== $initialImageUrl) {
            $resolved->setImageUrl($initialImageUrl);
        }

        return $resolved;
    }
}

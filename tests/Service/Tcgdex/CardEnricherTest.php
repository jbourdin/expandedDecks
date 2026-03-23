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

namespace App\Tests\Service\Tcgdex;

use App\Entity\CardPrinting;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Tcgdex\CardEnricher;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Service\Tcgdex\TcgdexCard;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class CardEnricherTest extends TestCase
{
    private EntityManagerInterface $em;
    private CardIdentityResolver $identityResolver;

    protected function setUp(): void
    {
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->em->method('flush');
        $this->identityResolver = $this->createStub(CardIdentityResolver::class);
        $this->identityResolver->method('resolveFromTcgdexCard')->willReturn(new CardPrinting());
    }

    public function testEnrichVersionSetsFieldsCorrectly(): void
    {
        $card = new DeckCard();
        $card->setCardName('Arceus VSTAR');
        $card->setSetCode('BRS');
        $card->setCardNumber('123');
        $card->setCardType('pokemon');
        $card->setQuantity(2);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'swsh9-123',
                name: 'Arceus VSTAR',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: 'https://assets.tcgdex.net/en/swsh/swsh9/123/high.webp',
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertSame(0, $report->notFoundCount);
        self::assertCount(0, $report->notFoundCards);
        self::assertCount(0, $report->legalityWarnings);

        self::assertSame('swsh9-123', $card->getTcgdexId());
        self::assertSame('https://assets.tcgdex.net/en/swsh/swsh9/123/high.webp', $card->getImageUrl());
        self::assertSame('done', $version->getEnrichmentStatus());
    }

    public function testEnrichVersionSetsTrainerSubtype(): void
    {
        $card = new DeckCard();
        $card->setCardName("Boss's Orders");
        $card->setSetCode('BRS');
        $card->setCardNumber('132');
        $card->setCardType('trainer');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'swsh9-132',
                name: "Boss's Orders",
                category: 'Trainer',
                trainerType: 'Supporter',
                imageUrl: 'https://assets.tcgdex.net/en/swsh/swsh9/132/high.webp',
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertSame('Supporter', $card->getTrainerSubtype());
    }

    public function testEnrichVersionAssignsStaticImageForBasicEnergy(): void
    {
        $card = new DeckCard();
        $card->setCardName('Lightning Energy');
        $card->setSetCode('SVE');
        $card->setCardNumber('4');
        $card->setCardType('energy');
        $card->setQuantity(4);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->expects(self::never())->method('findCard');

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertSame(0, $report->notFoundCount);
        self::assertStringContainsString('SVE_EN_4', (string) $card->getImageUrl());
    }

    public function testEnrichVersionAssignsNullForUnknownEnergyName(): void
    {
        $card = new DeckCard();
        $card->setCardName('Dragon Energy');
        $card->setSetCode('SVE');
        $card->setCardNumber('99');
        $card->setCardType('energy');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertNull($card->getImageUrl());
    }

    public function testEnrichVersionReportsNotFoundCardsWithDetails(): void
    {
        $card = new DeckCard();
        $card->setCardName('Mystery Card');
        $card->setSetCode('BRS');
        $card->setCardNumber('999');
        $card->setCardType('pokemon');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(0, $report->enrichedCount);
        self::assertSame(1, $report->notFoundCount);
        self::assertCount(1, $report->notFoundCards);
        self::assertSame('Mystery Card (BRS 999)', $report->notFoundCards[0]);
    }

    public function testEnrichVersionWarnsOnNonExpandedLegalCard(): void
    {
        $card = new DeckCard();
        $card->setCardName('Illegal Card');
        $card->setSetCode('BRS');
        $card->setCardNumber('1');
        $card->setCardType('pokemon');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'swsh9-1',
                name: 'Illegal Card',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: null,
                isExpandedLegal: false,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertCount(1, $report->legalityWarnings);
        self::assertStringContainsString('Illegal Card', $report->legalityWarnings[0]);
        self::assertStringContainsString('not marked as Expanded-legal', $report->legalityWarnings[0]);
    }

    public function testEnrichVersionFallsBackToPokemontcgioWhenImageUrlIsNull(): void
    {
        $card = new DeckCard();
        $card->setCardName('Double Colorless Energy');
        $card->setSetCode('SM3.5');
        $card->setCardNumber('69');
        $card->setCardType('energy');
        $card->setQuantity(4);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'sm35-69',
                name: 'Double Colorless Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: null,
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        // Falls back to PokemonTCG.io CDN URL built from tcgdex ID
        self::assertSame('https://images.pokemontcg.io/sm35/69_hires.png', $card->getImageUrl());
        self::assertSame('sm35-69', $card->getTcgdexId());
    }

    public function testEnrichVersionDoesNotCallFindImageByNameWhenImageUrlIsPresent(): void
    {
        $card = new DeckCard();
        $card->setCardName('Arceus VSTAR');
        $card->setSetCode('BRS');
        $card->setCardNumber('123');
        $card->setCardType('pokemon');
        $card->setQuantity(2);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'swsh9-123',
                name: 'Arceus VSTAR',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: 'https://assets.tcgdex.net/en/swsh/swsh9/123/high.webp',
                isExpandedLegal: true,
            ));
        $apiClient->expects(self::never())
            ->method('findImageByName');

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertSame('https://assets.tcgdex.net/en/swsh/swsh9/123/high.webp', $card->getImageUrl());
    }

    public function testEnrichVersionSetsFailedOnException(): void
    {
        $card = new DeckCard();
        $card->setCardName('Arceus VSTAR');
        $card->setSetCode('BRS');
        $card->setCardNumber('123');
        $card->setCardType('pokemon');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willThrowException(new \RuntimeException('API error'));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('API error');

        $enricher->enrichVersion($version);

        // After exception, status should be 'failed'
        self::assertSame('failed', $version->getEnrichmentStatus());
    }

    /**
     * Covers enrichBasicEnergy() path for a basic energy detected by name from a non-energy set.
     * TCGdex lookup succeeds, so the card gets enriched via findCard.
     *
     * @see docs/features.md F6.9 — Improved energy card enrichment
     */
    public function testEnrichBasicEnergyFromNonEnergySetUsesTcgdexLookup(): void
    {
        $card = new DeckCard();
        $card->setCardName('Water Energy');
        $card->setSetCode('SVI');
        $card->setCardNumber('3');
        $card->setCardType('energy');
        $card->setQuantity(4);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'sv1-264',
                name: 'Water Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: 'https://assets.tcgdex.net/en/sv/sv1/264/high.webp',
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertSame('sv1-264', $card->getTcgdexId());
        self::assertSame('https://assets.tcgdex.net/en/sv/sv1/264/high.webp', $card->getImageUrl());
    }

    /**
     * Covers enrichBasicEnergy() fallback: non-energy set + findCard returns null,
     * falls back to findSimplestBasicEnergyByName.
     *
     * @see docs/features.md F6.9 — Improved energy card enrichment
     */
    public function testEnrichBasicEnergyFallsBackToSimplestPrintingByName(): void
    {
        $card = new DeckCard();
        $card->setCardName('Fire Energy');
        $card->setSetCode('SVI');
        $card->setCardNumber('99');
        $card->setCardType('energy');
        $card->setQuantity(3);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: 'sm1-11',
                name: 'Fire Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: 'https://assets.tcgdex.net/en/sm/sm1/11/high.webp',
                isExpandedLegal: true,
                rarity: 'Common',
                setReleaseDate: '2017-02-03',
            ),
        ]);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertSame('sm1-11', $card->getTcgdexId());
        self::assertSame('https://assets.tcgdex.net/en/sm/sm1/11/high.webp', $card->getImageUrl());
    }

    /**
     * Covers enrichBasicEnergy() with energy-set card that has a leading-zero number.
     * The number should be normalized (SVE 04 → SVE 4).
     */
    public function testEnrichBasicEnergyNormalizesLeadingZeros(): void
    {
        $card = new DeckCard();
        $card->setCardName('Lightning Energy');
        $card->setSetCode('SVE');
        $card->setCardNumber('04');
        $card->setCardType('energy');
        $card->setQuantity(2);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertStringContainsString('SVE_EN_4', (string) $card->getImageUrl());
    }

    /**
     * Covers the name fallback path: findCard returns null, findFirstPrintingByName succeeds.
     * Also covers the legality warning for name-only matching.
     */
    public function testEnrichVersionFallsBackToNameLookupWhenSetLookupFails(): void
    {
        $card = new DeckCard();
        $card->setCardName("Professor's Research");
        $card->setSetCode('UNKNOWN');
        $card->setCardNumber('1');
        $card->setCardType('trainer');
        $card->setQuantity(2);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: 'swsh4-178',
                name: "Professor's Research",
                category: 'Trainer',
                trainerType: 'Supporter',
                imageUrl: 'https://assets.tcgdex.net/en/swsh/swsh4/178/high.webp',
                isExpandedLegal: true,
            ),
        ]);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertSame(0, $report->notFoundCount);
        self::assertSame('swsh4-178', $card->getTcgdexId());
        self::assertSame('https://assets.tcgdex.net/en/swsh/swsh4/178/high.webp', $card->getImageUrl());
        self::assertSame('Supporter', $card->getTrainerSubtype());

        // Should have a legality warning about name-only match
        self::assertCount(1, $report->legalityWarnings);
        self::assertStringContainsString('matched by name only', $report->legalityWarnings[0]);
    }

    /**
     * Covers name fallback when findAllPrintingsByName returns printings with non-matching names.
     * The card should be counted as not found.
     */
    public function testEnrichVersionNameFallbackSkipsNonMatchingNames(): void
    {
        $card = new DeckCard();
        $card->setCardName('Crobat V');
        $card->setSetCode('UNKNOWN');
        $card->setCardNumber('1');
        $card->setCardType('pokemon');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: 'swsh3-104',
                name: 'Crobat VMAX',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: 'https://assets.tcgdex.net/en/swsh/swsh3/104/high.webp',
                isExpandedLegal: true,
            ),
        ]);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(0, $report->enrichedCount);
        self::assertSame(1, $report->notFoundCount);
    }

    /**
     * Covers resolveCardType: when card type is unknown and TCGdex provides a category,
     * the card type should be resolved from the category mapping.
     */
    public function testResolveCardTypeFromTcgdexCategoryWhenUnknown(): void
    {
        $card = new DeckCard();
        $card->setCardName('Pikachu');
        $card->setSetCode('BRS');
        $card->setCardNumber('50');
        $card->setCardType('unknown');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'swsh9-50',
                name: 'Pikachu',
                category: 'Pokémon',
                trainerType: null,
                imageUrl: 'https://assets.tcgdex.net/en/swsh/swsh9/50/high.webp',
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertSame('pokemon', $card->getCardType());
    }

    /**
     * Covers resolveCardType: when card type is already known, it should NOT be overridden.
     */
    public function testResolveCardTypeDoesNotOverrideKnownType(): void
    {
        $card = new DeckCard();
        $card->setCardName('Pikachu');
        $card->setSetCode('BRS');
        $card->setCardNumber('50');
        $card->setCardType('pokemon');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'swsh9-50',
                name: 'Pikachu',
                category: 'Trainer',
                trainerType: null,
                imageUrl: 'https://assets.tcgdex.net/en/swsh/swsh9/50/high.webp',
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        // Card type should remain 'pokemon', not overridden to 'trainer'
        self::assertSame('pokemon', $card->getCardType());
    }

    /**
     * Covers findSimplestBasicEnergyByName: prefers Common rarity over non-Common.
     */
    public function testFindSimplestBasicEnergyPrefersCommonRarity(): void
    {
        $card = new DeckCard();
        $card->setCardName('Grass Energy');
        $card->setSetCode('SME');
        $card->setCardNumber('99');
        $card->setCardType('energy');
        $card->setQuantity(4);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: 'swsh12-rare',
                name: 'Grass Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: 'https://example.com/rare.webp',
                isExpandedLegal: true,
                rarity: 'Secret Rare',
                setReleaseDate: '2023-01-01',
            ),
            new TcgdexCard(
                id: 'sm1-common',
                name: 'Grass Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: 'https://example.com/common.webp',
                isExpandedLegal: true,
                rarity: 'Common',
                setReleaseDate: '2017-02-03',
            ),
        ]);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertSame('sm1-common', $card->getTcgdexId());
        self::assertSame('https://example.com/common.webp', $card->getImageUrl());
    }

    /**
     * Covers findSimplestBasicEnergyByName: within same rarity class, prefers most recent.
     */
    public function testFindSimplestBasicEnergyPrefersMostRecentWithinSameRarity(): void
    {
        $card = new DeckCard();
        $card->setCardName('Metal Energy');
        $card->setSetCode('SME');
        $card->setCardNumber('99');
        $card->setCardType('energy');
        $card->setQuantity(2);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: 'sm1-old',
                name: 'Metal Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: 'https://example.com/old.webp',
                isExpandedLegal: true,
                rarity: 'Common',
                setReleaseDate: '2017-02-03',
            ),
            new TcgdexCard(
                id: 'sv1-new',
                name: 'Metal Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: 'https://example.com/new.webp',
                isExpandedLegal: true,
                rarity: 'Common',
                setReleaseDate: '2023-03-31',
            ),
        ]);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertSame('sv1-new', $card->getTcgdexId());
        self::assertSame('https://example.com/new.webp', $card->getImageUrl());
    }

    /**
     * Covers findSimplestBasicEnergyByName: skips printings with null imageUrl.
     */
    public function testFindSimplestBasicEnergySkipsPrintingsWithoutImage(): void
    {
        $card = new DeckCard();
        $card->setCardName('Psychic Energy');
        $card->setSetCode('SME');
        $card->setCardNumber('99');
        $card->setCardType('energy');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: 'sm1-no-image',
                name: 'Psychic Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: null,
                isExpandedLegal: true,
                rarity: 'Common',
            ),
            new TcgdexCard(
                id: 'sm1-with-image',
                name: 'Psychic Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: 'https://example.com/psychic.webp',
                isExpandedLegal: true,
                rarity: 'Common',
            ),
        ]);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertSame('sm1-with-image', $card->getTcgdexId());
        self::assertSame('https://example.com/psychic.webp', $card->getImageUrl());
    }

    /**
     * Covers the image override path (GEN 73 override).
     */
    public function testApplyImageOverrideReplacesKnownBuggyImage(): void
    {
        $card = new DeckCard();
        $card->setCardName('Some Card');
        $card->setSetCode('GEN');
        $card->setCardNumber('73');
        $card->setCardType('trainer');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'g1-73',
                name: 'Some Card',
                category: 'Trainer',
                trainerType: null,
                imageUrl: 'https://assets.tcgdex.net/en/xy/g1/73/high.webp',
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        // Image should be overridden to the correct one
        self::assertSame('https://assets.tcgdex.net/en/xy/xy1/129/high.webp', $card->getImageUrl());
    }

    /**
     * @param list<DeckCard> $cards
     */
    private function createVersionWithCards(array $cards): DeckVersion
    {
        $version = new DeckVersion();

        foreach ($cards as $card) {
            $version->addCard($card);
        }

        return $version;
    }
}

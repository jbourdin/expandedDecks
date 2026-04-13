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

use App\Entity\CardIdentity;
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
        $this->identityResolver->method('resolveFromTcgdexCard')->willReturnCallback(
            static function (TcgdexCard $tcgdexCard): CardPrinting {
                $identity = new CardIdentity();
                $identity->setName($tcgdexCard->name);
                $identity->setCategory(strtolower($tcgdexCard->category));
                $identity->setTrainerType($tcgdexCard->trainerType);

                $printing = new CardPrinting();
                $printing->setCardIdentity($identity);
                $printing->setTcgdexId($tcgdexCard->id);
                $printing->setImageUrl($tcgdexCard->imageUrl);
                $printing->setIsExpandedLegal($tcgdexCard->isExpandedLegal);
                $identity->addPrinting($printing);

                return $printing;
            },
        );
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

        self::assertNotNull($card->getCardPrinting());
        self::assertSame('swsh9-123', $card->getCardPrinting()->getTcgdexId());
        self::assertSame('https://assets.tcgdex.net/en/swsh/swsh9/123/high.webp', $card->getCardPrinting()->getImageUrl());
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

        self::assertNotNull($card->getCardPrinting());
        self::assertSame('Supporter', $card->getCardPrinting()->getCardIdentity()->getTrainerType());
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
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: 'sm1-lightning',
                name: 'Lightning Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: 'https://example.com/lightning.webp',
                isExpandedLegal: true,
                rarity: 'Common',
            ),
        ]);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertSame(0, $report->notFoundCount);
        self::assertNotNull($card->getCardPrinting());
        // Static energy-set image overrides the TCGdex image
        self::assertStringContainsString('SVE_EN_4', (string) $card->getCardPrinting()->getImageUrl());
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
        // Dragon Energy is not a known basic energy name and SVE|99 is not in the static map,
        // so no CardPrinting is assigned — image URL is null
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
        self::assertNotNull($card->getCardPrinting());
        // Falls back to PokemonTCG.io CDN URL built from tcgdex ID
        self::assertSame('https://images.pokemontcg.io/sm35/69_hires.png', $card->getCardPrinting()->getImageUrl());
        self::assertSame('sm35-69', $card->getCardPrinting()->getTcgdexId());
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

        self::assertNotNull($card->getCardPrinting());
        self::assertSame('https://assets.tcgdex.net/en/swsh/swsh9/123/high.webp', $card->getCardPrinting()->getImageUrl());
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
        self::assertNotNull($card->getCardPrinting());
        self::assertSame('sv1-264', $card->getCardPrinting()->getTcgdexId());
        self::assertSame('https://assets.tcgdex.net/en/sv/sv1/264/high.webp', $card->getCardPrinting()->getImageUrl());
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
        self::assertNotNull($card->getCardPrinting());
        self::assertSame('sm1-11', $card->getCardPrinting()->getTcgdexId());
        self::assertSame('https://assets.tcgdex.net/en/sm/sm1/11/high.webp', $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers enrichBasicEnergy() with energy-set card that has a leading-zero number.
     * The number should be normalized (SVE 04 -> SVE 4).
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
        $apiClient->method('findAllPrintingsByName')->willReturn([
            new TcgdexCard(
                id: 'sm1-lightning',
                name: 'Lightning Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: 'https://example.com/lightning.webp',
                isExpandedLegal: true,
                rarity: 'Common',
            ),
        ]);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertNotNull($card->getCardPrinting());
        self::assertStringContainsString('SVE_EN_4', (string) $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers the Asian alias fallback: findCard returns null, findCardByNameInAliasedSet succeeds.
     * Also covers the legality warning for alias-based matching.
     */
    public function testEnrichVersionFallsBackToAsianAliasWhenSetLookupFails(): void
    {
        $card = new DeckCard();
        $card->setCardName("Professor's Research");
        $card->setSetCode('S6K');
        $card->setCardNumber('70');
        $card->setCardType('trainer');
        $card->setQuantity(2);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(
            new TcgdexCard(
                id: 'swsh6-178',
                name: "Professor's Research",
                category: 'Trainer',
                trainerType: 'Supporter',
                imageUrl: 'https://assets.tcgdex.net/en/swsh/swsh6/178/high.webp',
                isExpandedLegal: true,
                setCode: 'CRE',
            ),
        );

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertSame(0, $report->notFoundCount);
        self::assertNotNull($card->getCardPrinting());
        self::assertSame('swsh6-178', $card->getCardPrinting()->getTcgdexId());
        self::assertSame('Supporter', $card->getCardPrinting()->getCardIdentity()->getTrainerType());

        // Should have a legality warning about Asian alias match
        self::assertCount(1, $report->legalityWarnings);
        self::assertStringContainsString('Asian set alias', $report->legalityWarnings[0]);
    }

    /**
     * When both findCard and findCardByNameInAliasedSet return null, the card is not found.
     */
    public function testEnrichVersionCountsNotFoundWhenBothStrategiesFail(): void
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
        $apiClient->method('findCardByNameInAliasedSet')->willReturn(null);

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
        self::assertNotNull($card->getCardPrinting());
        self::assertSame('sm1-common', $card->getCardPrinting()->getTcgdexId());
        self::assertSame('https://example.com/common.webp', $card->getCardPrinting()->getImageUrl());
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

        self::assertNotNull($card->getCardPrinting());
        self::assertSame('sv1-new', $card->getCardPrinting()->getTcgdexId());
        self::assertSame('https://example.com/new.webp', $card->getCardPrinting()->getImageUrl());
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

        self::assertNotNull($card->getCardPrinting());
        self::assertSame('sm1-with-image', $card->getCardPrinting()->getTcgdexId());
        self::assertSame('https://example.com/psychic.webp', $card->getCardPrinting()->getImageUrl());
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
        self::assertNotNull($card->getCardPrinting());
        self::assertSame('https://assets.tcgdex.net/en/xy/xy1/129/high.webp', $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers resolveImageUrl() fallback path: when TCGdex returns a card with null imageUrl
     * and the tcgdexId contains a dash, the printing gets a pokemontcg.io fallback URL.
     *
     * @see docs/features.md F6.2 — TCGdex card data enrichment
     */
    public function testResolveImageUrlTriesPokemontcgioFallbackWhenPrintingHasNoImage(): void
    {
        $card = new DeckCard();
        $card->setCardName('Rare Candy');
        $card->setSetCode('SM3.5');
        $card->setCardNumber('68');
        $card->setCardType('trainer');
        $card->setQuantity(4);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'sm35-68',
                name: 'Rare Candy',
                category: 'Trainer',
                trainerType: 'Item',
                imageUrl: null,
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertNotNull($card->getCardPrinting());
        // Falls back to PokemonTCG.io CDN URL
        self::assertSame('https://images.pokemontcg.io/sm35/68_hires.png', $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers resolveImageUrl() second fallback: when TCGdex returns null imageUrl and
     * the tcgdexId has no dash (pokemontcg.io URL cannot be built), falls back to findImageByName.
     *
     * @see docs/features.md F6.2 — TCGdex card data enrichment
     */
    public function testResolveImageUrlFallsBackToFindImageByNameWhenNoDash(): void
    {
        $card = new DeckCard();
        $card->setCardName('Unknown Card');
        $card->setSetCode('BRS');
        $card->setCardNumber('1');
        $card->setCardType('trainer');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'nodashid',
                name: 'Unknown Card',
                category: 'Trainer',
                trainerType: null,
                imageUrl: null,
                isExpandedLegal: true,
            ));
        $apiClient->method('findImageByName')
            ->willReturn('https://example.com/fallback-image.webp');

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertNotNull($card->getCardPrinting());
        self::assertSame('https://example.com/fallback-image.webp', $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers applyImageOverride(): verifies that a known IMAGE_OVERRIDES key
     * replaces the printing's imageUrl.
     *
     * @see docs/features.md F6.2 — TCGdex card data enrichment
     */
    public function testApplyImageOverrideUpdatesCardPrintingUrl(): void
    {
        $card = new DeckCard();
        $card->setCardName('Overridden Card');
        $card->setSetCode('gen');
        $card->setCardNumber('73');
        $card->setCardType('trainer');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'g1-73',
                name: 'Overridden Card',
                category: 'Trainer',
                trainerType: null,
                imageUrl: 'https://assets.tcgdex.net/en/xy/g1/73/high.webp',
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertNotNull($card->getCardPrinting());
        // GEN|73 override should replace the TCGdex image
        self::assertSame('https://assets.tcgdex.net/en/xy/xy1/129/high.webp', $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers enrichBasicEnergy() final fallback: when findSimplestBasicEnergyByName returns null,
     * a synthetic CardPrinting is created from BASIC_ENERGY_IMAGES.
     *
     * @see docs/features.md F6.9 — Improved energy card enrichment
     */
    public function testEnrichBasicEnergyCreatesSyntheticPrintingFromStaticMap(): void
    {
        $card = new DeckCard();
        $card->setCardName('Grass Energy');
        $card->setSetCode('SME');
        $card->setCardNumber('99');
        $card->setCardType('energy');
        $card->setQuantity(4);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        // findAllPrintingsByName returns empty — no TCGdex printings at all
        $apiClient->method('findAllPrintingsByName')->willReturn([]);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertNotNull($card->getCardPrinting());
        // Should get the MEE fallback image from BASIC_ENERGY_IMAGES
        self::assertSame(
            'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
            $card->getCardPrinting()->getImageUrl(),
        );
        self::assertSame('energy', $card->getCardPrinting()->getCardIdentity()->getCategory());
    }

    /**
     * Covers resolveImageUrl() sibling fallback: when a Pokemon card has no image and no
     * PokemonTCG.io fallback, a sibling printing from the same CardIdentity is used.
     *
     * @see docs/features.md F6.2 — TCGdex card data enrichment
     */
    public function testResolveImageUrlUsesSiblingPrintingImageForPokemon(): void
    {
        $card = new DeckCard();
        $card->setCardName('Psyduck');
        $card->setSetCode('MEP');
        $card->setCardNumber('7');
        $card->setCardType('pokemon');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        // Pre-create a CardIdentity with a sibling printing that has an image
        $identity = new CardIdentity();
        $identity->setName('Psyduck');
        $identity->setCategory('pokemon');
        $identity->setHp(70);
        $identity->setAbilitySignature('Damp');
        $identity->setAttackSignature('Collision');

        $siblingPrinting = new CardPrinting();
        $siblingPrinting->setCardIdentity($identity);
        $siblingPrinting->setTcgdexId('me02.5-039');
        $siblingPrinting->setImageUrl('https://assets.tcgdex.net/en/me/me02.5/039/high.webp');
        $siblingPrinting->setSetReleaseDate(new \DateTimeImmutable('2026-01-15'));
        $identity->addPrinting($siblingPrinting);

        // The enriched printing has no image (mimics mep-007 with no TCGdex image)
        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $identityResolver->method('resolveFromTcgdexCard')->willReturnCallback(
            static function () use ($identity): CardPrinting {
                $printing = new CardPrinting();
                $printing->setCardIdentity($identity);
                $printing->setTcgdexId('mep-007');
                $printing->setImageUrl(null);
                $identity->addPrinting($printing);

                return $printing;
            },
        );

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'mep-007',
                name: 'Psyduck',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: null,
                isExpandedLegal: false,
            ));
        $apiClient->expects(self::never())->method('findImageByName');

        $enricher = new CardEnricher($apiClient, $identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertNotNull($card->getCardPrinting());
        self::assertSame('https://assets.tcgdex.net/en/me/me02.5/039/high.webp', $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers resolveImageUrl(): Pokemon card with no image and no siblings gets no image —
     * findImageByName is NOT called for Pokemon (too many false positives across eras).
     *
     * @see docs/features.md F6.2 — TCGdex card data enrichment
     */
    public function testResolveImageUrlDoesNotCallFindImageByNameForPokemon(): void
    {
        $card = new DeckCard();
        $card->setCardName('Psyduck');
        $card->setSetCode('MEP');
        $card->setCardNumber('7');
        $card->setCardType('pokemon');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'nodashid',
                name: 'Psyduck',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: null,
                isExpandedLegal: false,
            ));
        // findImageByName must NOT be called for Pokemon cards
        $apiClient->expects(self::never())->method('findImageByName');

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertNotNull($card->getCardPrinting());
        self::assertNull($card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers resolveImageUrl(): Trainer card with no image and no siblings still
     * falls back to findImageByName (trainer names are unique across eras).
     *
     * @see docs/features.md F6.2 — TCGdex card data enrichment
     */
    public function testResolveImageUrlStillCallsFindImageByNameForTrainer(): void
    {
        $card = new DeckCard();
        $card->setCardName('Judge');
        $card->setSetCode('MEP');
        $card->setCardNumber('10');
        $card->setCardType('trainer');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'nodashid',
                name: 'Judge',
                category: 'Trainer',
                trainerType: 'Supporter',
                imageUrl: null,
                isExpandedLegal: true,
            ));
        $apiClient->method('findImageByName')
            ->willReturn('https://example.com/judge-fallback.webp');

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertNotNull($card->getCardPrinting());
        self::assertSame('https://example.com/judge-fallback.webp', $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers findSiblingPrintingImage(): when multiple siblings have images,
     * the most recently released one is preferred.
     *
     * @see docs/features.md F6.2 — TCGdex card data enrichment
     */
    public function testSiblingImageFallbackPrefersMostRecentPrinting(): void
    {
        $card = new DeckCard();
        $card->setCardName('Psyduck');
        $card->setSetCode('MEP');
        $card->setCardNumber('7');
        $card->setCardType('pokemon');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        $identity = new CardIdentity();
        $identity->setName('Psyduck');
        $identity->setCategory('pokemon');

        $olderSibling = new CardPrinting();
        $olderSibling->setCardIdentity($identity);
        $olderSibling->setTcgdexId('old-set-039');
        $olderSibling->setImageUrl('https://example.com/old-psyduck.webp');
        $olderSibling->setSetReleaseDate(new \DateTimeImmutable('2020-01-01'));
        $identity->addPrinting($olderSibling);

        $newerSibling = new CardPrinting();
        $newerSibling->setCardIdentity($identity);
        $newerSibling->setTcgdexId('me02.5-039');
        $newerSibling->setImageUrl('https://example.com/new-psyduck.webp');
        $newerSibling->setSetReleaseDate(new \DateTimeImmutable('2026-01-15'));
        $identity->addPrinting($newerSibling);

        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $identityResolver->method('resolveFromTcgdexCard')->willReturnCallback(
            static function () use ($identity): CardPrinting {
                $printing = new CardPrinting();
                $printing->setCardIdentity($identity);
                $printing->setTcgdexId('mep-007');
                $printing->setImageUrl(null);
                $identity->addPrinting($printing);

                return $printing;
            },
        );

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'mep-007',
                name: 'Psyduck',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: null,
                isExpandedLegal: false,
            ));
        $apiClient->expects(self::never())->method('findImageByName');

        $enricher = new CardEnricher($apiClient, $identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertNotNull($card->getCardPrinting());
        // Should pick the newer sibling's image
        self::assertSame('https://example.com/new-psyduck.webp', $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers findSiblingPrintingImage() expand path: no existing sibling has an image,
     * so expandPrintings() is called to discover printings from the tcgdex_card table,
     * and the newly created sibling's image is used.
     *
     * @see docs/features.md F6.2 — TCGdex card data enrichment
     */
    public function testSiblingImageFallbackExpandsPrintingsWhenNoExistingSiblingHasImage(): void
    {
        $card = new DeckCard();
        $card->setCardName('Psyduck');
        $card->setSetCode('MEP');
        $card->setCardNumber('7');
        $card->setCardType('pokemon');
        $card->setQuantity(1);

        $version = $this->createVersionWithCards([$card]);

        // Identity starts with no siblings — expandPrintings will add one
        $identity = new CardIdentity();
        $identity->setName('Psyduck');
        $identity->setCategory('pokemon');

        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $identityResolver->method('resolveFromTcgdexCard')->willReturnCallback(
            static function () use ($identity): CardPrinting {
                $printing = new CardPrinting();
                $printing->setCardIdentity($identity);
                $printing->setTcgdexId('mep-007');
                $printing->setImageUrl(null);
                $identity->addPrinting($printing);

                return $printing;
            },
        );

        // expandPrintings simulates discovering a sibling in the tcgdex_card table
        $identityResolver->method('expandPrintings')->willReturnCallback(
            static function () use ($identity): void {
                $sibling = new CardPrinting();
                $sibling->setCardIdentity($identity);
                $sibling->setTcgdexId('me02.5-039');
                $sibling->setImageUrl('https://assets.tcgdex.net/en/me/me02.5/039/high.webp');
                $sibling->setSetReleaseDate(new \DateTimeImmutable('2026-01-15'));
                $identity->addPrinting($sibling);
            },
        );

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'mep-007',
                name: 'Psyduck',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: null,
                isExpandedLegal: false,
            ));
        $apiClient->expects(self::never())->method('findImageByName');

        $enricher = new CardEnricher($apiClient, $identityResolver, $this->em);
        $enricher->enrichVersion($version);

        self::assertNotNull($card->getCardPrinting());
        self::assertSame('https://assets.tcgdex.net/en/me/me02.5/039/high.webp', $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers enrichBasicEnergy() with a French energy name: "Énergie Obscurité"
     * is not in TCGdex but should resolve to "Darkness Energy" via English fallback.
     *
     * @see docs/features.md F6.9 — Improved energy card enrichment
     */
    public function testEnrichBasicEnergyResolvesLocalizedFrenchName(): void
    {
        $card = new DeckCard();
        $card->setCardName('Énergie Obscurité');
        $card->setSetCode('MEE');
        $card->setCardNumber('7');
        $card->setCardType('energy');
        $card->setQuantity(8);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        // French name returns empty, English name returns a printing
        $apiClient->method('findAllPrintingsByName')->willReturnCallback(
            static function (string $name): array {
                if ('Darkness Energy' === $name) {
                    return [
                        new TcgdexCard(
                            id: 'sm1-darkness',
                            name: 'Darkness Energy',
                            category: 'Energy',
                            trainerType: null,
                            imageUrl: 'https://example.com/darkness.webp',
                            isExpandedLegal: true,
                            rarity: 'Common',
                        ),
                    ];
                }

                return [];
            },
        );

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertNotNull($card->getCardPrinting());
        // Image should come from the static MEE|7 energy set image
        self::assertStringContainsString('MEE_EN_7', (string) $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers enrichBasicEnergy() synthetic fallback: when TCGdex has no printing
     * for the English energy name either, a synthetic printing is created with
     * the English name (not the localized name) for a consistent CardIdentity.
     *
     * @see docs/features.md F6.9 — Improved energy card enrichment
     */
    public function testEnrichBasicEnergySyntheticFallbackUsesEnglishName(): void
    {
        $card = new DeckCard();
        $card->setCardName('Énergie Obscurité');
        $card->setSetCode('MEE');
        $card->setCardNumber('7');
        $card->setCardType('energy');
        $card->setQuantity(4);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        // All name lookups return empty — force synthetic fallback
        $apiClient->method('findAllPrintingsByName')->willReturn([]);

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertNotNull($card->getCardPrinting());
        // CardIdentity should use English name, not French
        self::assertSame('Darkness Energy', $card->getCardPrinting()->getCardIdentity()->getName());
        self::assertStringContainsString('MEE_EN_7', (string) $card->getCardPrinting()->getImageUrl());
    }

    /**
     * Covers resolveDisplayName(): after enrichment, the card name should be updated
     * to the canonical name from CardIdentity (not the player's raw input).
     *
     * @see docs/features.md F6.2 — TCGdex card data enrichment
     */
    public function testEnrichVersionUpdatesCardNameToCanonicalName(): void
    {
        $card = new DeckCard();
        $card->setCardName('Ordres du Boss');
        $card->setSetCode('MEG');
        $card->setCardNumber('114');
        $card->setCardType('trainer');
        $card->setQuantity(2);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'me01-114',
                name: "Boss's Orders",
                category: 'Trainer',
                trainerType: 'Supporter',
                imageUrl: 'https://assets.tcgdex.net/en/me/me01/114/high.webp',
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->identityResolver, $this->em);
        $enricher->enrichVersion($version);

        // Card name should be updated to the canonical English name from TCGdex
        self::assertSame("Boss's Orders", $card->getCardName());
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

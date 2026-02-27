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

use App\Entity\DeckCard;
use App\Entity\DeckVersion;
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

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('flush');
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

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->with('BRS', '123')
            ->willReturn(new TcgdexCard(
                id: 'swsh9-123',
                name: 'Arceus VSTAR',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: 'https://assets.tcgdex.net/en/swsh/swsh9/123/high.webp',
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->em);
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

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'swsh9-132',
                name: "Boss's Orders",
                category: 'Trainer',
                trainerType: 'Supporter',
                imageUrl: 'https://assets.tcgdex.net/en/swsh/swsh9/132/high.webp',
                isExpandedLegal: true,
            ));

        $enricher = new CardEnricher($apiClient, $this->em);
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

        $enricher = new CardEnricher($apiClient, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertSame(0, $report->notFoundCount);
        self::assertStringContainsString('tcgdex.net', (string) $card->getImageUrl());
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

        $apiClient = $this->createMock(TcgdexApiClient::class);

        $enricher = new CardEnricher($apiClient, $this->em);
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

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);

        $enricher = new CardEnricher($apiClient, $this->em);
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

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'swsh9-1',
                name: 'Illegal Card',
                category: 'Pokemon',
                trainerType: null,
                imageUrl: null,
                isExpandedLegal: false,
            ));

        $enricher = new CardEnricher($apiClient, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertCount(1, $report->legalityWarnings);
        self::assertStringContainsString('Illegal Card', $report->legalityWarnings[0]);
        self::assertStringContainsString('not marked as Expanded-legal', $report->legalityWarnings[0]);
    }

    public function testEnrichVersionFallsBackToFindImageByNameWhenImageUrlIsNull(): void
    {
        $card = new DeckCard();
        $card->setCardName('Double Colorless Energy');
        $card->setSetCode('SM3.5');
        $card->setCardNumber('69');
        $card->setCardType('energy');
        $card->setQuantity(4);

        $version = $this->createVersionWithCards([$card]);

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willReturn(new TcgdexCard(
                id: 'sm35-69',
                name: 'Double Colorless Energy',
                category: 'Energy',
                trainerType: null,
                imageUrl: null,
                isExpandedLegal: true,
            ));
        $apiClient->expects(self::once())
            ->method('findImageByName')
            ->with('Double Colorless Energy')
            ->willReturn('https://assets.tcgdex.net/en/xy/xy1/130/high.webp');

        $enricher = new CardEnricher($apiClient, $this->em);
        $report = $enricher->enrichVersion($version);

        self::assertSame(1, $report->enrichedCount);
        self::assertSame('https://assets.tcgdex.net/en/xy/xy1/130/high.webp', $card->getImageUrl());
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

        $enricher = new CardEnricher($apiClient, $this->em);
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

        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->method('findCard')
            ->willThrowException(new \RuntimeException('API error'));

        $enricher = new CardEnricher($apiClient, $this->em);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('API error');

        $enricher->enrichVersion($version);

        // After exception, status should be 'failed'
        self::assertSame('failed', $version->getEnrichmentStatus());
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

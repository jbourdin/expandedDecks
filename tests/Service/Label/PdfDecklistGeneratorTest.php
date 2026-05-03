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

namespace App\Tests\Service\Label;

use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Entity\TcgdexCard;
use App\Entity\TcgdexSet;
use App\Entity\User;
use App\Enum\DeckFormat;
use App\Repository\TcgdexCardRepository;
use App\Service\Label\PdfDecklistGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Drives the generator end-to-end with a stubbed Twig (captures the render
 * template data) and a real Dompdf invocation so the renderPdf step is also
 * exercised. Assertions inspect the captured template data for grouping,
 * sorting, font-size + trigram + label translation behaviour.
 *
 * @see docs/features.md F5.13 — Printable A4 decklist PDF
 */
#[AllowMockObjectsWithoutExpectations]
class PdfDecklistGeneratorTest extends TestCase
{
    /** @var array<string, mixed>|null captured by the most recent Twig::render call */
    private ?array $capturedTemplateData = null;

    public function testGenerateAnonymousReturnsValidPdfBytes(): void
    {
        $deck = $this->buildDeck([$this->buildCard('pokemon', 'Pikachu', 4, 'LOT', '79')]);

        $output = $this->buildGenerator()->generateAnonymous($deck);

        self::assertStringStartsWith('%PDF-', $output);
        self::assertGreaterThan(500, \strlen($output));

        // The mode flag tells the template to render anonymous fields.
        self::assertSame('anonymous', $this->capturedTemplateData['mode']);
        self::assertNull($this->capturedTemplateData['playerName']);
        self::assertNull($this->capturedTemplateData['trigram']);
    }

    public function testGeneratePersonalIncludesPlayerInfoTrigramAndLabels(): void
    {
        $deck = $this->buildDeck([$this->buildCard('pokemon', 'Pikachu', 4, 'LOT', '79')]);

        $user = $this->createStub(User::class);
        $user->method('getFirstName')->willReturn('Ash');
        $user->method('getLastName')->willReturn('Ketchum');
        $user->method('getEmail')->willReturn('ash@example.com');
        $user->method('getPlayerId')->willReturn('1234567');
        $user->method('getYearOfBirth')->willReturn(1996);

        // Gravatar fetch should succeed.
        $http = new MockHttpClient([new MockResponse('jpeg-bytes', ['response_headers' => ['content-type' => 'image/jpeg']])]);

        $output = $this->buildGenerator(httpClient: $http)->generatePersonal($deck, $user);

        self::assertStringStartsWith('%PDF-', $output);
        self::assertSame('personal', $this->capturedTemplateData['mode']);
        self::assertSame('Ash Ketchum', $this->capturedTemplateData['playerName']);
        self::assertSame('1234567', $this->capturedTemplateData['playerId']);
        self::assertSame(1996, $this->capturedTemplateData['yearOfBirth']);
        // Trigram = first letter of firstName + first letter of lastName + last letter of lastName
        self::assertSame('AKM', $this->capturedTemplateData['trigram']);
        // Gravatar data URI was embedded.
        self::assertNotNull($this->capturedTemplateData['gravatarDataUri']);
        self::assertStringStartsWith('data:image/', (string) $this->capturedTemplateData['gravatarDataUri']);
    }

    public function testGeneratePersonalGravatarFailureKeepsNull(): void
    {
        $deck = $this->buildDeck([$this->buildCard('pokemon', 'Pikachu', 1, 'LOT', '79')]);

        $user = $this->createStub(User::class);
        $user->method('getFirstName')->willReturn('Ash');
        $user->method('getLastName')->willReturn('Ketchum');
        $user->method('getEmail')->willReturn('ash@example.com');

        // Gravatar 404.
        $http = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);

        $this->buildGenerator(httpClient: $http)->generatePersonal($deck, $user);

        self::assertNull($this->capturedTemplateData['gravatarDataUri']);
    }

    public function testGenerateHandlesDeckWithoutCurrentVersion(): void
    {
        $deck = $this->createStub(Deck::class);
        $deck->method('getCurrentVersion')->willReturn(null);
        $deck->method('getFormat')->willReturn(DeckFormat::Expanded);
        $deck->method('getLanguages')->willReturn([]);

        $this->buildGenerator()->generateAnonymous($deck);

        self::assertSame([], $this->capturedTemplateData['pokemonRows']);
        self::assertSame(0, $this->capturedTemplateData['pokemonCount']);
        self::assertSame(0, $this->capturedTemplateData['trainerCount']);
        self::assertSame(0, $this->capturedTemplateData['energyCount']);
    }

    public function testCardsAreGroupedAndSortedByQuantityThenName(): void
    {
        $deck = $this->buildDeck([
            $this->buildCard('pokemon', 'Charizard', 2, 'LOT', '20'),
            $this->buildCard('pokemon', 'Bulbasaur', 4, 'LOT', '10'),
            $this->buildCard('pokemon', 'Aerodactyl', 4, 'LOT', '5'),
            $this->buildCard('trainer', 'Boss', 2, 'LOT', '50', trainerSubtype: 'supporter'),
            $this->buildCard('trainer', 'Switch', 4, 'LOT', '60', trainerSubtype: 'item'),
            $this->buildCard('energy', 'Lightning', 6, 'LOT', '70'),
        ]);

        $this->buildGenerator()->generateAnonymous($deck);

        // Pokemon: qty desc (4, 4, 2), then name asc → Aerodactyl, Bulbasaur, Charizard
        $pokemonNames = array_column($this->capturedTemplateData['pokemonRows'], 'name');
        self::assertSame(['Aerodactyl', 'Bulbasaur', 'Charizard'], $pokemonNames);

        // Trainer sections come back ordered: supporter, item, ... (per controller's fixed order)
        $trainerSubtypes = array_column($this->capturedTemplateData['trainerSections'], 'subtype');
        self::assertSame(['supporter', 'item'], $trainerSubtypes);

        self::assertSame(10, $this->capturedTemplateData['pokemonCount']);
        self::assertSame(6, $this->capturedTemplateData['trainerCount']);
        self::assertSame(6, $this->capturedTemplateData['energyCount']);
    }

    public function testTrainerCardWithoutSubtypeFallsBackToTrainerBucket(): void
    {
        $deck = $this->buildDeck([
            $this->buildCard('trainer', 'Mystery Trainer', 1, 'LOT', '80', trainerSubtype: null),
        ]);

        $this->buildGenerator()->generateAnonymous($deck);

        $trainerSubtypes = array_column($this->capturedTemplateData['trainerSections'], 'subtype');
        self::assertContains('trainer', $trainerSubtypes);
    }

    public function testFontSizeShrinksForLargeCardLists(): void
    {
        $cards = [];
        // 80 distinct cards comfortably forces the auto-fit logic below the
        // 9pt ceiling on an A4 page.
        for ($i = 1; $i <= 80; ++$i) {
            $cards[] = $this->buildCard('pokemon', 'Card '.$i, 1, 'LOT', (string) $i);
        }
        $deck = $this->buildDeck($cards);

        $this->buildGenerator()->generateAnonymous($deck);

        $fontSize = $this->capturedTemplateData['fontSize'];
        self::assertGreaterThanOrEqual(6.0, $fontSize);
        self::assertLessThan(9.0, $fontSize);
    }

    public function testFontSizeStaysWithinBoundsForSmallDeck(): void
    {
        $deck = $this->buildDeck([$this->buildCard('pokemon', 'Single', 1, 'LOT', '1')]);

        $this->buildGenerator()->generateAnonymous($deck);

        $fontSize = $this->capturedTemplateData['fontSize'];
        // Small decks clamp to the 9.0 ceiling per computeFontSize.
        self::assertSame(9.0, $fontSize);
    }

    public function testLocalizedCardNameIsPreferredWhenLocaleMatchesAndTcgdexCardKnown(): void
    {
        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getName')->willReturn(['en' => 'Pikachu', 'fr' => 'Pikachu (FR)']);
        $tcgdexCard->method('getNameEn')->willReturn('Pikachu');

        $card = $this->buildCard(
            'pokemon', 'Pikachu', 1, 'LOT', '79',
            cardPrinting: $this->buildCardPrintingWithTcgdex($tcgdexCard),
        );

        $deck = $this->buildDeck([$card], languages: ['fr']);

        $this->buildGenerator()->generateAnonymous($deck);

        $row = $this->capturedTemplateData['pokemonRows'][0];
        self::assertSame('Pikachu (FR)', $row['name']);
    }

    public function testEnglishNameSubtitleAddedWhenDisplayNameDiffers(): void
    {
        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getName')->willReturn(['en' => 'Pikachu', 'fr' => 'Pikachu (FR)']);
        $tcgdexCard->method('getNameEn')->willReturn('Pikachu');

        $card = $this->buildCard(
            'pokemon', 'Pikachu', 1, 'LOT', '79',
            cardPrinting: $this->buildCardPrintingWithTcgdex($tcgdexCard),
        );

        $deck = $this->buildDeck([$card], languages: ['fr']);

        $this->buildGenerator()->generateAnonymous($deck);

        self::assertSame('Pikachu', $this->capturedTemplateData['pokemonRows'][0]['englishName']);
    }

    public function testSetSymbolFetchEmbedsBase64DataUri(): void
    {
        $set = $this->createStub(TcgdexSet::class);
        $set->method('getSymbolUrl')->willReturn('https://assets.tcgdex.net/symbols/lot');
        $set->method('getPtcgCode')->willReturn('LOT');

        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getName')->willReturn(['en' => 'Pikachu']);
        $tcgdexCard->method('getNameEn')->willReturn('Pikachu');
        $tcgdexCard->method('getSet')->willReturn($set);

        $card = $this->buildCard(
            'pokemon', 'Pikachu', 1, 'LOT', '79',
            cardPrinting: $this->buildCardPrintingWithTcgdex($tcgdexCard),
        );

        $deck = $this->buildDeck([$card]);

        // The fetch should append .png to the symbol URL.
        $http = new MockHttpClient([new MockResponse('png-bytes', ['response_headers' => ['content-type' => 'image/png']])]);

        $this->buildGenerator(httpClient: $http)->generateAnonymous($deck);

        $row = $this->capturedTemplateData['pokemonRows'][0];
        self::assertNotNull($row['symbolDataUri']);
        self::assertStringStartsWith('data:image/png;base64,', (string) $row['symbolDataUri']);
        self::assertSame('LOT', $row['setCode']);
    }

    public function testSetSymbolFetchSwallowsHttpExceptionGracefully(): void
    {
        $set = $this->createStub(TcgdexSet::class);
        $set->method('getSymbolUrl')->willReturn('https://assets.tcgdex.net/symbols/lot');

        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getName')->willReturn(['en' => 'Pikachu']);
        $tcgdexCard->method('getNameEn')->willReturn('Pikachu');
        $tcgdexCard->method('getSet')->willReturn($set);

        $card = $this->buildCard(
            'pokemon', 'Pikachu', 1, 'LOT', '79',
            cardPrinting: $this->buildCardPrintingWithTcgdex($tcgdexCard),
        );

        $deck = $this->buildDeck([$card]);

        $http = new MockHttpClient(static function (): MockResponse {
            throw new \RuntimeException('network down');
        });

        $this->buildGenerator(httpClient: $http)->generateAnonymous($deck);

        // Symbol unfetched → text-only fallback (null URI).
        self::assertNull($this->capturedTemplateData['pokemonRows'][0]['symbolDataUri']);
    }

    public function testTcgdexCardLookupViaRepositoryFallback(): void
    {
        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getName')->willReturn(['en' => 'Pikachu']);
        $tcgdexCard->method('getNameEn')->willReturn('Pikachu');

        $repository = $this->createMock(TcgdexCardRepository::class);
        $repository->expects(self::once())->method('find')->with('lot-79')->willReturn($tcgdexCard);

        $printing = $this->createStub(\App\Entity\CardPrinting::class);
        $printing->method('getTcgdexCard')->willReturn(null);
        $printing->method('getTcgdexId')->willReturn('lot-79');

        $card = $this->buildCard('pokemon', 'Pikachu', 1, 'LOT', '79', cardPrinting: $printing);
        $deck = $this->buildDeck([$card]);

        $this->buildGenerator(repository: $repository)->generateAnonymous($deck);

        // Lookup hit was verified via `expects(self::once())`.
        self::assertNotNull($this->capturedTemplateData['pokemonRows'][0]);
    }

    public function testTcgdexCardLookupCachesResultsAcrossCalls(): void
    {
        // Two cards sharing the same tcgdexId — repository should be called once.
        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getName')->willReturn(['en' => 'Pikachu']);
        $tcgdexCard->method('getNameEn')->willReturn('Pikachu');

        $repository = $this->createMock(TcgdexCardRepository::class);
        $repository->expects(self::once())->method('find')->willReturn($tcgdexCard);

        $printing = $this->createStub(\App\Entity\CardPrinting::class);
        $printing->method('getTcgdexCard')->willReturn(null);
        $printing->method('getTcgdexId')->willReturn('lot-79');

        $cards = [
            $this->buildCard('pokemon', 'Pikachu', 1, 'LOT', '79', cardPrinting: $printing),
            $this->buildCard('pokemon', 'Pikachu B', 1, 'LOT', '80', cardPrinting: $printing),
        ];

        $this->buildGenerator(repository: $repository)->generateAnonymous($this->buildDeck($cards));
    }

    public function testLocaleResolutionFallsBackToEnglishOnMultiLanguage(): void
    {
        $tcgdexCard = $this->createStub(TcgdexCard::class);
        $tcgdexCard->method('getName')->willReturn(['en' => 'Pikachu', 'fr' => 'Pikachu (FR)']);
        $tcgdexCard->method('getNameEn')->willReturn('Pikachu');

        $card = $this->buildCard(
            'pokemon', 'Pikachu', 1, 'LOT', '79',
            cardPrinting: $this->buildCardPrintingWithTcgdex($tcgdexCard),
        );

        // Two languages → falls back to English (display name unchanged).
        $deck = $this->buildDeck([$card], languages: ['en', 'fr']);

        $this->buildGenerator()->generateAnonymous($deck);

        self::assertSame('Pikachu', $this->capturedTemplateData['pokemonRows'][0]['name']);
    }

    private function buildGenerator(
        ?MockHttpClient $httpClient = null,
        ?TcgdexCardRepository $repository = null,
    ): PdfDecklistGenerator {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context = []): string {
            $this->capturedTemplateData = $context;

            return '<!DOCTYPE html><html><body><p>tiny pdf body</p></body></html>';
        });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);

        return new PdfDecklistGenerator(
            $twig,
            $httpClient ?? new MockHttpClient([]),
            $translator,
            $repository ?? $this->createStub(TcgdexCardRepository::class),
        );
    }

    /**
     * @param list<DeckCard> $cards
     * @param list<string>   $languages
     */
    private function buildDeck(array $cards, array $languages = ['en']): Deck
    {
        $version = $this->createStub(DeckVersion::class);
        $version->method('getCards')->willReturn(new ArrayCollection($cards));

        $deck = $this->createStub(Deck::class);
        $deck->method('getCurrentVersion')->willReturn($version);
        $deck->method('getFormat')->willReturn(DeckFormat::Expanded);
        $deck->method('getLanguages')->willReturn($languages);

        return $deck;
    }

    private function buildCard(
        string $cardType,
        string $name,
        int $quantity,
        string $setCode,
        string $cardNumber,
        ?string $trainerSubtype = null,
        ?\App\Entity\CardPrinting $cardPrinting = null,
    ): DeckCard {
        // Stub method overrides aren't allowed — pre-bind every getter the
        // generator exercises so individual tests can pick the right values
        // through the helper signature instead of mutating the stub later.
        $card = $this->createStub(DeckCard::class);
        $card->method('getCardType')->willReturn($cardType);
        $card->method('getCardName')->willReturn($name);
        $card->method('getQuantity')->willReturn($quantity);
        $card->method('getSetCode')->willReturn($setCode);
        $card->method('getCardNumber')->willReturn($cardNumber);
        $card->method('getTrainerSubtype')->willReturn($trainerSubtype);
        $card->method('getCardPrinting')->willReturn($cardPrinting);

        return $card;
    }

    private function buildCardPrintingWithTcgdex(TcgdexCard $tcgdexCard): \App\Entity\CardPrinting
    {
        $printing = $this->createStub(\App\Entity\CardPrinting::class);
        $printing->method('getTcgdexCard')->willReturn($tcgdexCard);
        $printing->method('getTcgdexId')->willReturn('lot-79');

        return $printing;
    }
}

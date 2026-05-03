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
use App\Repository\PokemonSpriteMappingRepository;
use App\Service\Label\PdfLabelGenerator;
use App\Service\Sprite\SpriteResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Drives PdfLabelGenerator end-to-end with a stubbed Twig (captures render
 * context) and a real Dompdf invocation. Covers both the simple label and
 * the foldable card-list variant, plus card grouping and adaptive font size.
 *
 * @see docs/features.md F5.7 — PDF label card (home printing)
 */
#[AllowMockObjectsWithoutExpectations]
class PdfLabelGeneratorTest extends TestCase
{
    /** @var array<string, mixed>|null captured template context for the most recent render call */
    private ?array $capturedTemplateData = null;

    /** @var string|null captured template name */
    private ?string $capturedTemplateName = null;

    public function testGenerateReturnsValidPdfWithQrCodeAndDeckUrl(): void
    {
        $deck = $this->buildDeck(shortTag: 'A1B2C3', cards: []);

        $output = $this->buildGenerator()->generate($deck);

        self::assertStringStartsWith('%PDF-', $output);
        self::assertSame('label/pdf_label.html.twig', $this->capturedTemplateName);

        // QR code is encoded inline as a PNG data URI.
        self::assertNotNull($this->capturedTemplateData['qrCodeDataUri']);
        self::assertStringStartsWith('data:image/png;base64,', (string) $this->capturedTemplateData['qrCodeDataUri']);

        // Base URL is derived from the absolute deck URL (scheme + host only).
        self::assertSame('https://example.test', $this->capturedTemplateData['baseUrl']);
    }

    public function testGenerateEmbedsResolvedSpritesAsDataUris(): void
    {
        $deck = $this->buildDeck(shortTag: 'A1B2C3', cards: [], pokemonSlugs: ['pikachu', 'charizard']);

        $resolver = $this->createStub(SpriteResolver::class);
        $resolver->method('resolveAsDataUri')->willReturnCallback(static fn (string $slug): ?string => 'data:image/png;base64,FAKE-'.$slug);

        $this->buildGenerator(spriteResolver: $resolver)->generate($deck);

        $sprites = $this->capturedTemplateData['spriteDataUris'];
        self::assertCount(2, $sprites);
        self::assertSame('data:image/png;base64,FAKE-pikachu', $sprites[0]['dataUri']);
        // Slug is title-cased for display.
        self::assertSame('Pikachu', $sprites[0]['name']);
        self::assertSame('Charizard', $sprites[1]['name']);
    }

    public function testGenerateSkipsSpritesWhenResolverReturnsNull(): void
    {
        $deck = $this->buildDeck(shortTag: 'A1B2C3', cards: [], pokemonSlugs: ['phantom-pokemon']);

        $resolver = $this->createStub(SpriteResolver::class);
        $resolver->method('resolveAsDataUri')->willReturn(null);

        $this->buildGenerator(spriteResolver: $resolver)->generate($deck);

        self::assertSame([], $this->capturedTemplateData['spriteDataUris']);
    }

    public function testSlugWithDashesGetsTitleCased(): void
    {
        $deck = $this->buildDeck(shortTag: 'A1B2C3', cards: [], pokemonSlugs: ['mr-mime']);

        $resolver = $this->createStub(SpriteResolver::class);
        $resolver->method('resolveAsDataUri')->willReturn('data:image/png;base64,FAKE');

        $this->buildGenerator(spriteResolver: $resolver)->generate($deck);

        self::assertSame('Mr Mime', $this->capturedTemplateData['spriteDataUris'][0]['name']);
    }

    public function testGenerateFoldableReturnsValidPdfAndUsesFoldableTemplate(): void
    {
        $deck = $this->buildDeck(shortTag: 'A1B2C3', cards: [
            $this->buildCard('pokemon', 'Pikachu', 4),
        ]);

        $output = $this->buildGenerator()->generateFoldable($deck);

        self::assertStringStartsWith('%PDF-', $output);
        self::assertSame('label/pdf_label_foldable.html.twig', $this->capturedTemplateName);

        // The template gets a grouped card map.
        self::assertArrayHasKey('groupedCards', $this->capturedTemplateData);
        self::assertArrayHasKey('decklistFontSize', $this->capturedTemplateData);
    }

    public function testGenerateFoldableHandlesDeckWithoutCurrentVersion(): void
    {
        $deck = $this->buildDeck(shortTag: 'A1B2C3', cards: [], hasVersion: false);

        $this->buildGenerator()->generateFoldable($deck);

        self::assertSame([], $this->capturedTemplateData['groupedCards']);
        self::assertSame(6.0, $this->capturedTemplateData['decklistFontSize']);
    }

    public function testGroupedCardsSplitTrainersBySubtypeAndSortByQuantityThenName(): void
    {
        $deck = $this->buildDeck(shortTag: 'A1B2C3', cards: [
            $this->buildCard('pokemon', 'Charizard', 2),
            $this->buildCard('pokemon', 'Bulbasaur', 4),
            $this->buildCard('trainer', 'Boss', 2, trainerSubtype: 'supporter'),
            $this->buildCard('trainer', 'Switch', 4, trainerSubtype: 'item'),
            $this->buildCard('trainer', 'Mystery', 1, trainerSubtype: null),
            $this->buildCard('energy', 'Lightning', 6),
        ]);

        $this->buildGenerator()->generateFoldable($deck);

        $grouped = $this->capturedTemplateData['groupedCards'];

        // Section keys are emitted in the controller's fixed order.
        self::assertSame(['pokemon', 'supporter', 'item', 'trainer', 'energy'], array_keys($grouped));

        // Pokemon sorted by quantity desc, then name asc.
        $names = array_map(static fn (DeckCard $c): string => $c->getCardName(), $grouped['pokemon']);
        self::assertSame(['Bulbasaur', 'Charizard'], $names);

        // Trainer with null subtype falls into the generic "trainer" bucket.
        self::assertCount(1, $grouped['trainer']);
        self::assertSame('Mystery', $grouped['trainer'][0]->getCardName());
    }

    public function testFoldableFontSizeShrinksWhenManyCards(): void
    {
        $cards = [];
        for ($i = 1; $i <= 60; ++$i) {
            $cards[] = $this->buildCard('pokemon', 'Card '.$i, 1);
        }

        $this->buildGenerator()->generateFoldable($this->buildDeck(shortTag: 'A1B2C3', cards: $cards));

        $fontSize = $this->capturedTemplateData['decklistFontSize'];
        self::assertGreaterThanOrEqual(4.0, $fontSize);
        self::assertLessThan(7.0, $fontSize);
    }

    public function testFoldableFontSizeStaysAtCeilingForSmallDeck(): void
    {
        $deck = $this->buildDeck(shortTag: 'A1B2C3', cards: [
            $this->buildCard('pokemon', 'Single', 1),
        ]);

        $this->buildGenerator()->generateFoldable($deck);

        // Tiny deck clamps to the 7pt ceiling.
        self::assertSame(7.0, $this->capturedTemplateData['decklistFontSize']);
    }

    private function buildGenerator(?SpriteResolver $spriteResolver = null): PdfLabelGenerator
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context = []): string {
            $this->capturedTemplateName = $template;
            $this->capturedTemplateData = $context;

            return '<!DOCTYPE html><html><body><p>tiny pdf body</p></body></html>';
        });

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.test/d/A1B2C3');

        if (null === $spriteResolver) {
            $spriteResolver = new SpriteResolver(
                $this->createStub(PokemonSpriteMappingRepository::class),
                new MockHttpClient([]),
                sys_get_temp_dir(),
            );
        }

        return new PdfLabelGenerator($twig, $urlGenerator, $spriteResolver);
    }

    /**
     * @param list<DeckCard> $cards
     * @param list<string>   $pokemonSlugs
     */
    private function buildDeck(string $shortTag, array $cards, array $pokemonSlugs = [], bool $hasVersion = true): Deck
    {
        $deck = $this->createStub(Deck::class);
        $deck->method('getShortTag')->willReturn($shortTag);
        $deck->method('getEffectivePokemonSlugs')->willReturn($pokemonSlugs);

        if ($hasVersion) {
            $version = $this->createStub(DeckVersion::class);
            $version->method('getCards')->willReturn(new ArrayCollection($cards));
            $deck->method('getCurrentVersion')->willReturn($version);
        } else {
            $deck->method('getCurrentVersion')->willReturn(null);
        }

        return $deck;
    }

    private function buildCard(string $cardType, string $name, int $quantity, ?string $trainerSubtype = null): DeckCard
    {
        $card = $this->createStub(DeckCard::class);
        $card->method('getCardType')->willReturn($cardType);
        $card->method('getCardName')->willReturn($name);
        $card->method('getQuantity')->willReturn($quantity);
        $card->method('getTrainerSubtype')->willReturn($trainerSubtype);

        return $card;
    }
}

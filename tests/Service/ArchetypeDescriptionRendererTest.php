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

use App\Entity\Archetype;
use App\Entity\DeckCard;
use App\Repository\ArchetypeRepository;
use App\Repository\DeckCardRepository;
use App\Service\ArchetypeDescriptionRenderer;
use App\Service\MarkdownRenderer;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Service\Tcgdex\TcgdexCard;
use App\Twig\Runtime\ArchetypeSpriteRuntime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see docs/features.md F2.10 — Archetype detail page
 */
class ArchetypeDescriptionRendererTest extends TestCase
{
    private ArchetypeDescriptionRenderer $renderer;
    private ArchetypeRepository&MockObject $archetypeRepository;
    private DeckCardRepository&MockObject $deckCardRepository;
    private TcgdexApiClient&MockObject $tcgdexApiClient;
    private UrlGeneratorInterface&MockObject $urlGenerator;

    protected function setUp(): void
    {
        $this->archetypeRepository = $this->createMock(ArchetypeRepository::class);
        $this->deckCardRepository = $this->createMock(DeckCardRepository::class);
        $this->tcgdexApiClient = $this->createMock(TcgdexApiClient::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->renderer = new ArchetypeDescriptionRenderer(
            new MarkdownRenderer(),
            $this->archetypeRepository,
            $this->deckCardRepository,
            $this->tcgdexApiClient,
            new ArchetypeSpriteRuntime(),
            $this->urlGenerator,
            new ArrayAdapter(),
        );
    }

    public function testRendersPlainMarkdown(): void
    {
        $result = $this->renderer->render('**Bold** and *italic*');

        self::assertStringContainsString('<strong>Bold</strong>', $result);
        self::assertStringContainsString('<em>italic</em>', $result);
    }

    public function testExpandsPublishedArchetypeTag(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Kyurem');
        $archetype->setPokemonSlugs(['kyurem']);
        $archetype->setIsPublished(true);

        $this->archetypeRepository->method('findOneBy')
            ->with(['slug' => 'kyurem'])
            ->willReturn($archetype);

        $this->urlGenerator->method('generate')
            ->with('app_archetype_show', ['slug' => 'kyurem'])
            ->willReturn('/archetypes/kyurem');

        $result = $this->renderer->render('Use [[archetype:kyurem]] to sweep.');

        self::assertStringContainsString('<a href="/archetypes/kyurem">', $result);
        self::assertStringContainsString('Kyurem', $result);
        self::assertStringContainsString('archetype-sprites', $result);
        self::assertStringNotContainsString('[[archetype:', $result);
    }

    public function testUnpublishedArchetypeRendersAsPlainText(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Lugia Archeops');
        $archetype->setPokemonSlugs(['lugia', 'archeops']);
        $archetype->setIsPublished(false);

        $this->archetypeRepository->method('findOneBy')
            ->with(['slug' => 'lugia-archeops'])
            ->willReturn($archetype);

        $result = $this->renderer->render('See [[archetype:lugia-archeops]].');

        self::assertStringNotContainsString('<a href=', $result);
        self::assertStringContainsString('Lugia Archeops', $result);
        self::assertStringContainsString('archetype-sprites', $result);
    }

    public function testUnknownArchetypeRemainsEscaped(): void
    {
        $this->archetypeRepository->method('findOneBy')
            ->willReturn(null);

        $result = $this->renderer->render('Try [[archetype:unknown-deck]].');

        self::assertStringContainsString('[[archetype:unknown-deck]]', $result);
        self::assertStringNotContainsString('<a href=', $result);
    }

    public function testExpandsDeckTag(): void
    {
        $this->urlGenerator->method('generate')
            ->with('app_deck_show', ['short_tag' => 'KL1T0T'])
            ->willReturn('/deck/KL1T0T');

        $result = $this->renderer->render('Check out [[deck:KL1T0T]].');

        self::assertStringContainsString('<a href="/deck/KL1T0T"', $result);
        self::assertStringContainsString('badge-short-id', $result);
        self::assertStringContainsString('KL1T0T', $result);
        self::assertStringNotContainsString('[[deck:', $result);
    }

    public function testExpandsCardTagFromDatabase(): void
    {
        $deckCard = $this->createMock(DeckCard::class);
        $deckCard->method('getCardName')->willReturn('Dialga GX');
        $deckCard->method('getImageUrl')->willReturn('https://images.tcgdex.net/dialga.png');

        $this->deckCardRepository->method('findOneBySetCodeAndCardNumber')
            ->with('SLG', '88')
            ->willReturn($deckCard);

        $result = $this->renderer->render('Use [[card:SLG-88]] for extra turns.');

        self::assertStringContainsString('card-hover', $result);
        self::assertStringContainsString('Dialga GX', $result);
        self::assertStringContainsString('https://images.tcgdex.net/dialga.png', $result);
        self::assertStringNotContainsString('[[card:', $result);
    }

    public function testExpandsCardTagFromTcgdexFallback(): void
    {
        $this->deckCardRepository->method('findOneBySetCodeAndCardNumber')
            ->willReturn(null);

        $tcgdexCard = new TcgdexCard('slg-88', 'Dialga GX', 'pokemon', null, 'https://tcgdex.net/dialga.png', true);

        $this->tcgdexApiClient->method('findCard')
            ->with('SLG', '88')
            ->willReturn($tcgdexCard);

        $result = $this->renderer->render('Use [[card:SLG-88]].');

        self::assertStringContainsString('card-hover', $result);
        self::assertStringContainsString('Dialga GX', $result);
    }

    public function testUnknownCardRemainsEscaped(): void
    {
        $this->deckCardRepository->method('findOneBySetCodeAndCardNumber')
            ->willReturn(null);

        $this->tcgdexApiClient->method('findCard')
            ->willReturn(null);

        $result = $this->renderer->render('Use [[card:FAKE-999]].');

        self::assertStringContainsString('[[card:FAKE-999]]', $result);
    }

    public function testCardWithoutImageRendersNameOnly(): void
    {
        $deckCard = $this->createMock(DeckCard::class);
        $deckCard->method('getCardName')->willReturn('Crispin');
        $deckCard->method('getImageUrl')->willReturn(null);

        $this->deckCardRepository->method('findOneBySetCodeAndCardNumber')
            ->willReturn($deckCard);

        $result = $this->renderer->render('Play [[card:SFA-6]].');

        self::assertStringContainsString('Crispin', $result);
        self::assertStringNotContainsString('card-hover', $result);
    }

    public function testMultipleTagTypesInSameDescription(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Kyurem');
        $archetype->setPokemonSlugs(['kyurem']);
        $archetype->setIsPublished(true);

        $this->archetypeRepository->method('findOneBy')
            ->willReturn($archetype);

        $this->urlGenerator->method('generate')
            ->willReturnMap([
                ['app_archetype_show', ['slug' => 'kyurem'], 1, '/archetypes/kyurem'],
                ['app_deck_show', ['short_tag' => 'ABC123'], 1, '/deck/ABC123'],
            ]);

        $deckCard = $this->createMock(DeckCard::class);
        $deckCard->method('getCardName')->willReturn('Dialga GX');
        $deckCard->method('getImageUrl')->willReturn('https://img.test/card.png');

        $this->deckCardRepository->method('findOneBySetCodeAndCardNumber')
            ->willReturn($deckCard);

        $result = $this->renderer->render('Use [[archetype:kyurem]] with [[deck:ABC123]] and [[card:SLG-88]].');

        self::assertStringContainsString('/archetypes/kyurem', $result);
        self::assertStringContainsString('ABC123', $result);
        self::assertStringContainsString('Dialga GX', $result);
    }

    public function testPromoSetCodeWithHyphen(): void
    {
        $deckCard = $this->createMock(DeckCard::class);
        $deckCard->method('getCardName')->willReturn('Professor Research');
        $deckCard->method('getImageUrl')->willReturn('https://img.test/prof.png');

        $this->deckCardRepository->method('findOneBySetCodeAndCardNumber')
            ->with('PR-SV', '12')
            ->willReturn($deckCard);

        $result = $this->renderer->render('Play [[card:PR-SV-12]].');

        self::assertStringContainsString('Professor Research', $result);
        self::assertStringContainsString('card-hover', $result);
    }
}

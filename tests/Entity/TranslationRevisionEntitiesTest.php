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

namespace App\Tests\Entity;

use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Entity\ArchetypeTranslationRevision;
use App\Entity\Deck;
use App\Entity\DeckTranslation;
use App\Entity\DeckTranslationRevision;
use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use App\Entity\MenuCategoryTranslationRevision;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Entity\User;
use App\Enum\TranslationRevisionState;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F9.7 — Translation foundation
 */
class TranslationRevisionEntitiesTest extends TestCase
{
    public function testPageRevisionAccessors(): void
    {
        $page = new Page();
        $author = new User();

        $translation = new PageTranslation();
        $translation->setPage($page);
        $translation->setLocale('en');
        $revision = PageTranslationRevision::fromTranslation($translation);
        $sourceRevision = PageTranslationRevision::fromTranslation($translation);

        $revision->setLocale('fr');
        $revision->setState(TranslationRevisionState::PendingReview);
        $revision->setAuthor($author);
        $revision->setSourceRevision($sourceRevision);
        $revision->setTitle('Titre');
        $revision->setContent('Contenu');
        $revision->setOgDescription('Description OG');

        self::assertNull($revision->getId());
        self::assertSame('fr', $revision->getLocale());
        self::assertSame(TranslationRevisionState::PendingReview, $revision->getState());
        self::assertSame($author, $revision->getAuthor());
        self::assertSame($sourceRevision, $revision->getSourceRevision());
        self::assertSame($page, $revision->getPage());
        self::assertSame($page, $revision->getSubject());
        self::assertSame('page', PageTranslationRevision::subjectFieldName());
        self::assertSame('Titre', $revision->getTitle());
        self::assertSame('Contenu', $revision->getContent());
        self::assertSame('Description OG', $revision->getOgDescription());
        self::assertInstanceOf(\DateTimeImmutable::class, $revision->getCreatedAt());
    }

    public function testArchetypeRevisionAccessors(): void
    {
        $archetype = new Archetype();
        $author = new User();

        $translation = new ArchetypeTranslation();
        $translation->setArchetype($archetype);
        $revision = ArchetypeTranslationRevision::fromTranslation($translation);
        $sourceRevision = ArchetypeTranslationRevision::fromTranslation($translation);

        $revision->setLocale('fr');
        $revision->setState(TranslationRevisionState::Rejected);
        $revision->setAuthor($author);
        $revision->setSourceRevision($sourceRevision);
        $revision->setName('Box Anciens');
        $revision->setDescription('Description');
        $revision->setMetaDescription('Méta');
        $revision->setOgDescription('OG');

        self::assertNull($revision->getId());
        self::assertSame('fr', $revision->getLocale());
        self::assertSame(TranslationRevisionState::Rejected, $revision->getState());
        self::assertSame($author, $revision->getAuthor());
        self::assertSame($sourceRevision, $revision->getSourceRevision());
        self::assertSame($archetype, $revision->getArchetype());
        self::assertSame($archetype, $revision->getSubject());
        self::assertSame('archetype', ArchetypeTranslationRevision::subjectFieldName());
        self::assertSame('Box Anciens', $revision->getName());
        self::assertSame('Description', $revision->getDescription());
        self::assertSame('Méta', $revision->getMetaDescription());
        self::assertSame('OG', $revision->getOgDescription());
    }

    public function testMenuCategoryRevisionAccessors(): void
    {
        $menuCategory = new MenuCategory();

        $translation = new MenuCategoryTranslation();
        $translation->setMenuCategory($menuCategory);
        $revision = MenuCategoryTranslationRevision::fromTranslation($translation);
        $sourceRevision = MenuCategoryTranslationRevision::fromTranslation($translation);

        $revision->setLocale('fr');
        $revision->setState(TranslationRevisionState::Draft);
        $revision->setSourceRevision($sourceRevision);
        $revision->setName('Guides');

        self::assertNull($revision->getId());
        self::assertSame('fr', $revision->getLocale());
        self::assertSame(TranslationRevisionState::Draft, $revision->getState());
        self::assertNull($revision->getAuthor());
        self::assertSame($sourceRevision, $revision->getSourceRevision());
        self::assertSame($menuCategory, $revision->getMenuCategory());
        self::assertSame($menuCategory, $revision->getSubject());
        self::assertSame('menuCategory', MenuCategoryTranslationRevision::subjectFieldName());
        self::assertSame('Guides', $revision->getName());
    }

    public function testDeckRevisionAccessors(): void
    {
        $deck = new Deck();
        $author = new User();

        $revision = DeckTranslationRevision::fromDeckNotes($deck, 'en');
        $sourceRevision = DeckTranslationRevision::fromDeckNotes($deck, 'en');

        $revision->setLocale('fr');
        $revision->setState(TranslationRevisionState::Validated);
        $revision->setAuthor($author);
        $revision->setSourceRevision($sourceRevision);
        $revision->setNotes('Notes');

        self::assertNull($revision->getId());
        self::assertSame('fr', $revision->getLocale());
        self::assertSame(TranslationRevisionState::Validated, $revision->getState());
        self::assertSame($author, $revision->getAuthor());
        self::assertSame($sourceRevision, $revision->getSourceRevision());
        self::assertSame($deck, $revision->getDeck());
        self::assertSame($deck, $revision->getSubject());
        self::assertSame('deck', DeckTranslationRevision::subjectFieldName());
        self::assertSame('Notes', $revision->getNotes());
    }

    public function testDeckTranslationAccessors(): void
    {
        $deck = new Deck();
        $translator = new User();

        $translation = new DeckTranslation();
        $translation->setDeck($deck);
        $translation->setLocale('fr');
        $translation->setNotes('Notes de variante');
        $translation->setSourceOutdated(true);
        $translation->setTranslator($translator);

        self::assertNull($translation->getId());
        self::assertSame($deck, $translation->getDeck());
        self::assertSame('fr', $translation->getLocale());
        self::assertSame('Notes de variante', $translation->getNotes());
        self::assertTrue($translation->isSourceOutdated());
        self::assertSame($translator, $translation->getTranslator());
    }

    public function testDeckTranslationDefaults(): void
    {
        $translation = new DeckTranslation();

        self::assertSame('en', $translation->getLocale());
        self::assertNull($translation->getNotes());
        self::assertFalse($translation->isSourceOutdated());
        self::assertNull($translation->getTranslator());
    }

    public function testSourceOutdatedAccessorsOnLiveTranslations(): void
    {
        $pageTranslation = new PageTranslation();
        self::assertFalse($pageTranslation->isSourceOutdated());
        $pageTranslation->setSourceOutdated(true);
        self::assertTrue($pageTranslation->isSourceOutdated());

        $archetypeTranslation = new ArchetypeTranslation();
        self::assertFalse($archetypeTranslation->isSourceOutdated());
        $archetypeTranslation->setSourceOutdated(true);
        self::assertTrue($archetypeTranslation->isSourceOutdated());

        $menuCategoryTranslation = new MenuCategoryTranslation();
        self::assertFalse($menuCategoryTranslation->isSourceOutdated());
        $menuCategoryTranslation->setSourceOutdated(true);
        self::assertTrue($menuCategoryTranslation->isSourceOutdated());
    }
}

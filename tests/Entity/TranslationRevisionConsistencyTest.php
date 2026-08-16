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

use App\Attribute\Translatable;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the drift between `#[Translatable]` fields and their revision
 * mirrors: forgetting to add a column (or a factory copy line) on the
 * revision entity fails here instead of silently losing history.
 *
 * @see docs/features.md F9.7 — Translation foundation
 */
class TranslationRevisionConsistencyTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function translationRevisionPairs(): iterable
    {
        yield 'page' => [PageTranslation::class, PageTranslationRevision::class];
        yield 'archetype' => [ArchetypeTranslation::class, ArchetypeTranslationRevision::class];
        yield 'menu category' => [MenuCategoryTranslation::class, MenuCategoryTranslationRevision::class];
        yield 'deck translation' => [DeckTranslation::class, DeckTranslationRevision::class];
        yield 'deck source notes' => [Deck::class, DeckTranslationRevision::class];
    }

    /**
     * @param class-string $translationClass
     * @param class-string $revisionClass
     */
    #[DataProvider('translationRevisionPairs')]
    public function testEveryTranslatableFieldHasARevisionMirror(string $translationClass, string $revisionClass): void
    {
        $translatableFields = self::translatableFields($translationClass);
        self::assertNotSame([], $translatableFields, \sprintf('%s declares no #[Translatable] field.', $translationClass));

        $revisionReflection = new \ReflectionClass($revisionClass);
        foreach ($translatableFields as $field) {
            self::assertTrue(
                $revisionReflection->hasProperty($field),
                \sprintf('%s misses a mirror property for %s::$%s marked #[Translatable].', $revisionClass, $translationClass, $field),
            );
        }
    }

    public function testPageFactoryCopiesEveryTranslatableField(): void
    {
        $translation = new PageTranslation();
        $translation->setPage(new Page());
        $translation->setLocale('fr');
        $translation->setTitle('Titre');
        $translation->setContent('Contenu');
        $translation->setOgDescription('Description OG');

        $revision = PageTranslationRevision::fromTranslation($translation);

        self::assertSame('fr', $revision->getLocale());
        self::assertSame($translation->getPage(), $revision->getSubject());
        $this->assertTranslatableFieldsCopied(PageTranslation::class, $translation, $revision);
    }

    public function testArchetypeFactoryCopiesEveryTranslatableField(): void
    {
        $translation = new ArchetypeTranslation();
        $translation->setArchetype(new Archetype());
        $translation->setLocale('fr');
        $translation->setName('Box Anciens');
        $translation->setDescription('Description française');
        $translation->setMetaDescription('Méta description');
        $translation->setOgDescription('Description OG');

        $revision = ArchetypeTranslationRevision::fromTranslation($translation);

        self::assertSame('fr', $revision->getLocale());
        self::assertSame($translation->getArchetype(), $revision->getSubject());
        $this->assertTranslatableFieldsCopied(ArchetypeTranslation::class, $translation, $revision);
    }

    public function testMenuCategoryFactoryCopiesEveryTranslatableField(): void
    {
        $translation = new MenuCategoryTranslation();
        $translation->setMenuCategory(new MenuCategory());
        $translation->setLocale('fr');
        $translation->setName('Guides');

        $revision = MenuCategoryTranslationRevision::fromTranslation($translation);

        self::assertSame('fr', $revision->getLocale());
        self::assertSame($translation->getMenuCategory(), $revision->getSubject());
        $this->assertTranslatableFieldsCopied(MenuCategoryTranslation::class, $translation, $revision);
    }

    public function testDeckTranslationFactoryCopiesEveryTranslatableField(): void
    {
        $translation = new DeckTranslation();
        $translation->setDeck(new Deck());
        $translation->setLocale('fr');
        $translation->setNotes('Notes de variante');

        $revision = DeckTranslationRevision::fromTranslation($translation);

        self::assertSame('fr', $revision->getLocale());
        self::assertSame($translation->getDeck(), $revision->getSubject());
        $this->assertTranslatableFieldsCopied(DeckTranslation::class, $translation, $revision);
    }

    public function testApplyToCopiesEveryTranslatableFieldBack(): void
    {
        $pageTranslation = new PageTranslation();
        $pageTranslation->setPage(new Page());
        $pageTranslation->setTitle('Titre');
        $pageTranslation->setContent('Contenu');
        $pageTranslation->setOgDescription('OG');
        $pageTarget = new PageTranslation();
        $pageTarget->setPage($pageTranslation->getPage());
        PageTranslationRevision::fromTranslation($pageTranslation)->applyTo($pageTarget);
        $this->assertTranslatableFieldsEqual(PageTranslation::class, $pageTranslation, $pageTarget);

        $archetypeTranslation = new ArchetypeTranslation();
        $archetypeTranslation->setArchetype(new Archetype());
        $archetypeTranslation->setName('Box Anciens');
        $archetypeTranslation->setDescription('Description');
        $archetypeTranslation->setMetaDescription('Méta');
        $archetypeTranslation->setOgDescription('OG');
        $archetypeTarget = new ArchetypeTranslation();
        $archetypeTarget->setArchetype($archetypeTranslation->getArchetype());
        ArchetypeTranslationRevision::fromTranslation($archetypeTranslation)->applyTo($archetypeTarget);
        $this->assertTranslatableFieldsEqual(ArchetypeTranslation::class, $archetypeTranslation, $archetypeTarget);

        $menuCategoryTranslation = new MenuCategoryTranslation();
        $menuCategoryTranslation->setMenuCategory(new MenuCategory());
        $menuCategoryTranslation->setName('Guides');
        $menuCategoryTarget = new MenuCategoryTranslation();
        $menuCategoryTarget->setMenuCategory($menuCategoryTranslation->getMenuCategory());
        MenuCategoryTranslationRevision::fromTranslation($menuCategoryTranslation)->applyTo($menuCategoryTarget);
        $this->assertTranslatableFieldsEqual(MenuCategoryTranslation::class, $menuCategoryTranslation, $menuCategoryTarget);

        $deckTranslation = new DeckTranslation();
        $deckTranslation->setDeck(new Deck());
        $deckTranslation->setNotes('Notes');
        $deckTarget = new DeckTranslation();
        $deckTarget->setDeck($deckTranslation->getDeck());
        DeckTranslationRevision::fromTranslation($deckTranslation)->applyTo($deckTarget);
        $this->assertTranslatableFieldsEqual(DeckTranslation::class, $deckTranslation, $deckTarget);
    }

    public function testDeckNotesFactorySnapshotsSourceLocale(): void
    {
        $deck = new Deck();
        $deck->setNotes('Canonical English notes');

        $revision = DeckTranslationRevision::fromDeckNotes($deck, 'en');

        self::assertSame('en', $revision->getLocale());
        self::assertSame($deck, $revision->getSubject());
        self::assertSame('Canonical English notes', $revision->getNotes());
        self::assertNull($revision->getSourceRevision());
    }

    /**
     * @param class-string $translationClass
     */
    private function assertTranslatableFieldsEqual(string $translationClass, object $expected, object $actual): void
    {
        $reflection = new \ReflectionClass($translationClass);
        foreach (self::translatableFields($translationClass) as $field) {
            self::assertSame(
                $reflection->getProperty($field)->getValue($expected),
                $reflection->getProperty($field)->getValue($actual),
                \sprintf('applyTo() does not copy the #[Translatable] field "%s" of %s.', $field, $translationClass),
            );
        }
    }

    /**
     * @param class-string $translationClass
     */
    private function assertTranslatableFieldsCopied(string $translationClass, object $translation, object $revision): void
    {
        $revisionReflection = new \ReflectionClass($revision);
        $translationReflection = new \ReflectionClass($translation);

        foreach (self::translatableFields($translationClass) as $field) {
            $expected = $translationReflection->getProperty($field)->getValue($translation);
            $actual = $revisionReflection->getProperty($field)->getValue($revision);

            self::assertSame(
                $expected,
                $actual,
                \sprintf('%s::fromTranslation() does not copy the #[Translatable] field "%s".', $revision::class, $field),
            );
        }
    }

    /**
     * @param class-string $entityClass
     *
     * @return list<string>
     */
    private static function translatableFields(string $entityClass): array
    {
        $fields = [];
        foreach ((new \ReflectionClass($entityClass))->getProperties() as $property) {
            if ([] !== $property->getAttributes(Translatable::class)) {
                $fields[] = $property->getName();
            }
        }

        return $fields;
    }
}

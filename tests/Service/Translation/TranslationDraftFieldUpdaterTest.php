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

namespace App\Tests\Service\Translation;

use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Service\Translation\TranslationDraftFieldUpdater;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 */
class TranslationDraftFieldUpdaterTest extends TestCase
{
    private function draft(): PageTranslationRevision
    {
        $translation = new PageTranslation();
        $translation->setPage(new Page());
        $translation->setLocale('fr');

        return PageTranslationRevision::fromTranslation($translation);
    }

    public function testWritableFieldsMatchTheTranslatableRegistry(): void
    {
        $updater = new TranslationDraftFieldUpdater();

        self::assertSame(['title', 'content', 'ogDescription'], $updater->writableFields($this->draft()));
    }

    public function testAppliesOnlyTranslatableFields(): void
    {
        $updater = new TranslationDraftFieldUpdater();
        $draft = $this->draft();

        $applied = $updater->apply($draft, [
            'title' => 'Titre',
            'content' => 'Contenu',
            'locale' => 'de',
            'state' => 'validated',
            'unknownField' => 'x',
        ]);

        self::assertSame(['title', 'content'], $applied);
        self::assertSame('Titre', $draft->getTitle());
        self::assertSame('Contenu', $draft->getContent());
        self::assertSame('fr', $draft->getLocale());
    }

    public function testNullOnNonNullableFieldCoercesToEmptyString(): void
    {
        $updater = new TranslationDraftFieldUpdater();
        $draft = $this->draft();
        $draft->setTitle('Something');
        $draft->setOgDescription('OG');

        $applied = $updater->apply($draft, ['title' => null, 'ogDescription' => null]);

        self::assertSame(['title', 'ogDescription'], $applied);
        self::assertSame('', $draft->getTitle());
        self::assertNull($draft->getOgDescription());
    }

    public function testNonStringValuesAreIgnored(): void
    {
        $updater = new TranslationDraftFieldUpdater();
        $draft = $this->draft();
        $draft->setTitle('Kept');

        $applied = $updater->apply($draft, ['title' => 42, 'content' => ['nested']]);

        self::assertSame([], $applied);
        self::assertSame('Kept', $draft->getTitle());
    }
}

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

use App\Entity\Deck;
use App\Entity\DeckTranslation;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F9.13 — Archetype + variants combined translation view
 */
class DeckLocalizedNotesTest extends TestCase
{
    public function testLocalizedNotesUsesTheTranslationRow(): void
    {
        $deck = new Deck();
        $deck->setNotes('Canonical English notes');

        $translation = new DeckTranslation();
        $translation->setLocale('fr');
        $translation->setNotes('Notes en français');
        $deck->addTranslation($translation);

        self::assertSame($translation, $deck->translationFor('fr'));
        self::assertSame('Notes en français', $deck->localizedNotes('fr'));
        self::assertSame($deck, $translation->getDeck());
    }

    public function testLocalizedNotesFallsBackToCanonicalNotes(): void
    {
        $deck = new Deck();
        $deck->setNotes('Canonical English notes');

        // No translation at all.
        self::assertNull($deck->translationFor('fr'));
        self::assertSame('Canonical English notes', $deck->localizedNotes('fr'));

        // An empty translation row also falls back.
        $emptyTranslation = new DeckTranslation();
        $emptyTranslation->setLocale('fr');
        $emptyTranslation->setNotes('');
        $deck->addTranslation($emptyTranslation);

        self::assertSame('Canonical English notes', $deck->localizedNotes('fr'));
        // Another locale never matches.
        self::assertNull($deck->translationFor('de'));
    }
}

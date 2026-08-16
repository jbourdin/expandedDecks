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

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfill translation revisions from existing live content (F9.7, epic #612).
 *
 * Seeds one `validated` revision per existing live translation row so history
 * and staleness tracking start complete at deploy time instead of at each
 * row's first post-deploy write. Source-locale (`en`) revisions are inserted
 * first, then non-source rows linked to their subject's source revision —
 * mirroring what `TranslationRevisionListener` does at runtime. Non-source
 * revisions credit the row's `translator` (F19.8) as author when set; source
 * revisions keep a NULL author (the original writer is not recorded).
 *
 * The `NOT EXISTS` guards make the backfill skip rows that already have a
 * revision for that locale, so environments where the listener already ran
 * are left untouched.
 */
final class Version20260816102343 extends AbstractMigration
{
    private const string SOURCE_LOCALE = 'en';

    public function getDescription(): string
    {
        return 'F9.7 backfill: seed validated translation revisions from existing live content';
    }

    public function up(Schema $schema): void
    {
        // --- CMS pages ---
        $this->addSql(<<<'SQL'
            INSERT INTO page_translation_revision (page_id, locale, state, created_at, title, content, og_description, author_id, source_revision_id)
            SELECT pt.page_id, pt.locale, 'validated', UTC_TIMESTAMP(), pt.title, pt.content, pt.og_description, NULL, NULL
            FROM page_translation pt
            WHERE pt.locale = :sourceLocale
              AND NOT EXISTS (SELECT 1 FROM page_translation_revision r WHERE r.page_id = pt.page_id AND r.locale = pt.locale)
            SQL, ['sourceLocale' => self::SOURCE_LOCALE]);
        $this->addSql(<<<'SQL'
            INSERT INTO page_translation_revision (page_id, locale, state, created_at, title, content, og_description, author_id, source_revision_id)
            SELECT pt.page_id, pt.locale, 'validated', UTC_TIMESTAMP(), pt.title, pt.content, pt.og_description, pt.translator_id,
                   (SELECT MAX(src.id) FROM page_translation_revision src WHERE src.page_id = pt.page_id AND src.locale = :sourceLocale)
            FROM page_translation pt
            WHERE pt.locale <> :sourceLocale
              AND NOT EXISTS (SELECT 1 FROM page_translation_revision r WHERE r.page_id = pt.page_id AND r.locale = pt.locale)
            SQL, ['sourceLocale' => self::SOURCE_LOCALE]);

        // --- Archetypes ---
        $this->addSql(<<<'SQL'
            INSERT INTO archetype_translation_revision (archetype_id, locale, state, created_at, name, description, meta_description, og_description, author_id, source_revision_id)
            SELECT at.archetype_id, at.locale, 'validated', UTC_TIMESTAMP(), at.name, at.description, at.meta_description, at.og_description, NULL, NULL
            FROM archetype_translation at
            WHERE at.locale = :sourceLocale
              AND NOT EXISTS (SELECT 1 FROM archetype_translation_revision r WHERE r.archetype_id = at.archetype_id AND r.locale = at.locale)
            SQL, ['sourceLocale' => self::SOURCE_LOCALE]);
        $this->addSql(<<<'SQL'
            INSERT INTO archetype_translation_revision (archetype_id, locale, state, created_at, name, description, meta_description, og_description, author_id, source_revision_id)
            SELECT at.archetype_id, at.locale, 'validated', UTC_TIMESTAMP(), at.name, at.description, at.meta_description, at.og_description, at.translator_id,
                   (SELECT MAX(src.id) FROM archetype_translation_revision src WHERE src.archetype_id = at.archetype_id AND src.locale = :sourceLocale)
            FROM archetype_translation at
            WHERE at.locale <> :sourceLocale
              AND NOT EXISTS (SELECT 1 FROM archetype_translation_revision r WHERE r.archetype_id = at.archetype_id AND r.locale = at.locale)
            SQL, ['sourceLocale' => self::SOURCE_LOCALE]);

        // --- Menu categories (no translator column on the live table) ---
        $this->addSql(<<<'SQL'
            INSERT INTO menu_category_translation_revision (menu_category_id, locale, state, created_at, name, author_id, source_revision_id)
            SELECT mct.menu_category_id, mct.locale, 'validated', UTC_TIMESTAMP(), mct.name, NULL, NULL
            FROM menu_category_translation mct
            WHERE mct.locale = :sourceLocale
              AND NOT EXISTS (SELECT 1 FROM menu_category_translation_revision r WHERE r.menu_category_id = mct.menu_category_id AND r.locale = mct.locale)
            SQL, ['sourceLocale' => self::SOURCE_LOCALE]);
        $this->addSql(<<<'SQL'
            INSERT INTO menu_category_translation_revision (menu_category_id, locale, state, created_at, name, author_id, source_revision_id)
            SELECT mct.menu_category_id, mct.locale, 'validated', UTC_TIMESTAMP(), mct.name, NULL,
                   (SELECT MAX(src.id) FROM menu_category_translation_revision src WHERE src.menu_category_id = mct.menu_category_id AND src.locale = :sourceLocale)
            FROM menu_category_translation mct
            WHERE mct.locale <> :sourceLocale
              AND NOT EXISTS (SELECT 1 FROM menu_category_translation_revision r WHERE r.menu_category_id = mct.menu_category_id AND r.locale = mct.locale)
            SQL, ['sourceLocale' => self::SOURCE_LOCALE]);

        // --- Archetype-variant notes (source lives on deck.notes; the new
        // deck_translation table is empty at backfill time, so there are no
        // non-source rows to seed) ---
        $this->addSql(<<<'SQL'
            INSERT INTO deck_translation_revision (deck_id, locale, state, created_at, notes, author_id, source_revision_id)
            SELECT d.id, :sourceLocale, 'validated', UTC_TIMESTAMP(), d.notes, NULL, NULL
            FROM deck d
            WHERE d.owner_id IS NULL AND d.archetype_id IS NOT NULL AND d.notes IS NOT NULL AND d.notes <> ''
              AND NOT EXISTS (SELECT 1 FROM deck_translation_revision r WHERE r.deck_id = d.id AND r.locale = :sourceLocale)
            SQL, ['sourceLocale' => self::SOURCE_LOCALE]);
    }

    public function down(Schema $schema): void
    {
        // Data backfill: once post-deploy writes interleave with the seeded
        // rows there is no reliable way to tell them apart again.
        $this->throwIrreversibleMigrationException('Revision backfill cannot be reliably distinguished from runtime revisions.');
    }
}

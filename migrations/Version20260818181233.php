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
 * Backfill card-translation source revisions (F9.17, #776): every banned
 * card with an explanation and staple card with a note gets its validated
 * source-locale revision, so staleness tracking starts complete. Idempotent
 * via NOT EXISTS guards; the new translation live tables start empty, so
 * there are no non-source rows to seed.
 */
final class Version20260818181233 extends AbstractMigration
{
    private const string SOURCE_LOCALE = 'en';

    public function getDescription(): string
    {
        return 'F9.17 backfill: seed source revisions for banned/staple card texts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO banned_card_translation_revision (banned_card_id, locale, state, created_at, explanation, author_id, source_revision_id)
            SELECT bc.id, :sourceLocale, 'validated', UTC_TIMESTAMP(), bc.explanation, NULL, NULL
            FROM banned_card bc
            WHERE bc.explanation IS NOT NULL AND bc.explanation <> '' AND bc.deleted_at IS NULL
              AND NOT EXISTS (SELECT 1 FROM banned_card_translation_revision r WHERE r.banned_card_id = bc.id AND r.locale = :sourceLocale)
            SQL, ['sourceLocale' => self::SOURCE_LOCALE]);

        $this->addSql(<<<'SQL'
            INSERT INTO staple_card_translation_revision (staple_card_id, locale, state, created_at, note, author_id, source_revision_id)
            SELECT sc.id, :sourceLocale, 'validated', UTC_TIMESTAMP(), sc.note, NULL, NULL
            FROM staple_card sc
            WHERE sc.note IS NOT NULL AND sc.note <> '' AND sc.deleted_at IS NULL
              AND NOT EXISTS (SELECT 1 FROM staple_card_translation_revision r WHERE r.staple_card_id = sc.id AND r.locale = :sourceLocale)
            SQL, ['sourceLocale' => self::SOURCE_LOCALE]);
    }

    public function down(Schema $schema): void
    {
        // Data backfill: once runtime writes interleave with the seeded rows
        // there is no reliable way to tell them apart again.
        $this->throwIrreversibleMigrationException('Revision backfill cannot be reliably distinguished from runtime revisions.');
    }
}

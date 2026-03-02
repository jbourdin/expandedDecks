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
 * Add Deck.shortTag (F2.1) and Event.finishedAt (F3.20).
 */
final class Version20260301202204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Deck.shortTag (6-char unique code) and Event.finishedAt timestamp';
    }

    public function up(Schema $schema): void
    {
        // 1. Add short_tag as nullable first (to allow backfill)
        $this->addSql('ALTER TABLE deck ADD short_tag VARCHAR(6) DEFAULT NULL');

        // 2. Backfill existing rows with unique random short tags
        $this->addSql(<<<'SQL'
            UPDATE deck SET short_tag = (
                SELECT CONCAT(
                    SUBSTRING('ABCDEFGHJKLMNPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 34), 1),
                    SUBSTRING('ABCDEFGHJKLMNPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 34), 1),
                    SUBSTRING('ABCDEFGHJKLMNPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 34), 1),
                    SUBSTRING('ABCDEFGHJKLMNPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 34), 1),
                    SUBSTRING('ABCDEFGHJKLMNPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 34), 1),
                    SUBSTRING('ABCDEFGHJKLMNPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 34), 1)
                )
            ) WHERE short_tag IS NULL
            SQL);

        // 3. Make column NOT NULL
        $this->addSql('ALTER TABLE deck MODIFY short_tag VARCHAR(6) NOT NULL');

        // 4. Add unique index
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4FAC363774C9F71C ON deck (short_tag)');

        // 5. Event.finishedAt
        $this->addSql('ALTER TABLE event ADD finished_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_4FAC363774C9F71C ON deck');
        $this->addSql('ALTER TABLE deck DROP short_tag');
        $this->addSql('ALTER TABLE event DROP finished_at');
    }
}

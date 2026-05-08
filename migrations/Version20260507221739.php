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
 * F6.15 — Staple cards schema.
 *
 * Adds:
 *   - `staple_card` (parent, one row per CardIdentity) with bucket-position and
 *     bucket-hotness composite indexes for cheap public-list and reorder queries.
 *   - `staple_card_printing` (child, one row per (set_code, card_number) pair).
 *   - `channel.enable_staples` boolean for per-channel public-page gating
 *     (mirrors `channel.enable_banned_cards`).
 *
 * @see https://github.com/jbourdin/expandedDecks/issues/532
 */
final class Version20260507221739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.15 — staple_card + staple_card_printing tables, channel.enable_staples';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE staple_card (
                id INT AUTO_INCREMENT NOT NULL,
                card_name VARCHAR(100) NOT NULL,
                bucket VARCHAR(20) NOT NULL,
                position INT NOT NULL,
                hotness INT NOT NULL,
                note LONGTEXT DEFAULT NULL,
                deleted_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                card_identity_id INT DEFAULT NULL,
                representative_printing_id INT DEFAULT NULL,
                INDEX IDX_8D910326B2864B3E (representative_printing_id),
                INDEX idx_staple_card_bucket_position (bucket, position),
                INDEX idx_staple_card_bucket_hotness (bucket, hotness),
                UNIQUE INDEX uniq_staple_card_identity (card_identity_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE staple_card_printing (
                id INT AUTO_INCREMENT NOT NULL,
                set_code VARCHAR(20) NOT NULL,
                card_number VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL,
                staple_card_id INT NOT NULL,
                card_printing_id INT DEFAULT NULL,
                INDEX IDX_8ABAAED7F6A40FFE (staple_card_id),
                INDEX IDX_8ABAAED79B43DC48 (card_printing_id),
                UNIQUE INDEX uniq_staple_card_printing (set_code, card_number),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);

        $this->addSql('ALTER TABLE staple_card ADD CONSTRAINT FK_8D91032618EA8B32 FOREIGN KEY (card_identity_id) REFERENCES card_identity (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE staple_card ADD CONSTRAINT FK_8D910326B2864B3E FOREIGN KEY (representative_printing_id) REFERENCES card_printing (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE staple_card_printing ADD CONSTRAINT FK_8ABAAED7F6A40FFE FOREIGN KEY (staple_card_id) REFERENCES staple_card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE staple_card_printing ADD CONSTRAINT FK_8ABAAED79B43DC48 FOREIGN KEY (card_printing_id) REFERENCES card_printing (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE channel ADD enable_staples TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel DROP enable_staples');
        $this->addSql('ALTER TABLE staple_card_printing DROP FOREIGN KEY FK_8ABAAED7F6A40FFE');
        $this->addSql('ALTER TABLE staple_card_printing DROP FOREIGN KEY FK_8ABAAED79B43DC48');
        $this->addSql('ALTER TABLE staple_card DROP FOREIGN KEY FK_8D91032618EA8B32');
        $this->addSql('ALTER TABLE staple_card DROP FOREIGN KEY FK_8D910326B2864B3E');
        $this->addSql('DROP TABLE staple_card_printing');
        $this->addSql('DROP TABLE staple_card');
    }
}

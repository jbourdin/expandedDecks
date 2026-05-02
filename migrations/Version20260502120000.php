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
 * Add banned-cards public page support: soft-delete, explanation, card printing
 * link on banned_card; new enable_banned_cards channel toggle.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — banned_card.deleted_at, explanation, card_printing_id; channel.enable_banned_cards';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE banned_card ADD card_printing_id INT DEFAULT NULL, ADD explanation LONGTEXT DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE banned_card ADD CONSTRAINT FK_banned_card_card_printing FOREIGN KEY (card_printing_id) REFERENCES card_printing (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_banned_card_card_printing ON banned_card (card_printing_id)');
        $this->addSql('CREATE INDEX IDX_banned_card_deleted_at ON banned_card (deleted_at)');

        $this->addSql('ALTER TABLE channel ADD enable_banned_cards TINYINT(1) NOT NULL DEFAULT 0');

        // Backfill: link each banned_card to the lowest-rarity Expanded-legal printing matching its (set_code, card_number).
        // Uses a correlated subquery instead of UPDATE...JOIN so MySQL can pick the best printing per row.
        $this->addSql(<<<'SQL'
            UPDATE banned_card bc
            SET bc.card_printing_id = (
                SELECT cp.id
                FROM card_printing cp
                WHERE cp.set_code = bc.set_code
                  AND cp.card_number = bc.card_number
                ORDER BY cp.is_expanded_legal DESC, cp.rarity_tier ASC, cp.id ASC
                LIMIT 1
            )
            WHERE bc.card_printing_id IS NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel DROP enable_banned_cards');

        $this->addSql('ALTER TABLE banned_card DROP FOREIGN KEY FK_banned_card_card_printing');
        $this->addSql('DROP INDEX IDX_banned_card_card_printing ON banned_card');
        $this->addSql('DROP INDEX IDX_banned_card_deleted_at ON banned_card');
        $this->addSql('ALTER TABLE banned_card DROP card_printing_id, DROP explanation, DROP deleted_at');
    }
}

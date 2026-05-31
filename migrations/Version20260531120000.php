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
 * Add the tcgdex_updated_at baseline column to tcgdex_card.
 *
 * Captures the TCGdex per-card "updated" timestamp on every API touch so set-level
 * freshness diffing can switch to it once TCGdex exposes a set-level field. No backfill:
 * existing rows start NULL and acquire a value the next time they are synced.
 *
 * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
 */
final class Version20260531120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tcgdex_updated_at column to tcgdex_card (multi-locale sync baseline).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tcgdex_card ADD tcgdex_updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tcgdex_card DROP tcgdex_updated_at');
    }
}

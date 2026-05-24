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
 * Adds `first_published_at` and `last_published_at` to `page` and `archetype`,
 * backfilling existing published rows from their `created_at` / `updated_at`
 * timestamps so the public dates section appears immediately after deploy.
 *
 * @see docs/features.md F11.4 — CMS publication dates
 * @see docs/features.md F2.27 — Archetype publication dates
 */
final class Version20260524201835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add publication date columns (first_published_at, last_published_at) on page and archetype, backfilling published rows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page ADD first_published_at DATETIME DEFAULT NULL, ADD last_published_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE archetype ADD first_published_at DATETIME DEFAULT NULL, ADD last_published_at DATETIME DEFAULT NULL');

        // Backfill: for previously published rows, fall back to created_at / updated_at — the best
        // approximation we have since these timestamps weren't recorded historically. Draft rows
        // intentionally stay NULL so they only get stamped the first time they're actually published.
        $this->addSql('UPDATE page SET first_published_at = created_at, last_published_at = COALESCE(updated_at, created_at) WHERE is_published = 1');
        $this->addSql('UPDATE archetype SET first_published_at = created_at, last_published_at = COALESCE(updated_at, created_at) WHERE is_published = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page DROP first_published_at, DROP last_published_at');
        $this->addSql('ALTER TABLE archetype DROP first_published_at, DROP last_published_at');
    }
}

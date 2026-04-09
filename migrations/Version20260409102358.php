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
 * Add theme_name column to channel for per-channel Twig/SCSS overrides.
 *
 * @see docs/features.md F18.28 — Per-channel theme system
 */
final class Version20260409102358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add theme_name to channel (F18.28)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel ADD theme_name VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel DROP theme_name');
    }
}

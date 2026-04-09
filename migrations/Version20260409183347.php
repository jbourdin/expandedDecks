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
 * Add parameters JSON column to channel for arbitrary key-value template variables.
 */
final class Version20260409183347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parameters JSON column to channel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel ADD parameters JSON NOT NULL DEFAULT \'{}\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel DROP parameters');
    }
}

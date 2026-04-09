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
 * Create the channel table for multi-domain support.
 *
 * @see docs/features.md F18.1 — Channel entity and database schema
 */
final class Version20260408204751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create channel table for multi-domain support (F18.1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE channel (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(30) NOT NULL, domain VARCHAR(255) NOT NULL, enable_decks TINYINT NOT NULL, enable_register TINYINT NOT NULL, enable_events TINYINT NOT NULL, enable_borrows TINYINT NOT NULL, enable_archetypes TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_channel_code (code), UNIQUE INDEX uniq_channel_domain (domain), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE channel');
    }
}

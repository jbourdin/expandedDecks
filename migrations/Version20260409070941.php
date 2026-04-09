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
 * Add channel_id FK to menu_category for per-channel navigation.
 *
 * @see docs/features.md F18.8 — Add channel association to MenuCategory
 */
final class Version20260409070941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add channel_id FK to menu_category (F18.8)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_category ADD channel_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_category ADD CONSTRAINT FK_2A1D5C5772F5A1AA FOREIGN KEY (channel_id) REFERENCES channel (id)');
        $this->addSql('CREATE INDEX IDX_2A1D5C5772F5A1AA ON menu_category (channel_id)');

        // Backfill: assign all existing categories to the 'content' channel
        $this->addSql("UPDATE menu_category SET channel_id = (SELECT id FROM channel WHERE code = 'content') WHERE channel_id IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_category DROP FOREIGN KEY FK_2A1D5C5772F5A1AA');
        $this->addSql('DROP INDEX IDX_2A1D5C5772F5A1AA ON menu_category');
        $this->addSql('ALTER TABLE menu_category DROP channel_id');
    }
}

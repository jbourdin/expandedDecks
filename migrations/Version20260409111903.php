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
 * Add channel_id FK to homepage_layout for per-channel homepages.
 *
 * @see docs/features.md F18.10 — Add channel association to HomepageLayout
 */
final class Version20260409111903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add channel_id to homepage_layout (F18.10)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE homepage_layout ADD channel_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE homepage_layout ADD CONSTRAINT FK_47683C7472F5A1AA FOREIGN KEY (channel_id) REFERENCES channel (id)');
        $this->addSql('CREATE INDEX IDX_47683C7472F5A1AA ON homepage_layout (channel_id)');

        // Backfill: assign existing layouts to the 'app' channel
        $this->addSql("UPDATE homepage_layout SET channel_id = (SELECT id FROM channel WHERE code = 'app') WHERE channel_id IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE homepage_layout DROP FOREIGN KEY FK_47683C7472F5A1AA');
        $this->addSql('DROP INDEX IDX_47683C7472F5A1AA ON homepage_layout');
        $this->addSql('ALTER TABLE homepage_layout DROP channel_id');
    }
}

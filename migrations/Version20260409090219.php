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
 * Add channel_id FK to page and change slug unique constraint to (slug, channel_id).
 *
 * Allows the same slug to exist on different channels.
 */
final class Version20260409090219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add channel_id to page, composite unique on (slug, channel_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_140AB620989D9B62 ON page');
        $this->addSql('ALTER TABLE page ADD channel_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE page ADD CONSTRAINT FK_140AB62072F5A1AA FOREIGN KEY (channel_id) REFERENCES channel (id)');
        $this->addSql('CREATE INDEX IDX_140AB62072F5A1AA ON page (channel_id)');

        // Backfill: assign all existing pages to the 'content' channel
        $this->addSql("UPDATE page SET channel_id = (SELECT id FROM channel WHERE code = 'content') WHERE channel_id IS NULL");

        $this->addSql('CREATE UNIQUE INDEX uniq_page_slug_channel ON page (slug, channel_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page DROP FOREIGN KEY FK_140AB62072F5A1AA');
        $this->addSql('DROP INDEX IDX_140AB62072F5A1AA ON page');
        $this->addSql('DROP INDEX uniq_page_slug_channel ON page');
        $this->addSql('ALTER TABLE page DROP channel_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_140AB620989D9B62 ON page (slug)');
    }
}

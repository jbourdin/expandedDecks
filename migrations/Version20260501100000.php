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
 * Create event_tag and event_event_tag join table for tagging events.
 *
 * @see docs/features.md F3.12 — Event tags
 */
final class Version20260501100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create event_tag table and event_event_tag join for event tagging';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, slug VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_event_tag_name (name), UNIQUE INDEX uniq_event_tag_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_event_tag (event_id INT NOT NULL, event_tag_id INT NOT NULL, INDEX IDX_event_event_tag_event (event_id), INDEX IDX_event_event_tag_tag (event_tag_id), PRIMARY KEY(event_id, event_tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_event_tag ADD CONSTRAINT FK_event_event_tag_event FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_event_tag ADD CONSTRAINT FK_event_event_tag_tag FOREIGN KEY (event_tag_id) REFERENCES event_tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_event_tag DROP FOREIGN KEY FK_event_event_tag_event');
        $this->addSql('ALTER TABLE event_event_tag DROP FOREIGN KEY FK_event_event_tag_tag');
        $this->addSql('DROP TABLE event_event_tag');
        $this->addSql('DROP TABLE event_tag');
    }
}

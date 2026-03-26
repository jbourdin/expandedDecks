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
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326101848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at column for soft deletion on Archetype, Deck, Event, and Page entities.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE deck ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE page ADD deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype DROP deleted_at');
        $this->addSql('ALTER TABLE deck DROP deleted_at');
        $this->addSql('ALTER TABLE event DROP deleted_at');
        $this->addSql('ALTER TABLE page DROP deleted_at');
    }
}

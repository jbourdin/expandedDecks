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
final class Version20260305105246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add visibility column to event table (F3.11)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE event ADD visibility VARCHAR(20) NOT NULL DEFAULT 'public'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP visibility');
    }
}

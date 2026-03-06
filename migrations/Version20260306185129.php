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
final class Version20260306185129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make event registration_link nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event CHANGE registration_link registration_link VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE event CHANGE registration_link registration_link VARCHAR(255) NOT NULL DEFAULT ''");
    }
}

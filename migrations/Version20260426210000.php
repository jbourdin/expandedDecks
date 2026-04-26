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
 * Add year_of_birth to user entity for tournament decklist PDF.
 *
 * @see docs/features.md F5.13 — Printable A4 decklist PDF
 */
final class Version20260426210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add year_of_birth column to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD year_of_birth INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP year_of_birth');
    }
}

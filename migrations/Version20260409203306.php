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

final class Version20260409203306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add locales JSON column to channel table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel ADD locales JSON NOT NULL');
        $this->addSql("UPDATE channel SET locales = '[\"en\", \"fr\"]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel DROP locales');
    }
}

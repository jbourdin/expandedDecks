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
 * Translation roles & access (F9.8, epic #612): target locales a user may
 * translate into, backing the single ROLE_TRANSLATION_EDITOR role.
 */
final class Version20260816121641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Added nullable first, backfilled, then tightened: MySQL cannot give
        // a JSON column a literal default, so a direct NOT NULL ADD would
        // fail on non-empty tables.
        $this->addSql('ALTER TABLE `user` ADD translation_locales JSON DEFAULT NULL');
        $this->addSql("UPDATE `user` SET translation_locales = '[]'");
        $this->addSql('ALTER TABLE `user` MODIFY translation_locales JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP translation_locales');
    }
}

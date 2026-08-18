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
 * Channel locale management (F9.16, #775): draft locales visible only to the
 * people preparing them.
 */
final class Version20260818170350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F9.16 channel locale management: draft_locales column';
    }

    public function up(Schema $schema): void
    {
        // Added nullable first, backfilled, then tightened: MySQL cannot give
        // a JSON column a literal default, so a direct NOT NULL ADD would
        // fail on non-empty tables.
        $this->addSql('ALTER TABLE channel ADD draft_locales JSON DEFAULT NULL');
        $this->addSql("UPDATE channel SET draft_locales = '[]'");
        $this->addSql('ALTER TABLE channel MODIFY draft_locales JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel DROP draft_locales');
    }
}

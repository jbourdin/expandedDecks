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
 * Add calendar_token column to user for the per-user iCal agenda feed.
 *
 * @see docs/features.md F3.14 — iCal agenda feed
 */
final class Version20260501150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.calendar_token for the personal iCal agenda feed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD calendar_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_calendar_token ON user (calendar_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_calendar_token ON user');
        $this->addSql('ALTER TABLE user DROP calendar_token');
    }
}

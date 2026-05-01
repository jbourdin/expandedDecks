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
 * Add event.allow_custody (organizer accepts staff custody of player decks).
 *
 * @see docs/features.md F4.8 — Staff-delegated lending
 */
final class Version20260501170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event.allow_custody flag — gates the staff-delegation action';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD allow_custody TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP allow_custody');
    }
}

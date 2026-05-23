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
 * Adds the `personal` boolean to `deck` for the F2.30 opt-out-of-lending flag.
 */
final class Version20260523130318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deck.personal boolean column (F2.30 personal deck flag)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck ADD personal TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck DROP personal');
    }
}

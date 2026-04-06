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

final class Version20260406120844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at column to deck_version for soft deletion.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck_version ADD deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck_version DROP deleted_at');
    }
}

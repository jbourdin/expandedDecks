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

final class Version20260401085844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position column to page table for category-based ordering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page ADD position INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page DROP position');
    }
}

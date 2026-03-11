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
final class Version20260311225218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add playstyle_tags JSON column to archetype table (F2.15)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype ADD playstyle_tags JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype DROP playstyle_tags');
    }
}

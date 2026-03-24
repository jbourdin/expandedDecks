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
 * Add pokemon_slugs JSON column to deck table.
 *
 * @see docs/features.md F2.22 — Custom Pokemon sprites on decks
 */
final class Version20260324192025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pokemon_slugs JSON column to deck table (F2.22)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck ADD pokemon_slugs JSON NOT NULL');
        $this->addSql("UPDATE deck SET pokemon_slugs = '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck DROP pokemon_slugs');
    }
}

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
 * Add archetype fields: pokemonSlugs, description, metaDescription, isPublished.
 *
 * @see docs/features.md F2.6 — Archetype management
 */
final class Version20260311191247 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pokemonSlugs, description, metaDescription, isPublished to archetype table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype ADD pokemon_slugs JSON NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD meta_description VARCHAR(255) DEFAULT NULL, ADD is_published TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype DROP pokemon_slugs, DROP description, DROP meta_description, DROP is_published');
    }
}

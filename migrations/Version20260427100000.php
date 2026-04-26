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
 * Create pokemon_sprite_mapping table for slug → PokeAPI dex ID resolution.
 *
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
final class Version20260427100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pokemon_sprite_mapping table for sprite CDN proxy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pokemon_sprite_mapping (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(80) NOT NULL, pokedex_id INT NOT NULL, UNIQUE INDEX uniq_sprite_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pokemon_sprite_mapping');
    }
}

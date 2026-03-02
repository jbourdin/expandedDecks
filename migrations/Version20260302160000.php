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
 * Move archetype (as entity) and languages from deck_version to deck.
 */
final class Version20260302160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create archetype table, move archetype + languages from deck_version to deck';
    }

    public function up(Schema $schema): void
    {
        // 1. Create archetype table
        $this->addSql('CREATE TABLE archetype (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_archetype_name (name), UNIQUE INDEX uniq_archetype_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');

        // 2. Add archetype_id + languages to deck
        $this->addSql('ALTER TABLE deck ADD archetype_id INT DEFAULT NULL, ADD languages JSON NOT NULL');
        $this->addSql('ALTER TABLE deck ADD CONSTRAINT FK_4FAC363748C921A8 FOREIGN KEY (archetype_id) REFERENCES archetype (id)');
        $this->addSql('CREATE INDEX IDX_4FAC363748C921A8 ON deck (archetype_id)');

        // 3. Migrate data: create archetype rows from deck_version, set on deck
        $this->addSql("
            INSERT INTO archetype (name, slug, created_at)
            SELECT DISTINCT dv.archetype_name,
                   LOWER(REPLACE(REPLACE(REPLACE(dv.archetype_name, ' ', '-'), '/', '-'), '--', '-')),
                   NOW()
            FROM deck_version dv
            WHERE dv.archetype_name IS NOT NULL AND dv.archetype_name != ''
        ");

        // Set archetype_id on deck from its current_version
        $this->addSql("
            UPDATE deck d
            JOIN deck_version dv ON d.current_version_id = dv.id
            JOIN archetype a ON a.name = dv.archetype_name
            SET d.archetype_id = a.id
            WHERE dv.archetype_name IS NOT NULL AND dv.archetype_name != ''
        ");

        // Copy languages from current_version to deck
        $this->addSql('
            UPDATE deck d
            JOIN deck_version dv ON d.current_version_id = dv.id
            SET d.languages = dv.languages
            WHERE d.current_version_id IS NOT NULL
        ');

        // 4. Drop old columns from deck_version
        $this->addSql('ALTER TABLE deck_version DROP archetype, DROP archetype_name, DROP languages');
    }

    public function down(Schema $schema): void
    {
        // Re-add columns to deck_version
        $this->addSql('ALTER TABLE deck_version ADD archetype VARCHAR(80) DEFAULT NULL, ADD archetype_name VARCHAR(100) DEFAULT NULL, ADD languages JSON NOT NULL');

        // Copy data back from deck to current_version
        $this->addSql('
            UPDATE deck_version dv
            JOIN deck d ON d.current_version_id = dv.id
            LEFT JOIN archetype a ON a.id = d.archetype_id
            SET dv.archetype_name = a.name,
                dv.archetype = a.slug,
                dv.languages = d.languages
        ');

        // Drop archetype relation from deck
        $this->addSql('ALTER TABLE deck DROP FOREIGN KEY FK_4FAC363748C921A8');
        $this->addSql('DROP INDEX IDX_4FAC363748C921A8 ON deck');
        $this->addSql('ALTER TABLE deck DROP archetype_id, DROP languages');

        // Drop archetype table
        $this->addSql('DROP TABLE archetype');
    }
}

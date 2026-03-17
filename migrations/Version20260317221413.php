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

final class Version20260317221413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create archetype_translation table and seed EN translations from existing archetype data (F9.6)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE archetype_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, meta_description VARCHAR(255) DEFAULT NULL, archetype_id INT NOT NULL, INDEX IDX_143B1B68732C6CC7 (archetype_id), UNIQUE INDEX archetype_translation_unique (archetype_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE archetype_translation ADD CONSTRAINT FK_143B1B68732C6CC7 FOREIGN KEY (archetype_id) REFERENCES archetype (id)');

        // Seed EN translations from existing archetype data
        $this->addSql("INSERT INTO archetype_translation (archetype_id, locale, name, description, meta_description) SELECT id, 'en', name, description, meta_description FROM archetype");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype_translation DROP FOREIGN KEY FK_143B1B68732C6CC7');
        $this->addSql('DROP TABLE archetype_translation');
    }
}

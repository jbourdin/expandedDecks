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

final class Version20260405203743 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tcgdex_serie, tcgdex_set, and tcgdex_card tables for local TCGdex data cache.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tcgdex_serie (id VARCHAR(20) NOT NULL, name JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE tcgdex_set (id VARCHAR(20) NOT NULL, serie_id VARCHAR(20) NOT NULL, name JSON NOT NULL, ptcg_code VARCHAR(20) DEFAULT NULL, release_date DATE DEFAULT NULL, official_card_count INT DEFAULT NULL, cardmarket_id INT DEFAULT NULL, tcgplayer_id INT DEFAULT NULL, INDEX IDX_C7B1E77CD94388BD (serie_id), INDEX idx_tcgdex_set_ptcg_code (ptcg_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql("CREATE TABLE tcgdex_card (id VARCHAR(30) NOT NULL, set_id VARCHAR(20) NOT NULL, local_id VARCHAR(20) NOT NULL, name JSON NOT NULL, name_en VARCHAR(100) GENERATED ALWAYS AS (name->>'$.en') STORED, name_fr VARCHAR(100) GENERATED ALWAYS AS (name->>'$.fr') STORED, category VARCHAR(20) NOT NULL, hp INT DEFAULT NULL, trainer_type VARCHAR(30) DEFAULT NULL, energy_type VARCHAR(10) DEFAULT NULL, rarity VARCHAR(50) DEFAULT NULL, image_url VARCHAR(255) DEFAULT NULL, is_expanded_legal TINYINT NOT NULL, abilities JSON NOT NULL, attacks JSON NOT NULL, effect JSON DEFAULT NULL, evolve_from JSON DEFAULT NULL, stage VARCHAR(20) DEFAULT NULL, types JSON NOT NULL, retreat INT DEFAULT NULL, regulation_mark VARCHAR(5) DEFAULT NULL, illustrator VARCHAR(100) DEFAULT NULL, cardmarket_product_id INT DEFAULT NULL, tcgplayer_product_id INT DEFAULT NULL, INDEX IDX_C0E39EF910FB0D18 (set_id), INDEX idx_tcgdex_card_name_en (name_en), INDEX idx_tcgdex_card_category (category), UNIQUE INDEX uniq_tcgdex_card_set_local (set_id, local_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");

        $this->addSql('ALTER TABLE tcgdex_set ADD CONSTRAINT FK_C7B1E77CD94388BD FOREIGN KEY (serie_id) REFERENCES tcgdex_serie (id)');
        $this->addSql('ALTER TABLE tcgdex_card ADD CONSTRAINT FK_C0E39EF910FB0D18 FOREIGN KEY (set_id) REFERENCES tcgdex_set (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tcgdex_card DROP FOREIGN KEY FK_C0E39EF910FB0D18');
        $this->addSql('ALTER TABLE tcgdex_set DROP FOREIGN KEY FK_C7B1E77CD94388BD');
        $this->addSql('DROP TABLE tcgdex_card');
        $this->addSql('DROP TABLE tcgdex_set');
        $this->addSql('DROP TABLE tcgdex_serie');
    }
}

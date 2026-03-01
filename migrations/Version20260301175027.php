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

final class Version20260301175027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove league table and league_id foreign key from event table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY `FK_3BAE0AA758AFC4DE`');
        $this->addSql('DROP INDEX IDX_3BAE0AA758AFC4DE ON event');
        $this->addSql('ALTER TABLE event DROP league_id');
        $this->addSql('DROP TABLE league');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE league (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, website VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, address VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, contact_details JSON DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE event ADD league_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT `FK_3BAE0AA758AFC4DE` FOREIGN KEY (league_id) REFERENCES league (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_3BAE0AA758AFC4DE ON event (league_id)');
    }
}

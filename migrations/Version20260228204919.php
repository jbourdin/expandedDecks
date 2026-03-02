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
final class Version20260228204919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_engagement (id INT AUTO_INCREMENT NOT NULL, state VARCHAR(30) NOT NULL, participation_mode VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, event_id INT NOT NULL, user_id INT NOT NULL, invited_by_id INT DEFAULT NULL, INDEX IDX_8081D86E71F7E88B (event_id), INDEX IDX_8081D86EA76ED395 (user_id), INDEX IDX_8081D86EA7B4A7E3 (invited_by_id), UNIQUE INDEX uniq_event_engagement (event_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE event_engagement ADD CONSTRAINT FK_8081D86E71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event_engagement ADD CONSTRAINT FK_8081D86EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE event_engagement ADD CONSTRAINT FK_8081D86EA7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE event_participant DROP FOREIGN KEY `FK_7C16B89171F7E88B`');
        $this->addSql('ALTER TABLE event_participant DROP FOREIGN KEY `FK_7C16B891A76ED395`');
        $this->addSql('DROP TABLE event_participant');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_participant (event_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_7C16B89171F7E88B (event_id), INDEX IDX_7C16B891A76ED395 (user_id), PRIMARY KEY (event_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE event_participant ADD CONSTRAINT `FK_7C16B89171F7E88B` FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_participant ADD CONSTRAINT `FK_7C16B891A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_engagement DROP FOREIGN KEY FK_8081D86E71F7E88B');
        $this->addSql('ALTER TABLE event_engagement DROP FOREIGN KEY FK_8081D86EA76ED395');
        $this->addSql('ALTER TABLE event_engagement DROP FOREIGN KEY FK_8081D86EA7B4A7E3');
        $this->addSql('DROP TABLE event_engagement');
    }
}

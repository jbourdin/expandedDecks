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
final class Version20260309202435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE menu_category (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu_category_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, name VARCHAR(100) NOT NULL, menu_category_id INT NOT NULL, INDEX IDX_151C09947ABA83AE (menu_category_id), UNIQUE INDEX menu_category_translation_unique (menu_category_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE page (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(150) NOT NULL, is_published TINYINT NOT NULL, canonical_url VARCHAR(255) DEFAULT NULL, no_index TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, menu_category_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_140AB620989D9B62 (slug), INDEX IDX_140AB6207ABA83AE (menu_category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE page_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, title VARCHAR(200) NOT NULL, slug VARCHAR(150) DEFAULT NULL, content LONGTEXT NOT NULL, meta_title VARCHAR(70) DEFAULT NULL, meta_description VARCHAR(160) DEFAULT NULL, og_image VARCHAR(255) DEFAULT NULL, page_id INT NOT NULL, INDEX IDX_A3D51B1DC4663E4 (page_id), UNIQUE INDEX page_translation_unique (page_id, locale), UNIQUE INDEX page_translation_slug_unique (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE menu_category_translation ADD CONSTRAINT FK_151C09947ABA83AE FOREIGN KEY (menu_category_id) REFERENCES menu_category (id)');
        $this->addSql('ALTER TABLE page ADD CONSTRAINT FK_140AB6207ABA83AE FOREIGN KEY (menu_category_id) REFERENCES menu_category (id)');
        $this->addSql('ALTER TABLE page_translation ADD CONSTRAINT FK_A3D51B1DC4663E4 FOREIGN KEY (page_id) REFERENCES page (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_category_translation DROP FOREIGN KEY FK_151C09947ABA83AE');
        $this->addSql('ALTER TABLE page DROP FOREIGN KEY FK_140AB6207ABA83AE');
        $this->addSql('ALTER TABLE page_translation DROP FOREIGN KEY FK_A3D51B1DC4663E4');
        $this->addSql('DROP TABLE menu_category');
        $this->addSql('DROP TABLE menu_category_translation');
        $this->addSql('DROP TABLE page');
        $this->addSql('DROP TABLE page_translation');
    }
}

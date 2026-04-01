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
final class Version20260401163521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create homepage_layout and homepage_layout_translation tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE homepage_layout (id INT AUTO_INCREMENT NOT NULL, blocks JSON NOT NULL, is_published TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE homepage_layout_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, block_translations JSON NOT NULL, homepage_layout_id INT NOT NULL, INDEX IDX_972BBE8B523956E (homepage_layout_id), UNIQUE INDEX homepage_layout_translation_unique (homepage_layout_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE homepage_layout_translation ADD CONSTRAINT FK_972BBE8B523956E FOREIGN KEY (homepage_layout_id) REFERENCES homepage_layout (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE homepage_layout_translation DROP FOREIGN KEY FK_972BBE8B523956E');
        $this->addSql('DROP TABLE homepage_layout_translation');
        $this->addSql('DROP TABLE homepage_layout');
    }
}

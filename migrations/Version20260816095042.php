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
 * Translation foundation (F9.7, epic #612).
 *
 * Adds the four translation-revision tables (page, archetype, menu category,
 * deck) holding the full per-locale history including the English source,
 * the `deck_translation` live table for archetype-variant notes, and the
 * denormalized `source_outdated` flag on the live translation tables.
 */
final class Version20260816095042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F9.7 translation foundation: revision tables, deck_translation, source_outdated flags';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE archetype_translation_revision (name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, meta_description VARCHAR(255) DEFAULT NULL, og_description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, state VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, archetype_id INT NOT NULL, source_revision_id INT DEFAULT NULL, author_id INT DEFAULT NULL, INDEX IDX_B7EAEF48732C6CC7 (archetype_id), INDEX IDX_B7EAEF4821852C2F (source_revision_id), INDEX IDX_B7EAEF48F675F31B (author_id), INDEX archetype_translation_revision_subject_locale (archetype_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE deck_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, notes LONGTEXT DEFAULT NULL, source_outdated TINYINT DEFAULT 0 NOT NULL, deck_id INT NOT NULL, translator_id INT DEFAULT NULL, INDEX IDX_F8F58FCC111948DC (deck_id), INDEX IDX_F8F58FCC5370E40B (translator_id), UNIQUE INDEX deck_translation_unique (deck_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE deck_translation_revision (notes LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, state VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, deck_id INT NOT NULL, source_revision_id INT DEFAULT NULL, author_id INT DEFAULT NULL, INDEX IDX_58CCB98C111948DC (deck_id), INDEX IDX_58CCB98C21852C2F (source_revision_id), INDEX IDX_58CCB98CF675F31B (author_id), INDEX deck_translation_revision_subject_locale (deck_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu_category_translation_revision (name VARCHAR(100) NOT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, state VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, menu_category_id INT NOT NULL, source_revision_id INT DEFAULT NULL, author_id INT DEFAULT NULL, INDEX IDX_38F03D277ABA83AE (menu_category_id), INDEX IDX_38F03D2721852C2F (source_revision_id), INDEX IDX_38F03D27F675F31B (author_id), INDEX menu_category_translation_revision_subject_locale (menu_category_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE page_translation_revision (title VARCHAR(200) NOT NULL, content LONGTEXT NOT NULL, og_description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, state VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, page_id INT NOT NULL, source_revision_id INT DEFAULT NULL, author_id INT DEFAULT NULL, INDEX IDX_598E9755C4663E4 (page_id), INDEX IDX_598E975521852C2F (source_revision_id), INDEX IDX_598E9755F675F31B (author_id), INDEX page_translation_revision_subject_locale (page_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE archetype_translation_revision ADD CONSTRAINT FK_B7EAEF48732C6CC7 FOREIGN KEY (archetype_id) REFERENCES archetype (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE archetype_translation_revision ADD CONSTRAINT FK_B7EAEF4821852C2F FOREIGN KEY (source_revision_id) REFERENCES archetype_translation_revision (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE archetype_translation_revision ADD CONSTRAINT FK_B7EAEF48F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE deck_translation ADD CONSTRAINT FK_F8F58FCC111948DC FOREIGN KEY (deck_id) REFERENCES deck (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deck_translation ADD CONSTRAINT FK_F8F58FCC5370E40B FOREIGN KEY (translator_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE deck_translation_revision ADD CONSTRAINT FK_58CCB98C111948DC FOREIGN KEY (deck_id) REFERENCES deck (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deck_translation_revision ADD CONSTRAINT FK_58CCB98C21852C2F FOREIGN KEY (source_revision_id) REFERENCES deck_translation_revision (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE deck_translation_revision ADD CONSTRAINT FK_58CCB98CF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu_category_translation_revision ADD CONSTRAINT FK_38F03D277ABA83AE FOREIGN KEY (menu_category_id) REFERENCES menu_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_category_translation_revision ADD CONSTRAINT FK_38F03D2721852C2F FOREIGN KEY (source_revision_id) REFERENCES menu_category_translation_revision (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu_category_translation_revision ADD CONSTRAINT FK_38F03D27F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE page_translation_revision ADD CONSTRAINT FK_598E9755C4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_translation_revision ADD CONSTRAINT FK_598E975521852C2F FOREIGN KEY (source_revision_id) REFERENCES page_translation_revision (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE page_translation_revision ADD CONSTRAINT FK_598E9755F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE archetype_translation ADD source_outdated TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE menu_category_translation ADD source_outdated TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE page_translation ADD source_outdated TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype_translation_revision DROP FOREIGN KEY FK_B7EAEF48732C6CC7');
        $this->addSql('ALTER TABLE archetype_translation_revision DROP FOREIGN KEY FK_B7EAEF4821852C2F');
        $this->addSql('ALTER TABLE archetype_translation_revision DROP FOREIGN KEY FK_B7EAEF48F675F31B');
        $this->addSql('ALTER TABLE deck_translation DROP FOREIGN KEY FK_F8F58FCC111948DC');
        $this->addSql('ALTER TABLE deck_translation DROP FOREIGN KEY FK_F8F58FCC5370E40B');
        $this->addSql('ALTER TABLE deck_translation_revision DROP FOREIGN KEY FK_58CCB98C111948DC');
        $this->addSql('ALTER TABLE deck_translation_revision DROP FOREIGN KEY FK_58CCB98C21852C2F');
        $this->addSql('ALTER TABLE deck_translation_revision DROP FOREIGN KEY FK_58CCB98CF675F31B');
        $this->addSql('ALTER TABLE menu_category_translation_revision DROP FOREIGN KEY FK_38F03D277ABA83AE');
        $this->addSql('ALTER TABLE menu_category_translation_revision DROP FOREIGN KEY FK_38F03D2721852C2F');
        $this->addSql('ALTER TABLE menu_category_translation_revision DROP FOREIGN KEY FK_38F03D27F675F31B');
        $this->addSql('ALTER TABLE page_translation_revision DROP FOREIGN KEY FK_598E9755C4663E4');
        $this->addSql('ALTER TABLE page_translation_revision DROP FOREIGN KEY FK_598E975521852C2F');
        $this->addSql('ALTER TABLE page_translation_revision DROP FOREIGN KEY FK_598E9755F675F31B');
        $this->addSql('DROP TABLE archetype_translation_revision');
        $this->addSql('DROP TABLE deck_translation');
        $this->addSql('DROP TABLE deck_translation_revision');
        $this->addSql('DROP TABLE menu_category_translation_revision');
        $this->addSql('DROP TABLE page_translation_revision');
        $this->addSql('ALTER TABLE archetype_translation DROP source_outdated');
        $this->addSql('ALTER TABLE menu_category_translation DROP source_outdated');
        $this->addSql('ALTER TABLE page_translation DROP source_outdated');
    }
}

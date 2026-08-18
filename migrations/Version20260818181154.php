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
 * Card translations (F9.17, #776): live translation tables and revision
 * tables for banned-card explanations and staple-card notes.
 */
final class Version20260818181154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F9.17 card translations: banned/staple translation and revision tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE banned_card_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, explanation LONGTEXT DEFAULT NULL, source_outdated TINYINT DEFAULT 0 NOT NULL, banned_card_id INT NOT NULL, translator_id INT DEFAULT NULL, INDEX IDX_C10C7E0EC14B8818 (banned_card_id), INDEX IDX_C10C7E0E5370E40B (translator_id), UNIQUE INDEX banned_card_translation_unique (banned_card_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE banned_card_translation_revision (explanation LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, state VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, review_comment LONGTEXT DEFAULT NULL, banned_card_id INT NOT NULL, source_revision_id INT DEFAULT NULL, author_id INT DEFAULT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_2FAFAFF9C14B8818 (banned_card_id), INDEX IDX_2FAFAFF921852C2F (source_revision_id), INDEX IDX_2FAFAFF9F675F31B (author_id), INDEX IDX_2FAFAFF9FC6B21F1 (reviewed_by_id), INDEX banned_card_translation_revision_subject_locale (banned_card_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE staple_card_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, note LONGTEXT DEFAULT NULL, source_outdated TINYINT DEFAULT 0 NOT NULL, staple_card_id INT NOT NULL, translator_id INT DEFAULT NULL, INDEX IDX_3D49EB47F6A40FFE (staple_card_id), INDEX IDX_3D49EB475370E40B (translator_id), UNIQUE INDEX staple_card_translation_unique (staple_card_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE staple_card_translation_revision (note LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, state VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, review_comment LONGTEXT DEFAULT NULL, staple_card_id INT NOT NULL, source_revision_id INT DEFAULT NULL, author_id INT DEFAULT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_4E9D238CF6A40FFE (staple_card_id), INDEX IDX_4E9D238C21852C2F (source_revision_id), INDEX IDX_4E9D238CF675F31B (author_id), INDEX IDX_4E9D238CFC6B21F1 (reviewed_by_id), INDEX staple_card_translation_revision_subject_locale (staple_card_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE banned_card_translation ADD CONSTRAINT FK_C10C7E0EC14B8818 FOREIGN KEY (banned_card_id) REFERENCES banned_card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE banned_card_translation ADD CONSTRAINT FK_C10C7E0E5370E40B FOREIGN KEY (translator_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE banned_card_translation_revision ADD CONSTRAINT FK_2FAFAFF9C14B8818 FOREIGN KEY (banned_card_id) REFERENCES banned_card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE banned_card_translation_revision ADD CONSTRAINT FK_2FAFAFF921852C2F FOREIGN KEY (source_revision_id) REFERENCES banned_card_translation_revision (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE banned_card_translation_revision ADD CONSTRAINT FK_2FAFAFF9F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE banned_card_translation_revision ADD CONSTRAINT FK_2FAFAFF9FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE staple_card_translation ADD CONSTRAINT FK_3D49EB47F6A40FFE FOREIGN KEY (staple_card_id) REFERENCES staple_card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE staple_card_translation ADD CONSTRAINT FK_3D49EB475370E40B FOREIGN KEY (translator_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE staple_card_translation_revision ADD CONSTRAINT FK_4E9D238CF6A40FFE FOREIGN KEY (staple_card_id) REFERENCES staple_card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE staple_card_translation_revision ADD CONSTRAINT FK_4E9D238C21852C2F FOREIGN KEY (source_revision_id) REFERENCES staple_card_translation_revision (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE staple_card_translation_revision ADD CONSTRAINT FK_4E9D238CF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE staple_card_translation_revision ADD CONSTRAINT FK_4E9D238CFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE channel DROP draft_locales');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE banned_card_translation DROP FOREIGN KEY FK_C10C7E0EC14B8818');
        $this->addSql('ALTER TABLE banned_card_translation DROP FOREIGN KEY FK_C10C7E0E5370E40B');
        $this->addSql('ALTER TABLE banned_card_translation_revision DROP FOREIGN KEY FK_2FAFAFF9C14B8818');
        $this->addSql('ALTER TABLE banned_card_translation_revision DROP FOREIGN KEY FK_2FAFAFF921852C2F');
        $this->addSql('ALTER TABLE banned_card_translation_revision DROP FOREIGN KEY FK_2FAFAFF9F675F31B');
        $this->addSql('ALTER TABLE banned_card_translation_revision DROP FOREIGN KEY FK_2FAFAFF9FC6B21F1');
        $this->addSql('ALTER TABLE staple_card_translation DROP FOREIGN KEY FK_3D49EB47F6A40FFE');
        $this->addSql('ALTER TABLE staple_card_translation DROP FOREIGN KEY FK_3D49EB475370E40B');
        $this->addSql('ALTER TABLE staple_card_translation_revision DROP FOREIGN KEY FK_4E9D238CF6A40FFE');
        $this->addSql('ALTER TABLE staple_card_translation_revision DROP FOREIGN KEY FK_4E9D238C21852C2F');
        $this->addSql('ALTER TABLE staple_card_translation_revision DROP FOREIGN KEY FK_4E9D238CF675F31B');
        $this->addSql('ALTER TABLE staple_card_translation_revision DROP FOREIGN KEY FK_4E9D238CFC6B21F1');
        $this->addSql('DROP TABLE banned_card_translation');
        $this->addSql('DROP TABLE banned_card_translation_revision');
        $this->addSql('DROP TABLE staple_card_translation');
        $this->addSql('DROP TABLE staple_card_translation_revision');
        $this->addSql('ALTER TABLE channel ADD draft_locales JSON NOT NULL');
    }
}

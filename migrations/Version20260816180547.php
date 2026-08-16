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
 * Translation review workflow (F9.9, epic #612): reviewer, timestamp, and
 * comment columns on the four revision tables.
 */
final class Version20260816180547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F9.9 review workflow: reviewedBy/reviewedAt/reviewComment on revision tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype_translation_revision ADD reviewed_at DATETIME DEFAULT NULL, ADD review_comment LONGTEXT DEFAULT NULL, ADD reviewed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE archetype_translation_revision ADD CONSTRAINT FK_B7EAEF48FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B7EAEF48FC6B21F1 ON archetype_translation_revision (reviewed_by_id)');
        $this->addSql('ALTER TABLE deck_translation_revision ADD reviewed_at DATETIME DEFAULT NULL, ADD review_comment LONGTEXT DEFAULT NULL, ADD reviewed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE deck_translation_revision ADD CONSTRAINT FK_58CCB98CFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_58CCB98CFC6B21F1 ON deck_translation_revision (reviewed_by_id)');
        $this->addSql('ALTER TABLE menu_category_translation_revision ADD reviewed_at DATETIME DEFAULT NULL, ADD review_comment LONGTEXT DEFAULT NULL, ADD reviewed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_category_translation_revision ADD CONSTRAINT FK_38F03D27FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_38F03D27FC6B21F1 ON menu_category_translation_revision (reviewed_by_id)');
        $this->addSql('ALTER TABLE page_translation_revision ADD reviewed_at DATETIME DEFAULT NULL, ADD review_comment LONGTEXT DEFAULT NULL, ADD reviewed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE page_translation_revision ADD CONSTRAINT FK_598E9755FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_598E9755FC6B21F1 ON page_translation_revision (reviewed_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype_translation_revision DROP FOREIGN KEY FK_B7EAEF48FC6B21F1');
        $this->addSql('DROP INDEX IDX_B7EAEF48FC6B21F1 ON archetype_translation_revision');
        $this->addSql('ALTER TABLE archetype_translation_revision DROP reviewed_at, DROP review_comment, DROP reviewed_by_id');
        $this->addSql('ALTER TABLE deck_translation_revision DROP FOREIGN KEY FK_58CCB98CFC6B21F1');
        $this->addSql('DROP INDEX IDX_58CCB98CFC6B21F1 ON deck_translation_revision');
        $this->addSql('ALTER TABLE deck_translation_revision DROP reviewed_at, DROP review_comment, DROP reviewed_by_id');
        $this->addSql('ALTER TABLE menu_category_translation_revision DROP FOREIGN KEY FK_38F03D27FC6B21F1');
        $this->addSql('DROP INDEX IDX_38F03D27FC6B21F1 ON menu_category_translation_revision');
        $this->addSql('ALTER TABLE menu_category_translation_revision DROP reviewed_at, DROP review_comment, DROP reviewed_by_id');
        $this->addSql('ALTER TABLE page_translation_revision DROP FOREIGN KEY FK_598E9755FC6B21F1');
        $this->addSql('DROP INDEX IDX_598E9755FC6B21F1 ON page_translation_revision');
        $this->addSql('ALTER TABLE page_translation_revision DROP reviewed_at, DROP review_comment, DROP reviewed_by_id');
    }
}

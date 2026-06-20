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
 * F19.8 — Author/translator attribution.
 *
 * Adds the public author/contributor profile columns to `user`, plus
 * nullable author FKs on archetype, deck, and page, and translator FKs on
 * the archetype/page translation rows. All content FKs ON DELETE SET NULL so
 * removing a user never deletes content.
 */
final class Version20260620214220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F19.8: author/translator attribution fields on user, archetype, deck, page, and translations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype ADD author_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE archetype ADD CONSTRAINT FK_E1D5BCE3F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E1D5BCE3F675F31B ON archetype (author_id)');
        $this->addSql('ALTER TABLE archetype_translation ADD translator_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE archetype_translation ADD CONSTRAINT FK_143B1B685370E40B FOREIGN KEY (translator_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_143B1B685370E40B ON archetype_translation (translator_id)');
        $this->addSql('ALTER TABLE deck ADD author_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE deck ADD CONSTRAINT FK_4FAC3637F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_4FAC3637F675F31B ON deck (author_id)');
        $this->addSql('ALTER TABLE page ADD author_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE page ADD CONSTRAINT FK_140AB620F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_140AB620F675F31B ON page (author_id)');
        $this->addSql('ALTER TABLE page_translation ADD translator_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE page_translation ADD CONSTRAINT FK_A3D51B1D5370E40B FOREIGN KEY (translator_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A3D51B1D5370E40B ON page_translation (translator_id)');
        $this->addSql('ALTER TABLE `user` ADD is_public_author TINYINT DEFAULT 0 NOT NULL, ADD credential VARCHAR(150) DEFAULT NULL, ADD bio LONGTEXT DEFAULT NULL, ADD same_as JSON DEFAULT NULL, ADD avatar_url VARCHAR(255) DEFAULT NULL, ADD primary_url VARCHAR(255) DEFAULT NULL, ADD public_slug VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E3C0D9B3 ON `user` (public_slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype DROP FOREIGN KEY FK_E1D5BCE3F675F31B');
        $this->addSql('DROP INDEX IDX_E1D5BCE3F675F31B ON archetype');
        $this->addSql('ALTER TABLE archetype DROP author_id');
        $this->addSql('ALTER TABLE archetype_translation DROP FOREIGN KEY FK_143B1B685370E40B');
        $this->addSql('DROP INDEX IDX_143B1B685370E40B ON archetype_translation');
        $this->addSql('ALTER TABLE archetype_translation DROP translator_id');
        $this->addSql('ALTER TABLE deck DROP FOREIGN KEY FK_4FAC3637F675F31B');
        $this->addSql('DROP INDEX IDX_4FAC3637F675F31B ON deck');
        $this->addSql('ALTER TABLE deck DROP author_id');
        $this->addSql('ALTER TABLE page DROP FOREIGN KEY FK_140AB620F675F31B');
        $this->addSql('DROP INDEX IDX_140AB620F675F31B ON page');
        $this->addSql('ALTER TABLE page DROP author_id');
        $this->addSql('ALTER TABLE page_translation DROP FOREIGN KEY FK_A3D51B1D5370E40B');
        $this->addSql('DROP INDEX IDX_A3D51B1D5370E40B ON page_translation');
        $this->addSql('ALTER TABLE page_translation DROP translator_id');
        $this->addSql('DROP INDEX UNIQ_8D93D649E3C0D9B3 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP is_public_author, DROP credential, DROP bio, DROP same_as, DROP avatar_url, DROP primary_url, DROP public_slug');
    }
}

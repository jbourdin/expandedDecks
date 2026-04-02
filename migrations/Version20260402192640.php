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
final class Version20260402192640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Simplify CMS pages: move ogImage to page, drop canonical_url, localized slug, metaTitle, metaDescription';
    }

    public function up(Schema $schema): void
    {
        // Migrate ogImage from page_translation (EN) to page, then rename canonical_url column
        $this->addSql('UPDATE page p SET p.canonical_url = (SELECT pt.og_image FROM page_translation pt WHERE pt.page_id = p.id AND pt.og_image IS NOT NULL LIMIT 1) WHERE p.canonical_url IS NULL');
        $this->addSql('ALTER TABLE page CHANGE canonical_url og_image VARCHAR(255) DEFAULT NULL');

        // Drop removed fields from page_translation
        $this->addSql('DROP INDEX page_translation_slug_unique ON page_translation');
        $this->addSql('ALTER TABLE page_translation DROP slug, DROP meta_title, DROP meta_description, DROP og_image');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page CHANGE og_image canonical_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE page_translation ADD slug VARCHAR(150) DEFAULT NULL, ADD meta_title VARCHAR(70) DEFAULT NULL, ADD meta_description VARCHAR(160) DEFAULT NULL, ADD og_image VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX page_translation_slug_unique ON page_translation (slug)');
    }
}

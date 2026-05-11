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
 * Add per-locale `title` and `og_description` columns to
 * `homepage_layout_translation` so editors can override the channel brand in
 * the HTML <title> and supply a social-share description on the homepage.
 */
final class Version20260512100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add title and og_description columns to homepage_layout_translation for per-page homepage metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE homepage_layout_translation ADD title VARCHAR(255) DEFAULT NULL, ADD og_description TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE homepage_layout_translation DROP title, DROP og_description');
    }
}

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
 * Add `og_image` column to `homepage_layout` so editors can configure the
 * Open Graph image rendered on the homepage. Mirrors `Page.ogImage`.
 *
 * @see https://github.com/jbourdin/expandedDecks/issues/554
 */
final class Version20260509220511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add og_image column to homepage_layout for the editor-configurable homepage OG image (#554).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE homepage_layout ADD og_image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE homepage_layout DROP og_image');
    }
}

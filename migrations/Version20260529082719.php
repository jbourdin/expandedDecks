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
 * Add editor-defined OG image and description fields on Deck, ArchetypeTranslation,
 * and PageTranslation so editors can override social-share metadata per locale.
 *
 * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
 * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
 */
final class Version20260529082719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add og_image/og_description columns to deck, archetype_translation, and page_translation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck ADD og_image VARCHAR(255) DEFAULT NULL, ADD og_description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE archetype_translation ADD og_image VARCHAR(255) DEFAULT NULL, ADD og_description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE page_translation ADD og_image VARCHAR(255) DEFAULT NULL, ADD og_description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck DROP og_image, DROP og_description');
        $this->addSql('ALTER TABLE archetype_translation DROP og_image, DROP og_description');
        $this->addSql('ALTER TABLE page_translation DROP og_image, DROP og_description');
    }
}

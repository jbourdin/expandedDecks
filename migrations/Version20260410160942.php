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
 * Add position field to Archetype and Deck for drag-and-drop ordering.
 *
 * @see docs/features.md F18.11 — Archetype relevance ordering
 * @see docs/features.md F18.19 — Archetype variant ordering
 */
final class Version20260410160942 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position field to Archetype and Deck for ordering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype ADD position INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE deck ADD position INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype DROP position');
        $this->addSql('ALTER TABLE deck DROP position');
    }
}

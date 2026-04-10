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
 * Make Deck.owner nullable and add Deck.canonical boolean for archetype variant decks.
 *
 * @see docs/features.md F18.13 — Archetype variant decks
 */
final class Version20260410073311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make Deck.owner nullable and add canonical boolean for archetype variant decks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck ADD canonical TINYINT DEFAULT 0 NOT NULL, CHANGE owner_id owner_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck DROP canonical, CHANGE owner_id owner_id INT NOT NULL');
    }
}

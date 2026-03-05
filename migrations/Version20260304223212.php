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
 * F3.7 — Add tournament result fields to EventDeckEntry.
 */
final class Version20260304223212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add final_placement and match_record columns to event_deck_entry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_deck_entry ADD final_placement SMALLINT DEFAULT NULL, ADD match_record VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_deck_entry DROP final_placement, DROP match_record');
    }
}

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
 * Adds the `sort_order` column on `deck_card` for the F2.28 preserve-import-order feature.
 *
 * Nullable so historical rows remain valid until the admin backfill (F2.28) populates them.
 * Indexed on (deck_version_id, sort_order) so the deck-show "import order" toggle can sort
 * within a version without a filesort.
 */
final class Version20260523172247 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deck_card.sort_order column + index (F2.28 preserve imported list order)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck_card ADD sort_order INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_deck_card_version_sort_order ON deck_card (deck_version_id, sort_order)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_deck_card_version_sort_order ON deck_card');
        $this->addSql('ALTER TABLE deck_card DROP sort_order');
    }
}

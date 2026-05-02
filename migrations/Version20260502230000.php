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
 * F6.14 — drop any banned_card parents that ended up with zero child
 * printings. Such rows are placeholders that never picked up a printing
 * during sync (or stale state from an interrupted backfill); they'd render
 * as ghost tiles on the public page. The defensive `INNER JOIN` in
 * {@see \App\Repository\BannedCardRepository::findActiveOrderedByEffectiveDate()}
 * also hides them at query time, but cleaning the storage is worth doing.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — delete banned_card parent rows that have no child printings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                DELETE bc FROM banned_card bc
                LEFT JOIN banned_card_printing bcp ON bcp.banned_card_id = bc.id
                WHERE bcp.id IS NULL
                SQL
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: we deleted invalid placeholder rows; nothing to restore.
    }
}

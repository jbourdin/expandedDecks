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
 * F6.14 — correct Medicham V metadata.
 *
 * The previous seed flagged Medicham V as Japan-only pending an international
 * announcement. The card is in fact banned internationally with the
 * 2026-04-07 TCG rulebook republication, which carries no separate URL.
 *
 * Overrides the previous values unconditionally because the prior data was
 * incorrect, not admin-curated.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — correct Medicham V seed metadata (banned internationally on 2026-04-07)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET
                    effective_date = :effective_date,
                    source_url = NULL,
                    explanation = :explanation
                WHERE card_name = 'Medicham V'
                SQL,
            [
                'effective_date' => '2026-04-07',
                'explanation' => 'Yoga Loop + damage-counter abilities enable extra-turn wins. Banned internationally with the new TCG rulebook published 2026-04-07; no separate announcement URL.',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: rolling back would not restore admin edits made on top of these defaults.
    }
}

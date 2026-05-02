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
 * F6.14 — correct Medicham V effective date to 2026-04-10 and the explanation
 * to mention the announcement / effective dates straight from the official
 * Mega Evolution: Perfect Order page. The previous correction guessed the
 * date from the rulebook publication.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — correct Medicham V effective date to 2026-04-10 (Mega Evolution: Perfect Order announcement)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET
                    effective_date = :effective_date,
                    explanation = :explanation
                WHERE card_name = 'Medicham V'
                SQL,
            [
                'effective_date' => '2026-04-10',
                'explanation' => 'Yoga Loop attack takes an extra turn and wins quickly. Banned internationally with the Mega Evolution: Perfect Order rule changes (announced 2026-03-12, effective 2026-04-10).',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: rolling back would not restore admin edits made on top of these defaults.
    }
}

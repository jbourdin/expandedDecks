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
 * F6.14 — link Medicham V to the official Mega Evolution: Perfect Order
 * announcement that ships the international ban (2026-04-07). The previous
 * correction left the source URL null because no announcement was known at
 * the time.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — set Medicham V source URL to the Mega Evolution: Perfect Order announcement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET
                    source_url = :source_url,
                    explanation = :explanation
                WHERE card_name = 'Medicham V'
                SQL,
            [
                'source_url' => 'https://www.pokemon.com/us/play-pokemon/about/mega-evolution/mega-evolution-perfect-order-banned-list-and-rule-changes-announcement',
                'explanation' => 'Yoga Loop + damage-counter abilities enable extra-turn wins. Banned internationally with the Mega Evolution: Perfect Order rule changes (2026-04-07).',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: rolling back would not restore admin edits made on top of these defaults.
    }
}

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
 * F6.14 — roll Lysandre's Trump Card source URL back to Bulbanews.
 *
 * The original 2015 pokemon.com news URL (`/us/pokemon-news/lysandres-trump-
 * card-banned/`) is dead — pokemon.com only kept news entries from a later
 * reorganisation onward. The verbatim 2015 announcement text remains in the
 * `explanation` field; only the source link reverts to the Bulbanews mirror,
 * which is the most stable archive of that announcement.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — roll back Lysandre\'s Trump Card source URL to Bulbanews (the 2015 pokemon.com news URL is 404)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET source_url = :source_url
                WHERE card_name IN ('Lysandre''s Trump Card', 'Lysandre’s Trump Card')
                  AND source_url = 'https://www.pokemon.com/us/pokemon-news/lysandres-trump-card-banned/'
                SQL,
            ['source_url' => 'https://bulbanews.bulbagarden.net/wiki/Lysandre%27s_Trump_Card_banned_from_TCG_competitive_play'],
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: the previous URL is dead, restoring it would harm the page.
    }
}

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
 * F6.14 — adopt the official pokemon.com announcements as the canonical
 * source for the 2018 Celestial Storm batch and the 2025 Mega Evolution
 * Flapple ban. Replaces the previous PokeBeach citations and uses the
 * verbatim rationale paragraphs from each page.
 *
 * Sources:
 * - https://www.pokemon.com/us/sun-moon-celestial-storm-banned-list-and-rule-changes-quarterly-announcement/
 *   (Ghetsis, Hex Maniac, Puzzle of Time — effective 2018-08-17)
 * - https://www.pokemon.com/us/play-pokemon/about/mega-evolution/mega-evolution-banned-list-and-rule-changes-announcement
 *   (Flapple — effective 2025-10-10)
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — Celestial Storm + Mega Evolution announcements: official URLs and verbatim wording for Ghetsis, Hex Maniac, Puzzle of Time, Flapple';
    }

    public function up(Schema $schema): void
    {
        $celestialStorm = 'https://www.pokemon.com/us/sun-moon-celestial-storm-banned-list-and-rule-changes-quarterly-announcement/';
        $megaEvolution = 'https://www.pokemon.com/us/play-pokemon/about/mega-evolution/mega-evolution-banned-list-and-rule-changes-announcement';

        $ghetsisHexManiac = 'The overall goal of the Expanded format is to have a fun environment where players can enjoy using a wide variety of strategies. Ghetsis and Hex Maniac were identified as cards that stifle creativity and prevent several kinds of strategies from being viable. These cards also have the potential to make a major negative impact on an opponent before they get a chance to take their first turn, which can lead to a frustrating experience. Wally enables a combo with Trevenant that creates similar problems, so it falls into this category as well. Without these cards in the environment, hopefully gameplay will become more enjoyable.';

        $puzzleOfTime = 'Puzzle of Time is a flexible card that can be used in a wide variety of strategies. Its usage rate is quite high in popular decks, and it enables a lot of powerful combos. Removing this card from the environment will affect how many decks are constructed, which will hopefully make the Expanded format feel fresh and different.';

        $flapple = "Flapple was banned in the Expanded format. In combination with the upcoming Forest of Vitality Stadium card, there are several ways to use Flapple's Apple Drop Ability repeatedly until all of the opponent's Pokémon are Knocked Out. This strategy can be executed consistently on the second turn of the game, which creates an undesirable environment for the Expanded format.";

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET source_url = :source_url, explanation = :explanation
                WHERE card_name = 'Ghetsis'
                SQL,
            ['source_url' => $celestialStorm, 'explanation' => $ghetsisHexManiac],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET source_url = :source_url, explanation = :explanation
                WHERE card_name = 'Hex Maniac'
                SQL,
            ['source_url' => $celestialStorm, 'explanation' => $ghetsisHexManiac],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET source_url = :source_url, explanation = :explanation
                WHERE card_name = 'Puzzle of Time'
                SQL,
            ['source_url' => $celestialStorm, 'explanation' => $puzzleOfTime],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET source_url = :source_url, explanation = :explanation
                WHERE card_name = 'Flapple'
                SQL,
            ['source_url' => $megaEvolution, 'explanation' => $flapple],
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: rolling back would not restore admin edits made on top of these defaults.
    }
}

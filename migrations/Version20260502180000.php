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
 * F6.14 — replace Archeops and Forest of Giant Plants explanations with the
 * verbatim rationale from the official Sun & Moon — Burning Shadows banned-
 * list announcement (https://www.pokemon.com/us/sun-moon-burning-shadows-
 * banned-list-and-rule-changes-quarterly-announcement/). The previous custom
 * one-liners are replaced by the official wording so admins linking to the
 * announcement see the same text as the source page.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — adopt official Burning Shadows announcement wording for Archeops + Forest of Giant Plants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name = 'Archeops'
                SQL,
            [
                'explanation' => "The existence of Archeops's Ancient Power Ability has a very negative effect on decks that rely on evolved Pokémon. There are ways to combat it—Hex Maniac, Evosoda, or Wobbuffet are a few examples—but decks that focus on evolved Pokémon are forced to use these cards just to evolve their Pokémon. The combination of Maxie's Hidden Ball Trick with Archeops can stop Evolution before the opponent ever gets a chance to evolve their Pokémon, which limits the number of viable strategies.",
            ],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name = 'Forest of Giant Plants'
                SQL,
            [
                'explanation' => "The Forest of Giant Plants Stadium card enables many dangerous strategies with Grass-type Pokémon in the Expanded format. These strategies can range from locking down the opponent's options to winning the game on the first turn, and all of them can happen before the opponent ever gets a chance to play. No single strategy was powerful enough to ban this Stadium card, but so many of them existing at the same time gave sufficient cause to ban it.",
            ],
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: rolling back would not restore admin edits made on top of these defaults.
    }
}

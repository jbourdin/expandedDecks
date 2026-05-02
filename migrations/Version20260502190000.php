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
 * F6.14 — adopt the official Sword & Shield — Vivid Voltage banned-list
 * announcement (https://www.pokemon.com/us/sword-shield-vivid-voltage-banned-
 * list-and-rule-changes-announcement/) as the canonical source for the four
 * cards it covers (Milotic, Oranguru, Sableye, Shaymin-EX). Replaces the
 * PokeBeach URLs and adopts the verbatim rationale from the page.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — Vivid Voltage announcement: source URL + verbatim wording for Milotic, Oranguru, Sableye, Shaymin-EX';
    }

    public function up(Schema $schema): void
    {
        $url = 'https://www.pokemon.com/us/sword-shield-vivid-voltage-banned-list-and-rule-changes-announcement/';

        $milotic = "Milotic's Energy Grace Ability doesn't work with Pokémon-EX, but it still works with Pokémon-GX and Pokémon V. This created some undesirable combos, such as the one with Trevenant & Dusknoir-GX and Ace Trainer. As more Pokémon V come out in the future, there's a high likelihood of even more combos with Milotic being discovered.";

        $oranguruSableye = "Oranguru's Resource Management attack and Sableye's Junk Hunt attack allow for infinite resource recursion strategies that are relatively simple to achieve. In an attempt to curb the effectiveness of some of these control and lock strategies, these cards have been banned.";

        $shayminEx = "The sheer amount of card drawing provided by Shaymin-EX's Set Up Ability allowed dangerous combo decks to function at an alarmingly consistent rate. With the introduction of Scoop Up Net, it became too easy to use Set Up repeatedly in a single turn. Crobat V and Dedenne-GX provide effects similar to Shaymin-EX, so this type of card isn't gone completely, but their Dark Asset and Dedechange Abilities are limited to one use per turn.";

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET source_url = :source_url, explanation = :explanation
                WHERE card_name = 'Milotic'
                SQL,
            ['source_url' => $url, 'explanation' => $milotic],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET source_url = :source_url, explanation = :explanation
                WHERE card_name = 'Oranguru'
                SQL,
            ['source_url' => $url, 'explanation' => $oranguruSableye],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET source_url = :source_url, explanation = :explanation
                WHERE card_name = 'Sableye'
                SQL,
            ['source_url' => $url, 'explanation' => $oranguruSableye],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET source_url = :source_url, explanation = :explanation
                WHERE card_name IN ('Shaymin-EX', 'Shaymin EX')
                SQL,
            ['source_url' => $url, 'explanation' => $shayminEx],
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: rolling back would not restore admin edits made on top of these defaults.
    }
}

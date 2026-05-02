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
 * F6.14 — backfill historical Expanded-format ban metadata.
 *
 * Each existing banned_card row gets its effective date, official-announcement
 * URL, and short explanation populated. UPDATEs use COALESCE so admin edits
 * are preserved (the column stays whatever it currently is unless null).
 *
 * On fresh installations there are no rows when this migration runs — the
 * sibling {@see \App\Service\BannedCardSeedData} service is invoked from
 * {@see \App\Service\BannedCardsSyncService} so the same defaults are applied
 * to newly-provisioned parents on the very first sync.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — backfill effective date, source URL and explanation on existing banned_card rows';
    }

    public function up(Schema $schema): void
    {
        // Update by `card_name`. Curly-apostrophe variants are listed twice so
        // the seed lands regardless of which form upstream pokemon.com returned.
        foreach ($this->seeds() as [$name, $effectiveDate, $sourceUrl, $explanation]) {
            $this->addSql(
                <<<'SQL'
                    UPDATE banned_card
                    SET
                        effective_date = COALESCE(effective_date, :effective_date),
                        source_url = COALESCE(source_url, :source_url),
                        explanation = COALESCE(explanation, :explanation)
                    WHERE card_name = :card_name
                    SQL,
                [
                    'card_name' => $name,
                    'effective_date' => $effectiveDate,
                    'source_url' => $sourceUrl,
                    'explanation' => $explanation,
                ],
            );
        }

        // Two Unown bans share the card_name but landed in different
        // announcements — disambiguate via the (set, number) on a child printing.
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card bc
                INNER JOIN banned_card_printing bcp ON bcp.banned_card_id = bc.id
                SET
                    bc.effective_date = COALESCE(bc.effective_date, :effective_date),
                    bc.source_url = COALESCE(bc.source_url, :source_url),
                    bc.explanation = COALESCE(bc.explanation, :explanation)
                WHERE bc.card_name = 'Unown'
                  AND bcp.set_code = 'LOT'
                  AND bcp.card_number = '90'
                SQL,
            [
                'effective_date' => '2019-02-15',
                'source_url' => 'https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/',
                'explanation' => '"DAMAGE" Ability auto-wins with 66+ damage counters on bench, enabling T1/T2 self-damage win combos.',
            ],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card bc
                INNER JOIN banned_card_printing bcp ON bcp.banned_card_id = bc.id
                SET
                    bc.effective_date = COALESCE(bc.effective_date, :effective_date),
                    bc.source_url = COALESCE(bc.source_url, :source_url),
                    bc.explanation = COALESCE(bc.explanation, :explanation)
                WHERE bc.card_name = 'Unown'
                  AND bcp.set_code = 'LOT'
                  AND bcp.card_number = '91'
                SQL,
            [
                'effective_date' => '2019-11-15',
                'source_url' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
                'explanation' => '"HAND" Ability auto-wins when holding 35+ cards; combined with draw engines for consistent OTKs.',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: rolling back would erase admin edits made on top of these defaults.
    }

    /**
     * @return list<array{0: string, 1: ?string, 2: ?string, 3: ?string}>
     */
    private function seeds(): array
    {
        $cosmicEclipse = 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/';
        $teamUp = 'https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/';
        $burningShadows = 'https://www.pokemon.com/us/sun-moon-burning-shadows-banned-list-and-rule-changes-quarterly-announcement/';
        $stellarCrown = 'https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-stellar-crown-banned-list-and-rule-changes-announcement';
        $paldeanFates = 'https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-paldean-fates-banned-list-and-rule-changes-announcement';
        $pokebeach2018 = 'https://www.pokebeach.com/2018/07/ghetsis-hex-maniac-and-more-banned-from-expanded-format';
        $pokebeach2020 = 'https://www.pokebeach.com/2020/10/resource-management-oranguru-shaymin-ex-sableye-and-energy-grace-milotic-banned-in-the-expanded-format';
        $pokebeach2025 = 'https://www.pokebeach.com/2025/09/flapple-banned-from-expanded-format-prism-energy-receives-errata';
        $pokebeach2026Medicham = 'https://www.pokebeach.com/2026/01/medicham-v-banned-from-japans-expanded-format';
        $bulbanewsLysandre = 'https://bulbanews.bulbagarden.net/wiki/Lysandre%27s_Trump_Card_banned_from_TCG_competitive_play';

        return [
            ['Archeops', '2017-08-18', $burningShadows, 'Ancient Power Ability locks evolution; a turn-1 Maxie\'s Hidden Ball Trick + Archeops stops the opponent from evolving.'],
            ['Chip-Chip Ice Axe', '2019-11-15', $cosmicEclipse, 'Manipulates the top of the opponent\'s deck, enabling oppressive hand-disruption / discard-lock combos.'],
            ['Delinquent', '2019-02-15', $teamUp, 'Discards the opponent\'s Stadium and three cards; Red Card + Delinquent reduced opponent to 1 card pre-turn.'],
            ['Duskull', '2024-09-27', $stellarCrown, 'Spiritborne Evolution + Dusclops "Cursed Blast" enabled consistent T1 wins (Dusclops Donk).'],
            ['Flabébé', '2019-11-15', $cosmicEclipse, 'Bench-evolution Ability fueled T1 hand-disruption decks (Marshadow / Red Card / Reset Stamp).'],
            ['Flapple', '2025-10-10', $pokebeach2025, 'Apple Drop Ability + Forest of Vitality enabled an infinite damage loop winning consistently on T2.'],
            ['Forest of Giant Plants', '2017-08-18', $burningShadows, 'Free T1 Grass evolutions enabled Vileplume item-lock and other turn-1 lockdown wins.'],
            ['Ghetsis', '2018-08-17', $pokebeach2018, 'Discards Item cards from the opponent\'s opening hand, crippling them before their first turn.'],
            ['Hex Maniac', '2018-08-17', $pokebeach2018, 'Shuts off all Pokémon Abilities for a turn, enabling oppressive T1 setups while disabling defenses.'],
            ['Island Challenge Amulet', '2019-11-15', $cosmicEclipse, 'Reduces a V/GX Pokémon to a 1-Prize attacker, distorting Prize math in stall/control decks.'],
            ['Jessie & James', '2019-11-15', $cosmicEclipse, 'Forces the opponent to discard 2 random cards, oppressive in hand-disruption / first-turn decks.'],
            ['Lt. Surge\'s Strategy', '2019-11-15', $cosmicEclipse, 'Lets you play a second Supporter, breaking the one-Supporter-per-turn balancing rule.'],
            ["Lt. Surge\u{2019}s Strategy", '2019-11-15', $cosmicEclipse, 'Lets you play a second Supporter, breaking the one-Supporter-per-turn balancing rule.'],
            ['Lysandre\'s Trump Card', '2015-06-15', $bulbanewsLysandre, 'Shuffles all discard piles back into decks, eliminating deck-out as a win condition and enabling infinite resource loops.'],
            ["Lysandre\u{2019}s Trump Card", '2015-06-15', $bulbanewsLysandre, 'Shuffles all discard piles back into decks, eliminating deck-out as a win condition and enabling infinite resource loops.'],
            ['Marshadow', '2019-11-15', $cosmicEclipse, '"Let Loose" Ability force-shuffles both hands to 4, weaponized via Scoop Up Net for repeated T1 disruption.'],
            ['Maxie\'s Hidden Ball Trick', '2019-02-15', $teamUp, 'Cheats a Stage-1/2 Fighting Pokémon directly into play; Team Up enabled devastating T1 combos.'],
            ["Maxie\u{2019}s Hidden Ball Trick", '2019-02-15', $teamUp, 'Cheats a Stage-1/2 Fighting Pokémon directly into play; Team Up enabled devastating T1 combos.'],
            ['Medicham V', null, $pokebeach2026Medicham, 'Yoga Loop + damage-counter abilities enable extra-turn wins (banned 2026-02-20 in Japan; international ban not yet announced).'],
            ['Milotic', '2020-11-27', $pokebeach2020, '"Energy Grace" enables infinite Energy acceleration loops with Scoop Up Net in control / stall decks.'],
            ['Mismagius', '2019-11-15', $cosmicEclipse, '"Mysterious Message" draw engine fueled overpowered Ultra Beast / hand-disruption decks.'],
            ['Oranguru', '2020-11-27', $pokebeach2020, '"Resource Management" attack recycles 3 cards, enabling infinite control loops with Scoop Up Net.'],
            ['Puzzle of Time', '2018-08-17', $pokebeach2018, 'Two-card combo recovers any 2 cards from the discard pile, enabling powerful loops in too many archetypes.'],
            ['Red Card', '2019-11-15', $cosmicEclipse, 'Forces the opponent to shuffle their hand and draw 4, central piece of T1 hand-disruption combos.'],
            ['Reset Stamp', '2019-11-15', $cosmicEclipse, 'Sets opponent\'s hand to Prizes-remaining, devastating in late-game disruption.'],
            ['Sableye', '2020-11-27', $pokebeach2020, '"Junk Hunt" attack recycles Items every turn, enabling effectively infinite resource recursion.'],
            ['Scoop Up Net', '2024-02-09', $paldeanFates, 'Returns non-V/GX Pokémon to hand; enabled Iron Valiant ex Tachyon Bits T1 wins and many ability loops.'],
            ['Shaymin-EX', '2020-11-27', $pokebeach2020, '"Set Up" draws to 6 on bench-play; reused via Scoop Up Net for infinite draw and consistency.'],
            ['Shaymin EX', '2020-11-27', $pokebeach2020, '"Set Up" draws to 6 on bench-play; reused via Scoop Up Net for infinite draw and consistency.'],
        ];
    }
}

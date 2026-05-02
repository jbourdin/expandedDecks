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
 * F6.14 — adopt the verbatim "Details of Changes" wording from the Team Up,
 * Cosmic Eclipse, Paldean Fates, and Stellar Crown announcements, and switch
 * Lysandre's Trump Card to its original 2015 pokemon.com URL.
 *
 * Sources:
 * - https://www.pokemon.com/us/pokemon-news/lysandres-trump-card-banned/ (2015)
 * - https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/ (2019)
 * - https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/ (2019)
 * - https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-paldean-fates-banned-list-and-rule-changes-announcement (2024)
 * - https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-stellar-crown-banned-list-and-rule-changes-announcement (2024)
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — verbatim wording for Lysandre, Team Up, Cosmic Eclipse, Paldean Fates, Stellar Crown';
    }

    public function up(Schema $schema): void
    {
        $cosmicRationale = "These card bans were applied in Japan recently. In an effort to maintain a more global experience for the Expanded format, TPCi has also banned these cards. Most of these card bans are an attempt to weaken strategies that involve disrupting or destroying an opponent's hand. These cards contribute to several combos that result in a player having to discard their entire hand before they get to take a turn. The Expanded format currently has a reputation for being dominated by hand-disruption decks, which many players dislike. Hopefully these card bans will promote a more enjoyable environment and change that reputation.";

        $delinquentRationale = 'A popular combo with Red Card, Delinquent, and Peeking Red Card created a lot of situations where one player essentially lost the game before taking their first turn. When this kind of strategy can be executed successfully a high percentage of the time and is effective, it creates an unhealthy environment.';

        $maxieRationale = "A Fighting-type Pokémon in the Sun & Moon—Team Up expansion would create a potentially devastating combo with Maxie's Hidden Ball Trick that can be achieved on the first turn of the game. Rather than wait and see how this turns out, it was determined that the best course of action was to prevent this combo before it happened.";

        $unownDamageRationale = "With multiple combos that exist in the Expanded format, the DAMAGE Ability of Unown could be used to win the game on the first or second turn. Even though these combos haven't yet proven to be successful in tournament play, they will become easier to achieve with the release of new cards, so Unown is being banned as a preventive measure. Note that Unown with the HAND Ability and Unown with the MISSING Ability, also from Sun & Moon—Lost Thunder, are still legal for tournament play.";

        $duskullRationale = "Duskull from the Sun & Moon—Cosmic Eclipse expansion was banned from the Expanded format. With the release of Dusclops in Scarlet & Violet—Shrouded Fable, it became very possible to win the game on the first turn when going first by using Duskull's Spiritborne Evolution Ability to evolve into Dusclops. By getting enough Dusclops into play, players can use many Cursed Blast Abilities to Knock Out the opponent's only Pokémon in play and win the game. Many cards are required to assemble the combo that allows this strategy to be dangerous, but Duskull has the lowest overall impact on other strategies that are used in the Expanded format, so it was chosen to be banned.";

        $scoopUpNetRationale = "Scoop Up Net cannot be used on Pokémon V or Pokémon-GX, but it can be used on other Pokémon with a Rule Box. There are many dangerous combos with this card, and a new one was introduced with the Scarlet & Violet—Paradox Rift expansion. The combination of Iron Valiant ex and Scoop Up Net allows the use of the Tachyon Bits Ability repeatedly, making it very possible to win the game on the first turn when going first. While this strategy isn't guaranteed to be successful, it happens frequently enough to create an undesirable environment for the Expanded format. Scoop Up Net may lead to even more powerful combos in the future, so it is the card that was chosen to be banned.";

        $lysandreRationale = <<<'TEXT'
            As of June 15, 2015, Lysandre's Trump Card (XY—Phantom Forces, 99/119 and 118/119) will be banned from all sanctioned Play! Pokémon tournaments in most of the world. (The ban will go into effect in Japan on June 20.)

            This card has created an undesirable play environment because it:

            - Eliminates one of your opponent's victory conditions (running out of cards in your deck)
            - Allows repeated use of powerful Trainer cards
            - Allows drawing through your deck quickly with minimal repercussions
            - Extends the time of battles

            All sanctioned tournaments will be affected by this change, including Pokémon National Championships occurring after June 15 (except in Japan) and the Pokémon World Championships in August.
            TEXT;

        // 1. Cosmic Eclipse — shared paragraph for nine parents.
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name IN (
                    'Chip-Chip Ice Axe',
                    'Flabébé',
                    'Island Challenge Amulet',
                    'Jessie & James',
                    "Lt. Surge's Strategy",
                    'Lt. Surge’s Strategy',
                    'Marshadow',
                    'Mismagius',
                    'Red Card',
                    'Reset Stamp'
                )
                SQL,
            ['explanation' => $cosmicRationale],
        );

        // Unown HAND (LOT 91) is also part of the Cosmic Eclipse batch — match by printing.
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card bc
                INNER JOIN banned_card_printing bcp ON bcp.banned_card_id = bc.id
                SET bc.explanation = :explanation
                WHERE bc.card_name = 'Unown'
                  AND bcp.set_code = 'LOT'
                  AND bcp.card_number = '91'
                SQL,
            ['explanation' => $cosmicRationale],
        );

        // 2. Team Up — three independent paragraphs.
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name = 'Delinquent'
                SQL,
            ['explanation' => $delinquentRationale],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name IN ('Maxie''s Hidden Ball Trick', 'Maxie’s Hidden Ball Trick')
                SQL,
            ['explanation' => $maxieRationale],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card bc
                INNER JOIN banned_card_printing bcp ON bcp.banned_card_id = bc.id
                SET bc.explanation = :explanation
                WHERE bc.card_name = 'Unown'
                  AND bcp.set_code = 'LOT'
                  AND bcp.card_number = '90'
                SQL,
            ['explanation' => $unownDamageRationale],
        );

        // 3. Stellar Crown — Duskull.
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name = 'Duskull'
                SQL,
            ['explanation' => $duskullRationale],
        );

        // 4. Paldean Fates — Scoop Up Net.
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name = 'Scoop Up Net'
                SQL,
            ['explanation' => $scoopUpNetRationale],
        );

        // 5. Lysandre's Trump Card — switch source URL + verbatim 2015 announcement text.
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET source_url = :source_url, explanation = :explanation
                WHERE card_name IN ('Lysandre''s Trump Card', 'Lysandre’s Trump Card')
                SQL,
            [
                'source_url' => 'https://www.pokemon.com/us/pokemon-news/lysandres-trump-card-banned/',
                'explanation' => $lysandreRationale,
            ],
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: rolling back would not restore admin edits made on top of these defaults.
    }
}

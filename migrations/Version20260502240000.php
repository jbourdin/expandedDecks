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
 * F6.14 — reformat every banned-card explanation in Markdown.
 *
 * The admin form now ships a Markdown editor and the public page already
 * renders the column through the existing MarkdownRenderer service, so the
 * verbatim wording carried over from each pokemon.com announcement is
 * augmented with:
 *   - **bold** on the banned card's name, on key combo cards, and on
 *     Ability / attack names (they're proper nouns in the TCG game text);
 *   - *italics* on expansion / set names, mirroring the original `<em>`
 *     tags used on pokemon.com.
 *
 * Wording itself is unchanged — only emphasis markers were added.
 *
 * Medicham V's explanation also picks up the verbatim phrasing from the
 * official Mega Evolution: Perfect Order announcement page, replacing the
 * earlier paraphrase.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — Markdown formatting on every banned-card explanation';
    }

    public function up(Schema $schema): void
    {
        $cosmic = "These card bans were applied in Japan recently. In an effort to maintain a more global experience for the Expanded format, TPCi has also banned these cards. Most of these card bans are an attempt to weaken strategies that involve disrupting or destroying an opponent's hand. These cards contribute to several combos that result in a player having to discard their entire hand before they get to take a turn. The Expanded format currently has a reputation for being dominated by hand-disruption decks, which many players dislike. Hopefully these card bans will promote a more enjoyable environment and change that reputation.";

        $ghetsisHexManiac = 'The overall goal of the Expanded format is to have a fun environment where players can enjoy using a wide variety of strategies. **Ghetsis** and **Hex Maniac** were identified as cards that stifle creativity and prevent several kinds of strategies from being viable. These cards also have the potential to make a major negative impact on an opponent before they get a chance to take their first turn, which can lead to a frustrating experience. **Wally** enables a combo with **Trevenant** that creates similar problems, so it falls into this category as well. Without these cards in the environment, hopefully gameplay will become more enjoyable.';

        $oranguruSableye = "**Oranguru**'s **Resource Management** attack and **Sableye**'s **Junk Hunt** attack allow for infinite resource recursion strategies that are relatively simple to achieve. In an attempt to curb the effectiveness of some of these control and lock strategies, these cards have been banned.";

        $maxie = "A Fighting-type Pokémon in the *Sun & Moon—Team Up* expansion would create a potentially devastating combo with **Maxie's Hidden Ball Trick** that can be achieved on the first turn of the game. Rather than wait and see how this turns out, it was determined that the best course of action was to prevent this combo before it happened.";

        $shayminEx = "The sheer amount of card drawing provided by **Shaymin-EX**'s **Set Up** Ability allowed dangerous combo decks to function at an alarmingly consistent rate. With the introduction of **Scoop Up Net**, it became too easy to use **Set Up** repeatedly in a single turn. **Crobat V** and **Dedenne-GX** provide effects similar to **Shaymin-EX**, so this type of card isn't gone completely, but their **Dark Asset** and **Dedechange** Abilities are limited to one use per turn.";

        $archeops = "The existence of **Archeops**'s **Ancient Power** Ability has a very negative effect on decks that rely on evolved Pokémon. There are ways to combat it—**Hex Maniac**, **Evosoda**, or **Wobbuffet** are a few examples—but decks that focus on evolved Pokémon are forced to use these cards just to evolve their Pokémon. The combination of **Maxie's Hidden Ball Trick** with **Archeops** can stop Evolution before the opponent ever gets a chance to evolve their Pokémon, which limits the number of viable strategies.";

        $forestOfGiantPlants = "The **Forest of Giant Plants** Stadium card enables many dangerous strategies with Grass-type Pokémon in the Expanded format. These strategies can range from locking down the opponent's options to winning the game on the first turn, and all of them can happen before the opponent ever gets a chance to play. No single strategy was powerful enough to ban this Stadium card, but so many of them existing at the same time gave sufficient cause to ban it.";

        $puzzleOfTime = '**Puzzle of Time** is a flexible card that can be used in a wide variety of strategies. Its usage rate is quite high in popular decks, and it enables a lot of powerful combos. Removing this card from the environment will affect how many decks are constructed, which will hopefully make the Expanded format feel fresh and different.';

        $delinquent = 'A popular combo with **Red Card**, **Delinquent**, and **Peeking Red Card** created a lot of situations where one player essentially lost the game before taking their first turn. When this kind of strategy can be executed successfully a high percentage of the time and is effective, it creates an unhealthy environment.';

        $unownDamage = "With multiple combos that exist in the Expanded format, the **DAMAGE** Ability of **Unown** could be used to win the game on the first or second turn. Even though these combos haven't yet proven to be successful in tournament play, they will become easier to achieve with the release of new cards, so **Unown** is being banned as a preventive measure. Note that **Unown** with the **HAND** Ability and **Unown** with the **MISSING** Ability, also from *Sun & Moon—Lost Thunder*, are still legal for tournament play.";

        $milotic = "**Milotic**'s **Energy Grace** Ability doesn't work with Pokémon-*EX*, but it still works with Pokémon-*GX* and Pokémon V. This created some undesirable combos, such as the one with **Trevenant & Dusknoir-GX** and **Ace Trainer**. As more Pokémon V come out in the future, there's a high likelihood of even more combos with **Milotic** being discovered.";

        $duskull = "**Duskull** from the *Sun & Moon—Cosmic Eclipse* expansion was banned from the Expanded format. With the release of **Dusclops** in *Scarlet & Violet—Shrouded Fable*, it became very possible to win the game on the first turn when going first by using **Duskull**'s **Spiritborne Evolution** Ability to evolve into **Dusclops**. By getting enough **Dusclops** into play, players can use many **Cursed Blast** Abilities to Knock Out the opponent's only Pokémon in play and win the game. Many cards are required to assemble the combo that allows this strategy to be dangerous, but **Duskull** has the lowest overall impact on other strategies that are used in the Expanded format, so it was chosen to be banned.";

        $scoopUpNet = "**Scoop Up Net** cannot be used on Pokémon V or Pokémon-*GX*, but it can be used on other Pokémon with a Rule Box. There are many dangerous combos with this card, and a new one was introduced with the *Scarlet & Violet—Paradox Rift* expansion. The combination of **Iron Valiant ex** and **Scoop Up Net** allows the use of the **Tachyon Bits** Ability repeatedly, making it very possible to win the game on the first turn when going first. While this strategy isn't guaranteed to be successful, it happens frequently enough to create an undesirable environment for the Expanded format. **Scoop Up Net** may lead to even more powerful combos in the future, so it is the card that was chosen to be banned.";

        $flapple = "**Flapple** was banned in the Expanded format. In combination with the upcoming **Forest of Vitality** Stadium card, there are several ways to use **Flapple**'s **Apple Drop** Ability repeatedly until all of the opponent's Pokémon are Knocked Out. This strategy can be executed consistently on the second turn of the game, which creates an undesirable environment for the Expanded format.";

        $medicham = "In combination with various Abilities and effects that place damage counters on the opponent's Pokémon, **Medicham V**'s **Yoga Loop** attack can be used to take an additional turn and win the game quickly. To weaken this style of deck, **Medicham V** will be banned from the Expanded format.";

        $lysandre = <<<'TEXT'
            As of June 15, 2015, **Lysandre's Trump Card** (*XY—Phantom Forces*, 99/119 and 118/119) will be banned from all sanctioned Play! Pokémon tournaments in most of the world. (The ban will go into effect in Japan on June 20.)

            This card has created an undesirable play environment because it:

            - Eliminates one of your opponent's victory conditions (running out of cards in your deck)
            - Allows repeated use of powerful Trainer cards
            - Allows drawing through your deck quickly with minimal repercussions
            - Extends the time of battles

            All sanctioned tournaments will be affected by this change, including Pokémon National Championships occurring after June 15 (except in Japan) and the Pokémon World Championships in August.
            TEXT;

        // ---------- Single-card explanations ----------

        $simpleUpdates = [
            'Archeops' => $archeops,
            'Forest of Giant Plants' => $forestOfGiantPlants,
            'Puzzle of Time' => $puzzleOfTime,
            'Delinquent' => $delinquent,
            'Milotic' => $milotic,
            'Duskull' => $duskull,
            'Scoop Up Net' => $scoopUpNet,
            'Flapple' => $flapple,
            'Medicham V' => $medicham,
        ];

        foreach ($simpleUpdates as $name => $explanation) {
            $this->addSql(
                <<<'SQL'
                    UPDATE banned_card
                    SET explanation = :explanation
                    WHERE card_name = :card_name
                    SQL,
                ['card_name' => $name, 'explanation' => $explanation],
            );
        }

        // ---------- Shared rationales ----------

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name IN ('Ghetsis', 'Hex Maniac')
                SQL,
            ['explanation' => $ghetsisHexManiac],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name IN ('Oranguru', 'Sableye')
                SQL,
            ['explanation' => $oranguruSableye],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name IN ('Maxie''s Hidden Ball Trick', 'Maxie’s Hidden Ball Trick')
                SQL,
            ['explanation' => $maxie],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name IN ('Shaymin-EX', 'Shaymin EX')
                SQL,
            ['explanation' => $shayminEx],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card
                SET explanation = :explanation
                WHERE card_name IN ('Lysandre''s Trump Card', 'Lysandre’s Trump Card')
                SQL,
            ['explanation' => $lysandre],
        );

        // Cosmic Eclipse covers ten parents — nine by name, plus Unown HAND (LOT 91) by printing.
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
            ['explanation' => $cosmic],
        );

        $this->addSql(
            <<<'SQL'
                UPDATE banned_card bc
                INNER JOIN banned_card_printing bcp ON bcp.banned_card_id = bc.id
                SET bc.explanation = :explanation
                WHERE bc.card_name = 'Unown'
                  AND bcp.set_code = 'LOT'
                  AND bcp.card_number = '91'
                SQL,
            ['explanation' => $cosmic],
        );

        // Unown DAMAGE (LOT 90) — Team Up announcement.
        $this->addSql(
            <<<'SQL'
                UPDATE banned_card bc
                INNER JOIN banned_card_printing bcp ON bcp.banned_card_id = bc.id
                SET bc.explanation = :explanation
                WHERE bc.card_name = 'Unown'
                  AND bcp.set_code = 'LOT'
                  AND bcp.card_number = '90'
                SQL,
            ['explanation' => $unownDamage],
        );
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: rolling back would not restore admin edits made on top of these defaults.
    }
}

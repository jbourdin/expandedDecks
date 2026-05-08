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
 * F6.15 — Seed the initial staple-cards content curated by the editor team.
 *
 * Inserts 36 placeholder rows across the seven buckets (Pokémon / Supporter /
 * Item / Tool / Stadium / Energy / Ace Spec). Each row carries:
 *   - cardName (display name as seen on the card)
 *   - bucket (pre-set per editor grouping; matches what computeBucketFor would
 *     produce after enrichment runs, so the technical re-enrich won't re-bucket)
 *   - position (0-based, scoped to each bucket, in the editor's intended order)
 *   - hotness (default STAPLE_THRESHOLD = 5; editors can promote/demote later)
 *   - note (Markdown prose from the editor, verbatim)
 *
 * Each row gets one StapleCardPrinting child for the editor's chosen printing.
 * `cardIdentity` and `cardPrinting` are left null — populated by the technical
 * re-enrich (`/admin/technical/staple-cards-enrich` or
 * `app:staple-cards:enrich`) after this migration.
 *
 * @see https://github.com/jbourdin/expandedDecks/issues/532
 */
final class Version20260508110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.15 — seed initial staple cards (36 cards across 6 buckets, with editor notes)';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Shared notes — multiple cards share a paragraph in the editor's source.
        $trioNote = <<<'TEXT'
            The trio of Dedenne-GX, Crobat V and Tapu Lele-GX form a trio seen together in many decks. The first two let you draw cards without using a Supporter for the turn, while the latter grabs any Supporter card from your deck. When playing a very important turn where you need a specific combo of cards, you might use all of them, but they can also just be kept at your disposal for when you need to draw, for example after being hit with a hand disruption card.
            TEXT;

        $ionoNNote = <<<'TEXT'
            Iono and N both play the same role: disrupt the opponent by putting their hand back into their deck and giving them a smaller amount of cards. They are mostly played in the late game, while trying to make the opponent whiff a certain card or combo, but they can also be used for their draw effect in the early game. The choice between Iono and N comes down to whether you want to make sure your opponent doesn't draw again the cards they had, or if you'd rather their draw be completely random. From what I've seen, Iono seems to be more common recently.
            TEXT;

        $ballsNote = <<<'TEXT'
            There are many Items that search for Pokémon in the Expanded format, which usually have some restrictions, like only searching for Pokémon of a given type. The three above are the most universal. Ultra Ball, of course, can search anything at the cost of two cards. Quick Ball is even more common, though, as it can search for any Basic Pokémon (which every deck plays) at a lower cost. Note that unlike Nest Ball, Quick Ball lets you use the effect of Pokémon like Dedenne-GX and Tapu Lele-GX, making it a much better choice in most decks. Finally, Hisuian Heavy Ball is one of the very few ways to get a Pokémon out of the Prizes, and given that many Expanded decks run a lot of 1-ofs, Hisuian Heavy Ball is invaluable.
            TEXT;

        $stretchersNote = <<<'TEXT'
            Night Stretcher is just as good a card in Expanded as it is in Standard. Some decks, though, especially those that don't play Basic Energy (or few of them), will prefer Rescue Stretcher, which can recover multiple Pokémon.
            TEXT;

        $abilityStadiumsNote = <<<'TEXT'
            While they are used in different archetypes, both Path to the Peak remove the Abilities of certain Pokémon. These cards can sometimes stick throughout the game and prevent the opponent from playing, but that's not particularly common. More commonly, they end up being removed, but their presence still forces the opponent to dedicate resources to dealing with them (for example, by playing a Lillie's Determination when they would rather have used Guzma to go on the offensive).
            TEXT;

        $cards = [
            // ─── Pokémon ─────────────────────────────────────────────────────
            ['cardName' => 'Dedenne-GX', 'bucket' => 'pokemon', 'position' => 0, 'note' => $trioNote, 'printings' => [['UNB', '57']]],
            ['cardName' => 'Crobat V', 'bucket' => 'pokemon', 'position' => 1, 'note' => $trioNote, 'printings' => [['DAA', '104']]],
            ['cardName' => 'Tapu Lele-GX', 'bucket' => 'pokemon', 'position' => 2, 'note' => $trioNote, 'printings' => [['GRI', '60']]],
            ['cardName' => 'Squawkabilly ex', 'bucket' => 'pokemon', 'position' => 3, 'note' => "Squawkabilly ex is not as widespread as the three above, since its utility is limited to the first turn of the game, but it is still fairly common. It's mostly used in aggro decks, which tend to put a higher emphasis on their first turn.", 'printings' => [['PAL', '169']]],
            ['cardName' => 'Wobbuffet', 'bucket' => 'pokemon', 'position' => 4, 'note' => "Wobbuffet is one of the most common disruption cards. Its Bide Barricade Ability shuts down all non-Psychic Pokémon's Abilities when it is Active, and many decks will have a copy of this card that they can bring Active on the first turn. Wobbuffet prevents the use of Dedenne-GX, Crobat V and Squawkabilly ex, and is one of the reasons why Tapu Lele-GX is so important (and preferred to the likes of Lumineon V and Meowth ex): it is the only consistency Pokémon that can be used while under the effect of Bide Barricade.", 'printings' => [['PHF', '36']]],
            ['cardName' => 'Budew', 'bucket' => 'pokemon', 'position' => 5, 'note' => "Budew is perhaps even more important in Expanded than in Standard. Item locking the opponent for no Energy cost is incredibly good, especially given that Expanded has some extremely important Item cards (like VS Seeker) that are key to making decks work. Budew is not used in every deck, but when it is, it's typically there to slow down an opponent while building up a board.", 'printings' => [['PRE', '4']]],

            // ─── Supporters ──────────────────────────────────────────────────
            ['cardName' => "Lillie's Determination", 'bucket' => 'supporter', 'position' => 0, 'note' => "While the Expanded format has some incredible older Trainer cards, recent ones can sometimes be just as impactful, and Lillie's Determination is a good example. Drawing eight cards is simply extremely good.", 'printings' => [['MEG', '119']]],
            ['cardName' => "Professor's Research", 'bucket' => 'supporter', 'position' => 1, 'note' => "Professor's Research (or Professor Sycamore, or Professor Juniper) is another key draw Supporter. Decks that have strong discard synergy will tend to prefer it over Lillie's Determination, while decks that can't afford to sacrifice many resources will prefer shuffling back their cards into their deck.", 'printings' => [['JTG', '155']]],
            ['cardName' => 'Iono', 'bucket' => 'supporter', 'position' => 2, 'note' => $ionoNNote, 'printings' => [['PAL', '185']]],
            ['cardName' => 'N', 'bucket' => 'supporter', 'position' => 3, 'note' => $ionoNNote, 'printings' => [['FCO', '105']]],
            ['cardName' => 'Marnie', 'bucket' => 'supporter', 'position' => 4, 'note' => "Marnie is another draw Supporter that doubles as disruption. It's not as effective in the late game, but stronger early on, and especially against Control decks that don't draw Prizes (or very slowly). Aggro decks, who tend to be ahead in Prizes and therefore would be hurt by Iono or N more than their opponent, may play Marnie rather than one of the two others.", 'printings' => [['SSH', '169']]],
            ['cardName' => 'Guzma', 'bucket' => 'supporter', 'position' => 5, 'note' => "Guzma is Boss's Orders and Switch in one card. It's basically a Boss's Orders that also lets you get rid of Special Conditions, effects of attacks, etc., and is simply better than it if you have a Pokémon with free retreat on the Bench. Because of this, Boss's Orders is almost never seen in Expanded, while Guzma is everywhere.", 'printings' => [['BUS', '115']]],
            ['cardName' => 'Carmine', 'bucket' => 'supporter', 'position' => 6, 'note' => "Carmine is like a weaker Professor's Research that can be played on turn 1. Several decks run one copy of it, that they can search out with Tapu Lele-GX on turn 1 if needed. This combination is also available in Standard, but in Expanded, many decks can't afford to simply pass their first turn.", 'printings' => [['TWM', '145']]],
            ['cardName' => 'Arven', 'bucket' => 'supporter', 'position' => 7, 'note' => "Arven is not played in every deck, far from it, but there are decks where it shines. It's especially good in decks that rely on a specific Item card, like Precious Trolley, especially since Tapu Lele-GX can search it on turn 1.", 'printings' => [['SVI', '166']]],
            ['cardName' => 'Raihan', 'bucket' => 'supporter', 'position' => 8, 'note' => 'Like Arven, Raihan only fits in some specific decks: those that play Basic Energy, have attackers that cost more than one Energy (and therefore require Energy acceleration), and whose Energy acceleration is otherwise limited. In these decks, though, it is extremely good!', 'printings' => [['EVS', '152']]],
            ['cardName' => 'Pokémon Ranger', 'bucket' => 'supporter', 'position' => 9, 'note' => "Pokémon Ranger is that card that you should know about, whether you play it or not. This card is the ultimate answer to all attacks that have lasting effects. It can remove the Item lock from Budew's Itchy Pollen, other forms of lock like Noivern ex's Dominant Echo or Chimecho's Bell of Silence, and even Arceus & Dialga & Palkia-GX's Altered Creation GX. It is mostly played in decks that rely on Special Energy, since they could otherwise lose to attacks that prevent them from playing these cards.", 'printings' => [['STS', '104']]],

            // ─── Items ───────────────────────────────────────────────────────
            ['cardName' => 'VS Seeker', 'bucket' => 'item', 'position' => 0, 'note' => "VS Seeker is one of the cards that define the format. It gets Supporters back from the discard, which means that many decks will only play one or two copies of each Supporter. If they need to play a specific one on a given turn, they can use Tapu Lele-GX to search it out, and then they can use VS Seeker to use it again. It is even better in decks that have discard synergy, who will often discard these Supporters with Professor's Research or Dedenne-GX.", 'printings' => [['PHF', '109']]],
            ['cardName' => 'Battle Compressor', 'bucket' => 'item', 'position' => 1, 'note' => 'Battle Compressor is another key card, although it is not as ubiquitous as it once was. This card is a godsend for any deck that wants to put specific cards in the discard. It also has great synergy with VS Seeker (you can discard a specific Supporter you want to play), as well as Night Stretcher. In decks that run multiple copies, it can also be used to simply thin out the deck, in order to keep only the best cards to minimize the impact of a late-game Iono.', 'printings' => [['PHF', '92']]],
            ['cardName' => "Trainers' Mail", 'bucket' => 'item', 'position' => 2, 'note' => "Trainers' Mail is an Item card that can dig for other Trainer cards. It is a great consistency card, though not every deck has the space for it. It is at its best in aggro decks that try to draw through a lot of their deck on the first turn. Slower decks tend to prefer having more resources overall in their deck, and draw them a bit more slowly.", 'printings' => [['ROS', '92']]],
            ['cardName' => 'Ultra Ball', 'bucket' => 'item', 'position' => 3, 'note' => $ballsNote, 'printings' => [['MEG', '131']]],
            ['cardName' => 'Quick Ball', 'bucket' => 'item', 'position' => 4, 'note' => $ballsNote, 'printings' => [['SSH', '179']]],
            ['cardName' => 'Hisuian Heavy Ball', 'bucket' => 'item', 'position' => 5, 'note' => $ballsNote, 'printings' => [['ASR', '146']]],
            ['cardName' => 'Field Blower', 'bucket' => 'item', 'position' => 6, 'note' => "Field Blower is a very common inclusion just because of its versatility, being able to discard Tools and Stadiums. It's especially useful against disruptive Stadiums like Path to the Peak.", 'printings' => [['GRI', '125']]],
            ['cardName' => 'Night Stretcher', 'bucket' => 'item', 'position' => 7, 'note' => $stretchersNote, 'printings' => [['SFA', '61']]],
            ['cardName' => 'Rescue Stretcher', 'bucket' => 'item', 'position' => 8, 'note' => $stretchersNote, 'printings' => [['GRI', '130']]],

            // ─── Tools ───────────────────────────────────────────────────────
            ['cardName' => 'Float Stone', 'bucket' => 'tool', 'position' => 0, 'note' => "Arguably the best Tool in the history of the game, Float Stone is a Switch card that can be played in advance and reused. The fact that it gives your Pokémon free retreat is part of what makes Guzma so good: there will almost always be a Pokémon with free retreat in play, so the cost of Guzma, being forced to switch your Pokémon, doesn't matter.", 'printings' => [['BKT', '137']]],
            ['cardName' => 'Forest Seal Stone', 'bucket' => 'tool', 'position' => 1, 'note' => "Forest Seal Stone's text is essentially \"get any card from your deck, once per game\", though it requires you to have a Pokémon V in play (and can't be played in VSTAR decks, which usually rely on a specific VSTAR Power already). But many decks already include at least one Pokémon V (in Crobat V), so that requirement is easily met.", 'printings' => [['SIT', '156']]],
            ['cardName' => 'Muscle Band', 'bucket' => 'tool', 'position' => 2, 'note' => "An universal damage boost, Muscle Band is not necessary in every deck, but it regularly finds its way into archetypes that enjoy the extra 20 damage. One fun use is that Budew can equip a Muscle Band to OHKO another Budew, winning the Budew war and breaking the opponent's Item lock while maintaining its own.", 'printings' => [['XY', '121']]],
            ['cardName' => 'Stealthy Hood', 'bucket' => 'tool', 'position' => 3, 'note' => "Stealthy Hood protects a Pokémon from the effects of other Pokémon's Abilities. There are multiple uses for this card depending on the archetype, but the most common one is to make sure a key Pokémon in your deck keeps its Ability even when faced with an opponent's Wobbuffet, Garbodor, or Iron Thorns ex (among others).", 'printings' => [['UNB', '186']]],

            // ─── Stadiums ────────────────────────────────────────────────────
            ['cardName' => 'Path to the Peak', 'bucket' => 'stadium', 'position' => 0, 'note' => $abilityStadiumsNote, 'printings' => [['CRE', '148']]],
            ['cardName' => 'Silent Lab', 'bucket' => 'stadium', 'position' => 1, 'note' => $abilityStadiumsNote, 'printings' => [['PRC', '140']]],
            ['cardName' => 'Temple of Sinnoh', 'bucket' => 'stadium', 'position' => 2, 'note' => 'Temple of Sinnoh is another disruptive Stadium, but it targets Special Energy rather than Abilities. It has seen play in multiple archetypes in order to deal with Special Energy-reliant decks.', 'printings' => [['ASR', '155']]],
            ['cardName' => 'Sky Field', 'bucket' => 'stadium', 'position' => 3, 'note' => "Sky Field, on the other hand, is mostly played in aggro decks that need a lot of space on the Bench to play their draw Pokémon. Sudowoodo can be used in these decks to stop the opponent from benefitting from the stadium's effect.", 'printings' => [['ROS', '89']]],
            ['cardName' => 'Tropical Beach', 'bucket' => 'stadium', 'position' => 4, 'note' => "Tropical Beach deserves a mention as a particularly expensive card (especially in English), being the promo card given to players at the World Championships in 2011 and 2012 only. In case you're worried, it's only played in a couple of stall decks, so it's absolutely not needed to give the format a try.", 'printings' => [['BWP', 'BW50']]],

            // ─── Energy ──────────────────────────────────────────────────────
            ['cardName' => 'Double Dragon Energy', 'bucket' => 'energy', 'position' => 0, 'note' => "One of the best Energy cards of all time, Double Dragon Energy makes Dragon-type Pokémon much more viable, and some of the format's most dangerous attackers.", 'printings' => [['ROS', '97']]],
            ['cardName' => 'Double Colorless Energy', 'bucket' => 'energy', 'position' => 1, 'note' => "The iconic Double Colorless Energy has seen less play as years went on, but it's still good in multiple archetypes.", 'printings' => [['SUM', '136']]],
        ];

        foreach ($cards as $card) {
            $this->connection->insert('staple_card', [
                'card_name' => $card['cardName'],
                'bucket' => $card['bucket'],
                'position' => $card['position'],
                'hotness' => 5,
                'note' => $card['note'],
                'created_at' => $now,
            ]);

            $stapleCardId = (int) $this->connection->lastInsertId();

            foreach ($card['printings'] as [$setCode, $cardNumber]) {
                $this->connection->insert('staple_card_printing', [
                    'staple_card_id' => $stapleCardId,
                    'set_code' => $setCode,
                    'card_number' => $cardNumber,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Delete every parent whose printings match the seed (set_code, card_number) pairs.
        // FK_8ABAAED7F6A40FFE has ON DELETE CASCADE, so children disappear with their parent.
        $printingPairs = [
            ['UNB', '57'], ['DAA', '104'], ['GRI', '60'], ['PAL', '169'], ['PHF', '36'], ['PRE', '4'],
            ['MEG', '119'], ['JTG', '155'], ['PAL', '185'], ['FCO', '105'], ['SSH', '169'], ['BUS', '115'],
            ['TWM', '145'], ['SVI', '166'], ['EVS', '152'], ['STS', '104'],
            ['PHF', '109'], ['PHF', '92'], ['ROS', '92'], ['MEG', '131'], ['SSH', '179'], ['ASR', '146'],
            ['GRI', '125'], ['SFA', '61'], ['GRI', '130'],
            ['BKT', '137'], ['SIT', '156'], ['XY', '121'], ['UNB', '186'],
            ['CRE', '148'], ['PRC', '140'], ['ASR', '155'], ['ROS', '89'], ['BWP', 'BW50'],
            ['ROS', '97'], ['SUM', '136'],
        ];

        foreach ($printingPairs as [$setCode, $cardNumber]) {
            $stapleCardId = $this->connection->fetchOne(
                'SELECT staple_card_id FROM staple_card_printing WHERE set_code = ? AND card_number = ?',
                [$setCode, $cardNumber],
            );
            if (false !== $stapleCardId) {
                $this->connection->delete('staple_card', ['id' => (int) $stapleCardId]);
            }
        }
    }
}

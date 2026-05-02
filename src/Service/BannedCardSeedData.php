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

namespace App\Service;

use App\Entity\BannedCard;
use App\Repository\BannedCardRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Default Expanded-format ban metadata (effective dates, official-announcement
 * URLs, short explanations) for every card known at the time of writing.
 *
 * Applied two ways:
 *   - {@see BannedCardsSyncService} calls {@see self::applyTo()} when it
 *     provisions a fresh {@see BannedCard} parent so newly-seen rows ship
 *     with metadata immediately.
 *   - {@see \App\Command\BannedCardsSeedCommand} re-runs {@see self::applyAll()}
 *     against every existing row so installations that already synced before
 *     this seed shipped pick the data up.
 *
 * Seeds only fill columns that are still null — admin edits and any later
 * announcement updates are preserved.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final readonly class BannedCardSeedData
{
    /**
     * Per-card seeds keyed by the canonical name. Curly-apostrophe variants
     * are listed as additional keys when both forms appear in upstream data.
     *
     * @var array<string, array{effectiveDate: ?string, sourceUrl: ?string, explanation: ?string}>
     */
    private const array SEEDS = [
        'Archeops' => [
            'effectiveDate' => '2017-08-18',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-burning-shadows-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => "The existence of Archeops's Ancient Power Ability has a very negative effect on decks that rely on evolved Pokémon. There are ways to combat it—Hex Maniac, Evosoda, or Wobbuffet are a few examples—but decks that focus on evolved Pokémon are forced to use these cards just to evolve their Pokémon. The combination of Maxie's Hidden Ball Trick with Archeops can stop Evolution before the opponent ever gets a chance to evolve their Pokémon, which limits the number of viable strategies.",
        ],
        'Chip-Chip Ice Axe' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
        'Delinquent' => [
            'effectiveDate' => '2019-02-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => 'A popular combo with Red Card, Delinquent, and Peeking Red Card created a lot of situations where one player essentially lost the game before taking their first turn. When this kind of strategy can be executed successfully a high percentage of the time and is effective, it creates an unhealthy environment.',
        ],
        'Duskull' => [
            'effectiveDate' => '2024-09-27',
            'sourceUrl' => 'https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-stellar-crown-banned-list-and-rule-changes-announcement',
            'explanation' => "Duskull from the Sun & Moon—Cosmic Eclipse expansion was banned from the Expanded format. With the release of Dusclops in Scarlet & Violet—Shrouded Fable, it became very possible to win the game on the first turn when going first by using Duskull's Spiritborne Evolution Ability to evolve into Dusclops. By getting enough Dusclops into play, players can use many Cursed Blast Abilities to Knock Out the opponent's only Pokémon in play and win the game. Many cards are required to assemble the combo that allows this strategy to be dangerous, but Duskull has the lowest overall impact on other strategies that are used in the Expanded format, so it was chosen to be banned.",
        ],
        'Flabébé' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
        'Flapple' => [
            'effectiveDate' => '2025-10-10',
            'sourceUrl' => 'https://www.pokemon.com/us/play-pokemon/about/mega-evolution/mega-evolution-banned-list-and-rule-changes-announcement',
            'explanation' => "Flapple was banned in the Expanded format. In combination with the upcoming Forest of Vitality Stadium card, there are several ways to use Flapple's Apple Drop Ability repeatedly until all of the opponent's Pokémon are Knocked Out. This strategy can be executed consistently on the second turn of the game, which creates an undesirable environment for the Expanded format.",
        ],
        'Forest of Giant Plants' => [
            'effectiveDate' => '2017-08-18',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-burning-shadows-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => "The Forest of Giant Plants Stadium card enables many dangerous strategies with Grass-type Pokémon in the Expanded format. These strategies can range from locking down the opponent's options to winning the game on the first turn, and all of them can happen before the opponent ever gets a chance to play. No single strategy was powerful enough to ban this Stadium card, but so many of them existing at the same time gave sufficient cause to ban it.",
        ],
        'Ghetsis' => [
            'effectiveDate' => '2018-08-17',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-celestial-storm-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => 'The overall goal of the Expanded format is to have a fun environment where players can enjoy using a wide variety of strategies. Ghetsis and Hex Maniac were identified as cards that stifle creativity and prevent several kinds of strategies from being viable. These cards also have the potential to make a major negative impact on an opponent before they get a chance to take their first turn, which can lead to a frustrating experience. Wally enables a combo with Trevenant that creates similar problems, so it falls into this category as well. Without these cards in the environment, hopefully gameplay will become more enjoyable.',
        ],
        'Hex Maniac' => [
            'effectiveDate' => '2018-08-17',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-celestial-storm-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => 'The overall goal of the Expanded format is to have a fun environment where players can enjoy using a wide variety of strategies. Ghetsis and Hex Maniac were identified as cards that stifle creativity and prevent several kinds of strategies from being viable. These cards also have the potential to make a major negative impact on an opponent before they get a chance to take their first turn, which can lead to a frustrating experience. Wally enables a combo with Trevenant that creates similar problems, so it falls into this category as well. Without these cards in the environment, hopefully gameplay will become more enjoyable.',
        ],
        'Island Challenge Amulet' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
        'Jessie & James' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
        'Lt. Surge\'s Strategy' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
        "Lt. Surge\u{2019}s Strategy" => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
        'Lysandre\'s Trump Card' => [
            'effectiveDate' => '2015-06-15',
            'sourceUrl' => 'https://bulbanews.bulbagarden.net/wiki/Lysandre%27s_Trump_Card_banned_from_TCG_competitive_play',
            'explanation' => self::LYSANDRE_RATIONALE,
        ],
        "Lysandre\u{2019}s Trump Card" => [
            'effectiveDate' => '2015-06-15',
            'sourceUrl' => 'https://bulbanews.bulbagarden.net/wiki/Lysandre%27s_Trump_Card_banned_from_TCG_competitive_play',
            'explanation' => self::LYSANDRE_RATIONALE,
        ],
        'Marshadow' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
        'Maxie\'s Hidden Ball Trick' => [
            'effectiveDate' => '2019-02-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => "A Fighting-type Pokémon in the Sun & Moon—Team Up expansion would create a potentially devastating combo with Maxie's Hidden Ball Trick that can be achieved on the first turn of the game. Rather than wait and see how this turns out, it was determined that the best course of action was to prevent this combo before it happened.",
        ],
        "Maxie\u{2019}s Hidden Ball Trick" => [
            'effectiveDate' => '2019-02-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => "A Fighting-type Pokémon in the Sun & Moon—Team Up expansion would create a potentially devastating combo with Maxie's Hidden Ball Trick that can be achieved on the first turn of the game. Rather than wait and see how this turns out, it was determined that the best course of action was to prevent this combo before it happened.",
        ],
        'Medicham V' => [
            'effectiveDate' => '2026-04-10',
            'sourceUrl' => 'https://www.pokemon.com/us/play-pokemon/about/mega-evolution/mega-evolution-perfect-order-banned-list-and-rule-changes-announcement',
            'explanation' => 'Yoga Loop attack takes an extra turn and wins quickly. Banned internationally with the Mega Evolution: Perfect Order rule changes (announced 2026-03-12, effective 2026-04-10).',
        ],
        'Milotic' => [
            'effectiveDate' => '2020-11-27',
            'sourceUrl' => 'https://www.pokemon.com/us/sword-shield-vivid-voltage-banned-list-and-rule-changes-announcement/',
            'explanation' => "Milotic's Energy Grace Ability doesn't work with Pokémon-EX, but it still works with Pokémon-GX and Pokémon V. This created some undesirable combos, such as the one with Trevenant & Dusknoir-GX and Ace Trainer. As more Pokémon V come out in the future, there's a high likelihood of even more combos with Milotic being discovered.",
        ],
        'Mismagius' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
        'Oranguru' => [
            'effectiveDate' => '2020-11-27',
            'sourceUrl' => 'https://www.pokemon.com/us/sword-shield-vivid-voltage-banned-list-and-rule-changes-announcement/',
            'explanation' => "Oranguru's Resource Management attack and Sableye's Junk Hunt attack allow for infinite resource recursion strategies that are relatively simple to achieve. In an attempt to curb the effectiveness of some of these control and lock strategies, these cards have been banned.",
        ],
        'Puzzle of Time' => [
            'effectiveDate' => '2018-08-17',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-celestial-storm-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => 'Puzzle of Time is a flexible card that can be used in a wide variety of strategies. Its usage rate is quite high in popular decks, and it enables a lot of powerful combos. Removing this card from the environment will affect how many decks are constructed, which will hopefully make the Expanded format feel fresh and different.',
        ],
        'Red Card' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
        'Reset Stamp' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
        'Sableye' => [
            'effectiveDate' => '2020-11-27',
            'sourceUrl' => 'https://www.pokemon.com/us/sword-shield-vivid-voltage-banned-list-and-rule-changes-announcement/',
            'explanation' => "Oranguru's Resource Management attack and Sableye's Junk Hunt attack allow for infinite resource recursion strategies that are relatively simple to achieve. In an attempt to curb the effectiveness of some of these control and lock strategies, these cards have been banned.",
        ],
        'Scoop Up Net' => [
            'effectiveDate' => '2024-02-09',
            'sourceUrl' => 'https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-paldean-fates-banned-list-and-rule-changes-announcement',
            'explanation' => "Scoop Up Net cannot be used on Pokémon V or Pokémon-GX, but it can be used on other Pokémon with a Rule Box. There are many dangerous combos with this card, and a new one was introduced with the Scarlet & Violet—Paradox Rift expansion. The combination of Iron Valiant ex and Scoop Up Net allows the use of the Tachyon Bits Ability repeatedly, making it very possible to win the game on the first turn when going first. While this strategy isn't guaranteed to be successful, it happens frequently enough to create an undesirable environment for the Expanded format. Scoop Up Net may lead to even more powerful combos in the future, so it is the card that was chosen to be banned.",
        ],
        'Shaymin-EX' => [
            'effectiveDate' => '2020-11-27',
            'sourceUrl' => 'https://www.pokemon.com/us/sword-shield-vivid-voltage-banned-list-and-rule-changes-announcement/',
            'explanation' => "The sheer amount of card drawing provided by Shaymin-EX's Set Up Ability allowed dangerous combo decks to function at an alarmingly consistent rate. With the introduction of Scoop Up Net, it became too easy to use Set Up repeatedly in a single turn. Crobat V and Dedenne-GX provide effects similar to Shaymin-EX, so this type of card isn't gone completely, but their Dark Asset and Dedechange Abilities are limited to one use per turn.",
        ],
        'Shaymin EX' => [
            'effectiveDate' => '2020-11-27',
            'sourceUrl' => 'https://www.pokemon.com/us/sword-shield-vivid-voltage-banned-list-and-rule-changes-announcement/',
            'explanation' => "The sheer amount of card drawing provided by Shaymin-EX's Set Up Ability allowed dangerous combo decks to function at an alarmingly consistent rate. With the introduction of Scoop Up Net, it became too easy to use Set Up repeatedly in a single turn. Crobat V and Dedenne-GX provide effects similar to Shaymin-EX, so this type of card isn't gone completely, but their Dark Asset and Dedechange Abilities are limited to one use per turn.",
        ],
    ];

    /**
     * Per-printing seeds (set, number → seed). Used to disambiguate same-name
     * cards whose ban dates differ, e.g. the two Unown abilities in LOT.
     *
     * @var array<string, array{effectiveDate: ?string, sourceUrl: ?string, explanation: ?string}>
     */
    private const array PRINTING_SEEDS = [
        'LOT|90' => [
            'effectiveDate' => '2019-02-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => "With multiple combos that exist in the Expanded format, the DAMAGE Ability of Unown could be used to win the game on the first or second turn. Even though these combos haven't yet proven to be successful in tournament play, they will become easier to achieve with the release of new cards, so Unown is being banned as a preventive measure. Note that Unown with the HAND Ability and Unown with the MISSING Ability, also from Sun & Moon—Lost Thunder, are still legal for tournament play.",
        ],
        'LOT|91' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => self::COSMIC_ECLIPSE_RATIONALE,
        ],
    ];

    private const string COSMIC_ECLIPSE_RATIONALE = "These card bans were applied in Japan recently. In an effort to maintain a more global experience for the Expanded format, TPCi has also banned these cards. Most of these card bans are an attempt to weaken strategies that involve disrupting or destroying an opponent's hand. These cards contribute to several combos that result in a player having to discard their entire hand before they get to take a turn. The Expanded format currently has a reputation for being dominated by hand-disruption decks, which many players dislike. Hopefully these card bans will promote a more enjoyable environment and change that reputation.";

    private const string LYSANDRE_RATIONALE = <<<'TEXT'
        As of June 15, 2015, Lysandre's Trump Card (XY—Phantom Forces, 99/119 and 118/119) will be banned from all sanctioned Play! Pokémon tournaments in most of the world. (The ban will go into effect in Japan on June 20.)

        This card has created an undesirable play environment because it:

        - Eliminates one of your opponent's victory conditions (running out of cards in your deck)
        - Allows repeated use of powerful Trainer cards
        - Allows drawing through your deck quickly with minimal repercussions
        - Extends the time of battles

        All sanctioned tournaments will be affected by this change, including Pokémon National Championships occurring after June 15 (except in Japan) and the Pokémon World Championships in August.
        TEXT;

    public function __construct(
        private BannedCardRepository $bannedCardRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Apply seed metadata to a single ban — typically a freshly-created parent
     * during sync. Only fills fields that are still null.
     */
    public function applyTo(BannedCard $card): void
    {
        $seed = $this->lookupSeedForCard($card);

        if (null === $seed) {
            return;
        }

        $this->fillIfNull($card, $seed);
    }

    /**
     * Apply seeds across every active banned card. Returns [filled-count, skipped-count].
     *
     * @return array{0: int, 1: int}
     */
    public function applyAll(): array
    {
        $cards = $this->bannedCardRepository->findActiveOrderedByEffectiveDate();

        $filled = 0;
        $skipped = 0;
        foreach ($cards as $card) {
            $seed = $this->lookupSeedForCard($card);
            if (null === $seed) {
                ++$skipped;
                continue;
            }

            if ($this->fillIfNull($card, $seed)) {
                ++$filled;
            } else {
                ++$skipped;
            }
        }

        $this->entityManager->flush();

        return [$filled, $skipped];
    }

    /**
     * @return array{effectiveDate: ?string, sourceUrl: ?string, explanation: ?string}|null
     */
    private function lookupSeedForCard(BannedCard $card): ?array
    {
        // Per-printing override: walk the children, take the first match.
        foreach ($card->getPrintings() as $printing) {
            $key = $printing->getSetCode().'|'.$printing->getCardNumber();
            if (isset(self::PRINTING_SEEDS[$key])) {
                return self::PRINTING_SEEDS[$key];
            }
        }

        return self::SEEDS[$card->getCardName()] ?? null;
    }

    /**
     * @param array{effectiveDate: ?string, sourceUrl: ?string, explanation: ?string} $seed
     */
    private function fillIfNull(BannedCard $card, array $seed): bool
    {
        $changed = false;

        if (null === $card->getEffectiveDate() && null !== $seed['effectiveDate']) {
            $card->setEffectiveDate(new \DateTimeImmutable($seed['effectiveDate']));
            $changed = true;
        }
        if (null === $card->getSourceUrl() && null !== $seed['sourceUrl']) {
            $card->setSourceUrl($seed['sourceUrl']);
            $changed = true;
        }
        if (null === $card->getExplanation() && null !== $seed['explanation']) {
            $card->setExplanation($seed['explanation']);
            $changed = true;
        }

        return $changed;
    }
}

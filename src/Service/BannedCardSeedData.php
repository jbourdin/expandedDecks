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
            'explanation' => 'Ancient Power Ability locks evolution; a turn-1 Maxie\'s Hidden Ball Trick + Archeops stops the opponent from evolving.',
        ],
        'Chip-Chip Ice Axe' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => 'Manipulates the top of the opponent\'s deck, enabling oppressive hand-disruption / discard-lock combos.',
        ],
        'Delinquent' => [
            'effectiveDate' => '2019-02-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => 'Discards the opponent\'s Stadium and three cards; Red Card + Delinquent reduced opponent to 1 card pre-turn.',
        ],
        'Duskull' => [
            'effectiveDate' => '2024-09-27',
            'sourceUrl' => 'https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-stellar-crown-banned-list-and-rule-changes-announcement',
            'explanation' => 'Spiritborne Evolution + Dusclops "Cursed Blast" enabled consistent T1 wins (Dusclops Donk).',
        ],
        'Flabébé' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => 'Bench-evolution Ability fueled T1 hand-disruption decks (Marshadow / Red Card / Reset Stamp).',
        ],
        'Flapple' => [
            'effectiveDate' => '2025-10-10',
            'sourceUrl' => 'https://www.pokebeach.com/2025/09/flapple-banned-from-expanded-format-prism-energy-receives-errata',
            'explanation' => 'Apple Drop Ability + Forest of Vitality enabled an infinite damage loop winning consistently on T2.',
        ],
        'Forest of Giant Plants' => [
            'effectiveDate' => '2017-08-18',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-burning-shadows-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => 'Free T1 Grass evolutions enabled Vileplume item-lock and other turn-1 lockdown wins.',
        ],
        'Ghetsis' => [
            'effectiveDate' => '2018-08-17',
            'sourceUrl' => 'https://www.pokebeach.com/2018/07/ghetsis-hex-maniac-and-more-banned-from-expanded-format',
            'explanation' => 'Discards Item cards from the opponent\'s opening hand, crippling them before their first turn.',
        ],
        'Hex Maniac' => [
            'effectiveDate' => '2018-08-17',
            'sourceUrl' => 'https://www.pokebeach.com/2018/07/ghetsis-hex-maniac-and-more-banned-from-expanded-format',
            'explanation' => 'Shuts off all Pokémon Abilities for a turn, enabling oppressive T1 setups while disabling defenses.',
        ],
        'Island Challenge Amulet' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => 'Reduces a V/GX Pokémon to a 1-Prize attacker, distorting Prize math in stall/control decks.',
        ],
        'Jessie & James' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => 'Forces the opponent to discard 2 random cards, oppressive in hand-disruption / first-turn decks.',
        ],
        'Lt. Surge\'s Strategy' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => 'Lets you play a second Supporter, breaking the one-Supporter-per-turn balancing rule.',
        ],
        "Lt. Surge\u{2019}s Strategy" => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => 'Lets you play a second Supporter, breaking the one-Supporter-per-turn balancing rule.',
        ],
        'Lysandre\'s Trump Card' => [
            'effectiveDate' => '2015-06-15',
            'sourceUrl' => 'https://bulbanews.bulbagarden.net/wiki/Lysandre%27s_Trump_Card_banned_from_TCG_competitive_play',
            'explanation' => 'Shuffles all discard piles back into decks, eliminating deck-out as a win condition and enabling infinite resource loops.',
        ],
        "Lysandre\u{2019}s Trump Card" => [
            'effectiveDate' => '2015-06-15',
            'sourceUrl' => 'https://bulbanews.bulbagarden.net/wiki/Lysandre%27s_Trump_Card_banned_from_TCG_competitive_play',
            'explanation' => 'Shuffles all discard piles back into decks, eliminating deck-out as a win condition and enabling infinite resource loops.',
        ],
        'Marshadow' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => '"Let Loose" Ability force-shuffles both hands to 4, weaponized via Scoop Up Net for repeated T1 disruption.',
        ],
        'Maxie\'s Hidden Ball Trick' => [
            'effectiveDate' => '2019-02-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => 'Cheats a Stage-1/2 Fighting Pokémon directly into play; Team Up enabled devastating T1 combos.',
        ],
        "Maxie\u{2019}s Hidden Ball Trick" => [
            'effectiveDate' => '2019-02-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/',
            'explanation' => 'Cheats a Stage-1/2 Fighting Pokémon directly into play; Team Up enabled devastating T1 combos.',
        ],
        'Medicham V' => [
            'effectiveDate' => null,
            'sourceUrl' => 'https://www.pokebeach.com/2026/01/medicham-v-banned-from-japans-expanded-format',
            'explanation' => 'Yoga Loop + damage-counter abilities enable extra-turn wins (banned 2026-02-20 in Japan; international ban not yet announced).',
        ],
        'Milotic' => [
            'effectiveDate' => '2020-11-27',
            'sourceUrl' => 'https://www.pokebeach.com/2020/10/resource-management-oranguru-shaymin-ex-sableye-and-energy-grace-milotic-banned-in-the-expanded-format',
            'explanation' => '"Energy Grace" enables infinite Energy acceleration loops with Scoop Up Net in control / stall decks.',
        ],
        'Mismagius' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => '"Mysterious Message" draw engine fueled overpowered Ultra Beast / hand-disruption decks.',
        ],
        'Oranguru' => [
            'effectiveDate' => '2020-11-27',
            'sourceUrl' => 'https://www.pokebeach.com/2020/10/resource-management-oranguru-shaymin-ex-sableye-and-energy-grace-milotic-banned-in-the-expanded-format',
            'explanation' => '"Resource Management" attack recycles 3 cards, enabling infinite control loops with Scoop Up Net.',
        ],
        'Puzzle of Time' => [
            'effectiveDate' => '2018-08-17',
            'sourceUrl' => 'https://www.pokebeach.com/2018/07/ghetsis-hex-maniac-and-more-banned-from-expanded-format',
            'explanation' => 'Two-card combo recovers any 2 cards from the discard pile, enabling powerful loops in too many archetypes.',
        ],
        'Red Card' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => 'Forces the opponent to shuffle their hand and draw 4, central piece of T1 hand-disruption combos.',
        ],
        'Reset Stamp' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => 'Sets opponent\'s hand to Prizes-remaining, devastating in late-game disruption.',
        ],
        'Sableye' => [
            'effectiveDate' => '2020-11-27',
            'sourceUrl' => 'https://www.pokebeach.com/2020/10/resource-management-oranguru-shaymin-ex-sableye-and-energy-grace-milotic-banned-in-the-expanded-format',
            'explanation' => '"Junk Hunt" attack recycles Items every turn, enabling effectively infinite resource recursion.',
        ],
        'Scoop Up Net' => [
            'effectiveDate' => '2024-02-09',
            'sourceUrl' => 'https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-paldean-fates-banned-list-and-rule-changes-announcement',
            'explanation' => 'Returns non-V/GX Pokémon to hand; enabled Iron Valiant ex Tachyon Bits T1 wins and many ability loops.',
        ],
        'Shaymin-EX' => [
            'effectiveDate' => '2020-11-27',
            'sourceUrl' => 'https://www.pokebeach.com/2020/10/resource-management-oranguru-shaymin-ex-sableye-and-energy-grace-milotic-banned-in-the-expanded-format',
            'explanation' => '"Set Up" draws to 6 on bench-play; reused via Scoop Up Net for infinite draw and consistency.',
        ],
        'Shaymin EX' => [
            'effectiveDate' => '2020-11-27',
            'sourceUrl' => 'https://www.pokebeach.com/2020/10/resource-management-oranguru-shaymin-ex-sableye-and-energy-grace-milotic-banned-in-the-expanded-format',
            'explanation' => '"Set Up" draws to 6 on bench-play; reused via Scoop Up Net for infinite draw and consistency.',
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
            'explanation' => '"DAMAGE" Ability auto-wins with 66+ damage counters on bench, enabling T1/T2 self-damage win combos.',
        ],
        'LOT|91' => [
            'effectiveDate' => '2019-11-15',
            'sourceUrl' => 'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/',
            'explanation' => '"HAND" Ability auto-wins when holding 35+ cards; combined with draw engines for consistent OTKs.',
        ],
    ];

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

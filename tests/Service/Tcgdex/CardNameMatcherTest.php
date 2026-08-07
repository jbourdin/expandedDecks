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

namespace App\Tests\Service\Tcgdex;

use App\Service\Tcgdex\CardNameMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
 */
class CardNameMatcherTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function equivalentNameProvider(): iterable
    {
        yield 'identical' => ['Arceus VSTAR', 'Arceus VSTAR'];
        yield 'case' => ['ULTRA BALL', 'Ultra Ball'];
        yield 'accents' => ['Pokemon Communication', 'Pokémon Communication'];
        yield 'french accents' => ['Energie Obscurite', 'Énergie Obscurité'];
        yield 'hyphen versus space' => ['Trevenant & Dusknoir-GX', 'Trevenant & Dusknoir GX'];
        yield 'typographic apostrophe' => ["Professor's Research", 'Professor’s Research'];
        yield 'collapsed whitespace' => ['Boss   Orders', 'Boss Orders'];
    }

    #[DataProvider('equivalentNameProvider')]
    public function testEquivalentNamesMatchExactly(string $first, string $second): void
    {
        $matcher = new CardNameMatcher();

        self::assertTrue($matcher->isExactMatch($first, $second));
        self::assertSame(1.0, $matcher->score($first, $second));
    }

    public function testDifferentCardsDoNotMatch(): void
    {
        $matcher = new CardNameMatcher();

        self::assertFalse($matcher->isExactMatch('Rampardos', "Team Rocket's Meowth"));
        self::assertLessThan(CardNameMatcher::MINIMUM_SIMILARITY, $matcher->score('Rampardos', "Team Rocket's Meowth"));
    }

    public function testEmptyNameNeverMatches(): void
    {
        $matcher = new CardNameMatcher();

        self::assertFalse($matcher->isExactMatch('', ''));
        self::assertSame(0.0, $matcher->score('', 'Ultra Ball'));
    }

    /**
     * A name made only of punctuation folds to an empty string, which must not
     * be treated as matching another empty fold.
     */
    public function testPunctuationOnlyNameNeverMatches(): void
    {
        $matcher = new CardNameMatcher();

        self::assertFalse($matcher->isExactMatch('---', '???'));
    }

    public function testScoreIsSymmetric(): void
    {
        $matcher = new CardNameMatcher();

        self::assertSame(
            $matcher->score('Arceus VSTAR', 'Arceus V'),
            $matcher->score('Arceus V', 'Arceus VSTAR'),
        );
    }

    /**
     * A near-miss must sit between "unrelated" and "identical" rather than
     * collapsing to either extreme.
     */
    public function testNearMissScoresBetweenUnrelatedAndIdentical(): void
    {
        $matcher = new CardNameMatcher();
        $score = $matcher->score('Arceus VSTAR', 'Arceus V');

        self::assertGreaterThan(0.0, $score);
        self::assertLessThan(1.0, $score);
    }

    public function testNormalizeFoldsToComparableForm(): void
    {
        $matcher = new CardNameMatcher();

        self::assertSame('professors research', $matcher->normalize('Professor’s Research'));
        self::assertSame('trevenant dusknoir gx', $matcher->normalize('Trevenant & Dusknoir-GX'));
        self::assertSame('', $matcher->normalize('---'));
    }
}

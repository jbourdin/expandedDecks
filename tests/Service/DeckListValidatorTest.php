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

namespace App\Tests\Service;

use App\Service\DeckListParseResult;
use App\Service\DeckListValidator;
use App\Service\ParsedCard;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.3 — Validate deck list (card count, duplicates)
 */
class DeckListValidatorTest extends TestCase
{
    private DeckListValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DeckListValidator();
    }

    public function testValid60CardDeckPasses(): void
    {
        $cards = [];

        // 15 unique pokemon, 4 copies each = 60 cards
        for ($i = 1; $i <= 15; ++$i) {
            $cards[] = new ParsedCard(4, 'Pokemon '.$i, 'BRS', (string) $i, 'pokemon');
        }

        $parseResult = new DeckListParseResult($cards, [], ['pokemon' => 60]);

        $result = $this->validator->validate($parseResult);

        self::assertTrue($result->isValid());
        self::assertCount(0, $result->errors);
    }

    public function testDeckWithNot60CardsProducesError(): void
    {
        $cards = [
            new ParsedCard(4, 'Pikachu V', 'CEL', '25', 'pokemon'),
        ];

        $parseResult = new DeckListParseResult($cards, [], ['pokemon' => 4]);

        $result = $this->validator->validate($parseResult);

        self::assertFalse($result->isValid());
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('exactly 60 cards', $result->errors[0]);
        self::assertStringContainsString('4', $result->errors[0]);
    }

    public function testMoreThan4CopiesOfNonEnergyCardProducesError(): void
    {
        $cards = [
            new ParsedCard(5, 'Pikachu V', 'CEL', '25', 'pokemon'),
        ];

        // Add 55 filler cards to make 60
        for ($i = 1; $i <= 11; ++$i) {
            $cards[] = new ParsedCard(5, 'Filler '.$i, 'BRS', (string) (100 + $i), 'trainer');
        }

        $parseResult = new DeckListParseResult($cards, [], ['pokemon' => 5, 'trainer' => 55]);

        $result = $this->validator->validate($parseResult);

        self::assertFalse($result->isValid());
        // At least one error for Pikachu V (5 copies) — filler cards also have 5 copies
        $pikaError = false;

        foreach ($result->errors as $error) {
            if (str_contains($error, 'Pikachu V')) {
                $pikaError = true;
            }
        }
        self::assertTrue($pikaError, 'Expected an error for Pikachu V exceeding 4 copies.');
    }

    public function testBasicEnergyExceeding4CopiesIsAllowed(): void
    {
        $cards = [
            new ParsedCard(10, 'Lightning Energy', 'SVE', '4', 'energy'),
        ];

        // Add 50 more unique cards
        for ($i = 1; $i <= 10; ++$i) {
            $cards[] = new ParsedCard(5, 'Trainer '.$i, 'BRS', (string) $i, 'trainer');
        }

        $parseResult = new DeckListParseResult($cards, [], ['energy' => 10, 'trainer' => 50]);

        $result = $this->validator->validate($parseResult);

        // Only expect errors for the trainer cards with 5 copies, not for the energy
        $energyError = false;

        foreach ($result->errors as $error) {
            if (str_contains($error, 'Lightning Energy')) {
                $energyError = true;
            }
        }
        self::assertFalse($energyError, 'Basic energy should be exempt from the 4-copy limit.');
    }

    public function testSpecialEnergyIsNotExempt(): void
    {
        $cards = [
            new ParsedCard(5, 'V Guard Energy', 'SIT', '169', 'energy'),
        ];

        // Add 55 filler cards
        for ($i = 1; $i <= 11; ++$i) {
            $cards[] = new ParsedCard(5, 'Pokemon '.$i, 'BRS', (string) $i, 'pokemon');
        }

        $parseResult = new DeckListParseResult($cards, [], ['energy' => 5, 'pokemon' => 55]);

        $result = $this->validator->validate($parseResult);

        $energyError = false;

        foreach ($result->errors as $error) {
            if (str_contains($error, 'V Guard Energy')) {
                $energyError = true;
            }
        }
        self::assertTrue($energyError, 'Special energy should still be limited to 4 copies.');
    }
}

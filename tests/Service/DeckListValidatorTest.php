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

use App\Repository\BannedCardRepository;
use App\Service\DeckListParseResult;
use App\Service\DeckListValidator;
use App\Service\ParsedCard;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F6.3 — Validate deck list (card count, duplicates)
 * @see docs/features.md F6.5 — Banned card list management
 */
class DeckListValidatorTest extends TestCase
{
    private DeckListValidator $validator;

    protected function setUp(): void
    {
        $bannedCardRepo = $this->createMock(BannedCardRepository::class);
        $bannedCardRepo->method('findBannedCardKeys')->willReturn([
            'AOR|74' => true,   // Forest of Giant Plants
            'PHF|99' => true,   // Lysandre's Trump Card
            'PHF|118' => true,  // Lysandre's Trump Card (full art)
        ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []): string => $id.' '.implode(' ', array_map('strval', array_values($params))),
        );

        $this->validator = new DeckListValidator($bannedCardRepo, $translator);
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
        self::assertStringContainsString('app.deck.validation.card_count', $result->errors[0]);
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

    public function testBannedCardProducesError(): void
    {
        $cards = [];

        // Include a banned card (Forest of Giant Plants, AOR 74)
        $cards[] = new ParsedCard(4, 'Forest of Giant Plants', 'AOR', '74', 'trainer');

        // Fill with valid cards to reach 60
        for ($i = 1; $i <= 14; ++$i) {
            $cards[] = new ParsedCard(4, 'Pokemon '.$i, 'BRS', (string) $i, 'pokemon');
        }

        $parseResult = new DeckListParseResult($cards, [], ['trainer' => 4, 'pokemon' => 56]);

        $result = $this->validator->validate($parseResult);

        self::assertFalse($result->isValid());

        $bannedError = false;

        foreach ($result->errors as $error) {
            if (str_contains($error, 'Forest of Giant Plants') && str_contains($error, 'banned_card')) {
                $bannedError = true;
            }
        }
        self::assertTrue($bannedError, 'Expected an error for banned card "Forest of Giant Plants".');
    }

    public function testSameNameDifferentSetNotBanned(): void
    {
        $cards = [];

        // A card with the same name but different set/number should not be banned
        $cards[] = new ParsedCard(4, 'Forest of Giant Plants', 'XY', '99', 'trainer');

        for ($i = 1; $i <= 14; ++$i) {
            $cards[] = new ParsedCard(4, 'Pokemon '.$i, 'BRS', (string) $i, 'pokemon');
        }

        $parseResult = new DeckListParseResult($cards, [], ['trainer' => 4, 'pokemon' => 56]);

        $result = $this->validator->validate($parseResult);

        self::assertTrue($result->isValid());
        self::assertCount(0, $result->errors);
    }
}

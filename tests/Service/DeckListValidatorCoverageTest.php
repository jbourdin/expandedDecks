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
 * Additional coverage for DeckListValidator — duplicate banned-card check skip.
 *
 * @see docs/features.md F6.3 — Validate deck list (card count, duplicates)
 */
class DeckListValidatorCoverageTest extends TestCase
{
    /**
     * When the same card key appears in multiple ParsedCard entries,
     * the banned-card check should only report it once (the continue branch).
     */
    public function testDuplicateBannedCardKeyIsReportedOnlyOnce(): void
    {
        $bannedCardRepo = $this->createStub(BannedCardRepository::class);
        $bannedCardRepo->method('findBannedCardKeys')->willReturn([
            'AOR|74' => true,
        ]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []): string => $id.' '.implode(' ', array_map('strval', array_values($params))),
        );

        $validator = new DeckListValidator($bannedCardRepo, $translator);

        // Two ParsedCard entries for the same banned card (split across sections)
        $cards = [
            new ParsedCard(2, 'Forest of Giant Plants', 'AOR', '74', 'trainer'),
            new ParsedCard(2, 'Forest of Giant Plants', 'AOR', '74', 'trainer'),
        ];

        // Fill to 60 cards
        for ($index = 1; $index <= 14; ++$index) {
            $cards[] = new ParsedCard(4, 'Pokemon '.$index, 'BRS', (string) $index, 'pokemon');
        }

        $parseResult = new DeckListParseResult($cards, [], ['trainer' => 4, 'pokemon' => 56]);

        $result = $validator->validate($parseResult);

        // Should have exactly one banned-card error (not two), plus one max-copies error
        $bannedErrors = array_filter($result->errors, static fn (string $error): bool => str_contains($error, 'banned_card'));
        self::assertCount(1, $bannedErrors, 'Duplicate card key should only trigger one banned-card error.');
    }
}

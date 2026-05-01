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

namespace App\Tests\Service\DeckList;

use App\Service\DeckList\CardNumberFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.7 — Export deck list as PTCGL text
 */
class CardNumberFormatterTest extends TestCase
{
    #[DataProvider('cardNumberProvider')]
    public function testDisplay(string $input, string $expected): void
    {
        self::assertSame($expected, CardNumberFormatter::display($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cardNumberProvider(): iterable
    {
        yield 'plain number is unchanged' => ['51', '51'];
        yield 'three-digit unpadded is unchanged' => ['127', '127'];
        yield 'leading zero is stripped' => ['051', '51'];
        yield 'multiple leading zeros are stripped' => ['086', '86'];
        yield 'all zeros collapses to single zero' => ['000', '0'];
        yield 'single zero is preserved' => ['0', '0'];
        yield 'empty string is preserved' => ['', ''];
        yield 'alphanumeric suffix is preserved' => ['001A', '1A'];
        yield 'leading-letter number is unchanged' => ['SV001', 'SV001'];
    }
}

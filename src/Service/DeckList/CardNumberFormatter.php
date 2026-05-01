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

namespace App\Service\DeckList;

/**
 * Formats card set numbers for user-facing output.
 *
 * Card numbers may be stored zero-padded (e.g. "051", "086"), but PTCG Live's
 * text format and the on-screen displays expect plain numbers ("51", "86").
 *
 * @see docs/features.md F6.7 — Export deck list as PTCGL text
 */
final class CardNumberFormatter
{
    public static function display(string $cardNumber): string
    {
        if ('' === $cardNumber) {
            return '';
        }

        $stripped = ltrim($cardNumber, '0');

        return '' === $stripped ? '0' : $stripped;
    }
}

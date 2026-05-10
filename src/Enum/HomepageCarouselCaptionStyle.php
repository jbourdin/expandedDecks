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

namespace App\Enum;

/**
 * Color-and-outline preset for a carousel item's caption overlay.
 *
 * Stored as the `captionStyle` key on each carousel item's JSON in
 * `HomepageLayout.blocks`. When absent or unrecognised, callers should
 * fall back to {@see self::WhiteOnBlack} (the most readable default on
 * arbitrary photographic backgrounds).
 *
 * @see https://github.com/jbourdin/expandedDecks/issues/555
 */
enum HomepageCarouselCaptionStyle: string
{
    case WhiteOnBlack = 'white_on_black';
    case BlackOnWhite = 'black_on_white';
    case Brand = 'brand';

    /**
     * Resolve a (potentially missing or invalid) caption style string to an
     * enum case, defaulting to {@see self::WhiteOnBlack}.
     */
    public static function fromStringOrDefault(?string $value): self
    {
        if (null === $value || '' === $value) {
            return self::WhiteOnBlack;
        }

        return self::tryFrom($value) ?? self::WhiteOnBlack;
    }
}

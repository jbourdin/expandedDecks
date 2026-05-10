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
 * Layout variants for the homepage carousel block.
 *
 * Stored as the `variant` key on each carousel block's JSON in
 * `HomepageLayout.blocks`. When absent or unrecognised, callers should
 * fall back to {@see self::Slideshow}.
 *
 * @see https://github.com/jbourdin/expandedDecks/issues/553
 */
enum HomepageCarouselVariant: string
{
    case Slideshow = 'slideshow';
    case FeatureGrid = 'feature_grid';

    /**
     * Number of carousel items the variant displays. Variants that need a
     * specific count expose it here so the renderer can fall back to
     * {@see self::Slideshow} when fewer items survive scheduling.
     */
    public function requiredItemCount(): ?int
    {
        return match ($this) {
            self::Slideshow => null,
            self::FeatureGrid => 3,
        };
    }

    /**
     * Resolve a (potentially missing or invalid) variant string to an enum
     * case, defaulting to {@see self::Slideshow}.
     */
    public static function fromStringOrDefault(?string $value): self
    {
        if (null === $value || '' === $value) {
            return self::Slideshow;
        }

        return self::tryFrom($value) ?? self::Slideshow;
    }
}

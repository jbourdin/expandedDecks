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
 * @see docs/features.md F2.15 — Archetype playstyle tags
 */
enum PlaystyleTag: string
{
    case Aggressive = 'aggressive';
    case Control = 'control';
    case Combo = 'combo';
    case Lock = 'lock';
    case Spread = 'spread';
    case Toolbox = 'toolbox';

    public function translationKey(): string
    {
        return 'app.archetype.playstyle.'.$this->value;
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Aggressive => 'danger',
            self::Control => 'primary',
            self::Combo => 'warning',
            self::Lock => 'dark',
            self::Spread => 'info',
            self::Toolbox => 'success',
        };
    }
}

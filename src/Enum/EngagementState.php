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
 * @see docs/features.md F3.4 — Register participation to an event
 * @see docs/features.md F3.13 — Player engagement states
 */
enum EngagementState: string
{
    case Interested = 'interested';
    case Invited = 'invited';
    case RegisteredPlaying = 'registered_playing';
    case RegisteredSpectating = 'registered_spectating';
}

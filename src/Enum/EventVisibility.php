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
 * @see docs/features.md F3.11 — Event visibility
 */
enum EventVisibility: string
{
    case Public = 'public';
    case Draft = 'draft';
    case Private = 'private';
}

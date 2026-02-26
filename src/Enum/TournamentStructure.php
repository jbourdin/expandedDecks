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
 * @see docs/features.md F3.1 — Create a new event
 */
enum TournamentStructure: string
{
    case Swiss = 'swiss';
    case SwissTopCut = 'swiss_top_cut';
    case SingleElimination = 'single_elimination';
    case RoundRobin = 'round_robin';
}

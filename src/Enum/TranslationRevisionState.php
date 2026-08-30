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
 * Workflow state of a translation revision.
 *
 * Revisions snapshotted from direct editor saves (including every
 * source-locale write) are born `Validated` — the review states are only
 * traversed by translator submissions (F9.9).
 *
 * @see docs/features.md F9.7 — Translation foundation
 */
enum TranslationRevisionState: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Validated = 'validated';
    case Rejected = 'rejected';
}

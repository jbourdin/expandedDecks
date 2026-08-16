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

namespace App\Service\Translation;

/**
 * Mutable flag shared between `TranslationReviewService` and
 * `TranslationRevisionListener`.
 *
 * Approving a revision copies its fields into the live translation row; that
 * live write must NOT auto-snapshot a new revision — the approved revision
 * itself is the history entry, so the "every live write leaves a validated
 * revision behind" invariant already holds.
 *
 * @see docs/features.md F9.9 — Translation review workflow
 */
final class TranslationRevisionSuppression
{
    private bool $suppressed = false;

    public function suppress(): void
    {
        $this->suppressed = true;
    }

    public function release(): void
    {
        $this->suppressed = false;
    }

    public function isSuppressed(): bool
    {
        return $this->suppressed;
    }
}

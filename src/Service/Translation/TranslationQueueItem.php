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
 * One row of the translation queue (F9.12). For the archetype tab a row
 * represents the whole context (archetype + its variants) with aggregate
 * variant counters; other tabs carry zeros.
 *
 * @see docs/features.md F9.12 — Translation queue
 */
final readonly class TranslationQueueItem
{
    public function __construct(
        public string $contentType,
        public int $contentId,
        public string $label,
        public string $locale,
        public ?string $state,
        public bool $sourceOutdated,
        public int $pendingVariants = 0,
        public int $outdatedVariants = 0,
    ) {
    }

    /**
     * @return array{contentType: string, contentId: int, label: string, locale: string, state: ?string, sourceOutdated: bool, pendingVariants: int, outdatedVariants: int}
     */
    public function toArray(): array
    {
        return [
            'contentType' => $this->contentType,
            'contentId' => $this->contentId,
            'label' => $this->label,
            'locale' => $this->locale,
            'state' => $this->state,
            'sourceOutdated' => $this->sourceOutdated,
            'pendingVariants' => $this->pendingVariants,
            'outdatedVariants' => $this->outdatedVariants,
        ];
    }
}

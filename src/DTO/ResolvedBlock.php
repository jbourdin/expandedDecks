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

namespace App\DTO;

use App\Enum\HomepageBlockType;

/**
 * A fully resolved homepage block ready for rendering.
 *
 * @see docs/features.md F10.4 — Homepage rendering service and Twig block partials
 */
final readonly class ResolvedBlock
{
    /**
     * @param array<string, mixed> $settings     Non-translatable block settings (from HomepageLayout.blocks)
     * @param array<string, mixed> $translations Translated content (from HomepageLayoutTranslation)
     * @param array<string, mixed> $resolvedData Dynamic data resolved at runtime (counts, page lists, etc.)
     */
    public function __construct(
        public HomepageBlockType $type,
        public ?int $columnWidth,
        public ?string $cssClasses,
        public array $settings,
        public array $translations,
        public array $resolvedData,
    ) {
    }
}

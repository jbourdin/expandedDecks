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

namespace App\Security;

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\MenuCategory;
use App\Entity\Page;

/**
 * Voter subject describing "translate this content into this locale".
 *
 * @see docs/features.md F9.8 — Translation roles & access
 */
final readonly class TranslationTarget
{
    public function __construct(
        public Page|Archetype|MenuCategory|Deck $content,
        public string $locale,
    ) {
    }
}

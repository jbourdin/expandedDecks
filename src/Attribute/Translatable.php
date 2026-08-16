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

namespace App\Attribute;

/**
 * Marks a translation-entity property as part of the translation workflow.
 *
 * The attribute is the single registry deciding three things at once: which
 * fields the revision listener snapshots, which source-locale changes flag
 * sibling translations as outdated, and which fields the translator form
 * edits. A per-locale property without the attribute (e.g. `ogImage`) stays
 * editor-managed and never enters the workflow.
 *
 * @see docs/features.md F9.7 — Translation foundation
 * @see docs/technicalities/translation_workflow.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Translatable
{
}

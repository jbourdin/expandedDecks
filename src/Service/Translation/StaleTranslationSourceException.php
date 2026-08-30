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
 * Submission refused because a newer source-locale revision exists than the
 * one the translation was pinned to (US-T5). The message is a translation
 * key, rendered by the translator UI (F9.10).
 *
 * @see docs/features.md F9.9 — Translation review workflow
 */
final class StaleTranslationSourceException extends \DomainException
{
    public const string TRANSLATION_KEY = 'app.translation.error.stale_source';

    public function __construct()
    {
        parent::__construct(self::TRANSLATION_KEY);
    }
}

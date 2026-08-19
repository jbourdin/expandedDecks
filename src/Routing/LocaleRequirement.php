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

namespace App\Routing;

/**
 * Route requirement for the `_locale` parameter.
 *
 * Locales are admin-managed per channel (F9.18), so the router accepts any
 * ISO 639-1 shaped code; whether a locale actually exists on the current
 * channel — and who may browse it while it is a draft — is decided by
 * `LocaleListener`, which redirects unknown or unauthorized locales to the
 * channel's published equivalent.
 *
 * @see docs/features.md F9.18 — Admin-managed channel locales
 */
final class LocaleRequirement
{
    public const string PATTERN = '[a-z]{2}';

    private function __construct()
    {
    }
}

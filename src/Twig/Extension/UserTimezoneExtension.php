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

namespace App\Twig\Extension;

use App\Twig\Runtime\UserTimezoneRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @see docs/features.md F9.2 — User timezone
 */
class UserTimezoneExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('user_datetime', [UserTimezoneRuntime::class, 'formatDatetime']),
            new TwigFilter('user_date', [UserTimezoneRuntime::class, 'formatDate']),
            new TwigFilter('tz_abbr', [UserTimezoneRuntime::class, 'timezoneAbbreviation']),
        ];
    }
}

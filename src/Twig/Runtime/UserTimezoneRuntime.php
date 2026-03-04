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

namespace App\Twig\Runtime;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Formats datetimes in the user's timezone and locale using IntlDateFormatter.
 *
 * @see docs/features.md F9.2 — User timezone
 */
class UserTimezoneRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Formats a datetime with both date and time (e.g. "Mar 4, 2026 3:30 PM" / "4 mars 2026 15:30").
     */
    public function formatDatetime(\DateTimeInterface $date): string
    {
        return $this->format($date, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
    }

    /**
     * Formats a date only (e.g. "Mar 4, 2026" / "4 mars 2026").
     */
    public function formatDate(\DateTimeInterface $date): string
    {
        return $this->format($date, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
    }

    private function format(\DateTimeInterface $date, int $dateType, int $timeType): string
    {
        $user = $this->security->getUser();

        $timezone = 'UTC';
        $locale = 'en';

        if ($user instanceof User) {
            $timezone = $user->getTimezone();
            $locale = $user->getPreferredLocale();
        } else {
            $request = $this->requestStack->getCurrentRequest();
            if (null !== $request) {
                $locale = $request->getLocale();
            }
        }

        $formatter = new \IntlDateFormatter(
            $locale,
            $dateType,
            $timeType,
            $timezone,
        );

        $result = $formatter->format($date);
        \assert(false !== $result);

        return $result;
    }
}

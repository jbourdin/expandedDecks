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
 * Formats datetimes in a given or user-preferred timezone and locale.
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
     *
     * @param string|null $timezone explicit timezone override (e.g. event timezone); null = user preference
     */
    public function formatDatetime(\DateTimeInterface $date, ?string $timezone = null): string
    {
        return $this->format($date, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, $timezone);
    }

    /**
     * Formats a date only (e.g. "Mar 4, 2026" / "4 mars 2026").
     *
     * @param string|null $timezone explicit timezone override; null = user preference
     */
    public function formatDate(\DateTimeInterface $date, ?string $timezone = null): string
    {
        return $this->format($date, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, $timezone);
    }

    /**
     * Returns the short timezone abbreviation (e.g. "EST", "CET") for a datetime in a given timezone.
     */
    public function timezoneAbbreviation(\DateTimeInterface $date, ?string $timezone = null): string
    {
        [$resolvedTimezone] = $this->resolveContext($timezone);

        $dt = \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone($resolvedTimezone));

        return $dt->format('T');
    }

    private function format(\DateTimeInterface $date, int $dateType, int $timeType, ?string $timezone): string
    {
        [$resolvedTimezone, $locale] = $this->resolveContext($timezone);

        $formatter = new \IntlDateFormatter(
            $locale,
            $dateType,
            $timeType,
            $resolvedTimezone,
        );

        $result = $formatter->format($date);
        \assert(false !== $result);

        return $result;
    }

    /**
     * @return array{string, string} [timezone, locale]
     */
    private function resolveContext(?string $timezone): array
    {
        $user = $this->security->getUser();

        $resolvedTimezone = $timezone ?? 'UTC';
        $locale = 'en';

        if ($user instanceof User) {
            if (null === $timezone) {
                $resolvedTimezone = $user->getTimezone();
            }
            $locale = $user->getPreferredLocale();
        } else {
            $request = $this->requestStack->getCurrentRequest();
            if (null !== $request) {
                $locale = $request->getLocale();
            }
        }

        return [$resolvedTimezone, $locale];
    }
}

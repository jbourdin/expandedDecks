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

namespace App\Tests\Twig;

use App\Entity\User;
use App\Twig\Runtime\UserTimezoneRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class UserTimezoneExtensionTest extends TestCase
{
    public function testFormatDatetimeWithUserTimezone(): void
    {
        $user = new User();
        $user->setTimezone('Europe/Paris');
        $user->setPreferredLocale('fr');

        $runtime = $this->createRuntime($user);
        $date = new \DateTimeImmutable('2026-03-04 14:30:00', new \DateTimeZone('UTC'));

        $result = $runtime->formatDatetime($date);

        // Europe/Paris is UTC+1 in March, so 14:30 UTC → 15:30 CET
        self::assertStringContainsString('15:30', $result);
        self::assertStringContainsString('mars', $result);
    }

    public function testFormatDatetimeWithExplicitTimezone(): void
    {
        $user = new User();
        $user->setTimezone('Europe/Paris');
        $user->setPreferredLocale('en');

        $runtime = $this->createRuntime($user);
        $date = new \DateTimeImmutable('2026-03-04 14:30:00', new \DateTimeZone('UTC'));

        // Explicit timezone overrides user preference
        $result = $runtime->formatDatetime($date, 'America/New_York');

        // 14:30 UTC → 09:30 EST
        self::assertStringContainsString('9:30', $result);
    }

    public function testFormatDateWithUserTimezone(): void
    {
        $user = new User();
        $user->setTimezone('America/New_York');
        $user->setPreferredLocale('en');

        $runtime = $this->createRuntime($user);
        $date = new \DateTimeImmutable('2026-03-04 14:30:00', new \DateTimeZone('UTC'));

        $result = $runtime->formatDate($date);

        self::assertStringContainsString('Mar', $result);
        self::assertStringContainsString('2026', $result);
        // Should NOT contain time
        self::assertStringNotContainsString(':', $result);
    }

    public function testAnonymousUserUsesUtcAndRequestLocale(): void
    {
        $runtime = $this->createRuntime(null, 'fr');
        $date = new \DateTimeImmutable('2026-03-04 14:30:00', new \DateTimeZone('UTC'));

        $result = $runtime->formatDatetime($date);

        self::assertStringContainsString('14:30', $result);
        self::assertStringContainsString('mars', $result);
    }

    public function testFormatDatetimeEnglishLocale(): void
    {
        $user = new User();
        $user->setTimezone('UTC');
        $user->setPreferredLocale('en');

        $runtime = $this->createRuntime($user);
        $date = new \DateTimeImmutable('2026-03-04 14:30:00', new \DateTimeZone('UTC'));

        $result = $runtime->formatDatetime($date);

        self::assertStringContainsString('Mar', $result);
        self::assertStringContainsString('2026', $result);
    }

    public function testTimezoneAbbreviation(): void
    {
        $runtime = $this->createRuntime(null);
        $date = new \DateTimeImmutable('2026-03-04 14:30:00', new \DateTimeZone('UTC'));

        self::assertSame('EST', $runtime->timezoneAbbreviation($date, 'America/New_York'));
        self::assertSame('UTC', $runtime->timezoneAbbreviation($date, 'UTC'));
        self::assertSame('CET', $runtime->timezoneAbbreviation($date, 'Europe/Paris'));
    }

    public function testTimezoneAbbreviationDst(): void
    {
        $runtime = $this->createRuntime(null);
        // July = DST in New York
        $date = new \DateTimeImmutable('2026-07-04 14:30:00', new \DateTimeZone('UTC'));

        self::assertSame('EDT', $runtime->timezoneAbbreviation($date, 'America/New_York'));
        self::assertSame('CEST', $runtime->timezoneAbbreviation($date, 'Europe/Paris'));
    }

    private function createRuntime(?User $user, string $requestLocale = 'en'): UserTimezoneRuntime
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $request = Request::create('/');
        $request->setLocale($requestLocale);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new UserTimezoneRuntime($security, $requestStack);
    }
}

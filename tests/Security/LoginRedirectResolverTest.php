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

namespace App\Tests\Security;

use App\Entity\Channel;
use App\Security\LoginRedirectResolver;
use App\Service\Channel\ChannelContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @see docs/features.md F1.2 — Log in / Log out
 */
class LoginRedirectResolverTest extends TestCase
{
    /**
     * @return list<array{string}>
     */
    public static function unsafePathsProvider(): array
    {
        return [
            [''],
            ['relative/path'],
            ['//evil.com/path'],
            ['https://evil.com'],
            ['http://evil.com'],
            ['/foo://bar'],
            ['/register?_target_path=/login'],
            ['/register%3F_target_path%3D/login'],
            ['/register%3F_target_path%253D/login'],
        ];
    }

    #[DataProvider('unsafePathsProvider')]
    public function testIsSafePathRejectsUnsafeValues(string $path): void
    {
        $resolver = new LoginRedirectResolver($this->buildChannelContext(new Channel()));

        self::assertFalse($resolver->isSafePath($path));
    }

    /**
     * @return list<array{string}>
     */
    public static function safePathsProvider(): array
    {
        return [
            ['/'],
            ['/deck'],
            ['/deck/ABC123'],
            ['/profile?tab=notifications'],
        ];
    }

    #[DataProvider('safePathsProvider')]
    public function testIsSafePathAcceptsRelativeSamesite(string $path): void
    {
        $resolver = new LoginRedirectResolver($this->buildChannelContext(new Channel()));

        self::assertTrue($resolver->isSafePath($path));
    }

    public function testDefaultRouteIsDashboardWhenChannelHasDecks(): void
    {
        $channel = (new Channel())->setEnableDecks(true);
        $resolver = new LoginRedirectResolver($this->buildChannelContext($channel));

        self::assertSame('app_dashboard', $resolver->defaultRouteName());
    }

    public function testDefaultRouteIsHomeWhenChannelLacksDecks(): void
    {
        $channel = (new Channel())->setEnableDecks(false);
        $resolver = new LoginRedirectResolver($this->buildChannelContext($channel));

        self::assertSame('app_home', $resolver->defaultRouteName());
    }

    private function buildChannelContext(Channel $channel): ChannelContext
    {
        $request = new Request();
        $request->attributes->set('_channel', $channel);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new ChannelContext($requestStack);
    }
}

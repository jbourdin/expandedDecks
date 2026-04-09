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

namespace App\Tests\EventListener;

use App\Entity\Channel;
use App\EventListener\ThemeRequestListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Loader\FilesystemLoader;

/**
 * @see docs/features.md F18.28 — Per-channel theme system
 */
final class ThemeRequestListenerTest extends TestCase
{
    public function testPrependsThemePathWhenThemeExists(): void
    {
        $projectDir = \dirname(__DIR__, 2);
        $loader = new FilesystemLoader($projectDir.'/templates');

        $channel = (new Channel())->setCode('content')->setDomain('expandedtalks.wip')->setThemeName('expandedtalks');

        $listener = new ThemeRequestListener($loader, $projectDir);
        $listener($this->createEvent($channel));

        $paths = $loader->getPaths();
        self::assertSame($projectDir.'/templates/themes/expandedtalks', $paths[0]);
    }

    public function testSkipsWhenNoThemeName(): void
    {
        $projectDir = \dirname(__DIR__, 2);
        $loader = new FilesystemLoader($projectDir.'/templates');
        $originalPaths = $loader->getPaths();

        $channel = (new Channel())->setCode('app')->setDomain('expanded-decks.wip');

        $listener = new ThemeRequestListener($loader, $projectDir);
        $listener($this->createEvent($channel));

        self::assertSame($originalPaths, $loader->getPaths());
    }

    public function testSkipsWhenThemeDirectoryDoesNotExist(): void
    {
        $projectDir = \dirname(__DIR__, 2);
        $loader = new FilesystemLoader($projectDir.'/templates');
        $originalPaths = $loader->getPaths();

        $channel = (new Channel())->setCode('test')->setDomain('test.wip')->setThemeName('nonexistent');

        $listener = new ThemeRequestListener($loader, $projectDir);
        $listener($this->createEvent($channel));

        self::assertSame($originalPaths, $loader->getPaths());
    }

    public function testSkipsSubRequest(): void
    {
        $projectDir = \dirname(__DIR__, 2);
        $loader = new FilesystemLoader($projectDir.'/templates');
        $originalPaths = $loader->getPaths();

        $channel = (new Channel())->setCode('content')->setDomain('expandedtalks.wip')->setThemeName('expandedtalks');

        $listener = new ThemeRequestListener($loader, $projectDir);
        $listener($this->createEvent($channel, HttpKernelInterface::SUB_REQUEST));

        self::assertSame($originalPaths, $loader->getPaths());
    }

    public function testSkipsWhenNoChannelAttribute(): void
    {
        $projectDir = \dirname(__DIR__, 2);
        $loader = new FilesystemLoader($projectDir.'/templates');
        $originalPaths = $loader->getPaths();

        $request = Request::create('/');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ThemeRequestListener($loader, $projectDir);
        $listener($event);

        self::assertSame($originalPaths, $loader->getPaths());
    }

    private function createEvent(Channel $channel, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/');
        $request->attributes->set('_channel', $channel);

        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, $requestType);
    }
}

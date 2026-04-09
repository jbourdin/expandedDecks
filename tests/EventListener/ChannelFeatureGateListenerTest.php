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
use App\EventListener\ChannelFeatureGateListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @see docs/features.md F18.7 — Feature-gate middleware for deck, event, and borrow routes
 */
final class ChannelFeatureGateListenerTest extends TestCase
{
    public function testDeckRouteBlockedWhenDecksDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/deck/AB3K7N', $this->createContentChannel()));
    }

    public function testDeckRouteAllowedWhenDecksEnabled(): void
    {
        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/deck/AB3K7N', $this->createAppChannel()));

        self::assertTrue(true);
    }

    public function testEventRouteBlockedWhenEventsDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/event/42', $this->createContentChannel()));
    }

    public function testBorrowRouteBlockedWhenBorrowsDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/borrow/1', $this->createContentChannel()));
    }

    public function testBorrowsListRouteBlockedWhenBorrowsDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/borrows', $this->createContentChannel()));
    }

    public function testDashboardBlockedWhenDecksDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/dashboard', $this->createContentChannel()));
    }

    public function testRegisterBlockedWhenRegisterDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/register', $this->createContentChannel()));
    }

    public function testProfileAlwaysAllowed(): void
    {
        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/profile', $this->createContentChannel()));

        self::assertTrue(true);
    }

    public function testForgotPasswordAlwaysAllowed(): void
    {
        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/forgot-password', $this->createContentChannel()));

        self::assertTrue(true);
    }

    public function testLoginAlwaysAllowed(): void
    {
        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/login', $this->createContentChannel()));

        self::assertTrue(true);
    }

    public function testAdminAlwaysAllowed(): void
    {
        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/admin/channels', $this->createContentChannel()));

        self::assertTrue(true);
    }

    public function testProfilerAlwaysAllowed(): void
    {
        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/_profiler/abc123', $this->createContentChannel()));

        self::assertTrue(true);
    }

    public function testHomepageAllowedOnContentChannel(): void
    {
        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/', $this->createContentChannel()));

        self::assertTrue(true);
    }

    public function testSubRequestIsIgnored(): void
    {
        $listener = new ChannelFeatureGateListener();
        $listener($this->createEvent('/deck/AB3K7N', $this->createContentChannel(), HttpKernelInterface::SUB_REQUEST));

        self::assertTrue(true);
    }

    public function testNoChannelAttributeIsIgnored(): void
    {
        $request = Request::create('/deck/AB3K7N');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new ChannelFeatureGateListener();
        $listener($event);

        self::assertTrue(true);
    }

    private function createAppChannel(): Channel
    {
        return (new Channel())
            ->setCode('app')
            ->setDomain('expandeddecks.wip')
            ->setEnableDecks(true)
            ->setEnableRegister(true)
            ->setEnableEvents(true)
            ->setEnableBorrows(true)
            ->setEnableArchetypes(false);
    }

    private function createContentChannel(): Channel
    {
        return (new Channel())
            ->setCode('content')
            ->setDomain('expandedtalks.wip')
            ->setEnableDecks(false)
            ->setEnableRegister(false)
            ->setEnableEvents(false)
            ->setEnableBorrows(false)
            ->setEnableArchetypes(true);
    }

    private function createEvent(string $path, Channel $channel, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create($path);
        $request->attributes->set('_channel', $channel);

        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, $requestType);
    }
}

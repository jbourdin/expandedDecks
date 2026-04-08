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
use App\EventListener\ChannelResolverListener;
use App\Repository\ChannelRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @see docs/features.md F18.2 — Channel resolver: request-to-channel matching via domain
 */
final class ChannelResolverListenerTest extends TestCase
{
    public function testChannelResolvedFromHostHeader(): void
    {
        $channel = (new Channel())->setCode('app')->setDomain('expanded-decks.wip');

        $repository = $this->createStub(ChannelRepository::class);
        $repository->method('findByDomain')->willReturn($channel);

        $request = Request::create('/', server: ['HTTP_HOST' => 'expanded-decks.wip']);
        $event = $this->createRequestEvent($request);

        $listener = new ChannelResolverListener($repository, $this->createStub(EntityManagerInterface::class));
        $listener($event);

        self::assertSame($channel, $request->attributes->get('_channel'));
    }

    public function testFallsBackToDefaultChannelWhenHostNotFound(): void
    {
        $defaultChannel = (new Channel())->setCode('app')->setDomain('expanded-decks.wip');

        $repository = $this->createStub(ChannelRepository::class);
        $repository->method('findByDomain')->willReturn(null);
        $repository->method('findByCode')->willReturn($defaultChannel);

        $request = Request::create('/', server: ['HTTP_HOST' => 'unknown.example.com']);
        $event = $this->createRequestEvent($request);

        $listener = new ChannelResolverListener($repository, $this->createStub(EntityManagerInterface::class));
        $listener($event);

        self::assertSame($defaultChannel, $request->attributes->get('_channel'));
    }

    public function testCreatesDefaultChannelWhenNoneExist(): void
    {
        $repository = $this->createStub(ChannelRepository::class);
        $repository->method('findByDomain')->willReturn(null);
        $repository->method('findByCode')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Channel::class));
        $entityManager->expects(self::once())->method('flush');

        $request = Request::create('/', server: ['HTTP_HOST' => 'unknown.example.com']);
        $event = $this->createRequestEvent($request);

        $listener = new ChannelResolverListener($repository, $entityManager);
        $listener($event);

        $channel = $request->attributes->get('_channel');
        self::assertInstanceOf(Channel::class, $channel);
        self::assertSame('app', $channel->getCode());
        self::assertSame('unknown.example.com', $channel->getDomain());
        self::assertTrue($channel->getEnableDecks());
        self::assertTrue($channel->getEnableRegister());
    }

    public function testSubRequestIsIgnored(): void
    {
        $repository = $this->createStub(ChannelRepository::class);

        $request = Request::create('/');
        $event = $this->createRequestEvent($request, HttpKernelInterface::SUB_REQUEST);

        $listener = new ChannelResolverListener($repository, $this->createStub(EntityManagerInterface::class));
        $listener($event);

        self::assertFalse($request->attributes->has('_channel'));
    }

    private function createRequestEvent(Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, $requestType);
    }
}

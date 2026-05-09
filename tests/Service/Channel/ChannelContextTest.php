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

namespace App\Tests\Service\Channel;

use App\Entity\Channel;
use App\Service\Channel\ChannelContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @see docs/features.md F18.2 — Channel resolver: request-to-channel matching via domain
 */
final class ChannelContextTest extends TestCase
{
    public function testGetChannelReturnsChannelFromRequestAttribute(): void
    {
        $channel = (new Channel())->setCode('app')->setDomain('expandeddecks.wip');

        $request = new Request();
        $request->attributes->set('_channel', $channel);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $context = new ChannelContext($requestStack);

        self::assertSame($channel, $context->getChannel());
    }

    public function testGetChannelThrowsWhenNoRequest(): void
    {
        $context = new ChannelContext(new RequestStack());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('outside an HTTP request');

        $context->getChannel();
    }

    public function testGetChannelThrowsWhenNoChannelAttribute(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $context = new ChannelContext($requestStack);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No channel resolved');

        $context->getChannel();
    }

    public function testGetChannelThrowsWhenAttributeIsNotChannel(): void
    {
        $request = new Request();
        $request->attributes->set('_channel', 'not-a-channel');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $context = new ChannelContext($requestStack);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No channel resolved');

        $context->getChannel();
    }

    public function testGetChannelReadsFromMainRequestInsideSubRequest(): void
    {
        $channel = (new Channel())->setCode('app')->setDomain('expandeddecks.wip');

        $mainRequest = new Request();
        $mainRequest->attributes->set('_channel', $channel);

        // Symfony's ErrorListener creates a sub-request with a fresh
        // attribute bag (replaces, not merges), so `_channel` is absent here.
        $subRequest = new Request();

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);
        $requestStack->push($subRequest);

        $context = new ChannelContext($requestStack);

        self::assertSame($channel, $context->getChannel());
    }
}

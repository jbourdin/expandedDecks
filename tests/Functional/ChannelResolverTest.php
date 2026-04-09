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

namespace App\Tests\Functional;

use App\Entity\Channel;

/**
 * @see docs/features.md F18.2 — Channel resolver: request-to-channel matching via domain
 * @see docs/features.md F18.3 — Twig channel context and global template variables
 */
class ChannelResolverTest extends AbstractFunctionalTest
{
    public function testAppChannelResolvesFromHostHeader(): void
    {
        $this->client->request('GET', '/', server: ['HTTP_HOST' => 'expanded-decks.wip']);

        self::assertResponseIsSuccessful();

        $channel = $this->client->getRequest()->attributes->get('_channel');
        self::assertInstanceOf(Channel::class, $channel);
        self::assertSame('app', $channel->getCode());
    }

    public function testContentChannelResolvesFromHostHeader(): void
    {
        $this->client->request('GET', '/', server: ['HTTP_HOST' => 'expandedtalks.wip']);

        self::assertResponseIsSuccessful();

        $channel = $this->client->getRequest()->attributes->get('_channel');
        self::assertInstanceOf(Channel::class, $channel);
        self::assertSame('content', $channel->getCode());
    }

    public function testUnknownHostFallsBackToAppChannel(): void
    {
        $this->client->request('GET', '/', server: ['HTTP_HOST' => 'unknown.example.com']);

        self::assertResponseIsSuccessful();

        $channel = $this->client->getRequest()->attributes->get('_channel');
        self::assertInstanceOf(Channel::class, $channel);
        self::assertSame('app', $channel->getCode());
    }

    public function testAppChannelHasDecksEnabled(): void
    {
        $this->client->request('GET', '/', server: ['HTTP_HOST' => 'expanded-decks.wip']);

        self::assertResponseIsSuccessful();

        $channel = $this->client->getRequest()->attributes->get('_channel');
        self::assertInstanceOf(Channel::class, $channel);
        self::assertTrue($channel->getEnableDecks());
        self::assertTrue($channel->getEnableRegister());
    }

    public function testContentChannelHasDecksDisabled(): void
    {
        $this->client->request('GET', '/', server: ['HTTP_HOST' => 'expandedtalks.wip']);

        self::assertResponseIsSuccessful();

        $channel = $this->client->getRequest()->attributes->get('_channel');
        self::assertInstanceOf(Channel::class, $channel);
        self::assertFalse($channel->getEnableDecks());
        self::assertFalse($channel->getEnableRegister());
        self::assertTrue($channel->getEnableArchetypes());
    }
}

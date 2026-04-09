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

namespace App\Tests\Entity;

use App\Entity\Channel;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F18.1 — Channel entity and database schema
 */
class ChannelTest extends TestCase
{
    public function testConstructorSetsCreatedAt(): void
    {
        $channel = new Channel();

        self::assertInstanceOf(\DateTimeImmutable::class, $channel->getCreatedAt());
        self::assertNull($channel->getUpdatedAt());
    }

    public function testGettersReturnDefaults(): void
    {
        $channel = new Channel();

        self::assertNull($channel->getId());
        self::assertSame('', $channel->getCode());
        self::assertSame('', $channel->getDomain());
        self::assertFalse($channel->getEnableDecks());
        self::assertFalse($channel->getEnableRegister());
        self::assertFalse($channel->getEnableEvents());
        self::assertFalse($channel->getEnableBorrows());
        self::assertFalse($channel->getEnableArchetypes());
    }

    public function testFluentSetters(): void
    {
        $channel = new Channel();

        $result = $channel
            ->setCode('app')
            ->setDomain('expanded-decks.wip')
            ->setEnableDecks(true)
            ->setEnableRegister(true)
            ->setEnableEvents(true)
            ->setEnableBorrows(true)
            ->setEnableArchetypes(false);

        self::assertSame($channel, $result);
        self::assertSame('app', $channel->getCode());
        self::assertSame('expanded-decks.wip', $channel->getDomain());
        self::assertTrue($channel->getEnableDecks());
        self::assertTrue($channel->getEnableRegister());
        self::assertTrue($channel->getEnableEvents());
        self::assertTrue($channel->getEnableBorrows());
        self::assertFalse($channel->getEnableArchetypes());
    }

    public function testOnPreUpdateSetsUpdatedAt(): void
    {
        $channel = new Channel();

        self::assertNull($channel->getUpdatedAt());

        $channel->onPreUpdate();

        self::assertInstanceOf(\DateTimeImmutable::class, $channel->getUpdatedAt());
    }
}

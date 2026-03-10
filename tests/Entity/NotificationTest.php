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

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F8.1 — Receive in-app notification
 */
class NotificationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $notification = new Notification();

        self::assertNull($notification->getId());
        self::assertSame(NotificationType::BorrowRequested, $notification->getType());
        self::assertSame('', $notification->getTitle());
        self::assertSame('', $notification->getMessage());
        self::assertNull($notification->getContext());
        self::assertFalse($notification->isRead());
        self::assertNull($notification->getReadAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $notification->getCreatedAt());
    }

    public function testSetAndGetRecipient(): void
    {
        $notification = new Notification();
        $user = new User();

        $result = $notification->setRecipient($user);

        self::assertSame($user, $notification->getRecipient());
        self::assertSame($notification, $result);
    }

    public function testSetAndGetType(): void
    {
        $notification = new Notification();
        $result = $notification->setType(NotificationType::BorrowApproved);

        self::assertSame(NotificationType::BorrowApproved, $notification->getType());
        self::assertSame($notification, $result);
    }

    public function testSetAndGetTitle(): void
    {
        $notification = new Notification();
        $result = $notification->setTitle('Borrow Approved');

        self::assertSame('Borrow Approved', $notification->getTitle());
        self::assertSame($notification, $result);
    }

    public function testSetAndGetMessage(): void
    {
        $notification = new Notification();
        $result = $notification->setMessage('Your borrow request was approved.');

        self::assertSame('Your borrow request was approved.', $notification->getMessage());
        self::assertSame($notification, $result);
    }

    public function testSetAndGetContext(): void
    {
        $notification = new Notification();
        $context = ['borrowId' => 42, 'deckName' => 'Lugia Archeops'];

        $result = $notification->setContext($context);

        self::assertSame($context, $notification->getContext());
        self::assertSame($notification, $result);
    }

    public function testSetContextToNull(): void
    {
        $notification = new Notification();
        $notification->setContext(['key' => 'value']);
        $notification->setContext(null);

        self::assertNull($notification->getContext());
    }

    public function testSetIsReadSetsReadAt(): void
    {
        $notification = new Notification();
        $result = $notification->setIsRead(true);

        self::assertTrue($notification->isRead());
        self::assertInstanceOf(\DateTimeImmutable::class, $notification->getReadAt());
        self::assertSame($notification, $result);
    }

    public function testSetIsReadDoesNotOverwriteExistingReadAt(): void
    {
        $notification = new Notification();
        $notification->setIsRead(true);
        $firstReadAt = $notification->getReadAt();

        usleep(1000);
        $notification->setIsRead(true);

        self::assertSame($firstReadAt, $notification->getReadAt());
    }

    public function testOnPrePersistResetsCreatedAt(): void
    {
        $notification = new Notification();
        $initialCreatedAt = $notification->getCreatedAt();

        usleep(1000);
        $notification->onPrePersist();

        self::assertInstanceOf(\DateTimeImmutable::class, $notification->getCreatedAt());
        self::assertGreaterThanOrEqual($initialCreatedAt, $notification->getCreatedAt());
    }
}

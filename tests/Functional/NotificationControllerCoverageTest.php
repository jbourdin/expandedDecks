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

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Additional coverage tests for NotificationController uncovered branches.
 *
 * Covers informational borrow types (BorrowReturned) that generate
 * timeline URLs and the EventInvited anchor.
 *
 * @see docs/features.md F8.4 — In-app notification center
 */
class NotificationControllerCoverageTest extends AbstractFunctionalTest
{
    /**
     * BorrowReturned is an informational borrow type that should resolve
     * to a borrow timeline URL (#timeline).
     */
    public function testGoBorrowReturnedRedirectsToTimeline(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = new Notification();
        $notification->setRecipient($user);
        $notification->setType(NotificationType::BorrowReturned);
        $notification->setTitle('Deck returned');
        $notification->setMessage('The borrower returned your deck.');
        $notification->setContext(['borrowId' => 1, 'eventId' => 1]);
        $entityManager->persist($notification);
        $entityManager->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        self::assertResponseRedirects('/borrow/1#timeline');
    }

    /**
     * EventInvited notification should resolve to the event participation
     * section (#participation).
     */
    public function testGoEventInvitedRedirectsToParticipation(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = new Notification();
        $notification->setRecipient($user);
        $notification->setType(NotificationType::EventInvited);
        $notification->setTitle('Event invitation');
        $notification->setMessage('You have been invited to an event.');
        $notification->setContext(['eventId' => 1]);
        $entityManager->persist($notification);
        $entityManager->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        self::assertResponseRedirects('/event/1#participation');
    }

    /**
     * BorrowCancelled should redirect to event borrowing section.
     */
    public function testGoBorrowCancelledRedirectsToEventBorrowing(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = new Notification();
        $notification->setRecipient($user);
        $notification->setType(NotificationType::BorrowCancelled);
        $notification->setTitle('Borrow cancelled');
        $notification->setMessage('Your borrow was cancelled.');
        $notification->setContext(['borrowId' => 1, 'eventId' => 1]);
        $entityManager->persist($notification);
        $entityManager->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        self::assertResponseRedirects('/event/1#borrowing');
    }

    /**
     * Marking a notification as read for another user's notification
     * should be forbidden.
     */
    public function testMarkReadForbidsOtherUsersNotification(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getUser('admin@example.com');

        $notification = new Notification();
        $notification->setRecipient($admin);
        $notification->setType(NotificationType::BorrowRequested);
        $notification->setTitle('Admin notification');
        $notification->setMessage('Test');
        $entityManager->persist($notification);
        $entityManager->flush();

        $this->loginAs('borrower@example.com');

        $this->client->request('POST', '/notifications/'.$notification->getId().'/read', [
            '_token' => 'any-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * BorrowApproved (actionable) should redirect to borrow actions panel.
     */
    public function testGoBorrowApprovedRedirectsToActions(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = new Notification();
        $notification->setRecipient($user);
        $notification->setType(NotificationType::BorrowApproved);
        $notification->setTitle('Borrow approved');
        $notification->setMessage('Your borrow was approved.');
        $notification->setContext(['borrowId' => 1, 'eventId' => 1]);
        $entityManager->persist($notification);
        $entityManager->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        self::assertResponseRedirects('/borrow/1#actions');
    }

    /**
     * Already-read notification going through /go should not change read status
     * and still redirect to resolved URL.
     */
    public function testGoAlreadyReadNotificationStillRedirects(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = new Notification();
        $notification->setRecipient($user);
        $notification->setType(NotificationType::EventUpdated);
        $notification->setTitle('Event update');
        $notification->setMessage('Event was updated.');
        $notification->setContext(['eventId' => 1]);
        $notification->setIsRead(true);
        $entityManager->persist($notification);
        $entityManager->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        self::assertResponseRedirects('/event/1');
    }

    private function getUser(string $email): User
    {
        /** @var UserRepository $repository */
        $repository = static::getContainer()->get(UserRepository::class);

        /* @var User */
        return $repository->findOneBy(['email' => $email]);
    }
}

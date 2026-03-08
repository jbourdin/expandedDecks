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
 * @see docs/features.md F8.4 — In-app notification center
 */
class NotificationControllerTest extends AbstractFunctionalTest
{
    private function createNotification(EntityManagerInterface $em, User $user, string $title, bool $isRead = false): Notification
    {
        $notification = new Notification();
        $notification->setRecipient($user);
        $notification->setType(NotificationType::BorrowRequested);
        $notification->setTitle($title);
        $notification->setMessage('Test notification message for '.$title);
        if ($isRead) {
            $notification->setIsRead(true);
        }
        $em->persist($notification);

        return $notification;
    }

    private function getUser(string $email): User
    {
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);

        /* @var User */
        return $repo->findOneBy(['email' => $email]);
    }

    public function testApiListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/notifications');

        self::assertResponseRedirects('/login');
    }

    public function testApiListReturnsNotifications(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $this->createNotification($em, $user, 'Unread notification');
        $this->createNotification($em, $user, 'Read notification', true);
        $em->flush();

        $this->client->request('GET', '/api/notifications');

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        self::assertArrayHasKey('unreadCount', $data);
        self::assertArrayHasKey('notifications', $data);
        // 2 fixture unread + 1 test unread = 3
        self::assertSame(3, $data['unreadCount']);
        // 3 fixture + 2 test = 5
        self::assertCount(5, $data['notifications']);

        $titles = array_column($data['notifications'], 'title');
        self::assertContains('Unread notification', $titles);
        self::assertContains('Read notification', $titles);
    }

    public function testApiMarkReadMarksNotification(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $user, 'To be read');
        $em->flush();

        $this->client->request('POST', '/api/notifications/'.$notification->getId().'/read');

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        self::assertTrue($data['notification']['isRead']);
        // 2 fixture unread remain
        self::assertSame(2, $data['unreadCount']);
    }

    public function testApiMarkReadForbidsOtherUsersNotification(): void
    {
        $this->loginAs('borrower@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $admin, 'Admin notification');
        $em->flush();

        $this->client->request('POST', '/api/notifications/'.$notification->getId().'/read');

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiMarkAllReadClearsAll(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $this->createNotification($em, $user, 'Notif 1');
        $this->createNotification($em, $user, 'Notif 2');
        $em->flush();

        $this->client->request('POST', '/api/notifications/read-all');

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(0, $data['unreadCount']);
    }

    public function testListPageRequiresAuthentication(): void
    {
        $this->client->request('GET', '/notifications');

        self::assertResponseRedirects('/login');
    }

    public function testListPageRendersForAuthenticatedUser(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $this->createNotification($em, $user, 'Visible notification');
        $em->flush();

        $this->client->request('GET', '/notifications');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Notifications');
        self::assertSelectorTextContains('.list-group', 'Visible notification');
    }

    public function testListPageShowsEmptyState(): void
    {
        // Use borrower who has no fixture notifications
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/notifications');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.bi-bell-slash');
    }

    public function testGoMarksAsReadAndRedirectsToTarget(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $user, 'Clickable notification');
        $notification->setType(NotificationType::EventUpdated);
        $notification->setContext(['eventId' => 1]);
        $em->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        // EventUpdated links to event page top (no anchor)
        self::assertResponseRedirects('/event/1');

        /** @var Notification $updated */
        $updated = $em->find(Notification::class, $notification->getId());
        self::assertTrue($updated->isRead());
    }

    public function testGoRedirectsToAnchoredSection(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $user, 'Staff assigned');
        $notification->setType(NotificationType::StaffAssigned);
        $notification->setContext(['eventId' => 1]);
        $em->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        self::assertResponseRedirects('/event/1#staff');
    }

    public function testGoBorrowRequestRedirectsToActions(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $user, 'Borrow request');
        $notification->setType(NotificationType::BorrowRequested);
        $notification->setContext(['borrowId' => 1, 'eventId' => 1]);
        $em->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        self::assertResponseRedirects('/borrow/1#actions');
    }

    public function testGoBorrowDeniedRedirectsToEventBorrowing(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $user, 'Borrow denied');
        $notification->setType(NotificationType::BorrowDenied);
        $notification->setContext(['borrowId' => 1, 'eventId' => 1]);
        $em->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        self::assertResponseRedirects('/event/1#borrowing');
    }

    public function testGoWithoutContextRedirectsToList(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $user, 'No context notification');
        $em->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        self::assertResponseRedirects('/notifications');
    }

    public function testGoForbidsOtherUsersNotification(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $admin, 'Admin only');
        $notification->setContext(['eventId' => 1]);
        $em->flush();

        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/notifications/'.$notification->getId().'/go');

        self::assertResponseStatusCodeSame(403);
    }

    public function testMarkReadViaFormRedirects(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $user, 'To mark read');
        $em->flush();

        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/notifications');
        $form = $crawler->filter('form[action*="/notifications/'.$notification->getId().'/read"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects();
    }

    public function testMarkAllReadViaFormRedirects(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $this->createNotification($em, $user, 'Notif for mark all');
        $em->flush();

        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/notifications');
        $form = $crawler->filter('form[action*="/notifications/read-all"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects();
    }

    public function testDeleteNotificationRemovesIt(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $user, 'To delete');
        $em->flush();
        $id = $notification->getId();

        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/notifications');
        $form = $crawler->filter('form[action*="/notifications/'.$id.'/delete"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects();

        self::assertNull($em->find(Notification::class, $id));
    }

    public function testDeleteNotificationForbidsOtherUser(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getUser('admin@example.com');

        $notification = $this->createNotification($em, $admin, 'Admin only delete');
        $em->flush();

        $this->loginAs('borrower@example.com');

        $this->client->request('POST', '/notifications/'.$notification->getId().'/delete');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteReadRemovesOnlyReadNotifications(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $borrower = $this->getUser('borrower@example.com');

        $read = $this->createNotification($em, $borrower, 'Read to delete', true);
        $unread = $this->createNotification($em, $borrower, 'Unread to keep');
        $em->flush();
        $readId = $read->getId();
        $unreadId = $unread->getId();

        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', '/notifications');
        $form = $crawler->filter('form[action*="/notifications/delete-read"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects();

        self::assertNull($em->find(Notification::class, $readId));
        self::assertNotNull($em->find(Notification::class, $unreadId));
    }
}

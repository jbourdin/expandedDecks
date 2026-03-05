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

namespace App\Tests\Service;

use App\Entity\Event;
use App\Entity\EventEngagement;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Service\EventNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F8.2 — Event notifications
 */
class EventNotificationServiceTest extends TestCase
{
    private EventNotificationService $service;
    private EntityManagerInterface&MockObject $em;
    private MailerInterface&MockObject $mailer;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $urlGenerator->method('generate')
            ->willReturn('https://example.com/event/1');

        $this->translator->method('trans')
            ->willReturnCallback(static fn (string $key): string => $key);

        $this->service = new EventNotificationService(
            $this->em,
            $this->mailer,
            $urlGenerator,
            $this->translator,
        );
    }

    public function testNotifyStaffAssignedSendsEmailAndCreatesNotification(): void
    {
        $event = $this->createEvent();
        $staffUser = $this->createUser(2, 'staff@example.com');

        $this->mailer->expects(self::once())->method('send');
        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Notification $notification) use ($staffUser): bool {
                return $notification->getRecipient() === $staffUser
                    && NotificationType::StaffAssigned === $notification->getType();
            }));
        $this->em->expects(self::once())->method('flush');

        $this->service->notifyStaffAssigned($event, $staffUser);
    }

    public function testNotifyEventUpdatedNotifiesAllEngagedUsers(): void
    {
        $user1 = $this->createUser(2, 'user1@example.com');
        $user2 = $this->createUser(3, 'user2@example.com');
        $event = $this->createEventWithEngagements([$user1, $user2]);

        $this->mailer->expects(self::exactly(2))->method('send');
        $this->em->expects(self::exactly(2))
            ->method('persist')
            ->with(self::callback(static fn (Notification $n): bool => NotificationType::EventUpdated === $n->getType()));
        $this->em->expects(self::exactly(2))->method('flush');

        $this->service->notifyEventUpdated($event);
    }

    public function testNotifyEventUpdatedDoesNothingWithNoEngagements(): void
    {
        $event = $this->createEvent();

        $this->mailer->expects(self::never())->method('send');
        $this->em->expects(self::never())->method('persist');

        $this->service->notifyEventUpdated($event);
    }

    public function testNotifyEventCancelledNotifiesAllEngagedUsers(): void
    {
        $user1 = $this->createUser(2, 'user1@example.com');
        $user2 = $this->createUser(3, 'user2@example.com');
        $event = $this->createEventWithEngagements([$user1, $user2]);

        $this->mailer->expects(self::exactly(2))->method('send');
        $this->em->expects(self::exactly(2))
            ->method('persist')
            ->with(self::callback(static fn (Notification $n): bool => NotificationType::EventCancelled === $n->getType()));

        $this->service->notifyEventCancelled($event);
    }

    public function testNotifyUserInvitedSendsEmailAndCreatesNotification(): void
    {
        $event = $this->createEvent();
        $invitedUser = $this->createUser(2, 'invited@example.com');

        $this->mailer->expects(self::once())->method('send');
        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Notification $notification) use ($invitedUser): bool {
                return $notification->getRecipient() === $invitedUser
                    && NotificationType::EventInvited === $notification->getType();
            }));
        $this->em->expects(self::once())->method('flush');

        $this->service->notifyUserInvited($event, $invitedUser);
    }

    public function testNotificationContextContainsEventId(): void
    {
        $event = $this->createEvent();
        $user = $this->createUser(2, 'user@example.com');

        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Notification $notification): bool {
                $context = $notification->getContext();

                return null !== $context && 1 === $context['eventId'];
            }));

        $this->service->notifyStaffAssigned($event, $user);
    }

    public function testTranslationUsesRecipientLocale(): void
    {
        $event = $this->createEvent();
        $user = $this->createUser(2, 'french@example.com');
        $user->setPreferredLocale('fr');

        $locales = [];
        $this->translator->expects(self::atLeastOnce())
            ->method('trans')
            ->willReturnCallback(static function (string $key, array $params, ?string $domain, ?string $locale) use (&$locales): string {
                $locales[] = $locale;

                return $key;
            });

        $this->service->notifyStaffAssigned($event, $user);

        foreach ($locales as $locale) {
            self::assertSame('fr', $locale);
        }
    }

    private function createEvent(): Event
    {
        $event = new Event();
        $event->setName('Test Event');

        $organizer = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($organizer, 1);
        $organizer->setScreenName('Organizer');
        $event->setOrganizer($organizer);

        $eventRef = new \ReflectionProperty(Event::class, 'id');
        $eventRef->setValue($event, 1);

        return $event;
    }

    /**
     * @param User[] $users
     */
    private function createEventWithEngagements(array $users): Event
    {
        $event = $this->createEvent();

        foreach ($users as $user) {
            $engagement = new EventEngagement();
            $engagement->setUser($user);
            $engagement->setEvent($event);
            $event->getEngagements()->add($engagement);
        }

        return $event;
    }

    private function createUser(int $id, string $email): User
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);
        $user->setEmail($email);
        $user->setScreenName('User'.$id);

        return $user;
    }
}

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
use App\Entity\User;
use App\Enum\NotificationType;
use App\Service\EventNotificationService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F8.3 — Notification preferences
 */
class EventNotificationPreferenceTest extends TestCase
{
    private EventNotificationService $service;
    private EntityManagerInterface&MockObject $em;
    private MailerInterface&MockObject $mailer;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.com/event/1');
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->service = new EventNotificationService(
            $this->em,
            $this->mailer,
            $urlGenerator,
            $translator,
            'noreply@test.com',
        );
    }

    public function testNotifyStaffAssignedSkipsEmailWhenDisabled(): void
    {
        $event = $this->createEvent();
        $user = $this->createUserWithId(1);
        $user->setNotificationPreference(NotificationType::StaffAssigned, 'email', false);

        $this->mailer->expects(self::never())->method('send');
        // In-app notification should still be created
        $this->em->expects(self::once())->method('persist');

        $this->service->notifyStaffAssigned($event, $user);
    }

    public function testNotifyStaffAssignedSkipsInAppWhenDisabled(): void
    {
        $event = $this->createEvent();
        $user = $this->createUserWithId(1);
        $user->setNotificationPreference(NotificationType::StaffAssigned, 'inApp', false);

        // Email should still be sent
        $this->mailer->expects(self::once())->method('send');
        // In-app should be skipped
        $this->em->expects(self::never())->method('persist');

        $this->service->notifyStaffAssigned($event, $user);
    }

    public function testNotifyStaffAssignedSkipsBothWhenDisabled(): void
    {
        $event = $this->createEvent();
        $user = $this->createUserWithId(1);
        $user->setNotificationPreference(NotificationType::StaffAssigned, 'email', false);
        $user->setNotificationPreference(NotificationType::StaffAssigned, 'inApp', false);

        $this->mailer->expects(self::never())->method('send');
        $this->em->expects(self::never())->method('persist');

        $this->service->notifyStaffAssigned($event, $user);
    }

    public function testNotifyEventUpdatedSkipsEmailForUserWithDisabledPreference(): void
    {
        $user = $this->createUserWithId(1);
        $user->setNotificationPreference(NotificationType::EventUpdated, 'email', false);

        $event = $this->createEventWithEngagements([$user]);

        $this->mailer->expects(self::never())->method('send');
        // In-app notification should still be persisted
        $this->em->expects(self::once())->method('persist');

        $this->service->notifyEventUpdated($event);
    }

    public function testNotifyEventUpdatedSendsBothWhenPreferencesDefault(): void
    {
        $user = $this->createUserWithId(1);
        $event = $this->createEventWithEngagements([$user]);

        $this->mailer->expects(self::once())->method('send');
        $this->em->expects(self::once())->method('persist');

        $this->service->notifyEventUpdated($event);
    }

    private function createEvent(): Event
    {
        $event = new Event();
        $event->setName('Test Event');

        $organizer = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($organizer, 99);
        $event->setOrganizer($organizer);

        $eventRef = new \ReflectionProperty(Event::class, 'id');
        $eventRef->setValue($event, 1);

        return $event;
    }

    /**
     * @param list<User> $users
     */
    private function createEventWithEngagements(array $users): Event
    {
        $event = $this->createEvent();

        $engagements = new ArrayCollection();
        foreach ($users as $user) {
            $engagement = new EventEngagement();
            $engagement->setEvent($event);
            $engagement->setUser($user);
            $engagements->add($engagement);
        }

        $ref = new \ReflectionProperty(Event::class, 'engagements');
        $ref->setValue($event, $engagements);

        return $event;
    }

    private function createUserWithId(int $id): User
    {
        $user = new User();
        $user->setEmail("user{$id}@example.com");
        $user->setScreenName("User{$id}");
        $user->setFirstName('Test');
        $user->setLastName('User');

        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }
}

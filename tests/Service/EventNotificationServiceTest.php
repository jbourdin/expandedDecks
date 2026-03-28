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

use App\Entity\Borrow;
use App\Entity\Deck;
use App\Entity\DeckVersion;
use App\Entity\Event;
use App\Entity\EventEngagement;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Enum\NotificationType;
use App\Repository\BorrowRepository;
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
    private UrlGeneratorInterface $urlGenerator;
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $this->translator = $this->createStub(TranslatorInterface::class);

        $this->urlGenerator->method('generate')
            ->willReturn('https://example.com/event/1');

        $this->translator->method('trans')
            ->willReturnCallback(static fn (string $key): string => $key);

        $this->service = new EventNotificationService(
            $this->em,
            $this->mailer,
            $this->urlGenerator,
            $this->translator,
            'noreply@test.com',
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

        $this->mailer->expects(self::once())->method('send');
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
        // Silence unused class-level mocks from setUp
        $this->mailer->expects(self::never())->method('send');
        $this->em->expects(self::never())->method('persist');

        $event = $this->createEvent();
        $user = $this->createUser(2, 'french@example.com');
        $user->setPreferredLocale('fr');

        $locales = [];
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::atLeastOnce())
            ->method('trans')
            ->willReturnCallback(static function (string $key, array $params, ?string $domain, ?string $locale) use (&$locales): string {
                $locales[] = $locale;

                return $key;
            });

        $mailer = $this->createStub(MailerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);

        $service = new EventNotificationService(
            $em,
            $mailer,
            $this->urlGenerator,
            $translator,
            'noreply@test.com',
        );

        $service->notifyStaffAssigned($event, $user);

        foreach ($locales as $locale) {
            self::assertSame('fr', $locale);
        }
    }

    /**
     * @see docs/features.md F4.6 — Overdue tracking
     */
    public function testNotifyEndingPhaseSendsEmailAndNotificationToBorrowersAndOwners(): void
    {
        $event = $this->createEvent();
        $borrower = $this->createUser(2, 'borrower@example.com');
        $owner = $this->createUser(3, 'owner@example.com');

        $borrow = $this->createBorrow($owner, $borrower, 'Test Deck', $event);

        $borrowRepository = $this->createStub(BorrowRepository::class);
        $borrowRepository->method('findLentBorrowsByEvent')->willReturn([$borrow]);

        // 2 emails (borrower + owner), 2 notifications persisted
        $this->mailer->expects(self::exactly(2))->method('send');
        $this->em->expects(self::exactly(2))
            ->method('persist')
            ->with(self::callback(static fn (Notification $notification): bool => NotificationType::EventEndingPhase === $notification->getType()));

        $this->service->notifyEndingPhase($event, $borrowRepository);
    }

    public function testNotifyEndingPhaseGroupsBorrowsByBorrowerAndOwner(): void
    {
        $event = $this->createEvent();
        $borrower = $this->createUser(2, 'borrower@example.com');
        $owner = $this->createUser(3, 'owner@example.com');

        // Same borrower, same owner, 2 borrows
        $borrow1 = $this->createBorrow($owner, $borrower, 'Deck A', $event);
        $borrow2 = $this->createBorrow($owner, $borrower, 'Deck B', $event);

        $borrowRepository = $this->createStub(BorrowRepository::class);
        $borrowRepository->method('findLentBorrowsByEvent')->willReturn([$borrow1, $borrow2]);

        // 1 email to borrower + 1 email to owner = 2 emails (grouped, not 4)
        $this->mailer->expects(self::exactly(2))->method('send');
        // 1 notification to borrower + 1 notification to owner = 2
        $this->em->expects(self::exactly(2))->method('persist');

        $this->service->notifyEndingPhase($event, $borrowRepository);
    }

    public function testNotifyEndingPhaseDoesNothingWithNoLentBorrows(): void
    {
        $event = $this->createEvent();

        $borrowRepository = $this->createStub(BorrowRepository::class);
        $borrowRepository->method('findLentBorrowsByEvent')->willReturn([]);

        $this->mailer->expects(self::never())->method('send');
        $this->em->expects(self::never())->method('persist');

        $this->service->notifyEndingPhase($event, $borrowRepository);
    }

    public function testNotifyEndingPhaseSkipsEmailWhenDisabled(): void
    {
        $event = $this->createEvent();
        $borrower = $this->createUser(2, 'borrower@example.com');
        $borrower->setNotificationPreference(NotificationType::EventEndingPhase, 'email', false);
        $owner = $this->createUser(3, 'owner@example.com');
        $owner->setNotificationPreference(NotificationType::EventEndingPhase, 'email', false);

        $borrow = $this->createBorrow($owner, $borrower, 'Test Deck', $event);

        $borrowRepository = $this->createStub(BorrowRepository::class);
        $borrowRepository->method('findLentBorrowsByEvent')->willReturn([$borrow]);

        $this->mailer->expects(self::never())->method('send');
        // In-app notifications still sent (2)
        $this->em->expects(self::exactly(2))->method('persist');

        $this->service->notifyEndingPhase($event, $borrowRepository);
    }

    /**
     * @see docs/features.md F4.6 — Overdue tracking
     */
    public function testNotifyCustodyPickupSendsEmailAndNotificationToOwners(): void
    {
        $event = $this->createEvent();
        $owner = $this->createUser(2, 'owner@example.com');
        $borrower = $this->createUser(3, 'borrower@example.com');

        $borrow = $this->createBorrow($owner, $borrower, 'Custody Deck', $event);

        $borrowRepository = $this->createStub(BorrowRepository::class);
        $borrowRepository->method('findInCustodyBorrowsByEvent')->willReturn([$borrow]);

        // 1 email + 1 notification to owner
        $this->mailer->expects(self::once())->method('send');
        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(static fn (Notification $notification): bool => NotificationType::EventCustodyPickup === $notification->getType()));

        $this->service->notifyCustodyPickup($event, $borrowRepository);
    }

    public function testNotifyCustodyPickupGroupsByOwner(): void
    {
        $event = $this->createEvent();
        $owner = $this->createUser(2, 'owner@example.com');
        $borrower1 = $this->createUser(3, 'b1@example.com');
        $borrower2 = $this->createUser(4, 'b2@example.com');

        // Same owner, 2 custody borrows
        $borrow1 = $this->createBorrow($owner, $borrower1, 'Deck A', $event);
        $borrow2 = $this->createBorrow($owner, $borrower2, 'Deck B', $event);

        $borrowRepository = $this->createStub(BorrowRepository::class);
        $borrowRepository->method('findInCustodyBorrowsByEvent')->willReturn([$borrow1, $borrow2]);

        // Grouped: 1 email + 1 notification
        $this->mailer->expects(self::once())->method('send');
        $this->em->expects(self::once())->method('persist');

        $this->service->notifyCustodyPickup($event, $borrowRepository);
    }

    public function testNotifyCustodyPickupDoesNothingWithNoCustodyBorrows(): void
    {
        $event = $this->createEvent();

        $borrowRepository = $this->createStub(BorrowRepository::class);
        $borrowRepository->method('findInCustodyBorrowsByEvent')->willReturn([]);

        $this->mailer->expects(self::never())->method('send');
        $this->em->expects(self::never())->method('persist');

        $this->service->notifyCustodyPickup($event, $borrowRepository);
    }

    public function testNotifyCustodyPickupSkipsEmailWhenDisabled(): void
    {
        $event = $this->createEvent();
        $owner = $this->createUser(2, 'owner@example.com');
        $owner->setNotificationPreference(NotificationType::EventCustodyPickup, 'email', false);
        $borrower = $this->createUser(3, 'borrower@example.com');

        $borrow = $this->createBorrow($owner, $borrower, 'Custody Deck', $event);

        $borrowRepository = $this->createStub(BorrowRepository::class);
        $borrowRepository->method('findInCustodyBorrowsByEvent')->willReturn([$borrow]);

        $this->mailer->expects(self::never())->method('send');
        // In-app notification still sent
        $this->em->expects(self::once())->method('persist');

        $this->service->notifyCustodyPickup($event, $borrowRepository);
    }

    private function createBorrow(User $owner, User $borrower, string $deckName, Event $event): Borrow
    {
        $deck = new Deck();
        $deck->setName($deckName);
        $deck->setOwner($owner);
        $deckRef = new \ReflectionProperty(Deck::class, 'id');
        $deckRef->setValue($deck, random_int(1, 9999));

        $version = new DeckVersion();
        $version->setDeck($deck);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($version);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($event);
        $borrow->setStatus(BorrowStatus::Lent);

        $ref = new \ReflectionProperty(Borrow::class, 'id');
        $ref->setValue($borrow, random_int(1, 9999));

        return $borrow;
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

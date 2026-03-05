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
use App\Entity\EventDeckRegistration;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Enum\NotificationType;
use App\Repository\BorrowRepository;
use App\Service\StaffCustodyService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @see docs/features.md F8.3 — Notification preferences
 */
class StaffCustodyPreferenceTest extends TestCase
{
    private StaffCustodyService $service;
    private EntityManagerInterface&MockObject $em;
    private BorrowRepository&MockObject $borrowRepository;
    private WorkflowInterface&MockObject $workflow;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->borrowRepository = $this->createMock(BorrowRepository::class);
        $this->workflow = $this->createMock(WorkflowInterface::class);

        $this->service = new StaffCustodyService(
            $this->em,
            $this->borrowRepository,
            $this->workflow,
        );
    }

    public function testOwnerReclaimSkipsInAppNotificationWhenDisabled(): void
    {
        $owner = $this->createUserWithId(1);
        $borrower = $this->createUserWithId(2);

        // Disable in-app for BorrowReturned on the borrower
        $borrower->setNotificationPreference(NotificationType::BorrowReturned, 'inApp', false);

        $deck = new Deck();
        $deck->setName('Test Deck');
        $deck->setOwner($owner);
        $deckRef = new \ReflectionProperty(Deck::class, 'id');
        $deckRef->setValue($deck, 100);

        $event = new Event();
        $event->setName('Test Event');
        $event->setOrganizer($owner);
        $eventRef = new \ReflectionProperty(Event::class, 'id');
        $eventRef->setValue($event, 1);

        $registration = new EventDeckRegistration();
        $registration->setEvent($event);
        $registration->setDeck($deck);
        $registration->setDelegateToStaff(true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReceivedBy($owner);

        $version = new DeckVersion();
        $version->setDeck($deck);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($version);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($event);
        $borrow->setStatus(BorrowStatus::Lent);
        $borrowRef = new \ReflectionProperty(Borrow::class, 'id');
        $borrowRef->setValue($borrow, 50);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$borrow]);

        $this->workflow->method('apply')->willReturn(new Marking());

        // No notification should be persisted since in-app is disabled
        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $this->service->ownerReclaimDeck($registration, $owner);
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

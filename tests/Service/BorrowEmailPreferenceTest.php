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
use App\Entity\User;
use App\Enum\NotificationType;
use App\Service\BorrowNotificationEmailService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F8.3 — Notification preferences
 */
class BorrowEmailPreferenceTest extends TestCase
{
    private BorrowNotificationEmailService $service;
    private MailerInterface&MockObject $mailer;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.com/borrow/1');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->service = new BorrowNotificationEmailService(
            $this->mailer,
            $urlGenerator,
            $translator,
        );
    }

    public function testSendBorrowRequestedSkipsWhenEmailDisabled(): void
    {
        $borrow = $this->createBorrow();
        $borrow->getDeck()->getOwner()->setNotificationPreference(
            NotificationType::BorrowRequested,
            'email',
            false,
        );

        $this->mailer->expects(self::never())->method('send');

        $this->service->sendBorrowRequested($borrow);
    }

    public function testSendBorrowRequestedSendsWhenEmailEnabled(): void
    {
        $borrow = $this->createBorrow();

        $this->mailer->expects(self::once())->method('send');

        $this->service->sendBorrowRequested($borrow);
    }

    public function testSendBorrowApprovedSkipsWhenEmailDisabled(): void
    {
        $borrow = $this->createBorrow();
        $borrow->getBorrower()->setNotificationPreference(
            NotificationType::BorrowApproved,
            'email',
            false,
        );

        $this->mailer->expects(self::never())->method('send');

        $this->service->sendBorrowApproved($borrow);
    }

    public function testSendBorrowDeniedSkipsWhenEmailDisabled(): void
    {
        $borrow = $this->createBorrow();
        $borrow->getBorrower()->setNotificationPreference(
            NotificationType::BorrowDenied,
            'email',
            false,
        );

        $this->mailer->expects(self::never())->method('send');

        $this->service->sendBorrowDenied($borrow);
    }

    public function testSendBorrowOverdueSkipsForOwnerWhenDisabled(): void
    {
        $borrow = $this->createBorrow();
        $borrow->getDeck()->getOwner()->setNotificationPreference(
            NotificationType::BorrowOverdue,
            'email',
            false,
        );

        // Only borrower should receive the email (1 send, not 2)
        $this->mailer->expects(self::once())->method('send');

        $this->service->sendBorrowOverdue($borrow);
    }

    public function testSendBorrowOverdueSkipsForBorrowerWhenDisabled(): void
    {
        $borrow = $this->createBorrow();
        $borrow->getBorrower()->setNotificationPreference(
            NotificationType::BorrowOverdue,
            'email',
            false,
        );

        // Only owner should receive the email (1 send, not 2)
        $this->mailer->expects(self::once())->method('send');

        $this->service->sendBorrowOverdue($borrow);
    }

    public function testSendBorrowOverdueSkipsBothWhenDisabled(): void
    {
        $borrow = $this->createBorrow();
        $borrow->getDeck()->getOwner()->setNotificationPreference(
            NotificationType::BorrowOverdue,
            'email',
            false,
        );
        $borrow->getBorrower()->setNotificationPreference(
            NotificationType::BorrowOverdue,
            'email',
            false,
        );

        $this->mailer->expects(self::never())->method('send');

        $this->service->sendBorrowOverdue($borrow);
    }

    public function testSendBorrowCancelledSkipsWhenEmailDisabled(): void
    {
        $borrow = $this->createBorrow();
        // Actor is borrower, so recipient is owner
        $borrow->getDeck()->getOwner()->setNotificationPreference(
            NotificationType::BorrowCancelled,
            'email',
            false,
        );

        $this->mailer->expects(self::never())->method('send');

        $this->service->sendBorrowCancelled($borrow, $borrow->getBorrower());
    }

    private function createBorrow(): Borrow
    {
        $owner = new User();
        $owner->setEmail('owner@example.com');
        $owner->setScreenName('Owner');
        $owner->setFirstName('Own');
        $owner->setLastName('Er');
        $ownerRef = new \ReflectionProperty(User::class, 'id');
        $ownerRef->setValue($owner, 10);

        $borrower = new User();
        $borrower->setEmail('borrower@example.com');
        $borrower->setScreenName('Borrower');
        $borrower->setFirstName('Bor');
        $borrower->setLastName('Rower');
        $borrowerRef = new \ReflectionProperty(User::class, 'id');
        $borrowerRef->setValue($borrower, 20);

        $deck = new Deck();
        $deck->setName('Test Deck');
        $deck->setOwner($owner);
        $deckRef = new \ReflectionProperty(Deck::class, 'id');
        $deckRef->setValue($deck, 100);

        $version = new DeckVersion();
        $version->setDeck($deck);

        $event = new Event();
        $event->setName('Test Event');
        $event->setOrganizer($owner);
        $eventRef = new \ReflectionProperty(Event::class, 'id');
        $eventRef->setValue($event, 1);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($version);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($event);

        $ref = new \ReflectionProperty(Borrow::class, 'id');
        $ref->setValue($borrow, 1);

        return $borrow;
    }
}

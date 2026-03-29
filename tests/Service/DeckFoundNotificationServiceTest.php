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

use App\Entity\Deck;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Service\DeckFoundNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F4.16 — Lost & found deck alert
 */
class DeckFoundNotificationServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private DeckFoundNotificationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.com/deck/ABC123');
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->service = new DeckFoundNotificationService(
            $this->entityManager,
            $this->mailer,
            $urlGenerator,
            $translator,
            'noreply@test.com',
            'Test App',
        );
    }

    public function testNotifyCreatesInAppNotificationAndSendsEmail(): void
    {
        $owner = $this->createOwner();
        $deck = $this->createDeck($owner);
        $reporter = $this->createReporter();

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Notification $notification) use ($owner): bool {
                return $notification->getRecipient() === $owner
                    && NotificationType::DeckFound === $notification->getType()
                    && str_contains($notification->getMessage(), 'Found at table 5');
            }));
        $this->entityManager->expects(self::once())->method('flush');
        $this->mailer->expects(self::once())->method('send');

        $this->service->notify($deck, $reporter, 'Found at table 5');
    }

    public function testNotifyWithAnonymousReporter(): void
    {
        $owner = $this->createOwner();
        $deck = $this->createDeck($owner);

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Notification $notification): bool {
                $context = $notification->getContext();

                return null !== $context && !isset($context['reporterScreenName']);
            }));
        $this->entityManager->expects(self::once())->method('flush');
        $this->mailer->expects(self::once())->method('send');

        $this->service->notify($deck, null, 'Left at reception');
    }

    public function testNotifySkipsInAppWhenDisabled(): void
    {
        $owner = $this->createOwner();
        $owner->setNotificationPreference(NotificationType::DeckFound, 'inApp', false);
        $deck = $this->createDeck($owner);

        $this->entityManager->expects(self::never())->method('persist');
        $this->mailer->expects(self::once())->method('send');

        $this->service->notify($deck, null, 'Message');
    }

    public function testNotifySkipsEmailWhenDisabled(): void
    {
        $owner = $this->createOwner();
        $owner->setNotificationPreference(NotificationType::DeckFound, 'email', false);
        $deck = $this->createDeck($owner);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');
        $this->mailer->expects(self::never())->method('send');

        $this->service->notify($deck, null, 'Message');
    }

    public function testNotifyWithEmptyMessageOmitsFromContext(): void
    {
        $owner = $this->createOwner();
        $deck = $this->createDeck($owner);

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Notification $notification): bool {
                $context = $notification->getContext();

                return null !== $context && !isset($context['reporterMessage']);
            }));
        $this->entityManager->expects(self::once())->method('flush');
        $this->mailer->expects(self::once())->method('send');

        $this->service->notify($deck, null, null);
    }

    private function createOwner(): User
    {
        $owner = new User();
        $owner->setEmail('owner@test.com');
        $owner->setScreenName('DeckOwner');
        $owner->setFirstName('Owner');
        $owner->setLastName('Test');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($owner, 1);

        return $owner;
    }

    private function createReporter(): User
    {
        $reporter = new User();
        $reporter->setEmail('reporter@test.com');
        $reporter->setScreenName('Reporter');
        $reporter->setFirstName('Reporter');
        $reporter->setLastName('Test');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($reporter, 2);

        return $reporter;
    }

    private function createDeck(User $owner): Deck
    {
        $deck = new Deck();
        $deck->setName('Test Deck');
        $deck->setOwner($owner);
        $ref = new \ReflectionProperty(Deck::class, 'id');
        $ref->setValue($deck, 100);
        $shortTagRef = new \ReflectionProperty(Deck::class, 'shortTag');
        $shortTagRef->setValue($deck, 'ABC123');

        return $deck;
    }
}

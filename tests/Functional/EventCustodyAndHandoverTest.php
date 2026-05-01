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

use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\EventDeckRegistration;
use App\Entity\EventEngagement;
use App\Entity\User;
use App\Enum\EngagementState;
use App\Repository\DeckRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @see docs/features.md F4.8 — Staff-delegated lending (allow_custody gate)
 * @see docs/features.md F3.23 — Organizer handover
 */
class EventCustodyAndHandoverTest extends AbstractFunctionalTest
{
    public function testNewEventStartsWithCustodyDisabled(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/event/new');
        $this->client->submitForm('Create Event', [
            'event_form[name]' => 'Custody Default Event',
            'event_form[date]' => '2026-09-15T14:00',
            'event_form[timezone]' => 'UTC',
            'event_form[registrationLink]' => 'https://example.com/custody-default',
        ]);

        self::assertResponseRedirects();

        $event = $this->eventRepository()->findOneBy(['name' => 'Custody Default Event']);
        self::assertNotNull($event);
        self::assertFalse($event->isAllowCustody());
    }

    public function testEditCanEnableAllowCustody(): void
    {
        $event = $this->createOrganizedEvent($this->getUser('admin@example.com'), 'Toggle Custody Event');
        $eventId = $event->getId();
        self::assertNotNull($eventId);

        $this->loginAs('admin@example.com');
        $this->client->request('GET', \sprintf('/event/%d/edit', $eventId));
        $this->client->submitForm('Save', [
            'event_form[allowCustody]' => '1',
        ]);

        self::assertResponseRedirects();
        $this->em()->clear();

        /** @var Event $reloaded */
        $reloaded = $this->eventRepository()->find($eventId);
        self::assertTrue($reloaded->isAllowCustody());
    }

    public function testDelegationIsRejectedWhenAllowCustodyIsOff(): void
    {
        $organizer = $this->getUser('admin@example.com');
        $player = $this->getUser('borrower@example.com');

        $event = $this->createOrganizedEvent($organizer, 'No-Custody Event');
        // No allowCustody — default false.

        $deck = $this->getFirstActiveDeck($player);
        $this->registerPlayerWithDeck($event, $player, $deck);

        $this->loginAs('borrower@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $token = $this->extractTokenFromForm($crawler, '/toggle-delegation');

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => $token,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects();
        $this->em()->clear();

        $registration = $this->registrationRepository()->findOneByEventAndDeck(
            $this->eventRepository()->find($event->getId()),
            $this->deckRepository()->find($deck->getId()),
        );
        self::assertNotNull($registration);
        self::assertFalse($registration->isDelegateToStaff());
    }

    public function testDelegationIsAcceptedWhenAllowCustodyIsOn(): void
    {
        $organizer = $this->getUser('admin@example.com');
        $player = $this->getUser('borrower@example.com');

        $event = $this->createOrganizedEvent($organizer, 'With-Custody Event');
        $event->setAllowCustody(true);
        $deck = $this->getFirstActiveDeck($player);
        $this->registerPlayerWithDeck($event, $player, $deck);
        $this->em()->flush();

        $this->loginAs('borrower@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $token = $this->extractTokenFromForm($crawler, '/toggle-delegation');

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => $token,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects();
        $this->em()->clear();

        $registration = $this->registrationRepository()->findOneByEventAndDeck(
            $this->eventRepository()->find($event->getId()),
            $this->deckRepository()->find($deck->getId()),
        );
        self::assertNotNull($registration);
        self::assertTrue($registration->isDelegateToStaff());
    }

    public function testTransferInitiateSetsPendingTarget(): void
    {
        $organizer = $this->getUser('admin@example.com');
        $event = $this->createOrganizedEvent($organizer, 'Transfer Initiate Event');

        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $token = $this->extractTokenFromForm($crawler, '/transfer/initiate');

        $this->client->request('POST', \sprintf('/event/%d/transfer/initiate', $event->getId()), [
            '_token' => $token,
            'target' => 'organizer@example.com',
        ]);

        self::assertResponseRedirects();
        $this->em()->clear();

        /** @var Event $reloaded */
        $reloaded = $this->eventRepository()->find($event->getId());
        self::assertTrue($reloaded->hasPendingTransfer());
        self::assertSame('organizer@example.com', $reloaded->getPendingTransferTo()?->getEmail());
    }

    public function testTransferTargetCannotBeOrganizerThemselves(): void
    {
        $organizer = $this->getUser('admin@example.com');
        $event = $this->createOrganizedEvent($organizer, 'Self-Transfer Event');

        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $token = $this->extractTokenFromForm($crawler, '/transfer/initiate');

        $this->client->request('POST', \sprintf('/event/%d/transfer/initiate', $event->getId()), [
            '_token' => $token,
            'target' => 'admin@example.com',
        ]);

        self::assertResponseRedirects();
        $this->em()->clear();

        /** @var Event $reloaded */
        $reloaded = $this->eventRepository()->find($event->getId());
        self::assertFalse($reloaded->hasPendingTransfer());
    }

    public function testTransferAcceptSwapsOrganizer(): void
    {
        $organizer = $this->getUser('admin@example.com');
        $target = $this->getUser('organizer@example.com');

        $event = $this->createOrganizedEvent($organizer, 'Transfer Accept Event');
        $event->requestTransferTo($target);
        $this->em()->flush();

        $this->loginAs('organizer@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $token = $this->extractTokenFromForm($crawler, '/transfer/accept');

        $this->client->request('POST', \sprintf('/event/%d/transfer/accept', $event->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        $this->em()->clear();

        /** @var Event $reloaded */
        $reloaded = $this->eventRepository()->find($event->getId());
        self::assertSame('organizer@example.com', $reloaded->getOrganizer()->getEmail());
        self::assertFalse($reloaded->hasPendingTransfer());
    }

    public function testTransferDeclineKeepsOrganizer(): void
    {
        $organizer = $this->getUser('admin@example.com');
        $target = $this->getUser('organizer@example.com');

        $event = $this->createOrganizedEvent($organizer, 'Transfer Decline Event');
        $event->requestTransferTo($target);
        $this->em()->flush();

        $this->loginAs('organizer@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $token = $this->extractTokenFromForm($crawler, '/transfer/decline');

        $this->client->request('POST', \sprintf('/event/%d/transfer/decline', $event->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        $this->em()->clear();

        /** @var Event $reloaded */
        $reloaded = $this->eventRepository()->find($event->getId());
        self::assertSame('admin@example.com', $reloaded->getOrganizer()->getEmail());
        self::assertFalse($reloaded->hasPendingTransfer());
    }

    public function testTransferAcceptDeniedForNonTarget(): void
    {
        $organizer = $this->getUser('admin@example.com');
        $target = $this->getUser('organizer@example.com');

        $event = $this->createOrganizedEvent($organizer, 'Transfer Wrong-User Event');
        $event->requestTransferTo($target);
        $this->em()->flush();

        // borrower@example.com is neither organizer nor target — access check
        // runs before CSRF, so a bogus token still produces 403.
        $this->loginAs('borrower@example.com');
        $this->client->request('POST', \sprintf('/event/%d/transfer/accept', $event->getId()), [
            '_token' => 'irrelevant',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testTransferCancelClearsPendingTarget(): void
    {
        $organizer = $this->getUser('admin@example.com');
        $target = $this->getUser('organizer@example.com');

        $event = $this->createOrganizedEvent($organizer, 'Transfer Cancel Event');
        $event->requestTransferTo($target);
        $this->em()->flush();

        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $token = $this->extractTokenFromForm($crawler, '/transfer/cancel');

        $this->client->request('POST', \sprintf('/event/%d/transfer/cancel', $event->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        $this->em()->clear();

        /** @var Event $reloaded */
        $reloaded = $this->eventRepository()->find($event->getId());
        self::assertFalse($reloaded->hasPendingTransfer());
    }

    private function extractTokenFromForm(Crawler $crawler, string $actionSuffix): string
    {
        $form = $crawler->filter(\sprintf('form[action$="%s"]', $actionSuffix));
        self::assertGreaterThan(0, $form->count(), 'Expected to find form with action ending in '.$actionSuffix);
        $token = $form->first()->filter('input[name="_token"]')->attr('value');
        self::assertNotEmpty($token);

        return $token;
    }

    private function createOrganizedEvent(User $organizer, string $name): Event
    {
        $event = new Event();
        $event->setName($name);
        $event->setDate(new \DateTimeImmutable('+10 days'));
        $event->setTimezone('UTC');
        $event->setOrganizer($organizer);
        $event->setRegistrationLink('https://example.com/'.bin2hex(random_bytes(4)));
        $event->setFormat('Expanded');

        $this->em()->persist($event);
        $this->em()->flush();

        return $event;
    }

    private function registerPlayerWithDeck(Event $event, User $player, Deck $deck): void
    {
        $engagement = new EventEngagement();
        $engagement->setEvent($event);
        $engagement->setUser($player);
        $engagement->setState(EngagementState::RegisteredPlaying);
        $this->em()->persist($engagement);

        $registration = new EventDeckRegistration();
        $registration->setEvent($event);
        $registration->setDeck($deck);
        $this->em()->persist($registration);

        $this->em()->flush();
    }

    private function getFirstActiveDeck(User $owner): Deck
    {
        $deck = $this->deckRepository()->findOneBy(['owner' => $owner]);
        self::assertNotNull($deck, 'Fixture must include at least one deck for '.$owner->getEmail());

        return $deck;
    }

    private function getUser(string $email): User
    {
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);

        $user = $repo->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }

    private function eventRepository(): EventRepository
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);

        return $repo;
    }

    private function registrationRepository(): EventDeckRegistrationRepository
    {
        /** @var EventDeckRegistrationRepository $repo */
        $repo = static::getContainer()->get(EventDeckRegistrationRepository::class);

        return $repo;
    }

    private function deckRepository(): DeckRepository
    {
        /** @var DeckRepository $repo */
        $repo = static::getContainer()->get(DeckRepository::class);

        return $repo;
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }
}

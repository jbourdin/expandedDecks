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

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\EventStaffRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F3.5 — Assign event staff team
 */
class EventStaffTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // Assign staff
    // ---------------------------------------------------------------

    public function testAssignStaff(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->getAssignStaffCsrfToken($crawler, $event->getId());

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'Organizer',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', '"Organizer" has been added to the staff team.');

        /** @var EventStaffRepository $repo */
        $repo = static::getContainer()->get(EventStaffRepository::class);
        $staffEntries = $repo->findBy(['event' => $event->getId()]);

        // Fixture already has Borrower as staff, so we expect 2
        self::assertCount(2, $staffEntries);
    }

    public function testAssignStaffAppearsOnShowPage(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Borrower is already staff via fixtures
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('.list-group-item', 'Borrower');
    }

    public function testAssignStaffInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => 'invalid-token',
            'user_query' => 'Organizer',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');

        /** @var EventStaffRepository $repo */
        $repo = static::getContainer()->get(EventStaffRepository::class);
        $staffEntries = $repo->findBy(['event' => $event->getId()]);

        // Only the fixture staff (Borrower)
        self::assertCount(1, $staffEntries);
    }

    public function testAssignStaffUserNotFound(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->getAssignStaffCsrfToken($crawler, $event->getId());

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'NonExistentUser',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'User "NonExistentUser" not found.');
    }

    public function testAssignStaffAlreadyAssigned(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Borrower is already staff via fixtures
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->getAssignStaffCsrfToken($crawler, $event->getId());

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'Borrower',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', '"Borrower" is already a staff member.');
    }

    public function testAssignStaffIsOrganizer(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->getAssignStaffCsrfToken($crawler, $event->getId());

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'Admin',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'The organizer cannot be assigned as staff.');
    }

    public function testAssignStaffCancelledEvent(): void
    {
        $event = $this->getFixtureEvent();

        // Get a valid CSRF token before cancelling
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->getAssignStaffCsrfToken($crawler, $event->getId());

        // Cancel the event
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $em->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'Organizer',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot assign staff to a cancelled event.');
    }

    public function testAssignStaffNonOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('organizer@example.com');

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => 'any-token',
            'user_query' => 'Borrower',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAssignStaffByEmail(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->getAssignStaffCsrfToken($crawler, $event->getId());

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'organizer@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', '"Organizer" has been added to the staff team.');
    }

    public function testAssignStaffByPlayerId(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->getAssignStaffCsrfToken($crawler, $event->getId());

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'PKM-ORG-001',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', '"Organizer" has been added to the staff team.');
    }

    // ---------------------------------------------------------------
    // Remove staff
    // ---------------------------------------------------------------

    public function testRemoveStaff(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        /** @var EventStaffRepository $repo */
        $repo = static::getContainer()->get(EventStaffRepository::class);
        $staffMember = $repo->findOneBy(['event' => $event->getId()]);
        self::assertNotNull($staffMember);

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $removeForm = $crawler->filter(\sprintf('form[action$="/remove-staff/%d"]', $staffMember->getId()));
        $csrfToken = $removeForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/remove-staff/%d', $event->getId(), $staffMember->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', '"Borrower" has been removed from the staff team.');

        $remaining = $repo->findBy(['event' => $event->getId()]);
        self::assertCount(0, $remaining);
    }

    public function testRemoveStaffInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        /** @var EventStaffRepository $repo */
        $repo = static::getContainer()->get(EventStaffRepository::class);
        $staffMember = $repo->findOneBy(['event' => $event->getId()]);
        self::assertNotNull($staffMember);

        $this->client->request('POST', \sprintf('/event/%d/remove-staff/%d', $event->getId(), $staffMember->getId()), [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');

        // Staff should still exist
        $remaining = $repo->findBy(['event' => $event->getId()]);
        self::assertCount(1, $remaining);
    }

    public function testRemoveStaffCancelledEvent(): void
    {
        $event = $this->getFixtureEvent();

        /** @var EventStaffRepository $repo */
        $repo = static::getContainer()->get(EventStaffRepository::class);
        $staffMember = $repo->findOneBy(['event' => $event->getId()]);
        self::assertNotNull($staffMember);
        $staffId = $staffMember->getId();

        // Get a valid CSRF token before cancelling
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $removeForm = $crawler->filter(\sprintf('form[action$="/remove-staff/%d"]', $staffId));
        $csrfToken = $removeForm->filter('input[name="_token"]')->attr('value');

        // Cancel the event
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $em->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $this->client->request('POST', \sprintf('/event/%d/remove-staff/%d', $event->getId(), $staffId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot remove staff from a cancelled event.');
    }

    public function testRemoveStaffNonOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        /** @var EventStaffRepository $repo */
        $repo = static::getContainer()->get(EventStaffRepository::class);
        $staffMember = $repo->findOneBy(['event' => $event->getId()]);
        self::assertNotNull($staffMember);

        $this->loginAs('organizer@example.com');

        $this->client->request('POST', \sprintf('/event/%d/remove-staff/%d', $event->getId(), $staffMember->getId()), [
            '_token' => 'any-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRemoveStaffWrongEvent(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EventRepository $eventRepo */
        $eventRepo = static::getContainer()->get(EventRepository::class);
        $events = $eventRepo->findAll();
        self::assertGreaterThanOrEqual(2, \count($events));

        // Get the second event (Lyon Expanded Cup 2026)
        $secondEvent = $events[1];

        // Get staff from the first event
        /** @var EventStaffRepository $repo */
        $repo = static::getContainer()->get(EventStaffRepository::class);
        $staffMember = $repo->findOneBy(['event' => $events[0]->getId()]);
        self::assertNotNull($staffMember);
        $staffId = $staffMember->getId();

        // Get a valid CSRF token from the first event's show page
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $events[0]->getId()));
        $removeForm = $crawler->filter(\sprintf('form[action$="/remove-staff/%d"]', $staffId));
        $csrfToken = $removeForm->filter('input[name="_token"]')->attr('value');

        // Try to use the staff ID on the wrong event
        $this->client->request('POST', \sprintf('/event/%d/remove-staff/%d', $secondEvent->getId(), $staffId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    // ---------------------------------------------------------------
    // UI visibility
    // ---------------------------------------------------------------

    public function testCancelledEventHidesStaffSection(): void
    {
        $event = $this->getFixtureEvent();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $event->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('h6:contains("Staff Team")');
    }

    public function testStaffSectionOnlyVisibleToOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('borrower@example.com');

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('h6:contains("Staff Team")');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function getFixtureEvent(): Event
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy([]);
        self::assertNotNull($event);

        return $event;
    }

    private function getAssignStaffCsrfToken(\Symfony\Component\DomCrawler\Crawler $crawler, ?int $eventId): string
    {
        $form = $crawler->filter(\sprintf('form[action$="/event/%d/assign-staff"]', $eventId));
        $token = $form->filter('input[name="_token"]')->attr('value');
        self::assertNotEmpty($token);

        return $token;
    }
}

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
use App\Enum\EventVisibility;
use App\Repository\EventDeckEntryRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Additional coverage tests for TournamentResultsController.
 *
 * @see docs/features.md F3.17 — Tournament Results
 */
class TournamentResultsControllerCoverageTest extends AbstractFunctionalTest
{
    private function getFinishedEvent(): Event
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $event = $eventRepository->findOneBy(['name' => 'Past Expanded Weekly #40']);
        self::assertNotNull($event);

        return $event;
    }

    // ---------------------------------------------------------------
    // results() — Cancelled event returns 404
    // ---------------------------------------------------------------

    public function testResultsOnCancelledEventReturns404(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        // Create a cancelled event for this test
        $event = new Event();
        $event->setName('Cancelled Tournament');
        $event->setDate(new \DateTimeImmutable('-1 week'));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('Lyon');
        /** @var \App\Entity\User $admin */
        $admin = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@example.com']);
        $event->setOrganizer($admin);
        $event->setFormat('Expanded');
        $event->setFinishedAt(new \DateTimeImmutable('-1 week +6 hours'));
        $event->setCancelledAt(new \DateTimeImmutable('-3 days'));
        $entityManager->persist($event);
        $entityManager->flush();

        $this->client->request('GET', '/event/'.$event->getId().'/results');

        self::assertResponseStatusCodeSame(404);
    }

    // ---------------------------------------------------------------
    // results() — Private event access control
    // ---------------------------------------------------------------

    public function testResultsOnPrivateEventDeniedForAnonymousRedirectsToLogin(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        // Create a private finished event
        $event = new Event();
        $event->setName('Private Tournament');
        $event->setDate(new \DateTimeImmutable('-1 week'));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('Paris');
        /** @var \App\Entity\User $admin */
        $admin = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@example.com']);
        $event->setOrganizer($admin);
        $event->setFormat('Expanded');
        $event->setVisibility(EventVisibility::Private);
        $event->setFinishedAt(new \DateTimeImmutable('-1 week +6 hours'));
        $entityManager->persist($event);
        $entityManager->flush();

        $this->client->request('GET', '/event/'.$event->getId().'/results');

        // Anonymous user on a private event triggers the access denied handler which redirects to login
        self::assertResponseRedirects('/login');
    }

    public function testResultsOnPrivateEventDeniedForNonStaffUser(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        // Create a private finished event organized by admin
        $event = new Event();
        $event->setName('Private Tournament For Access Test');
        $event->setDate(new \DateTimeImmutable('-1 week'));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('Paris');
        /** @var \App\Entity\User $admin */
        $admin = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@example.com']);
        $event->setOrganizer($admin);
        $event->setFormat('Expanded');
        $event->setVisibility(EventVisibility::Private);
        $event->setFinishedAt(new \DateTimeImmutable('-1 week +6 hours'));
        $entityManager->persist($event);
        $entityManager->flush();

        // Borrower is not staff/organizer of this event
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/event/'.$event->getId().'/results');

        self::assertResponseStatusCodeSame(403);
    }

    public function testResultsOnPrivateEventAllowedForOrganizer(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        $event = new Event();
        $event->setName('Private Tournament Organizer Access');
        $event->setDate(new \DateTimeImmutable('-1 week'));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('Paris');
        /** @var \App\Entity\User $admin */
        $admin = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@example.com']);
        $event->setOrganizer($admin);
        $event->setFormat('Expanded');
        $event->setVisibility(EventVisibility::Private);
        $event->setFinishedAt(new \DateTimeImmutable('-1 week +6 hours'));
        $entityManager->persist($event);
        $entityManager->flush();

        // Admin is the organizer
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event/'.$event->getId().'/results');

        self::assertResponseIsSuccessful();
    }

    // ---------------------------------------------------------------
    // edit() — Invalid CSRF token
    // ---------------------------------------------------------------

    public function testEditWithInvalidCsrfTokenRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        $this->client->request('POST', '/event/'.$event->getId().'/results/edit', [
            '_token' => 'invalid-csrf-token',
            'results' => [],
        ]);

        self::assertResponseRedirects('/event/'.$event->getId().'/results/edit');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // edit() — Invalid placement values
    // ---------------------------------------------------------------

    public function testEditWithNonPositivePlacementShowsError(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        /** @var EventDeckEntryRepository $entryRepository */
        $entryRepository = static::getContainer()->get(EventDeckEntryRepository::class);
        $entries = $entryRepository->findByEventOrderedByPlacement($event);
        self::assertNotEmpty($entries);

        $firstEntryId = (string) $entries[0]->getId();

        // Get the CSRF token from the edit page
        $crawler = $this->client->request('GET', '/event/'.$event->getId().'/results/edit');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/event/'.$event->getId().'/results/edit', [
            '_token' => $csrfToken,
            'results' => [
                $firstEntryId => [
                    'placement' => '0',
                    'match_record' => '',
                ],
            ],
        ]);

        // Should re-render the form with errors (not redirect)
        self::assertResponseIsSuccessful();
    }

    public function testEditWithNegativePlacementShowsError(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        /** @var EventDeckEntryRepository $entryRepository */
        $entryRepository = static::getContainer()->get(EventDeckEntryRepository::class);
        $entries = $entryRepository->findByEventOrderedByPlacement($event);
        self::assertNotEmpty($entries);

        $firstEntryId = (string) $entries[0]->getId();

        $crawler = $this->client->request('GET', '/event/'.$event->getId().'/results/edit');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/event/'.$event->getId().'/results/edit', [
            '_token' => $csrfToken,
            'results' => [
                $firstEntryId => [
                    'placement' => '-3',
                    'match_record' => '',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testEditWithNonIntegerPlacementShowsError(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        /** @var EventDeckEntryRepository $entryRepository */
        $entryRepository = static::getContainer()->get(EventDeckEntryRepository::class);
        $entries = $entryRepository->findByEventOrderedByPlacement($event);
        self::assertNotEmpty($entries);

        $firstEntryId = (string) $entries[0]->getId();

        $crawler = $this->client->request('GET', '/event/'.$event->getId().'/results/edit');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/event/'.$event->getId().'/results/edit', [
            '_token' => $csrfToken,
            'results' => [
                $firstEntryId => [
                    'placement' => 'abc',
                    'match_record' => '',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
    }

    // ---------------------------------------------------------------
    // edit() — Invalid match record format
    // ---------------------------------------------------------------

    public function testEditWithInvalidMatchRecordFormatShowsError(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        /** @var EventDeckEntryRepository $entryRepository */
        $entryRepository = static::getContainer()->get(EventDeckEntryRepository::class);
        $entries = $entryRepository->findByEventOrderedByPlacement($event);
        self::assertNotEmpty($entries);

        $firstEntryId = (string) $entries[0]->getId();

        $crawler = $this->client->request('GET', '/event/'.$event->getId().'/results/edit');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/event/'.$event->getId().'/results/edit', [
            '_token' => $csrfToken,
            'results' => [
                $firstEntryId => [
                    'placement' => '1',
                    'match_record' => 'invalid-format',
                ],
            ],
        ]);

        // Should re-render the form with errors
        self::assertResponseIsSuccessful();
    }

    public function testEditWithPartialMatchRecordShowsError(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        /** @var EventDeckEntryRepository $entryRepository */
        $entryRepository = static::getContainer()->get(EventDeckEntryRepository::class);
        $entries = $entryRepository->findByEventOrderedByPlacement($event);
        self::assertNotEmpty($entries);

        $firstEntryId = (string) $entries[0]->getId();

        $crawler = $this->client->request('GET', '/event/'.$event->getId().'/results/edit');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/event/'.$event->getId().'/results/edit', [
            '_token' => $csrfToken,
            'results' => [
                $firstEntryId => [
                    'placement' => '1',
                    'match_record' => '3-1',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
    }

    // ---------------------------------------------------------------
    // edit() — Clearing results (empty values)
    // ---------------------------------------------------------------

    public function testEditWithEmptyValuesSucceeds(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        /** @var EventDeckEntryRepository $entryRepository */
        $entryRepository = static::getContainer()->get(EventDeckEntryRepository::class);
        $entries = $entryRepository->findByEventOrderedByPlacement($event);
        self::assertNotEmpty($entries);

        $firstEntryId = (string) $entries[0]->getId();

        $crawler = $this->client->request('GET', '/event/'.$event->getId().'/results/edit');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/event/'.$event->getId().'/results/edit', [
            '_token' => $csrfToken,
            'results' => [
                $firstEntryId => [
                    'placement' => '',
                    'match_record' => '',
                ],
            ],
        ]);

        // Empty values clear the fields — should succeed and redirect
        self::assertResponseRedirects('/event/'.$event->getId().'/results');
    }

    // ---------------------------------------------------------------
    // edit() — Unfinished / cancelled event redirects
    // ---------------------------------------------------------------

    public function testEditOnUnfinishedEventRedirects(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        // Today's event is not finished
        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        $this->client->request('GET', '/event/'.$event->getId().'/results/edit');

        self::assertResponseRedirects('/event/'.$event->getId());
    }

    public function testEditOnCancelledEventRedirects(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        $event = new Event();
        $event->setName('Cancelled Tournament For Edit Test');
        $event->setDate(new \DateTimeImmutable('-1 week'));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('Lyon');
        /** @var \App\Entity\User $admin */
        $admin = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@example.com']);
        $event->setOrganizer($admin);
        $event->setFormat('Expanded');
        $event->setFinishedAt(new \DateTimeImmutable('-1 week +6 hours'));
        $event->setCancelledAt(new \DateTimeImmutable('-3 days'));
        $entityManager->persist($event);
        $entityManager->flush();

        $this->client->request('GET', '/event/'.$event->getId().'/results/edit');

        self::assertResponseRedirects('/event/'.$event->getId());
    }

    // ---------------------------------------------------------------
    // edit() — Staff user access
    // ---------------------------------------------------------------

    public function testStaffCanEditResults(): void
    {
        // staff1 is staff at "Past Expanded Weekly #40"
        $this->loginAs('staff1@example.com');

        $event = $this->getFinishedEvent();

        $this->client->request('GET', '/event/'.$event->getId().'/results/edit');

        self::assertResponseIsSuccessful();
    }

    // ---------------------------------------------------------------
    // edit() — POST with unknown entry ID is silently ignored
    // ---------------------------------------------------------------

    public function testEditWithUnknownEntryIdIsIgnored(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        $crawler = $this->client->request('GET', '/event/'.$event->getId().'/results/edit');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/event/'.$event->getId().'/results/edit', [
            '_token' => $csrfToken,
            'results' => [
                '99999' => [
                    'placement' => '1',
                    'match_record' => '3-0-0',
                ],
            ],
        ]);

        // Unknown entry IDs are skipped → no errors → redirect
        self::assertResponseRedirects('/event/'.$event->getId().'/results');
    }

    // ---------------------------------------------------------------
    // edit() — Valid submission with placement and match record
    // ---------------------------------------------------------------

    public function testEditWithValidDataUpdatesResults(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        /** @var EventDeckEntryRepository $entryRepository */
        $entryRepository = static::getContainer()->get(EventDeckEntryRepository::class);
        $entries = $entryRepository->findByEventOrderedByPlacement($event);
        self::assertNotEmpty($entries);

        $firstEntryId = (string) $entries[0]->getId();
        $secondEntryId = (string) $entries[1]->getId();

        $crawler = $this->client->request('GET', '/event/'.$event->getId().'/results/edit');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/event/'.$event->getId().'/results/edit', [
            '_token' => $csrfToken,
            'results' => [
                $firstEntryId => [
                    'placement' => '1',
                    'match_record' => '4-0-0',
                ],
                $secondEntryId => [
                    'placement' => '2',
                    'match_record' => '3-1-0',
                ],
            ],
        ]);

        self::assertResponseRedirects('/event/'.$event->getId().'/results');
    }
}

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

use App\Repository\EventRepository;

/**
 * @see docs/features.md F3.17 — Tournament Results
 */
class TournamentResultsControllerTest extends AbstractFunctionalTest
{
    public function testAnonymousCanSeePublicEventResults(): void
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = static::getContainer()->get(EventRepository::class);
        $event = $eventRepo->findOneBy(['name' => 'Past Expanded Weekly #40']);
        self::assertNotNull($event);

        $this->client->request('GET', '/event/'.$event->getId().'/results');
        self::assertResponseIsSuccessful();

        // Anonymous users see privacy-friendly names (FirstName L.)
        self::assertSelectorTextContains('table', 'Jean-Michel A.');
        self::assertSelectorTextContains('table', 'Alice D.');
    }

    public function testAuthenticatedUserSeesFullNames(): void
    {
        $this->loginAs('borrower@example.com');

        /** @var EventRepository $eventRepo */
        $eventRepo = static::getContainer()->get(EventRepository::class);
        $event = $eventRepo->findOneBy(['name' => 'Past Expanded Weekly #40']);
        self::assertNotNull($event);

        $this->client->request('GET', '/event/'.$event->getId().'/results');
        self::assertResponseIsSuccessful();

        // Authenticated users see screenName
        self::assertSelectorTextContains('table', 'Admin');
        self::assertSelectorTextContains('table', 'Borrower');
    }

    public function testResultsNotAvailableForUnfinishedEvent(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EventRepository $eventRepo */
        $eventRepo = static::getContainer()->get(EventRepository::class);
        $event = $eventRepo->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        $this->client->request('GET', '/event/'.$event->getId().'/results');
        self::assertResponseRedirects('/event/'.$event->getId());
    }

    public function testOrganizerCanEditResults(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EventRepository $eventRepo */
        $eventRepo = static::getContainer()->get(EventRepository::class);
        $event = $eventRepo->findOneBy(['name' => 'Past Expanded Weekly #40']);
        self::assertNotNull($event);

        // GET edit page — renders the form with CSRF token
        $crawler = $this->client->request('GET', '/event/'.$event->getId().'/results/edit');
        self::assertResponseIsSuccessful();

        // Submit the form via the Save Results button
        $form = $crawler->selectButton('Save Results')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/event/'.$event->getId().'/results');
    }

    public function testNonStaffCannotEditResults(): void
    {
        $this->loginAs('borrower@example.com');

        /** @var EventRepository $eventRepo */
        $eventRepo = static::getContainer()->get(EventRepository::class);
        $event = $eventRepo->findOneBy(['name' => 'Past Expanded Weekly #40']);
        self::assertNotNull($event);

        $this->client->request('GET', '/event/'.$event->getId().'/results/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEventShowLinksToResultsWhenFinished(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EventRepository $eventRepo */
        $eventRepo = static::getContainer()->get(EventRepository::class);
        $event = $eventRepo->findOneBy(['name' => 'Past Expanded Weekly #40']);
        self::assertNotNull($event);

        $crawler = $this->client->request('GET', '/event/'.$event->getId());
        self::assertResponseIsSuccessful();

        // Should contain a link to results
        $resultsLink = $crawler->filter('a[href="/event/'.$event->getId().'/results"]');
        self::assertGreaterThan(0, $resultsLink->count(), 'Expected a "View Results" link on the event show page.');
    }
}

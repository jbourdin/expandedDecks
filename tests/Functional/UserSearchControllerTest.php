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

/**
 * @see docs/features.md F4.13 — Event-scoped autocompletes
 */
class UserSearchControllerTest extends AbstractFunctionalTest
{
    /**
     * Staff searching with event_id returns only event participants.
     *
     * @see docs/features.md F4.13 — Event-scoped autocompletes
     */
    public function testUserSearchWithEventIdReturnsParticipantsOnly(): void
    {
        // Borrower is staff at the today event
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // "Admin" is a participant at this event, "Lender" is not
        $this->client->request('GET', \sprintf('/api/user/search?q=Admin&event_id=%d', $event->getId()));
        self::assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $screenNames = array_column($data, 'screenName');
        self::assertContains('Admin', $screenNames);

        // Lender is NOT a participant at the today event
        $this->client->request('GET', \sprintf('/api/user/search?q=Lender&event_id=%d', $event->getId()));
        self::assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertEmpty($data, 'Lender should not appear in event-scoped search results.');
    }

    /**
     * Organizer searching without event_id returns global results.
     *
     * @see docs/features.md F4.13 — Event-scoped autocompletes
     */
    public function testUserSearchWithoutEventIdReturnsGlobally(): void
    {
        // Admin has ROLE_ADMIN (which includes ROLE_ORGANIZER)
        $this->loginAs('admin@example.com');

        // Search for Lender globally (no event_id)
        $this->client->request('GET', '/api/user/search?q=Lender');
        self::assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $screenNames = array_column($data, 'screenName');
        self::assertContains('Lender', $screenNames, 'Lender should appear in global search results.');
    }

    private function getFixtureEvent(): Event
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy([]);
        self::assertNotNull($event);

        return $event;
    }
}

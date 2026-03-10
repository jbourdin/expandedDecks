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
 * Additional coverage tests for DeckSearchController uncovered branches.
 *
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 */
class DeckSearchControllerCoverageTest extends AbstractFunctionalTest
{
    /**
     * Missing event_id parameter should return an empty JSON array.
     */
    public function testSearchWithoutEventIdReturnsEmpty(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/api/deck/search?q=Iron');

        self::assertResponseIsSuccessful();

        /** @var string $content */
        $content = $this->client->getResponse()->getContent();

        /** @var list<mixed> $data */
        $data = json_decode($content, true);
        self::assertSame([], $data);
    }

    /**
     * A query shorter than 2 characters should return an empty JSON array.
     */
    public function testSearchWithShortQueryReturnsEmpty(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/api/deck/search?event_id=%d&q=I', $event->getId()));

        self::assertResponseIsSuccessful();

        /** @var string $content */
        $content = $this->client->getResponse()->getContent();

        /** @var list<mixed> $data */
        $data = json_decode($content, true);
        self::assertSame([], $data);
    }

    /**
     * A nonexistent event ID should return an empty JSON array.
     */
    public function testSearchWithNonexistentEventIdReturnsEmpty(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/api/deck/search?event_id=999999&q=Iron');

        self::assertResponseIsSuccessful();

        /** @var string $content */
        $content = $this->client->getResponse()->getContent();

        /** @var list<mixed> $data */
        $data = json_decode($content, true);
        self::assertSame([], $data);
    }

    private function getFixtureEvent(): Event
    {
        /** @var EventRepository $repository */
        $repository = static::getContainer()->get(EventRepository::class);
        $event = $repository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        return $event;
    }
}

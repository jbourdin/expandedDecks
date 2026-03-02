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
use App\Enum\BorrowStatus;
use App\Enum\DeckStatus;
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 */
class DeckSearchControllerTest extends AbstractFunctionalTest
{
    public function testDeckSearchReturnsResults(): void
    {
        // borrower@example.com is staff for the event
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Cancel the existing pending borrow for Iron Thorns so the deck is available
        $this->cancelExistingBorrowsForDeck('Iron Thorns', $event);

        $this->client->request('GET', \sprintf('/api/deck/search?event_id=%d&q=Iron', $event->getId()));
        self::assertResponseIsSuccessful();

        /** @var string $content */
        $content = $this->client->getResponse()->getContent();

        /** @var list<array{id: int, name: string}> $data */
        $data = json_decode($content, true);
        self::assertIsArray($data);

        $names = array_column($data, 'name');
        self::assertContains('Iron Thorns', $names);
    }

    public function testDeckSearchExcludesRetiredDecks(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $this->cancelExistingBorrowsForDeck('Iron Thorns', $event);

        // Retire Iron Thorns
        $em = $this->getEntityManager();
        /** @var DeckRepository $deckRepo */
        $deckRepo = static::getContainer()->get(DeckRepository::class);
        $deck = $deckRepo->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($deck);
        $deck->setStatus(DeckStatus::Retired);
        $em->flush();

        $this->client->request('GET', \sprintf('/api/deck/search?event_id=%d&q=Iron', $event->getId()));
        self::assertResponseIsSuccessful();

        /** @var string $content */
        $content = $this->client->getResponse()->getContent();

        /** @var list<array{id: int, name: string}> $data */
        $data = json_decode($content, true);
        $names = array_column($data, 'name');
        self::assertNotContains('Iron Thorns', $names);
    }

    public function testDeckSearchRequiresEventEngagement(): void
    {
        // Lender is not engaged in the event and not staff
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/api/deck/search?event_id=%d&q=Iron', $event->getId()));
        self::assertResponseIsSuccessful();

        /** @var string $content */
        $content = $this->client->getResponse()->getContent();

        /** @var list<mixed> $data */
        $data = json_decode($content, true);
        self::assertEmpty($data);
    }

    private function getFixtureEvent(): Event
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        return $event;
    }

    private function cancelExistingBorrowsForDeck(string $deckName, Event $event): void
    {
        $em = $this->getEntityManager();

        /** @var DeckRepository $deckRepo */
        $deckRepo = static::getContainer()->get(DeckRepository::class);
        $deck = $deckRepo->findOneBy(['name' => $deckName]);
        self::assertNotNull($deck);

        /** @var BorrowRepository $borrowRepo */
        $borrowRepo = static::getContainer()->get(BorrowRepository::class);
        $existing = $borrowRepo->findActiveBorrowForDeckAtEvent($deck, $event);

        if (null !== $existing) {
            $existing->setStatus(BorrowStatus::Cancelled);
            $existing->setCancelledAt(new \DateTimeImmutable());
            $em->flush();
        }
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }
}

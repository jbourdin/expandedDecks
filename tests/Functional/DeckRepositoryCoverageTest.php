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

use App\Entity\User;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Coverage for DeckRepository: searchAvailableForEvent, findAvailableDecks.
 *
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 * @see docs/features.md F2.1 — Register a new deck (owner)
 */
class DeckRepositoryCoverageTest extends AbstractFunctionalTest
{
    private function getDeckRepository(): DeckRepository
    {
        /** @var DeckRepository $repository */
        $repository = static::getContainer()->get(DeckRepository::class);

        return $repository;
    }

    private function getEventRepository(): EventRepository
    {
        /** @var EventRepository $repository */
        $repository = static::getContainer()->get(EventRepository::class);

        return $repository;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function getUserByEmail(string $email): User
    {
        $entityManager = $this->getEntityManager();
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }

    // ---------------------------------------------------------------
    // searchAvailableForEvent
    // ---------------------------------------------------------------

    public function testSearchAvailableForEventFindsMatchingDecks(): void
    {
        $deckRepository = $this->getDeckRepository();
        $eventRepository = $this->getEventRepository();

        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        // Search for "Regidrago" — exists in fixtures, owned by lender
        $decks = $deckRepository->searchAvailableForEvent('Regidrago', $event);

        $deckNames = array_map(static fn ($deck): string => $deck->getName(), $decks);
        self::assertContains('Regidrago', $deckNames, 'Should find Regidrago deck by name search.');
    }

    public function testSearchAvailableForEventReturnsEmptyForNoMatch(): void
    {
        $deckRepository = $this->getDeckRepository();
        $eventRepository = $this->getEventRepository();

        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        $decks = $deckRepository->searchAvailableForEvent('NonExistentDeckXYZ', $event);

        self::assertEmpty($decks, 'Should return empty for a query matching no decks.');
    }

    public function testSearchAvailableForEventRespectsLimit(): void
    {
        $deckRepository = $this->getDeckRepository();
        $eventRepository = $this->getEventRepository();

        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        $decks = $deckRepository->searchAvailableForEvent('', $event, 1);

        self::assertLessThanOrEqual(1, \count($decks), 'Should respect the limit parameter.');
    }

    public function testSearchAvailableForEventExcludesRetiredDecks(): void
    {
        $deckRepository = $this->getDeckRepository();
        $eventRepository = $this->getEventRepository();
        $entityManager = $this->getEntityManager();

        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        // Retire a deck and verify it no longer appears
        $regidrago = $deckRepository->findOneBy(['name' => 'Regidrago']);
        self::assertNotNull($regidrago);
        $regidrago->setStatus(\App\Enum\DeckStatus::Retired);
        $entityManager->flush();

        $decks = $deckRepository->searchAvailableForEvent('Regidrago', $event);

        $deckNames = array_map(static fn ($deck): string => $deck->getName(), $decks);
        self::assertNotContains('Regidrago', $deckNames, 'Retired decks should be excluded from search.');
    }

    public function testSearchAvailableForEventExcludesDecksWithoutCurrentVersion(): void
    {
        $deckRepository = $this->getDeckRepository();
        $eventRepository = $this->getEventRepository();
        $entityManager = $this->getEntityManager();

        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        // Create a deck without a version
        $admin = $this->getUserByEmail('admin@example.com');
        $noVersionDeck = new \App\Entity\Deck();
        $noVersionDeck->setName('NoVersionTestDeck');
        $noVersionDeck->setOwner($admin);
        $noVersionDeck->setFormat('Expanded');
        $entityManager->persist($noVersionDeck);
        $entityManager->flush();

        $decks = $deckRepository->searchAvailableForEvent('NoVersionTestDeck', $event);

        self::assertEmpty($decks, 'Decks without a current version should be excluded.');
    }

    // ---------------------------------------------------------------
    // findAvailableDecks
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     */
    public function testFindAvailableDecksReturnsOnlyAvailableDecks(): void
    {
        $deckRepository = $this->getDeckRepository();

        $decks = $deckRepository->findAvailableDecks();

        self::assertNotEmpty($decks, 'Should return at least some available decks from fixtures.');
        foreach ($decks as $deck) {
            self::assertSame(
                \App\Enum\DeckStatus::Available,
                $deck->getStatus(),
                'All returned decks should have Available status.',
            );
        }
    }

    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     */
    public function testFindAvailableDecksExcludesRetiredDecks(): void
    {
        $deckRepository = $this->getDeckRepository();
        $entityManager = $this->getEntityManager();

        // Retire a deck and verify it disappears
        $ironThorns = $deckRepository->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($ironThorns);
        $ironThorns->setStatus(\App\Enum\DeckStatus::Retired);
        $entityManager->flush();

        $decks = $deckRepository->findAvailableDecks();

        $deckNames = array_map(static fn ($deck): string => $deck->getName(), $decks);
        self::assertNotContains('Iron Thorns', $deckNames, 'Retired decks should be excluded.');
    }

    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     */
    public function testFindAvailableDecksOrdersByNameAscending(): void
    {
        $deckRepository = $this->getDeckRepository();

        $decks = $deckRepository->findAvailableDecks();

        if (\count($decks) >= 2) {
            for ($index = 1; $index < \count($decks); ++$index) {
                self::assertGreaterThanOrEqual(
                    0,
                    strcmp($decks[$index]->getName(), $decks[$index - 1]->getName()),
                    'Decks should be ordered by name ascending.',
                );
            }
        }
    }

    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     */
    public function testFindAvailableDecksEagerLoadsOwnerAndCurrentVersion(): void
    {
        $deckRepository = $this->getDeckRepository();

        $decks = $deckRepository->findAvailableDecks();

        self::assertNotEmpty($decks);
        $deck = $decks[0];

        // Verify eager-loaded associations are accessible
        self::assertNotNull($deck->getOwner()->getScreenName());
        // currentVersion can be null for some decks, but the join is a leftJoin
        // so this just verifies the query does not error
    }
}

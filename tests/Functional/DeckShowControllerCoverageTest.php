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
use Doctrine\ORM\EntityManagerInterface;

/**
 * Additional coverage tests for DeckShowController uncovered branches.
 *
 * Covers access control for private decks:
 * - Non-owner/non-admin/non-staff sees 403
 * - Staff of an event where the deck is registered can see a private deck
 *
 * @see docs/features.md F2.3 — Detail view
 */
class DeckShowControllerCoverageTest extends AbstractFunctionalTest
{
    /**
     * A logged-in user who is not the owner, admin, or event staff should
     * get 403 for a private deck.
     */
    public function testPrivateDeckDeniedForNonOwnerNonAdmin(): void
    {
        // Ancient Box is owned by admin, not public
        // lender@example.com is not admin, not owner, not staff for the event where Ancient Box is registered
        $this->loginAs('lender@example.com');

        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * An admin can see any private deck.
     */
    public function testPrivateDeckAccessibleByAdmin(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
    }

    /**
     * A staff member of an event where the private deck is registered can
     * see the private deck.
     *
     * Ancient Box is registered at "Expanded Weekly #42" event and borrower
     * is staff for that event.
     */
    public function testPrivateDeckAccessibleByEventStaff(): void
    {
        // borrower@example.com is staff on "Expanded Weekly #42" event
        // Ancient Box is registered on that same event
        $this->loginAs('borrower@example.com');

        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
    }

    /**
     * A deck with no current version should still display correctly for
     * the owner (no grouped cards section).
     */
    public function testDeckShowWithNoVersionShowsEmptyCardList(): void
    {
        $this->loginAs('admin@example.com');

        // Create a deck without a version
        $entityManager = $this->getEntityManager();
        $deck = new Deck();
        $deck->setName('No Version Deck');
        $deck->setOwner($entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@example.com']));
        $deck->setFormat('Expanded');
        $deck->setPublic(true);
        $entityManager->persist($deck);
        $entityManager->flush();

        $this->client->request('GET', '/deck/'.$deck->getShortTag());

        self::assertResponseIsSuccessful();
    }

    private function getDeckShortTag(string $name): string
    {
        $entityManager = $this->getEntityManager();
        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => $name]);

        return $deck->getShortTag();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }
}

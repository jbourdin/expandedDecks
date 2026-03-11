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
 * @see docs/features.md F5.12 — Deck show activity pagination
 */
class DeckBorrowHistoryControllerTest extends AbstractFunctionalTest
{
    public function testBorrowHistoryRedirectsForAnonymous(): void
    {
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', '/deck/'.$shortTag.'/borrows');

        self::assertResponseRedirects('/login');
    }

    public function testBorrowHistoryAccessibleByOwner(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', '/deck/'.$shortTag.'/borrows');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h5', 'Iron Thorns');
    }

    public function testBorrowHistoryAccessibleByBorrower(): void
    {
        $this->loginAs('borrower@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', '/deck/'.$shortTag.'/borrows');

        self::assertResponseIsSuccessful();
    }

    public function testBorrowHistoryDeniedForUnrelatedUserOnPrivateDeck(): void
    {
        $this->loginAs('lender@example.com');

        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag.'/borrows');

        self::assertResponseStatusCodeSame(403);
    }

    public function testBorrowHistoryShowsEmptyState(): void
    {
        $this->loginAs('borrower@example.com');

        $shortTag = $this->getDeckShortTag('Lugia Archeops');
        $this->client->request('GET', '/deck/'.$shortTag.'/borrows');

        self::assertResponseIsSuccessful();
    }

    public function testBorrowHistoryPaginationParam(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', '/deck/'.$shortTag.'/borrows?page=1');

        self::assertResponseIsSuccessful();
    }

    private function getDeckShortTag(string $name): string
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => $name]);

        return $deck->getShortTag();
    }
}

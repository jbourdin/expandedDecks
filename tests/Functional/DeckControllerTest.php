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
 * @see docs/features.md F2.3 — Detail view
 * @see docs/features.md F2.6 — Archetype management
 * @see docs/features.md F4.5 — Borrow history
 */
class DeckControllerTest extends AbstractFunctionalTest
{
    private function getDeckShortTag(string $name): string
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Deck $deck */
        $deck = $em->getRepository(Deck::class)->findOneBy(['name' => $name]);

        return $deck->getShortTag();
    }

    public function testDeckShowDisplaysBorrowActivityForOwner(): void
    {
        $this->loginAs('admin@example.com');

        // Iron Thorns is owned by admin and has borrows in fixtures
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $crawler = $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
        // Owner sees the borrow activity card with borrows table
        $borrowActivityHeaders = $crawler->filter('.card-header:contains("Borrow Activity")');
        self::assertGreaterThan(0, $borrowActivityHeaders->count(), 'Borrow Activity card should be present.');
    }

    public function testDeckShowDisplaysBorrowActivityForBorrower(): void
    {
        $this->loginAs('borrower@example.com');

        // Iron Thorns — borrower has a pending borrow for this deck
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $crawler = $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
        $borrowActivityHeaders = $crawler->filter('.card-header:contains("Borrow Activity")');
        self::assertGreaterThan(0, $borrowActivityHeaders->count(), 'Borrow Activity card should be present.');
    }

    public function testDeckShowDoesNotShowBorrowFormForOwner(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
        // Owner should NOT see a borrow request form (can't borrow own deck)
        self::assertSelectorNotExists('#borrow_event');
    }

    public function testDeckShowDisplaysArchetypeFromDeck(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $crawler = $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Iron Thorns ex', $crawler->text());
    }

    public function testDeckShowDisplaysLanguagesFromDeck(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $crawler = $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('en', $crawler->text());
    }

    public function testNewDeckFormDoesNotContainFormatField(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/deck/new');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('#deck_form_format')->count(), 'Format field should not be present.');
    }

    public function testEditDeckFormDoesNotContainFormatField(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Deck $deck */
        $deck = $em->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns']);

        $crawler = $this->client->request('GET', '/deck/'.$deck->getId().'/edit');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('#deck_form_format')->count(), 'Format field should not be present.');
    }

    public function testDeckShowShowsBorrowFormForEligibleUser(): void
    {
        $this->loginAs('borrower@example.com');

        // Regidrago is owned by lender, status available, borrower has engagement
        $shortTag = $this->getDeckShortTag('Regidrago');
        $crawler = $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
        // Borrower should see the event dropdown to request a borrow
        $eventSelect = $crawler->filter('#borrow_event');
        self::assertGreaterThan(0, $eventSelect->count(), 'Borrow event dropdown should be present for eligible user.');
    }

    public function testPublicDeckAccessibleAnonymously(): void
    {
        // Iron Thorns is public
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
    }

    public function testPrivateDeckRedirectsToLoginForAnonymous(): void
    {
        // Ancient Box is not public — anonymous users are redirected to login
        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseRedirects('/login');
    }

    public function testPrivateDeckAccessibleByOwner(): void
    {
        // Ancient Box is owned by admin and is not public
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
    }

    public function testAnonymousUserSeesLoginCta(): void
    {
        // Iron Thorns is public — anonymous should see login CTA, not borrow form
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $crawler = $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#borrow_event');
        $loginLink = $crawler->filter('a[href^="/login"]');
        self::assertGreaterThan(0, $loginLink->count(), 'Login CTA should be present for anonymous users.');
    }
}

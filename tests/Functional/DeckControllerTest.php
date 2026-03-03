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

    // ---------------------------------------------------------------
    // Inline deck list import on creation (F2.13)
    // ---------------------------------------------------------------

    private const string VALID_DECK_LIST = <<<'LIST'
        Pokémon: 13
        4 Flutter Mane TEF 78
        4 Roaring Moon TEF 109
        1 Roaring Moon ex PR-SV 67
        1 Great Tusk TEF 97
        1 Koraidon SSP 116
        1 Munkidori TWM 95
        1 Pecharunt ex SFA 85

        Trainer: 40
        4 Professor Sada's Vitality PAR 170
        4 Explorer's Guidance TEF 147
        2 Boss's Orders PAL 172
        2 Surfer SSP 187
        2 Janine's Secret Art PRE 112
        2 Professor's Research PAF 87
        4 Earthen Vessel PAR 163
        3 Nest Ball SVI 181
        2 Pokégear 3.0 SVI 186
        2 Counter Catcher PAR 160
        2 Night Stretcher SFA 61
        1 Pal Pad SVI 182
        1 Superior Energy Retrieval PAL 189
        1 Super Rod PAL 188
        1 Brilliant Blender SSP 164
        4 Ancient Booster Energy Capsule TEF 140
        1 Exp. Share SVI 174
        2 Artazon PAL 171

        Energy: 7
        7 Darkness Energy SVE 7

        Total Cards: 60
        LIST;

    /**
     * @see docs/features.md F2.13 — Inline deck list import on creation
     */
    public function testCreateDeckWithInlineImport(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/deck/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Deck')->form([
            'deck_form[name]' => 'Inline Import Test Deck',
            'deck_form[rawList]' => self::VALID_DECK_LIST,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        // Verify the deck was created with a version
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Deck|null $deck */
        $deck = $em->getRepository(Deck::class)->findOneBy(['name' => 'Inline Import Test Deck']);
        self::assertNotNull($deck, 'Deck should have been created.');
        self::assertNotNull($deck->getCurrentVersion(), 'Deck should have a current version from inline import.');
    }

    /**
     * @see docs/features.md F2.13 — Inline deck list import on creation
     */
    public function testCreateDeckWithInvalidListShowsErrors(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/deck/new');

        $form = $crawler->selectButton('Create Deck')->form([
            'deck_form[name]' => 'Bad Import Deck',
            'deck_form[rawList]' => "Pokémon: 2\n2 Pikachu V CEL 25",
        ]);
        $this->client->submit($form);

        // Should re-render the form (not redirect) with error flashes
        self::assertResponseIsSuccessful();

        // Verify the deck was NOT created
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Deck|null $deck */
        $deck = $em->getRepository(Deck::class)->findOneBy(['name' => 'Bad Import Deck']);
        self::assertNull($deck, 'Deck should not have been created when deck list is invalid.');
    }

    /**
     * @see docs/features.md F2.13 — Inline deck list import on creation
     */
    public function testCreateDeckWithoutListStillWorks(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/deck/new');

        $form = $crawler->selectButton('Create Deck')->form([
            'deck_form[name]' => 'No List Deck',
            'deck_form[rawList]' => '',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        // Verify the deck was created without a version
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Deck|null $deck */
        $deck = $em->getRepository(Deck::class)->findOneBy(['name' => 'No List Deck']);
        self::assertNotNull($deck, 'Deck should have been created.');
        self::assertNull($deck->getCurrentVersion(), 'Deck should not have a version when no list was provided.');
    }

    public function testNewDeckFormContainsRawListField(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/deck/new');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('#deck_form_rawList')->count(), 'Raw list textarea should be present on new deck form.');
    }
}

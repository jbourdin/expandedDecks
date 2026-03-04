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
use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
 */
class DeckCatalogControllerTest extends AbstractFunctionalTest
{
    public function testAnonymousAccessReturns200(): void
    {
        $this->client->request('GET', '/deck');

        self::assertResponseIsSuccessful();
    }

    public function testAuthenticatedAccessReturns200(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/deck');

        self::assertResponseIsSuccessful();
    }

    public function testEmptyFilterParamsDoNotCauseError(): void
    {
        $this->client->request('GET', '/deck?q=&archetype=&event=&owner=');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '2 decks found');
    }

    public function testArchetypeSearchIsPubliclyAccessible(): void
    {
        $this->client->request('GET', '/api/archetype/search?q=Iron');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertNotEmpty($data);
        self::assertArrayHasKey('name', $data[0]);
        self::assertArrayHasKey('slug', $data[0]);
    }

    public function testOnlyPublicDecksAreListed(): void
    {
        $this->client->request('GET', '/deck');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '2 decks found');
    }

    public function testNonPublicDecksNotListed(): void
    {
        $crawler = $this->client->request('GET', '/deck');

        self::assertResponseIsSuccessful();
        // Ancient Box and Lugia Archeops are not public — verify they don't appear as card titles
        $cardTitles = $crawler->filter('.card-title')->each(static fn ($node) => $node->text());
        foreach ($cardTitles as $title) {
            self::assertStringNotContainsString('Ancient Box', $title);
            self::assertStringNotContainsString('Lugia Archeops', $title);
        }
    }

    public function testSearchByName(): void
    {
        $crawler = $this->client->request('GET', '/deck?q=Iron');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '1 deck found');
        $cardTitles = $crawler->filter('.card-title');
        self::assertCount(1, $cardTitles);
        self::assertStringContainsString('Iron Thorns', $cardTitles->first()->text());
    }

    public function testSearchByShortTag(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Deck|null $deck */
        $deck = $em->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($deck);

        $shortTag = $deck->getShortTag();
        $this->client->request('GET', '/deck?q='.$shortTag);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Iron Thorns');
        self::assertSelectorTextContains('body', '1 deck found');
    }

    public function testArchetypeFilter(): void
    {
        $crawler = $this->client->request('GET', '/deck?archetype=iron-thorns-ex');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '1 deck found');
        $cardTitles = $crawler->filter('.card-title');
        self::assertCount(1, $cardTitles);
        self::assertStringContainsString('Iron Thorns', $cardTitles->first()->text());
    }

    public function testEventFilter(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Event|null $event */
        $event = $em->getRepository(Event::class)->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        $crawler = $this->client->request('GET', '/deck?event='.$event->getId());

        self::assertResponseIsSuccessful();
        // Iron Thorns and Regidrago are registered at this event (both public)
        // Ancient Box is also registered but not public
        self::assertSelectorTextContains('body', '2 decks found');
    }

    public function testRetiredDecksExcluded(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Make Iron Thorns retired — it should disappear from catalog
        /** @var Deck $ironThorns */
        $ironThorns = $em->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns']);
        $ironThorns->setStatus(\App\Enum\DeckStatus::Retired);
        $em->flush();

        $this->client->request('GET', '/deck');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '1 deck found');
    }

    public function testOwnerFilter(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Deck|null $deck */
        $deck = $em->getRepository(Deck::class)->findOneBy(['name' => 'Regidrago']);
        self::assertNotNull($deck);

        $ownerId = $deck->getOwner()->getId();
        $crawler = $this->client->request('GET', '/deck?owner='.$ownerId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '1 deck found');
        $cardTitles = $crawler->filter('.card-title');
        self::assertCount(1, $cardTitles);
        self::assertStringContainsString('Regidrago', $cardTitles->first()->text());
    }

    public function testEmptyResultsMessage(): void
    {
        $this->client->request('GET', '/deck?q=NonExistentDeckXYZ');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '0 decks found');
        self::assertSelectorTextContains('.alert-info', 'No decks match your search criteria.');
    }

    public function testDeckCardsLinkToShowPage(): void
    {
        $crawler = $this->client->request('GET', '/deck');

        self::assertResponseIsSuccessful();
        $deckLinks = $crawler->filter('.card-title a[href^="/deck/"]');
        self::assertGreaterThan(0, $deckLinks->count(), 'Deck cards should contain links to show pages.');
    }

    public function testAutoPublishOnEventRegistration(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Ancient Box is not public — verify
        /** @var Deck|null $ancientBox */
        $ancientBox = $em->getRepository(Deck::class)->findOneBy(['name' => 'Ancient Box']);
        self::assertNotNull($ancientBox);
        self::assertFalse($ancientBox->isPublic());

        // Find the future event (Lyon Expanded Cup 2026)
        /** @var Event|null $event */
        $event = $em->getRepository(Event::class)->findOneBy(['name' => 'Lyon Expanded Cup 2026']);
        self::assertNotNull($event);

        // Visit event page to get CSRF token from the registration form
        $crawler = $this->client->request('GET', '/event/'.$event->getId());
        self::assertResponseIsSuccessful();

        // Find the registration form for Ancient Box and extract its CSRF token
        $registerForm = $crawler->filter(\sprintf('form[action$="/event/%d/toggle-registration"] input[name="deck_id"][value="%d"]', $event->getId(), $ancientBox->getId()));
        self::assertGreaterThan(0, $registerForm->count(), 'Registration form for Ancient Box should exist.');
        $csrfToken = $registerForm->closest('form')->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/event/'.$event->getId().'/toggle-registration', [
            '_token' => $csrfToken,
            'deck_id' => (string) $ancientBox->getId(),
        ]);

        self::assertResponseRedirects();

        // Refresh entity
        $em->clear();
        /** @var Deck $ancientBox */
        $ancientBox = $em->getRepository(Deck::class)->findOneBy(['name' => 'Ancient Box']);
        self::assertTrue($ancientBox->isPublic());
    }

    public function testEventSearchApiReturnsResults(): void
    {
        $this->client->request('GET', '/api/event/search?q=Expanded');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertNotEmpty($data);
        self::assertArrayHasKey('name', $data[0]);
        self::assertArrayHasKey('date', $data[0]);
    }

    public function testEventSearchApiReturnsEmptyForShortQuery(): void
    {
        $this->client->request('GET', '/api/event/search?q=E');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertEmpty($data);
    }

    public function testDeckOwnerSearchApiReturnsResults(): void
    {
        $this->client->request('GET', '/api/deck-owner/search?q=Admin');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertNotEmpty($data);
        self::assertArrayHasKey('screenName', $data[0]);
        self::assertArrayHasKey('id', $data[0]);
    }

    public function testDeckOwnerSearchApiReturnsEmptyForShortQuery(): void
    {
        $this->client->request('GET', '/api/deck-owner/search?q=A');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertEmpty($data);
    }

    public function testSelfOwnerFilterShowsPrivateDecks(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var User $admin */
        $admin = $em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);

        // Admin owns Iron Thorns (public) and Ancient Box (not public)
        $crawler = $this->client->request('GET', '/deck?owner='.$admin->getId());

        self::assertResponseIsSuccessful();
        // Should see both public and private decks when filtering own decks
        $cardTitles = $crawler->filter('.card-title')->each(static fn ($node) => $node->text());
        $allText = implode(' ', $cardTitles);
        self::assertStringContainsString('Iron Thorns', $allText);
        self::assertStringContainsString('Ancient Box', $allText);
    }

    public function testOtherOwnerFilterHidesPrivateDecks(): void
    {
        $this->loginAs('borrower@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var User $admin */
        $admin = $em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);

        // Borrower viewing admin's decks should only see public ones
        $crawler = $this->client->request('GET', '/deck?owner='.$admin->getId());

        self::assertResponseIsSuccessful();
        $cardTitles = $crawler->filter('.card-title')->each(static fn ($node) => $node->text());
        $allText = implode(' ', $cardTitles);
        self::assertStringContainsString('Iron Thorns', $allText);
        self::assertStringNotContainsString('Ancient Box', $allText);
    }

    public function testDeckOwnerSearchApiExcludesOwnersWithoutPublicDecks(): void
    {
        $this->client->request('GET', '/api/deck-owner/search?q=Borrower');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        // "Borrower" owns Lugia Archeops which is NOT public
        // So they should not appear in the owner search results
        $screenNames = array_column($data, 'screenName');
        self::assertNotContains('Borrower', $screenNames);
    }

    public function testCannotUnpublishWithActiveRegistration(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Iron Thorns is public and has active registrations at "today" event
        /** @var Deck|null $ironThorns */
        $ironThorns = $em->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($ironThorns);
        self::assertTrue($ironThorns->isPublic());

        // Attempt to edit and uncheck public
        $this->client->request('GET', '/deck/'.$ironThorns->getId().'/edit');
        self::assertResponseIsSuccessful();

        // The public checkbox should be disabled — verify the warning text
        self::assertSelectorTextContains('body', 'active event registrations');
    }
}

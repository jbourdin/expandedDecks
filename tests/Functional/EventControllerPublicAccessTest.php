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
 * @see docs/features.md F3.24 — Public event detail page
 */
class EventControllerPublicAccessTest extends AbstractFunctionalTest
{
    public function testAnonymousCanViewPublicEvent(): void
    {
        $event = $this->findEventByName('Expanded Weekly #42');

        $this->client->request('GET', '/event/'.$event->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h5', 'Expanded Weekly #42');
    }

    public function testAnonymousCannotViewDraftEvent(): void
    {
        $event = $this->findEventByName('Draft Event — Not Yet Published');

        $this->client->request('GET', '/event/'.$event->getId());

        // For unauthenticated users, Symfony's exception listener turns the
        // AccessDeniedException into a 302 redirect to the login form. The
        // assertion below covers both that path and a direct 403 response —
        // the contract is "anonymous viewers don't see draft events".
        $response = $this->client->getResponse();
        self::assertContains(
            $response->getStatusCode(),
            [302, 403],
            'Draft events must be inaccessible to anonymous viewers',
        );
        if (302 === $response->getStatusCode()) {
            self::assertStringContainsString('/login', $response->headers->get('Location') ?? '');
        }
    }

    public function testAnonymousSeesSignInLinksInsteadOfParticipateForms(): void
    {
        $event = $this->findEventByName('Lyon Expanded Cup 2026');

        $crawler = $this->client->request('GET', '/event/'.$event->getId());

        self::assertResponseIsSuccessful();

        // No POST form to participate is rendered for anonymous viewers.
        self::assertCount(
            0,
            $crawler->filter('form[action$="/participate"]'),
            'Anonymous viewers must not see a POST participate form',
        );

        // Sign-in links carrying _target_path back to the event page are rendered.
        $signinLinks = $crawler->filter('a[href^="/login"]');
        self::assertGreaterThan(
            0,
            $signinLinks->count(),
            'Anonymous viewers must see at least one sign-in link in the participation section',
        );
        $hrefs = $signinLinks->each(static fn ($node) => $node->attr('href') ?? '');
        $eventId = (string) $event->getId();
        $targetPattern = '_target_path=';
        $matchingHrefs = array_filter(
            $hrefs,
            static fn (string $href): bool => str_contains($href, $targetPattern)
                && (str_contains($href, '%2Fevent%2F'.$eventId) || str_contains($href, '/event/'.$eventId)),
        );
        self::assertNotEmpty(
            $matchingHrefs,
            'At least one sign-in link must carry a _target_path returning to the event page',
        );
    }

    public function testAnonymousCanBrowseAvailableDecks(): void
    {
        $event = $this->findEventByName('Lyon Expanded Cup 2026');

        $crawler = $this->client->request('GET', '/event/'.$event->getId().'/decks');

        self::assertResponseIsSuccessful();

        // No POST form to request a borrow for anonymous viewers.
        self::assertCount(
            0,
            $crawler->filter('form[action$="/borrow/request"]'),
            'Anonymous viewers must not see a borrow request form',
        );
    }

    public function testAnonymousPostToParticipateRedirectsToLogin(): void
    {
        $event = $this->findEventByName('Expanded Weekly #42');

        $this->client->request('POST', '/event/'.$event->getId().'/participate', [
            'mode' => 'spectating',
        ]);

        self::assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('/login', $location);
    }

    public function testAuthenticatedOrganizerStillSeesManagementPanels(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->findEventByName('Expanded Weekly #42');

        $crawler = $this->client->request('GET', '/event/'.$event->getId());

        self::assertResponseIsSuccessful();
        // Staff management form remains visible for the organizer.
        self::assertGreaterThan(
            0,
            $crawler->filter('form[action$="/assign-staff"]')->count(),
            'Organizer must still see the staff management form',
        );
    }

    private function findEventByName(string $name): \App\Entity\Event
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = static::getContainer()->get(EventRepository::class);
        $event = $eventRepo->findOneBy(['name' => $name]);
        self::assertNotNull($event, \sprintf('Fixture event "%s" not found', $name));

        return $event;
    }
}

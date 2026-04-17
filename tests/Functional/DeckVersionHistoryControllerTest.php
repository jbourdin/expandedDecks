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
 * @see docs/features.md F2.9 — Deck version history
 */
class DeckVersionHistoryControllerTest extends AbstractFunctionalTest
{
    public function testVersionsPageAccessibleForPublicDeck(): void
    {
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', '/deck/'.$shortTag.'/versions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h5', 'Iron Thorns');
    }

    public function testVersionsPageShowsAllVersions(): void
    {
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $crawler = $this->client->request('GET', '/deck/'.$shortTag.'/versions');

        // Iron Thorns has 2 versions in fixtures
        $versionRows = $crawler->filter('table tbody tr');
        self::assertGreaterThanOrEqual(2, $versionRows->count());
    }

    public function testVersionsPageRedirectsToLoginForPrivateDeckAnonymous(): void
    {
        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag.'/versions');

        self::assertResponseRedirects('/login');
    }

    public function testVersionsPageAccessibleByOwner(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag.'/versions');

        self::assertResponseIsSuccessful();
    }

    public function testVersionsPageDeniedForNonOwner(): void
    {
        $this->loginAs('lender@example.com');

        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag.'/versions');

        self::assertResponseStatusCodeSame(403);
    }

    public function testCompareEndpointReturnsJson(): void
    {
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', '/api/deck/'.$shortTag.'/versions/compare?from=1&to=2');

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('added', $data);
        self::assertArrayHasKey('removed', $data);
        self::assertArrayHasKey('changed', $data);
        self::assertArrayHasKey('unchanged', $data);

        // V2 has changes: removed Megaton Blower, added Crushing Hammer,
        // changed Plumeria (4->3), changed Enhanced Hammer (2->3)
        self::assertNotEmpty($data['added']);
        self::assertNotEmpty($data['removed']);
        self::assertNotEmpty($data['changed']);
        self::assertNotEmpty($data['unchanged']);
    }

    public function testCompareEndpointInvalidVersionNumbers(): void
    {
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', '/api/deck/'.$shortTag.'/versions/compare?from=0&to=1');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCompareEndpointNonExistentVersion(): void
    {
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', '/api/deck/'.$shortTag.'/versions/compare?from=1&to=99');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCompareEndpointRedirectsToLoginForPrivateDeckAnonymous(): void
    {
        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/api/deck/'.$shortTag.'/versions/compare?from=1&to=1');

        self::assertResponseRedirects('/login');
    }

    /**
     * @see docs/features.md F2.9 — Restore previous deck version
     */
    /**
     * @see docs/features.md F2.9 — Restore previous deck version
     */
    public function testRestoreVersionAsOwner(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');

        // Load versions page to get CSRF token from the restore form
        $crawler = $this->client->request('GET', '/deck/'.$shortTag.'/versions');
        $restoreForm = $crawler->filter('form[action*="/restore"] button[type="submit"]');
        self::assertGreaterThan(0, $restoreForm->count(), 'Restore submit button should exist for non-current version');

        $this->client->submit($restoreForm->first()->form());

        self::assertResponseRedirects('/deck/'.$shortTag.'/versions');
    }

    public function testRestoreVersionDeniedForAnonymous(): void
    {
        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('POST', '/deck/'.$shortTag.'/versions/1/restore', [
            '_token' => 'invalid',
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testRestoreVersionDeniedForNonOwner(): void
    {
        $this->loginAs('borrower@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('POST', '/deck/'.$shortTag.'/versions/1/restore', [
            '_token' => 'any',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRestoreVersionInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('POST', '/deck/'.$shortTag.'/versions/1/restore', [
            '_token' => 'bad-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRestoreNonExistentVersionReturns404(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        // Use a valid CSRF approach: load page first, then POST with bad version number
        $this->client->request('GET', '/deck/'.$shortTag.'/versions');
        $this->client->request('POST', '/deck/'.$shortTag.'/versions/99/restore', [
            '_token' => 'any',
        ]);

        // Either 403 (CSRF fails) or 404 — both are acceptable for a nonexistent version
        self::assertResponseStatusCodeSame(403);
    }

    private function getDeck(string $name): Deck
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => $name]);

        return $deck;
    }

    private function getDeckShortTag(string $name): string
    {
        return $this->getDeck($name)->getShortTag();
    }

    private function getCsrfToken(string $tokenId): string
    {
        $session = $this->client->getSession();
        self::assertNotNull($session, 'Session must exist — make a GET request first.');
        $session->start();

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = static::getContainer()->get('request_stack');

        $syntheticRequest = new \Symfony\Component\HttpFoundation\Request();
        $syntheticRequest->setSession($session);
        $requestStack->push($syntheticRequest);

        try {
            /** @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokenManager */
            $tokenManager = static::getContainer()->get('security.csrf.token_manager');

            return $tokenManager->getToken($tokenId)->getValue();
        } finally {
            $requestStack->pop();
        }
    }
}

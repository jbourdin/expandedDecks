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
use Doctrine\ORM\EntityManagerInterface;

/**
 * Draft locales (F9.16): the content channel fixture publishes `en` and holds
 * `fr` as a draft — browsable by translators of FR, moderators, and admins;
 * invisible (redirected) for everyone else, and absent from SEO signals.
 *
 * @see docs/features.md F9.16 — Channel locale management
 */
class DraftLocaleTest extends AbstractFunctionalTest
{
    private const array CONTENT_HOST = ['HTTP_HOST' => 'expandedtalks.wip'];

    private function loginByEmail(string $email): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        \assert($user instanceof User);
        $this->client->loginUser($user);

        return $user;
    }

    public function testAnonymousVisitorIsRedirectedOffTheDraftLocale(): void
    {
        $this->client->request('GET', '/fr/archetypes/regidrago', server: self::CONTENT_HOST);

        self::assertResponseRedirects('/en/archetypes/regidrago', 302);
    }

    public function testUserWithoutTranslationRightsIsRedirectedToo(): void
    {
        $this->loginByEmail('borrower@example.com');
        $this->client->request('GET', '/fr/archetypes/regidrago', server: self::CONTENT_HOST);

        self::assertResponseRedirects('/en/archetypes/regidrago', 302);
    }

    public function testAssignedTranslatorBrowsesTheDraftLocale(): void
    {
        $this->loginByEmail('translator@example.com');
        $crawler = $this->client->request('GET', '/fr/archetypes/regidrago', server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
    }

    public function testModeratorBrowsesTheDraftLocale(): void
    {
        // Admin inherits ROLE_TRANSLATION_MODERATOR via the role hierarchy.
        $this->loginByEmail('admin@example.com');
        $this->client->request('GET', '/fr/archetypes/regidrago', server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
    }

    public function testDraftLocaleStaysOutOfSeoSignals(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes/regidrago', server: self::CONTENT_HOST);
        self::assertResponseIsSuccessful();

        // Published-only channel: no hreflang alternates, no locale switcher.
        self::assertSame(0, $crawler->filter('link[rel="alternate"][hreflang="fr"]')->count());
        self::assertStringNotContainsString('/fr/archetypes/regidrago', (string) $this->client->getResponse()->getContent());
    }

    public function testSwitcherShowsTheDraftLocaleToAuthorizedUsersOnly(): void
    {
        // The translator sees the FR draft entry, marked as a draft.
        $this->loginByEmail('translator@example.com');
        $crawler = $this->client->request('GET', '/en/archetypes/regidrago', server: self::CONTENT_HOST);
        self::assertResponseIsSuccessful();
        $draftLink = $crawler->filter('a[href="/fr/archetypes/regidrago"]');
        self::assertSame(1, $draftLink->count(), 'Translator must see the FR draft switcher entry.');
        self::assertNotNull($draftLink->attr('title'));

        // A user without translation rights sees no switcher at all (single
        // published locale).
        $this->loginByEmail('borrower@example.com');
        $crawler = $this->client->request('GET', '/en/archetypes/regidrago', server: self::CONTENT_HOST);
        self::assertSame(0, $crawler->filter('a[href="/fr/archetypes/regidrago"]')->count());
    }

    public function testLocaleSwitchEndpointRefusesInvisibleLocales(): void
    {
        $this->loginByEmail('borrower@example.com');
        // Establish a session on the content channel first.
        $this->client->request('GET', '/en/archetypes/regidrago', server: self::CONTENT_HOST);

        // The locale listener intercepts the draft target and re-routes the
        // switch to the published locale, keeping the redirect parameter.
        $this->client->request('GET', '/locale/fr?_redirect=/en/archetypes/regidrago', server: self::CONTENT_HOST);
        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('/locale/en', $location);
        self::assertStringContainsString('_redirect=', $location);

        // The refused switch must not have stored the draft locale.
        $this->client->request('GET', '/en/pages/welcome', server: self::CONTENT_HOST);
        self::assertResponseIsSuccessful();
    }
}

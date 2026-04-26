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

/**
 * @see docs/features.md F18.2 — Global search results page
 */
class SearchControllerTest extends AbstractFunctionalTest
{
    public function testSearchPageAccessibleAnonymously(): void
    {
        $this->client->request('GET', '/en/search');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="q"]');
    }

    public function testSearchPageWithQuery(): void
    {
        $this->client->request('GET', '/en/search?q=test');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'test');
    }

    public function testSearchPageWithEmptyQuery(): void
    {
        $this->client->request('GET', '/en/search?q=');

        self::assertResponseIsSuccessful();
    }

    public function testSearchPageWithTypeFilter(): void
    {
        $this->client->request('GET', '/en/search?q=test&type=archetypes');

        self::assertResponseIsSuccessful();
    }

    public function testSearchPageWithInvalidTypeFilterIgnored(): void
    {
        $this->client->request('GET', '/en/search?q=test&type=invalid');

        self::assertResponseIsSuccessful();
    }

    public function testSearchPageHasNoIndexMeta(): void
    {
        $crawler = $this->client->request('GET', '/en/search?q=test');

        self::assertResponseIsSuccessful();
        $meta = $crawler->filter('meta[name="robots"]');
        self::assertSame('noindex', $meta->attr('content'));
    }

    public function testSearchPageFrenchLocale(): void
    {
        $this->client->request('GET', '/fr/search?q=test');

        self::assertResponseIsSuccessful();
    }
}

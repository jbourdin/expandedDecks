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
 * @see docs/features.md F18.3 — Quick-search autocomplete (navbar)
 */
class SearchApiControllerTest extends AbstractFunctionalTest
{
    public function testQuickSearchReturnsJsonArray(): void
    {
        $this->client->request('GET', '/api/search/quick?q=test');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
    }

    public function testQuickSearchRejectsShortQuery(): void
    {
        $this->client->request('GET', '/api/search/quick?q=a');

        self::assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame([], $data);
    }

    public function testQuickSearchRejectsEmptyQuery(): void
    {
        $this->client->request('GET', '/api/search/quick?q=');

        self::assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame([], $data);
    }

    public function testQuickSearchAccessibleAnonymously(): void
    {
        $this->client->request('GET', '/api/search/quick?q=regidrago');

        self::assertResponseIsSuccessful();
    }

    public function testQuickSearchOnContentChannel(): void
    {
        $this->client->request('GET', '/api/search/quick?q=test', server: ['HTTP_HOST' => 'expandedtalks.wip']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
    }
}

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

namespace App\Tests\Service\Tcgdex;

use App\Service\Tcgdex\TcgdexApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Additional coverage for TcgdexApiClient — findImageByName error path.
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class TcgdexApiClientCoverageTest extends TestCase
{
    /**
     * findImageByName returns null when the API responds with a non-200 status code.
     */
    public function testFindImageByNameReturnsNullOnNon200Status(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter());
        $imageUrl = $client->findImageByName('Nonexistent Card');

        self::assertNull($imageUrl);
    }
}

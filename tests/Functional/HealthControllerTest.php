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

class HealthControllerTest extends AbstractFunctionalTest
{
    public function testLivenessProbeReturnsOk(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('ok', $content);
    }

    public function testReadinessProbeReturnsOk(): void
    {
        $this->client->request('GET', '/health/ready');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testHealthEndpointsAccessibleWithoutAuth(): void
    {
        // No loginAs() call — verify both endpoints work without authentication
        $this->client->request('GET', '/health');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/health/ready');
        self::assertResponseIsSuccessful();
    }
}

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

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertSame('ok', $data['status']);
        self::assertArrayHasKey('version', $data);
    }

    public function testReadinessProbeReturnsOk(): void
    {
        $this->client->request('GET', '/health/ready');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('version', $data);
    }

    public function testReadinessReportsWorkerCheckAsSkippedInTestEnv(): void
    {
        $this->client->request('GET', '/health/ready');

        self::assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('checks', $data);
        self::assertIsArray($data['checks']);
        self::assertArrayHasKey('worker', $data['checks']);
        self::assertIsArray($data['checks']['worker']);

        // APP_ENV=test → supervisorctl is unprovisioned → checker returns
        // 'skipped' rather than 'fail' so local runs and CI stay green.
        self::assertSame('skipped', $data['checks']['worker']['status']);
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

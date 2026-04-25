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

namespace App\Tests\Controller;

use App\Controller\HealthController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @see docs/features.md F14.4 — Health check endpoint
 */
final class HealthControllerTest extends TestCase
{
    public function testLivenessReturns200(): void
    {
        $controller = $this->createController();

        $response = $controller->liveness();

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('ok', $data['status']);
        self::assertSame('test', $data['version']);
    }

    public function testReadinessReturns200WhenAllServicesReachable(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($this->createStub(\Doctrine\DBAL\Result::class));

        $controller = $this->createController($connection, $this->createHealthyMeilisearchClient());
        $response = $controller->readiness();

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('healthy', $data['status']);
        self::assertSame('test', $data['version']);
        self::assertSame('ok', $data['checks']['database']['status']);
        self::assertArrayHasKey('latency_ms', $data['checks']['database']);
        self::assertSame('ok', $data['checks']['meilisearch']['status']);
    }

    public function testReadinessReturns503WhenDatabaseIsUnreachable(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException(new \RuntimeException('Connection refused'));

        $controller = $this->createController($connection, $this->createHealthyMeilisearchClient());
        $response = $controller->readiness();

        self::assertSame(503, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('unhealthy', $data['status']);
        self::assertSame('test', $data['version']);
        self::assertSame('fail', $data['checks']['database']['status']);
        self::assertSame('Connection refused', $data['checks']['database']['error']);
    }

    public function testReadinessStillHealthyWhenMeilisearchIsUnreachable(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($this->createStub(\Doctrine\DBAL\Result::class));

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new \RuntimeException('Connection refused'));

        $controller = $this->createController($connection, $httpClient);
        $response = $controller->readiness();

        // MeiliSearch is non-critical: app stays healthy, but check reports fail
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('healthy', $data['status']);
        self::assertSame('ok', $data['checks']['database']['status']);
        self::assertSame('fail', $data['checks']['meilisearch']['status']);
    }

    private function createController(
        ?Connection $connection = null,
        ?HttpClientInterface $httpClient = null,
    ): HealthController {
        return new HealthController(
            $connection ?? $this->createStub(Connection::class),
            $httpClient ?? $this->createStub(HttpClientInterface::class),
            new NullLogger(),
            'test',
            'http://127.0.0.1:7700',
        );
    }

    private function createHealthyMeilisearchClient(): HttpClientInterface
    {
        $meiliResponse = $this->createStub(ResponseInterface::class);
        $meiliResponse->method('toArray')->willReturn(['status' => 'available']);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($meiliResponse);

        return $httpClient;
    }
}

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

/**
 * @see docs/features.md F14.4 — Health check endpoint
 */
final class HealthControllerTest extends TestCase
{
    public function testLivenessReturns200(): void
    {
        $controller = new HealthController($this->createStub(Connection::class), new NullLogger(), 'test');

        $response = $controller->liveness();

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('ok', $data['status']);
        self::assertSame('test', $data['version']);
    }

    public function testReadinessReturns200WhenDatabaseIsReachable(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($this->createStub(\Doctrine\DBAL\Result::class));

        $controller = new HealthController($connection, new NullLogger(), 'test');
        $response = $controller->readiness();

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('healthy', $data['status']);
        self::assertSame('test', $data['version']);
        self::assertSame('ok', $data['checks']['database']['status']);
        self::assertArrayHasKey('latency_ms', $data['checks']['database']);
    }

    public function testReadinessReturns503WhenDatabaseIsUnreachable(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException(new \RuntimeException('Connection refused'));

        $controller = new HealthController($connection, new NullLogger(), 'test');
        $response = $controller->readiness();

        self::assertSame(503, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('unhealthy', $data['status']);
        self::assertSame('test', $data['version']);
        self::assertSame('fail', $data['checks']['database']['status']);
        self::assertSame('Connection refused', $data['checks']['database']['error']);
    }
}

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

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liveness and readiness probes for container orchestration.
 *
 * @see docs/features.md F14.4 — Health check endpoint
 */
class HealthController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Lightweight liveness probe — confirms the app process is running.
     */
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function liveness(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    /**
     * Deep readiness probe — checks external dependencies.
     * Returns HTTP 200 when all checks pass, HTTP 503 when any check fails.
     * Compatible with Pingdom and similar uptime monitors.
     */
    #[Route('/health/ready', name: 'app_health_ready', methods: ['GET'])]
    public function readiness(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        $checks['database'] = $this->checkDatabase();
        if ('ok' !== $checks['database']['status']) {
            $healthy = false;
        }

        return new JsonResponse(
            ['status' => $healthy ? 'healthy' : 'unhealthy', 'checks' => $checks],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    /**
     * @return array{status: string, latency_ms?: float, error?: string}
     */
    private function checkDatabase(): array
    {
        try {
            $start = hrtime(true);
            $this->connection->executeQuery('SELECT 1');
            $latency = (hrtime(true) - $start) / 1_000_000;

            return ['status' => 'ok', 'latency_ms' => round($latency, 1)];
        } catch (\Throwable $exception) {
            return ['status' => 'fail', 'error' => $exception->getMessage()];
        }
    }
}

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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Liveness and readiness probes for container orchestration.
 *
 * @see docs/features.md F14.4 — Health check endpoint
 */
class HealthController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'APP_VERSION')]
        private readonly string $appVersion,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'MEILISEARCH_URL')]
        private readonly string $meilisearchUrl,
    ) {
    }

    /**
     * Lightweight liveness probe — confirms the app process is running.
     */
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function liveness(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok', 'version' => $this->appVersion]);
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

        // MeiliSearch is a non-critical dependency: search degrades gracefully
        // to database LIKE queries when unavailable. Reported but not fatal.
        $checks['meilisearch'] = $this->checkMeilisearch();

        return new JsonResponse(
            ['status' => $healthy ? 'healthy' : 'unhealthy', 'version' => $this->appVersion, 'checks' => $checks],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    /**
     * Sentry logs smoke test — emits an info and an error log, returns OK.
     */
    #[Route('/health/sentry-logs', name: 'app_health_sentry_logs', methods: ['GET'])]
    public function sentryLogs(): JsonResponse
    {
        $this->logger->info('Sentry logs smoke test: info level');
        $this->logger->error('Sentry logs smoke test: error level');

        return new JsonResponse(['status' => 'ok', 'message' => 'Logs emitted']);
    }

    /**
     * Sentry issue smoke test — throws an exception to trigger a Sentry issue.
     */
    #[Route('/health/sentry-error', name: 'app_health_sentry_error', methods: ['GET'])]
    public function sentryError(): never
    {
        throw new \RuntimeException('Sentry smoke test: this exception should appear as a Sentry issue.');
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

    /**
     * @return array{status: string, latency_ms?: float, error?: string}
     */
    private function checkMeilisearch(): array
    {
        try {
            $start = hrtime(true);
            $response = $this->httpClient->request('GET', $this->meilisearchUrl.'/health');
            $data = $response->toArray();
            $latency = (hrtime(true) - $start) / 1_000_000;

            if ('available' === ($data['status'] ?? null)) {
                return ['status' => 'ok', 'latency_ms' => round($latency, 1)];
            }

            $meiliStatus = $data['status'] ?? 'unknown';

            return ['status' => 'fail', 'error' => 'MeiliSearch status: '.(\is_string($meiliStatus) ? $meiliStatus : 'unknown')];
        } catch (\Throwable $exception) {
            return ['status' => 'fail', 'error' => $exception->getMessage()];
        }
    }
}

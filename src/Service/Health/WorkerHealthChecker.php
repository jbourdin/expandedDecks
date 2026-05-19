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

namespace App\Service\Health;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

/**
 * Probes Supervisor for the local `worker-messenger` program state.
 *
 * Uses supervisorctl (via the unix socket configured in supervisord.conf) to
 * confirm the Messenger consumer running inside the *same container* as the
 * HTTP process is alive. A cluster-wide queue stall heuristic on
 * messenger_messages is not used: any healthy peer drains the queue and would
 * mask a dead local worker.
 *
 * Failure semantics depend on APP_ENV:
 *   - prod: returns status=fail (so readiness probe goes 503 and the
 *     orchestrator keeps the pod out of rotation).
 *   - other envs: returns status=skipped (supervisorctl is not provisioned in
 *     dev / CI; the check stays informational so it does not break local runs).
 *
 * @see docs/features.md F14.8 — Worker liveness check on /health/ready
 */
final class WorkerHealthChecker
{
    public const string PROGRAM_NAME = 'worker-messenger';

    /**
     * STARTING is treated as healthy alongside RUNNING to cover two windows:
     * (1) initial pod cold start before `startsecs` elapses, and (2) the brief
     * gap when `messenger:consume --time-limit=1200` exits cleanly and
     * Supervisor's `autorestart=true` respawns the program. STOPPING is
     * intentionally excluded: it only occurs during pod shutdown, when
     * readiness *should* fail so the orchestrator drains traffic.
     */
    public const array HEALTHY_STATES = ['RUNNING', 'STARTING'];

    private const string SUPERVISORCTL_CONFIG = '/etc/supervisor/conf.d/supervisord.conf';
    private const float TIMEOUT_SECONDS = 2.0;

    /**
     * @var callable(): array{exitCode: int|null, output: string, errorMessage: string|null}
     */
    private $runner;

    /**
     * @param (callable(): array{exitCode: int|null, output: string, errorMessage: string|null})|null $runner
     */
    public function __construct(
        #[Autowire(env: 'APP_ENV')]
        private readonly string $appEnv,
        ?callable $runner = null,
    ) {
        $this->runner = $runner ?? self::defaultRunner(...);
    }

    /**
     * @return array{status: string, state?: string, latency_ms: float, error?: string}
     */
    public function check(): array
    {
        $start = hrtime(true);

        $result = ($this->runner)();

        if (null !== $result['errorMessage']) {
            return $this->buildFailure('supervisorctl unavailable: '.$result['errorMessage'], $start);
        }

        $state = self::parseState($result['output']);

        if (null === $state) {
            return $this->buildFailure(
                \sprintf('cannot parse supervisorctl output (exit %d): %s', $result['exitCode'] ?? -1, trim($result['output'])),
                $start,
            );
        }

        if (\in_array($state, self::HEALTHY_STATES, true)) {
            $latency = (hrtime(true) - $start) / 1_000_000;

            return ['status' => 'ok', 'state' => $state, 'latency_ms' => round($latency, 1)];
        }

        return $this->buildFailure(\sprintf('worker-messenger state: %s', $state), $start, $state);
    }

    /**
     * Extracts the first uppercase state token from `supervisorctl status` output.
     * Returns null when the program line is missing or malformed (e.g. the
     * "worker-messenger: ERROR (no such process)" form, which uses a colon and
     * does not match the standard whitespace-separated layout).
     */
    public static function parseState(string $output): ?string
    {
        if (1 === preg_match('/^'.preg_quote(self::PROGRAM_NAME, '/').'\s+([A-Z]+)/m', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array{exitCode: int|null, output: string, errorMessage: string|null}
     */
    private static function defaultRunner(): array
    {
        try {
            $process = new Process(['supervisorctl', '-c', self::SUPERVISORCTL_CONFIG, 'status', self::PROGRAM_NAME]);
            $process->setTimeout(self::TIMEOUT_SECONDS);
            $process->run();

            return [
                'exitCode' => $process->getExitCode(),
                'output' => $process->getOutput(),
                'errorMessage' => null,
            ];
        } catch (ProcessException $exception) {
            return [
                'exitCode' => null,
                'output' => '',
                'errorMessage' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, state?: string, latency_ms: float, error: string}
     */
    private function buildFailure(string $error, int $start, ?string $state = null): array
    {
        $latency = (hrtime(true) - $start) / 1_000_000;
        $isCritical = 'prod' === $this->appEnv;

        $result = [
            'status' => $isCritical ? 'fail' : 'skipped',
            'latency_ms' => round($latency, 1),
            'error' => $error,
        ];

        if (null !== $state) {
            $result['state'] = $state;
        }

        return $result;
    }
}

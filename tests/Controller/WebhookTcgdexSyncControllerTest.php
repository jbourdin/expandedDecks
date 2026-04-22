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

use App\Controller\WebhookTcgdexSyncController;
use App\Service\Tcgdex\TcgdexSyncStatusService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
final class WebhookTcgdexSyncControllerTest extends TestCase
{
    private const string SECRET = 'test-webhook-secret';

    public function testValidSignatureReturns202(): void
    {
        $controller = $this->createController(queueDepth: 0);
        $request = $this->createSignedRequest('{"trigger":"cron"}');

        $response = $controller($request);

        self::assertSame(202, $response->getStatusCode());
        self::assertStringContainsString('dispatched', (string) $response->getContent());
    }

    public function testInvalidSignatureReturns403(): void
    {
        $controller = $this->createController();
        $request = Request::create('/webhook/tcgdex-sync', 'POST', [], [], [], [], '{"trigger":"cron"}');
        $request->headers->set('X-Sync-Signature', 'sha256=invalid');

        $response = $controller($request);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMissingSignatureReturns403(): void
    {
        $controller = $this->createController();
        $request = Request::create('/webhook/tcgdex-sync', 'POST', [], [], [], [], '{"trigger":"cron"}');

        $response = $controller($request);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testEmptySecretReturns404(): void
    {
        $controller = $this->createController(secret: '');
        $request = Request::create('/webhook/tcgdex-sync', 'POST');

        $response = $controller($request);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testActiveSyncReturns200(): void
    {
        $controller = $this->createController(queueDepth: 42);
        $request = $this->createSignedRequest('{"trigger":"cron"}');

        $response = $controller($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('already_in_progress', (string) $response->getContent());
    }

    public function testSignatureWithoutPrefixReturns403(): void
    {
        $controller = $this->createController();
        $request = Request::create('/webhook/tcgdex-sync', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-Sync-Signature', hash_hmac('sha256', '{}', self::SECRET));

        $response = $controller($request);

        self::assertSame(403, $response->getStatusCode());
    }

    private function createController(string $secret = self::SECRET, int $queueDepth = 0): WebhookTcgdexSyncController
    {
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $syncStatus = $this->createStub(TcgdexSyncStatusService::class);
        $syncStatus->method('getQueueDepth')->willReturn($queueDepth);

        return new WebhookTcgdexSyncController($messageBus, $syncStatus, $secret);
    }

    private function createSignedRequest(string $body): Request
    {
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);
        $request = Request::create('/webhook/tcgdex-sync', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Sync-Signature', $signature);

        return $request;
    }
}

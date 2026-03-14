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

use App\Controller\MessengerWebhookController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface as MessengerSerializerInterface;

/**
 * @see docs/features.md F14.3 — SQS-compatible webhook message consumer
 */
final class MessengerWebhookControllerTest extends TestCase
{
    private const string SECRET = 'test-webhook-secret';

    private MessageBusInterface&MockObject $messageBus;
    private MessengerSerializerInterface&MockObject $serializer;
    private MessengerWebhookController $controller;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->serializer = $this->createMock(MessengerSerializerInterface::class);
        $this->controller = new MessengerWebhookController(
            $this->messageBus,
            $this->serializer,
            new NullLogger(),
            self::SECRET,
        );
    }

    public function testReturns503WhenSecretNotConfigured(): void
    {
        $controller = new MessengerWebhookController(
            $this->messageBus,
            $this->serializer,
            new NullLogger(),
            '',
        );

        $response = $controller('transactional_email', new Request());

        self::assertSame(503, $response->getStatusCode());
    }

    public function testReturns404ForUnknownTransport(): void
    {
        $request = $this->createSignedRequest('{}');

        $response = ($this->controller)('unknown_transport', $request);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testReturns403ForInvalidSignature(): void
    {
        $request = new Request(content: '{}');
        $request->headers->set('X-Messenger-Signature', 'invalid');

        $response = ($this->controller)('transactional_email', $request);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testReturns403WhenSignatureMissing(): void
    {
        $request = new Request(content: '{}');

        $response = ($this->controller)('transactional_email', $request);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testReturns400OnDeserializationFailure(): void
    {
        $body = '{"invalid": "message"}';
        $request = $this->createSignedRequest($body);

        $this->serializer->method('decode')->willThrowException(new \RuntimeException('Bad format'));

        $response = ($this->controller)('transactional_email', $request);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testReturns500OnHandlerFailure(): void
    {
        $body = '{"message": "test"}';
        $request = $this->createSignedRequest($body);
        $envelope = new Envelope(new \stdClass());

        $this->serializer->method('decode')->willReturn($envelope);
        $this->messageBus->method('dispatch')->willThrowException(new \RuntimeException('Handler failed'));

        $response = ($this->controller)('transactional_email', $request);

        self::assertSame(500, $response->getStatusCode());
    }

    public function testReturns200OnSuccess(): void
    {
        $body = '{"message": "test"}';
        $request = $this->createSignedRequest($body);
        $envelope = new Envelope(new \stdClass());

        $this->serializer->method('decode')->willReturn($envelope);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        $response = ($this->controller)('deck_enrichment', $request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testDispatchesWithReceivedStamp(): void
    {
        $body = '{"message": "test"}';
        $request = $this->createSignedRequest($body);
        $envelope = new Envelope(new \stdClass());

        $this->serializer->method('decode')->willReturn($envelope);
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (Envelope $dispatched): bool {
                $stamps = $dispatched->all(ReceivedStamp::class);
                if (1 !== \count($stamps)) {
                    return false;
                }

                return 'notification' === $stamps[0]->getTransportName();
            }))
            ->willReturn($envelope);

        ($this->controller)('notification', $request);
    }

    public function testAcceptsAllAllowedTransports(): void
    {
        $allowedTransports = ['transactional_email', 'deck_enrichment', 'notification', 'borrow_lifecycle'];
        $envelope = new Envelope(new \stdClass());

        $this->serializer->method('decode')->willReturn($envelope);
        $this->messageBus->method('dispatch')->willReturn($envelope);

        foreach ($allowedTransports as $transport) {
            $request = $this->createSignedRequest('{}');
            $response = ($this->controller)($transport, $request);

            self::assertSame(200, $response->getStatusCode(), "Transport '{$transport}' should be allowed");
        }
    }

    private function createSignedRequest(string $body): Request
    {
        $signature = hash_hmac('sha256', $body, self::SECRET);
        $request = new Request(content: $body);
        $request->headers->set('X-Messenger-Signature', $signature);

        return $request;
    }
}

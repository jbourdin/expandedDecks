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

namespace App\Tests\MessageHandler;

use App\Entity\TcgdexCard;
use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use App\Enum\SyncMode;
use App\Message\SyncTcgdexCardMessage;
use App\MessageHandler\SyncTcgdexCardHandler;
use App\Service\Tcgdex\TcgdexApiThrottle;
use App\Service\Tcgdex\TcgdexCardHydrator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
final class SyncTcgdexCardHandlerTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private TcgdexApiThrottle $throttle;
    private TcgdexCardHydrator $hydrator;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    /** @var list<object> */
    private array $dispatchedMessages = [];

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->throttle = $this->createStub(TcgdexApiThrottle::class);
        $this->hydrator = new TcgdexCardHydrator();
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->dispatchedMessages = [];
    }

    private function createHandler(): SyncTcgdexCardHandler
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatchedMessages[] = $message;

            return new Envelope($message);
        });

        return new SyncTcgdexCardHandler(
            $this->httpClient,
            $this->throttle,
            $this->hydrator,
            $this->entityManager,
            $bus,
            $this->logger,
        );
    }

    private function createApiResponse(): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'id' => 'sv05-001',
            'localId' => '001',
            'name' => 'Exeggcute',
            'category' => 'Pokemon',
            'hp' => 50,
            'image' => 'https://assets.tcgdex.net/en/sv/sv05/001',
            'legal' => ['expanded' => true],
        ]);

        return $response;
    }

    public function testPersistsNewCard(): void
    {
        $set = new TcgdexSet('sv05', new TcgdexSerie('sv'));

        $this->httpClient->method('request')->willReturn($this->createApiResponse());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturnMap([
            [TcgdexCard::class, 'sv05-001', null],
            [TcgdexSet::class, 'sv05', $set],
        ]);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(TcgdexCard::class));
        $entityManager->expects(self::once())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-001', 'sv05'));
    }

    public function testSkipsExistingCardInInsertMode(): void
    {
        $existingCard = $this->createStub(TcgdexCard::class);
        $this->entityManager->method('find')->willReturn($existingCard);

        // HTTP client should NOT be called
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('Should not be called'));

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-001', 'sv05'));

        // No error — handler returned early
        self::assertTrue(true);
    }

    public function testFullModeUpdatesExistingCard(): void
    {
        $set = new TcgdexSet('sv05', new TcgdexSerie('sv'));
        $existingCard = new TcgdexCard('sv05-001', $set, '001');

        $this->httpClient->method('request')->willReturn($this->createApiResponse());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturnMap([
            [TcgdexCard::class, 'sv05-001', $existingCard],
            [TcgdexSet::class, 'sv05', $set],
        ]);
        $entityManager->expects(self::never())->method('persist'); // Update, not insert
        $entityManager->expects(self::once())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-001', 'sv05', SyncMode::Full));

        self::assertSame('https://assets.tcgdex.net/en/sv/sv05/001', $existingCard->getImageBaseUrl());
    }

    public function test404DoesNotRedispatch(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->method('find')->willReturn(null);

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-999', 'sv05'));

        // No redispatch — 404 is not retried
        $retries = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexCardMessage);
        self::assertCount(0, $retries);
    }

    public function testHttpErrorRedispatches(): void
    {
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('Timeout'));
        $this->entityManager->method('find')->willReturn(null);

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-001', 'sv05'));

        $retries = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexCardMessage);
        self::assertCount(1, $retries);
    }
}

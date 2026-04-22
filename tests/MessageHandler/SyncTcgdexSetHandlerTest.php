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
use App\Message\SyncTcgdexSetMessage;
use App\MessageHandler\SyncTcgdexSetHandler;
use App\Service\Tcgdex\TcgdexApiThrottle;
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
final class SyncTcgdexSetHandlerTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private TcgdexApiThrottle $throttle;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    /** @var list<object> */
    private array $dispatchedMessages = [];

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->throttle = $this->createStub(TcgdexApiThrottle::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->dispatchedMessages = [];
    }

    private function createHandler(): SyncTcgdexSetHandler
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatchedMessages[] = $message;

            return new Envelope($message);
        });

        return new SyncTcgdexSetHandler(
            $this->httpClient,
            $this->throttle,
            $this->entityManager,
            $bus,
            $this->logger,
        );
    }

    private function createSet(): TcgdexSet
    {
        $serie = new TcgdexSerie('sv');

        return new TcgdexSet('sv05', $serie);
    }

    public function testDispatchesCardMessagesForMissingCards(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'cards' => [
                ['id' => 'sv05-001', 'localId' => '001', 'name' => 'Card A', 'image' => 'https://example.com/001'],
                ['id' => 'sv05-002', 'localId' => '002', 'name' => 'Card B', 'image' => 'https://example.com/002'],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->method('find')->willReturnCallback(
            fn (string $class, mixed $id): ?object => TcgdexSet::class === $class ? $this->createSet() : null,
        );

        ($this->createHandler())(new SyncTcgdexSetMessage('sv05'));

        $cardMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexCardMessage);
        self::assertCount(2, $cardMessages);
    }

    public function testSkipsExistingCardsInInsertMode(): void
    {
        $existingCard = $this->createStub(TcgdexCard::class);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'cards' => [
                ['id' => 'sv05-001', 'localId' => '001', 'name' => 'Existing', 'image' => 'https://example.com/001'],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->method('find')->willReturnMap([
            [TcgdexSet::class, 'sv05', $this->createSet()],
            [TcgdexCard::class, 'sv05-001', $existingCard],
        ]);

        ($this->createHandler())(new SyncTcgdexSetMessage('sv05'));

        $cardMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexCardMessage);
        self::assertCount(0, $cardMessages);
    }

    public function testFullModeDispatchesForExistingCards(): void
    {
        $existingCard = $this->createStub(TcgdexCard::class);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'cards' => [
                ['id' => 'sv05-001', 'localId' => '001', 'name' => 'Existing', 'image' => 'https://example.com/001'],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->method('find')->willReturnMap([
            [TcgdexSet::class, 'sv05', $this->createSet()],
            [TcgdexCard::class, 'sv05-001', $existingCard],
        ]);

        ($this->createHandler())(new SyncTcgdexSetMessage('sv05', SyncMode::Full));

        $cardMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexCardMessage);
        self::assertCount(1, $cardMessages);
    }

    public function testUpdateModeUpdatesImageUrlsFromSetResponse(): void
    {
        $existingCard = new TcgdexCard('sv05-001', $this->createSet(), '001');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'cards' => [
                ['id' => 'sv05-001', 'localId' => '001', 'name' => 'Card', 'image' => 'https://assets.tcgdex.net/en/sv/sv05/001'],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturnMap([
            [TcgdexSet::class, 'sv05', $this->createSet()],
            [TcgdexCard::class, 'sv05-001', $existingCard],
        ]);
        $entityManager->expects(self::atLeastOnce())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexSetMessage('sv05', SyncMode::Update));

        self::assertSame('https://assets.tcgdex.net/en/sv/sv05/001', $existingCard->getImageBaseUrl());

        // Update mode should NOT dispatch card messages for existing cards
        $cardMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexCardMessage);
        self::assertCount(0, $cardMessages);
    }

    public function testHttpErrorRedispatches(): void
    {
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('Timeout'));

        ($this->createHandler())(new SyncTcgdexSetMessage('sv05'));

        $retries = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSetMessage);
        self::assertCount(1, $retries);
    }
}

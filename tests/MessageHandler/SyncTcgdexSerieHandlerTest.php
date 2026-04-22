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

use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use App\Enum\SyncMode;
use App\Message\SyncTcgdexSerieMessage;
use App\Message\SyncTcgdexSetMessage;
use App\MessageHandler\SyncTcgdexSerieHandler;
use App\Repository\TcgdexCardRepository;
use App\Service\Tcgdex\TcgdexApiThrottle;
use Doctrine\Common\Collections\ArrayCollection;
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
final class SyncTcgdexSerieHandlerTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private TcgdexApiThrottle $throttle;
    private TcgdexCardRepository $cardRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    /** @var list<object> */
    private array $dispatchedMessages = [];

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->throttle = $this->createStub(TcgdexApiThrottle::class);
        $this->cardRepository = $this->createStub(TcgdexCardRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->dispatchedMessages = [];
    }

    private function createHandler(): SyncTcgdexSerieHandler
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatchedMessages[] = $message;

            return new Envelope($message);
        });

        return new SyncTcgdexSerieHandler(
            $this->httpClient,
            $this->throttle,
            $this->cardRepository,
            $this->entityManager,
            $bus,
            $this->logger,
        );
    }

    public function testCreatesNewSetAndDispatchesSyncMessage(): void
    {
        $serie = $this->createStub(TcgdexSerie::class);
        $serie->method('getSets')->willReturn(new ArrayCollection());

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'sets' => [
                ['id' => 'sv01', 'name' => 'Scarlet & Violet', 'releaseDate' => '2023-03-31'],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($serie);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(TcgdexSet::class));
        $entityManager->expects(self::once())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexSerieMessage('sv'));

        $setMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSetMessage);
        self::assertCount(1, $setMessages);
    }

    public function testDetectsCardCountMismatchInInsertMode(): void
    {
        $existingSet = $this->createStub(TcgdexSet::class);
        $existingSet->method('getId')->willReturn('sv01');

        $serie = $this->createStub(TcgdexSerie::class);
        $serie->method('getSets')->willReturn(new ArrayCollection([$existingSet]));

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'sets' => [
                ['id' => 'sv01', 'cardCount' => ['official' => 100, 'total' => 120]],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->method('find')->willReturn($serie);
        $this->cardRepository->method('countBySetId')->willReturn(100); // Mismatch: 100 local vs 120 total

        ($this->createHandler())(new SyncTcgdexSerieMessage('sv'));

        $setMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSetMessage);
        self::assertCount(1, $setMessages);
    }

    public function testSkipsExistingSetWhenCardCountMatchesInInsertMode(): void
    {
        $existingSet = $this->createStub(TcgdexSet::class);
        $existingSet->method('getId')->willReturn('sv01');

        $serie = $this->createStub(TcgdexSerie::class);
        $serie->method('getSets')->willReturn(new ArrayCollection([$existingSet]));

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'sets' => [
                ['id' => 'sv01', 'cardCount' => ['official' => 100, 'total' => 120]],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->method('find')->willReturn($serie);
        $this->cardRepository->method('countBySetId')->willReturn(120); // Matches total

        ($this->createHandler())(new SyncTcgdexSerieMessage('sv'));

        $setMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSetMessage);
        self::assertCount(0, $setMessages);
    }

    public function testUpdateModeSyncsAllExistingSets(): void
    {
        $existingSet = $this->createStub(TcgdexSet::class);
        $existingSet->method('getId')->willReturn('sv01');

        $serie = $this->createStub(TcgdexSerie::class);
        $serie->method('getSets')->willReturn(new ArrayCollection([$existingSet]));

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'sets' => [
                ['id' => 'sv01', 'cardCount' => ['official' => 100, 'total' => 120]],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->method('find')->willReturn($serie);
        $this->cardRepository->method('countBySetId')->willReturn(120); // Count matches, but update mode syncs anyway

        ($this->createHandler())(new SyncTcgdexSerieMessage('sv', SyncMode::Update));

        $setMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSetMessage);
        self::assertCount(1, $setMessages);
    }

    public function testHttpErrorRedispatches(): void
    {
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('Timeout'));

        ($this->createHandler())(new SyncTcgdexSerieMessage('sv'));

        $retries = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSerieMessage);
        self::assertCount(1, $retries);
    }

    public function testSortsSetsByReleaseDateDescending(): void
    {
        $serie = $this->createStub(TcgdexSerie::class);
        $serie->method('getSets')->willReturn(new ArrayCollection());

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'sets' => [
                ['id' => 'sv01', 'name' => 'Old Set', 'releaseDate' => '2023-01-01'],
                ['id' => 'sv05', 'name' => 'New Set', 'releaseDate' => '2024-03-22'],
                ['id' => 'sv03', 'name' => 'Mid Set', 'releaseDate' => '2023-09-15'],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($serie);
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexSerieMessage('sv'));

        $setMessages = array_values(array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSetMessage));
        self::assertCount(3, $setMessages);
        self::assertSame('sv05', $setMessages[0]->setId);
        self::assertSame('sv03', $setMessages[1]->setId);
        self::assertSame('sv01', $setMessages[2]->setId);
    }
}

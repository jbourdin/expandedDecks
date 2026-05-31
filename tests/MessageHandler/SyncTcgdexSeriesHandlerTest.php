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
use App\Enum\SyncMode;
use App\Message\SyncTcgdexCompleteMessage;
use App\Message\SyncTcgdexSerieMessage;
use App\Message\SyncTcgdexSeriesMessage;
use App\MessageHandler\SyncTcgdexSeriesHandler;
use App\Repository\TcgdexSerieRepository;
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
final class SyncTcgdexSeriesHandlerTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private TcgdexApiThrottle $throttle;
    private TcgdexSerieRepository $serieRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    /** @var list<object> */
    private array $dispatchedMessages = [];

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->throttle = $this->createStub(TcgdexApiThrottle::class);
        $this->serieRepository = $this->createStub(TcgdexSerieRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->dispatchedMessages = [];
    }

    private function createHandler(?MessageBusInterface $messageBus = null): SyncTcgdexSeriesHandler
    {
        $bus = $messageBus ?? $this->createBus();

        return new SyncTcgdexSeriesHandler(
            $this->httpClient,
            $this->throttle,
            $this->serieRepository,
            $this->entityManager,
            $bus,
            $this->logger,
            'https://api.tcgdex.net/v2',
        );
    }

    private function createBus(): MessageBusInterface
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatchedMessages[] = $message;

            return new Envelope($message);
        });

        return $bus;
    }

    public function testDispatchesSerieMessagesForEachSerie(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            ['id' => 'sv', 'name' => 'Scarlet & Violet', 'releaseDate' => '2023-03-31'],
            ['id' => 'swsh', 'name' => 'Sword & Shield', 'releaseDate' => '2020-02-07'],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->serieRepository->method('findAllIds')->willReturn(['sv', 'swsh']);

        ($this->createHandler())(new SyncTcgdexSeriesMessage());

        $serieMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSerieMessage);
        self::assertCount(2, $serieMessages);

        // Verify newest first (sv before swsh)
        $serieMessages = array_values($serieMessages);
        self::assertSame('sv', $serieMessages[0]->serieId);
        self::assertSame('swsh', $serieMessages[1]->serieId);
    }

    public function testExcludesTcgpSerie(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            ['id' => 'sv', 'name' => 'Scarlet & Violet'],
            ['id' => 'tcgp', 'name' => 'TCG Pocket'],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->serieRepository->method('findAllIds')->willReturn(['sv', 'tcgp']);

        ($this->createHandler())(new SyncTcgdexSeriesMessage());

        $serieMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSerieMessage);
        self::assertCount(1, $serieMessages);
    }

    public function testCreatesNewSerieEntity(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            ['id' => 'newone', 'name' => 'Brand New', 'logo' => 'https://example.com/logo.png'],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->serieRepository->method('findAllIds')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(TcgdexSerie::class));
        $entityManager->expects(self::once())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexSeriesMessage());
    }

    public function testDispatchesCompleteMessageWithDelay(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $this->httpClient->method('request')->willReturn($response);
        $this->serieRepository->method('findAllIds')->willReturn([]);

        ($this->createHandler())(new SyncTcgdexSeriesMessage());

        $completeMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexCompleteMessage);
        self::assertCount(1, $completeMessages);
    }

    public function testPropagatesSyncMode(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            ['id' => 'sv', 'name' => 'Scarlet & Violet'],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->serieRepository->method('findAllIds')->willReturn(['sv']);

        ($this->createHandler())(new SyncTcgdexSeriesMessage(SyncMode::Sync));

        $serieMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSerieMessage);
        $serieMessages = array_values($serieMessages);
        self::assertSame(SyncMode::Sync, $serieMessages[0]->mode);
    }

    public function testHttpErrorRedispatchesWithDelay(): void
    {
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('Connection timeout'));

        $this->serieRepository->method('findAllIds')->willReturn([]);

        ($this->createHandler())(new SyncTcgdexSeriesMessage());

        // Should redispatch the same message (SyncTcgdexSeriesMessage)
        $retries = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSeriesMessage);
        self::assertCount(1, $retries);
    }

    public function testRefreshesExistingSerieLogos(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            ['id' => 'sv', 'name' => 'Scarlet & Violet', 'logo' => 'https://new-logo.png'],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->serieRepository->method('findAllIds')->willReturn(['sv']);

        $serie = $this->createStub(TcgdexSerie::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($serie);
        $entityManager->expects(self::once())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexSeriesMessage());
    }
}

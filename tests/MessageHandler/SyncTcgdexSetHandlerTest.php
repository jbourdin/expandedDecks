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
 * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
 */
final class SyncTcgdexSetHandlerTest extends TestCase
{
    private const array LOCALES = ['en', 'fr'];

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
            'https://api.tcgdex.net/v2',
            self::LOCALES,
        );
    }

    private function createSet(): TcgdexSet
    {
        return new TcgdexSet('sv05', new TcgdexSerie('sv'));
    }

    /**
     * @param array<string, mixed> $name
     */
    private function createCard(array $name = []): TcgdexCard
    {
        $card = new TcgdexCard('sv05-001', $this->createSet(), '001');
        $card->setName($name);

        return $card;
    }

    /**
     * @return list<SyncTcgdexCardMessage>
     */
    private function dispatchedCardMessages(): array
    {
        return array_values(array_filter(
            $this->dispatchedMessages,
            static fn (object $message): bool => $message instanceof SyncTcgdexCardMessage,
        ));
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

        self::assertCount(2, $this->dispatchedCardMessages());
    }

    public function testSkipsCardsWithEveryLocaleInSyncMode(): void
    {
        $completeCard = $this->createCard(['en' => 'Existing', 'fr' => 'Existant']);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'cards' => [
                ['id' => 'sv05-001', 'localId' => '001', 'name' => 'Existing', 'image' => 'https://example.com/001'],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->method('find')->willReturnMap([
            [TcgdexSet::class, 'sv05', $this->createSet()],
            [TcgdexCard::class, 'sv05-001', $completeCard],
        ]);

        ($this->createHandler())(new SyncTcgdexSetMessage('sv05'));

        self::assertCount(0, $this->dispatchedCardMessages());
    }

    public function testDispatchesForCardsMissingALocaleInSyncMode(): void
    {
        // English-only card is still missing French → the set handler dispatches a gap-fill.
        $incompleteCard = $this->createCard(['en' => 'Existing']);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'cards' => [
                ['id' => 'sv05-001', 'localId' => '001', 'name' => 'Existing', 'image' => 'https://example.com/001'],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->method('find')->willReturnMap([
            [TcgdexSet::class, 'sv05', $this->createSet()],
            [TcgdexCard::class, 'sv05-001', $incompleteCard],
        ]);

        ($this->createHandler())(new SyncTcgdexSetMessage('sv05'));

        $cardMessages = $this->dispatchedCardMessages();
        self::assertCount(1, $cardMessages);
        self::assertSame(SyncMode::Sync, $cardMessages[0]->mode);
    }

    public function testForceUpdateModeDispatchesForEveryExistingCard(): void
    {
        $completeCard = $this->createCard(['en' => 'Existing', 'fr' => 'Existant']);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'cards' => [
                ['id' => 'sv05-001', 'localId' => '001', 'name' => 'Existing', 'image' => 'https://example.com/001'],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->method('find')->willReturnMap([
            [TcgdexSet::class, 'sv05', $this->createSet()],
            [TcgdexCard::class, 'sv05-001', $completeCard],
        ]);

        ($this->createHandler())(new SyncTcgdexSetMessage('sv05', SyncMode::ForceUpdate));

        $cardMessages = $this->dispatchedCardMessages();
        self::assertCount(1, $cardMessages);
        self::assertSame(SyncMode::ForceUpdate, $cardMessages[0]->mode);
    }

    public function testSyncRefreshesImageUrlForCompleteCardsWithoutDispatching(): void
    {
        $completeCard = $this->createCard(['en' => 'Card', 'fr' => 'Carte']);

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
            [TcgdexCard::class, 'sv05-001', $completeCard],
        ]);
        $entityManager->expects(self::atLeastOnce())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexSetMessage('sv05'));

        self::assertSame('https://assets.tcgdex.net/en/sv/sv05/001', $completeCard->getImageBaseUrl());
        self::assertCount(0, $this->dispatchedCardMessages());
    }

    public function testHttpErrorRedispatches(): void
    {
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('Timeout'));

        ($this->createHandler())(new SyncTcgdexSetMessage('sv05'));

        $retries = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexSetMessage);
        self::assertCount(1, $retries);
    }
}

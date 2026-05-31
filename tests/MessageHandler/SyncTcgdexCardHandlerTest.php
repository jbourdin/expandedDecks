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
 * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
 */
final class SyncTcgdexCardHandlerTest extends TestCase
{
    private const array LOCALES = ['en', 'fr'];

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
            'https://api.tcgdex.net/v2',
            self::LOCALES,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload, int $statusCode = 200): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('toArray')->willReturn($payload);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPayload(string $name): array
    {
        return [
            'id' => 'sv05-001',
            'localId' => '001',
            'name' => $name,
            'category' => 'Pokemon',
            'hp' => 50,
            'image' => 'https://assets.tcgdex.net/en/sv/sv05/001',
            'legal' => ['expanded' => true],
        ];
    }

    /**
     * Route the stubbed HTTP client by locale segment in the requested URL.
     *
     * @param array<string, ResponseInterface> $byLocale
     */
    private function routeByLocale(array $byLocale): void
    {
        $this->httpClient->method('request')->willReturnCallback(
            static function (string $method, string $url) use ($byLocale): ResponseInterface {
                foreach ($byLocale as $locale => $response) {
                    if (str_contains($url, '/'.$locale.'/')) {
                        return $response;
                    }
                }

                throw new \RuntimeException('No stubbed response for URL '.$url);
            },
        );
    }

    public function testPersistsNewCardAcrossLocales(): void
    {
        $set = new TcgdexSet('sv05', new TcgdexSerie('sv'));

        $this->routeByLocale([
            'en' => $this->jsonResponse($this->cardPayload('Exeggcute')),
            'fr' => $this->jsonResponse($this->cardPayload('Noeunoeuf')),
        ]);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturnMap([
            [TcgdexCard::class, 'sv05-001', null],
            [TcgdexSet::class, 'sv05', $set],
        ]);
        $entityManager->expects(self::once())->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            });
        $entityManager->expects(self::once())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-001', 'sv05'));

        self::assertInstanceOf(TcgdexCard::class, $persisted);
        self::assertSame('Exeggcute', $persisted->getLocalizedName('en'));
        self::assertSame('Noeunoeuf', $persisted->getLocalizedName('fr'));
    }

    public function testSkipsCardWithEveryLocaleInSyncMode(): void
    {
        $existing = $this->createStub(TcgdexCard::class);
        $existing->method('hasAllLocales')->willReturn(true);
        $this->entityManager->method('find')->willReturn($existing);

        // No HTTP call must happen for an already-complete card.
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('Should not be called'));

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-001', 'sv05'));

        self::assertCount(0, $this->dispatchedMessages);
    }

    public function testSyncFetchesOnlyMissingLocaleForExistingCard(): void
    {
        $set = new TcgdexSet('sv05', new TcgdexSerie('sv'));
        $existing = new TcgdexCard('sv05-001', $set, '001');
        $existing->setName(['en' => 'Exeggcute']); // French missing

        $this->routeByLocale([
            'en' => $this->jsonResponse($this->cardPayload('Exeggcute')),
            'fr' => $this->jsonResponse($this->cardPayload('Noeunoeuf')),
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturnMap([
            [TcgdexCard::class, 'sv05-001', $existing],
            [TcgdexSet::class, 'sv05', $set],
        ]);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-001', 'sv05'));

        self::assertSame('Exeggcute', $existing->getLocalizedName('en'));
        self::assertSame('Noeunoeuf', $existing->getLocalizedName('fr'));
    }

    public function testTranslationLocale404IsSkippedWithoutRedispatch(): void
    {
        $set = new TcgdexSet('sv05', new TcgdexSerie('sv'));
        $existing = new TcgdexCard('sv05-001', $set, '001');
        $existing->setName(['en' => 'Exeggcute']); // French missing, and not yet published upstream

        $this->routeByLocale([
            'en' => $this->jsonResponse($this->cardPayload('Exeggcute')),
            'fr' => $this->jsonResponse([], 404),
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturnMap([
            [TcgdexCard::class, 'sv05-001', $existing],
            [TcgdexSet::class, 'sv05', $set],
        ]);
        $entityManager->expects(self::once())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-001', 'sv05'));

        // English refreshed, French still absent, and the card is not redispatched.
        self::assertSame('Exeggcute', $existing->getLocalizedName('en'));
        self::assertNull($existing->getName()['fr'] ?? null);
        self::assertCount(0, $this->dispatchedMessages);
    }

    public function testForceUpdateRefetchesEveryLocale(): void
    {
        $set = new TcgdexSet('sv05', new TcgdexSerie('sv'));
        $existing = new TcgdexCard('sv05-001', $set, '001');
        $existing->setName(['en' => 'Stale', 'fr' => 'Périmé']); // already complete

        $this->routeByLocale([
            'en' => $this->jsonResponse($this->cardPayload('Exeggcute')),
            'fr' => $this->jsonResponse($this->cardPayload('Noeunoeuf')),
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturnMap([
            [TcgdexCard::class, 'sv05-001', $existing],
            [TcgdexSet::class, 'sv05', $set],
        ]);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');
        $this->entityManager = $entityManager;

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-001', 'sv05', SyncMode::ForceUpdate));

        // A complete card is still re-fetched and overwritten in ForceUpdate mode.
        self::assertSame('Exeggcute', $existing->getLocalizedName('en'));
        self::assertSame('Noeunoeuf', $existing->getLocalizedName('fr'));
    }

    public function testBaseLocale404DoesNotRedispatch(): void
    {
        $this->httpClient->method('request')->willReturn($this->jsonResponse([], 404));
        $this->entityManager->method('find')->willReturn(null);

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-999', 'sv05'));

        $retries = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexCardMessage);
        self::assertCount(0, $retries);
    }

    public function testBaseLocaleHttpErrorRedispatches(): void
    {
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('Timeout'));
        $this->entityManager->method('find')->willReturn(null);

        ($this->createHandler())(new SyncTcgdexCardMessage('sv05-001', 'sv05'));

        $retries = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof SyncTcgdexCardMessage);
        self::assertCount(1, $retries);
    }
}

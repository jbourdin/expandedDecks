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

use App\Message\BuildSetMappingsMessage;
use App\MessageHandler\BuildSetMappingsHandler;
use App\Repository\TcgdexSetMappingRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class BuildSetMappingsHandlerTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private TcgdexSetMappingRepository $repository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->repository = $this->createStub(TcgdexSetMappingRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function createHandler(
        ?EntityManagerInterface $entityManager = null,
    ): BuildSetMappingsHandler {
        return new BuildSetMappingsHandler(
            $this->httpClient,
            $this->repository,
            $entityManager ?? $this->entityManager,
            $this->logger,
        );
    }

    public function testSuccessPathFetchesSetsAndPersistsMappings(): void
    {
        // The list endpoint returns a list of sets
        $listResponse = $this->createStub(ResponseInterface::class);
        $listResponse->method('toArray')->willReturn([
            ['id' => 'sv01'],
            ['id' => 'swsh1'],
        ]);

        // Detail responses for each set
        $detailResponseSv01 = $this->createStub(ResponseInterface::class);
        $detailResponseSv01->method('toArray')->willReturn([
            'abbreviation' => ['official' => 'svi'],
            'tcgOnline' => 'SVI',
        ]);

        $detailResponseSwsh1 = $this->createStub(ResponseInterface::class);
        $detailResponseSwsh1->method('toArray')->willReturn([
            'abbreviation' => ['official' => 'ssh'],
        ]);

        $this->httpClient->method('request')->willReturnCallback(
            static function (string $method, string $url) use ($listResponse, $detailResponseSv01, $detailResponseSwsh1): ResponseInterface {
                if (str_ends_with($url, '/sets')) {
                    return $listResponse;
                }

                if (str_ends_with($url, '/sets/sv01')) {
                    return $detailResponseSv01;
                }

                if (str_ends_with($url, '/sets/swsh1')) {
                    return $detailResponseSwsh1;
                }

                throw new \RuntimeException('Unexpected URL: '.$url);
            },
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        // Static overrides (11) + 2 API sets, but sv01 is overridden by SVI static override
        // swsh1 gets abbreviation.official SSH
        // So we expect persist to be called for each unique mapping
        $entityManager->expects(self::atLeastOnce())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        ($this->createHandler($entityManager))(new BuildSetMappingsMessage());
    }

    public function testTcgOnlineOverridesOfficialAbbreviation(): void
    {
        $listResponse = $this->createStub(ResponseInterface::class);
        $listResponse->method('toArray')->willReturn([
            ['id' => 'sv03pt5'],
        ]);

        $detailResponse = $this->createStub(ResponseInterface::class);
        $detailResponse->method('toArray')->willReturn([
            'abbreviation' => ['official' => 'mev'],
            'tcgOnline' => 'MEW',
        ]);

        $this->httpClient->method('request')->willReturnCallback(
            static function (string $method, string $url) use ($listResponse, $detailResponse): ResponseInterface {
                if (str_ends_with($url, '/sets')) {
                    return $listResponse;
                }

                return $detailResponse;
            },
        );

        // Track what gets persisted to verify the PTCG code
        $persistedEntities = [];

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeastOnce())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedEntities): void {
                $persistedEntities[] = $entity;
            });
        $entityManager->expects(self::once())->method('flush');

        ($this->createHandler($entityManager))(new BuildSetMappingsMessage());

        // Find the entity for sv03pt5 — tcgOnline (MEW) should win over abbreviation.official (mev)
        $found = false;

        foreach ($persistedEntities as $entity) {
            if ('sv03pt5' === $entity->getTcgdexSetId()) {
                self::assertSame('MEW', $entity->getPtcgCode());
                $found = true;
            }
        }

        self::assertTrue($found, 'Expected a TcgdexSetMapping entity for sv03pt5');
    }

    public function testTruncateIsCalledBeforePersisting(): void
    {
        $listResponse = $this->createStub(ResponseInterface::class);
        $listResponse->method('toArray')->willReturn([]);

        $this->httpClient->method('request')->willReturn($listResponse);

        $repository = $this->createMock(TcgdexSetMappingRepository::class);
        $repository->expects(self::once())->method('truncate');

        $handler = new BuildSetMappingsHandler(
            $this->httpClient,
            $repository,
            $this->entityManager,
            $this->logger,
        );

        ($handler)(new BuildSetMappingsMessage());
    }
}

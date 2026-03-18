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

namespace App\Tests\Service\Mosaic;

use App\Entity\DeckVersion;
use App\Message\GenerateDeckMosaicMessage;
use App\Repository\DeckVersionRepository;
use App\Service\Mosaic\MosaicRedispatchService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
final class MosaicRedispatchServiceTest extends TestCase
{
    private DeckVersionRepository $repository;
    private MessageBusInterface $messageBus;
    private MosaicRedispatchService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(DeckVersionRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->messageBus->method('dispatch')->willReturnCallback(
            static fn (object $message): Envelope => new Envelope($message),
        );

        $this->service = new MosaicRedispatchService($this->repository, $this->messageBus);
    }

    public function testRedispatchWithNoVersionsReturnsZero(): void
    {
        $this->repository->method('findEnrichedWithoutMosaic')->willReturn([]);

        $this->messageBus->expects(self::never())->method('dispatch');

        self::assertSame(0, $this->service->redispatch());
    }

    public function testRedispatchDispatchesMessageForEachVersion(): void
    {
        $version1 = $this->createVersionWithId(10);
        $version2 = $this->createVersionWithId(20);

        $this->repository->method('findEnrichedWithoutMosaic')->willReturn([$version1, $version2]);

        $dispatched = [];
        $this->messageBus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });

        $count = $this->service->redispatch();

        self::assertSame(2, $count);
        self::assertInstanceOf(GenerateDeckMosaicMessage::class, $dispatched[0]);
        self::assertSame(10, $dispatched[0]->deckVersionId);
        self::assertInstanceOf(GenerateDeckMosaicMessage::class, $dispatched[1]);
        self::assertSame(20, $dispatched[1]->deckVersionId);
    }

    private function createVersionWithId(int $id): DeckVersion
    {
        $version = new DeckVersion();
        $reflection = new \ReflectionProperty(DeckVersion::class, 'id');
        $reflection->setValue($version, $id);

        return $version;
    }
}

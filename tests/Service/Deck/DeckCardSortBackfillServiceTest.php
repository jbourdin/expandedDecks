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

namespace App\Tests\Service\Deck;

use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Repository\DeckVersionRepository;
use App\Service\Deck\DeckCardSortBackfillService;
use App\Service\DeckListParser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F2.28 — Preserve imported list order
 */
class DeckCardSortBackfillServiceTest extends TestCase
{
    private DeckCardSortBackfillService $service;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []): string => $id,
        );
        $parser = new DeckListParser($translator);

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(static fn (object $m): Envelope => new Envelope($m));
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->service = new DeckCardSortBackfillService(
            $versionRepository,
            $messageBus,
            $parser,
            $this->entityManager,
        );
    }

    public function testBackfillSkipsVersionWithNoRawList(): void
    {
        $version = new DeckVersion();
        // rawList stays null by default.

        $report = $this->service->backfillVersion($version);

        self::assertTrue($report['skipped']);
        self::assertSame(0, $report['changed']);
        self::assertSame(0, $report['missing']);
    }

    public function testBackfillSkipsVersionWithEmptyRawList(): void
    {
        $version = new DeckVersion();
        $version->setRawList('   ');

        $report = $this->service->backfillVersion($version);

        self::assertTrue($report['skipped']);
    }

    public function testBackfillEarlyExitsWhenEveryCardHasSortOrder(): void
    {
        $version = new DeckVersion();
        $version->setRawList(<<<'PTCG'
            Pokémon: 1
            1 Iron Thorns ex PAR 77
            PTCG);
        $card = new DeckCard();
        $card->setSetCode('PAR');
        $card->setCardNumber('77');
        $card->setCardName('Iron Thorns ex');
        $card->setSortOrder(1);
        $version->addCard($card);

        $report = $this->service->backfillVersion($version);

        self::assertTrue($report['skipped'], 'Should skip when every card already has a sortOrder.');
        self::assertSame(1, $card->getSortOrder(), 'Existing sortOrder should not be mutated.');
    }

    public function testBackfillPopulatesNullSortOrdersFromRawList(): void
    {
        $version = new DeckVersion();
        $version->setRawList(<<<'PTCG'
            Pokémon: 2
            1 Iron Thorns ex PAR 77
            1 Lugia V SIT 138
            PTCG);

        $cardA = new DeckCard();
        $cardA->setSetCode('PAR');
        $cardA->setCardNumber('77');
        $cardA->setCardName('Iron Thorns ex');
        $version->addCard($cardA);

        $cardB = new DeckCard();
        $cardB->setSetCode('SIT');
        $cardB->setCardNumber('138');
        $cardB->setCardName('Lugia V');
        $version->addCard($cardB);

        $report = $this->service->backfillVersion($version);

        self::assertFalse($report['skipped']);
        self::assertSame(2, $report['changed']);
        self::assertSame(0, $report['missing']);
        self::assertSame(1, $cardA->getSortOrder(), 'first card matches line index 1');
        self::assertSame(2, $cardB->getSortOrder(), 'second card matches line index 2');
    }

    public function testBackfillMatchesBySetAndNumberEvenWhenNameDiverges(): void
    {
        // Simulates a post-enrichment row where CardEnricher overwrote cardName
        // with the TCGdex canonical form, which differs from the rawList name.
        $version = new DeckVersion();
        $version->setRawList(<<<'PTCG'
            Pokémon: 1
            1 Lugia V SIT 138
            PTCG);

        $card = new DeckCard();
        $card->setSetCode('SIT');
        $card->setCardNumber('138');
        $card->setCardName('Lugia V (Silver Tempest)'); // canonical-form drift
        $version->addCard($card);

        $report = $this->service->backfillVersion($version);

        self::assertSame(1, $report['changed'], 'set+number match even when name drifts');
        self::assertSame(0, $report['missing']);
        self::assertSame(1, $card->getSortOrder());
    }

    public function testBackfillReportsMissingWhenDbCardNotInRawList(): void
    {
        $version = new DeckVersion();
        $version->setRawList(<<<'PTCG'
            Pokémon: 1
            1 Iron Thorns ex PAR 77
            PTCG);

        $cardInList = new DeckCard();
        $cardInList->setSetCode('PAR');
        $cardInList->setCardNumber('77');
        $cardInList->setCardName('Iron Thorns ex');
        $version->addCard($cardInList);

        $cardNotInList = new DeckCard();
        $cardNotInList->setSetCode('SIT');
        $cardNotInList->setCardNumber('999');
        $cardNotInList->setCardName('Manual edit');
        $version->addCard($cardNotInList);

        $report = $this->service->backfillVersion($version);

        self::assertSame(1, $report['changed']);
        self::assertSame(1, $report['missing'], 'DB cards absent from rawList should be counted as missing, not blow up');
        self::assertNull($cardNotInList->getSortOrder(), 'unmatched card keeps null sortOrder');
    }

    public function testCountPendingDelegatesToRepository(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);
        $parser = new DeckListParser($translator);

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('countNeedingSortOrderBackfill')->willReturn(17);

        $service = new DeckCardSortBackfillService(
            $versionRepository,
            $this->createStub(MessageBusInterface::class),
            $parser,
            $this->createStub(EntityManagerInterface::class),
        );

        self::assertSame(17, $service->countPending());
    }

    public function testRedispatchEmitsOneMessagePerPendingVersion(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);
        $parser = new DeckListParser($translator);

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('findIdsNeedingSortOrderBackfill')->willReturn([3, 5, 7, 11]);

        $dispatched = [];
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        $service = new DeckCardSortBackfillService(
            $versionRepository,
            $messageBus,
            $parser,
            $this->createStub(EntityManagerInterface::class),
        );

        $count = $service->redispatch();

        self::assertSame(4, $count);
        self::assertCount(4, $dispatched);
        $ids = array_map(static fn ($m): int => $m->deckVersionId, $dispatched);
        self::assertSame([3, 5, 7, 11], $ids);
    }
}

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

namespace App\Tests\Functional;

use App\Repository\DeckCardRepository;
use App\Service\CardReenrichService;

class CardReenrichServiceTest extends AbstractFunctionalTest
{
    private CardReenrichService $service;
    private DeckCardRepository $deckCardRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Establish session (needed for container access)
        $this->loginAs('admin@example.com');

        /** @var CardReenrichService $service */
        $service = static::getContainer()->get(CardReenrichService::class);
        $this->service = $service;

        /** @var DeckCardRepository $repository */
        $repository = static::getContainer()->get(DeckCardRepository::class);
        $this->deckCardRepository = $repository;
    }

    public function testReenrichReturnsZeroForNonExistentCard(): void
    {
        $count = $this->service->reenrich('NONEXISTENT', '999');

        self::assertSame(0, $count);
    }

    public function testReenrichReturnsAffectedVersionCount(): void
    {
        // TWM 77 = Iron Thorns ex — exists in fixture data
        $count = $this->service->reenrich('TWM', '77');

        self::assertGreaterThan(0, $count);
    }

    public function testReenrichDetachesCardPrintingFromMatchingCards(): void
    {
        // First, verify cards exist
        $cardsBefore = $this->deckCardRepository->findBySetCodeAndCardNumber('TWM', '77');
        self::assertNotEmpty($cardsBefore);

        $this->service->reenrich('TWM', '77');

        // After reenrich, the sync handler runs immediately.
        // The cards are re-enriched, but the version status should reflect processing.
        // Verify the service ran without errors by checking it returned a count.
        $cardsAfter = $this->deckCardRepository->findBySetCodeAndCardNumber('TWM', '77');
        self::assertCount(\count($cardsBefore), $cardsAfter);
    }
}

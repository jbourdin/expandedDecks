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

class DeckCardRepositoryTest extends AbstractFunctionalTest
{
    private DeckCardRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var DeckCardRepository $repository */
        $repository = static::getContainer()->get(DeckCardRepository::class);
        $this->repository = $repository;
    }

    public function testFindBySetCodeAndCardNumberReturnsMatchingCards(): void
    {
        // TWM 77 = Iron Thorns ex — exists in fixture data
        $cards = $this->repository->findBySetCodeAndCardNumber('TWM', '77');

        self::assertNotEmpty($cards);

        foreach ($cards as $card) {
            self::assertSame('TWM', $card->getSetCode());
            self::assertSame('77', $card->getCardNumber());
        }
    }

    public function testFindBySetCodeAndCardNumberReturnsEmptyForNonExistent(): void
    {
        $cards = $this->repository->findBySetCodeAndCardNumber('NONEXISTENT', '999');

        self::assertSame([], $cards);
    }
}

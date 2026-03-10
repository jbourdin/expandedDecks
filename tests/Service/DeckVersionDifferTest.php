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

namespace App\Tests\Service;

use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Service\DeckVersionDiffer;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F2.9 — Deck version history
 */
class DeckVersionDifferTest extends TestCase
{
    private DeckVersionDiffer $differ;

    protected function setUp(): void
    {
        $this->differ = new DeckVersionDiffer();
    }

    public function testDiffIdenticalVersions(): void
    {
        $oldVersion = $this->createVersion([
            ['Pikachu', 'BW1', '42', 4, 'pokemon'],
        ]);
        $newVersion = $this->createVersion([
            ['Pikachu', 'BW1', '42', 4, 'pokemon'],
        ]);

        $result = $this->differ->diff($oldVersion, $newVersion);

        self::assertCount(0, $result['added']);
        self::assertCount(0, $result['removed']);
        self::assertCount(0, $result['changed']);
        self::assertCount(1, $result['unchanged']);
        self::assertSame('Pikachu', $result['unchanged'][0]['cardName']);
        self::assertArrayHasKey('imageUrl', $result['unchanged'][0]);
    }

    public function testDiffWithAddedCards(): void
    {
        $oldVersion = $this->createVersion([]);
        $newVersion = $this->createVersion([
            ['Pikachu', 'BW1', '42', 4, 'pokemon'],
        ]);

        $result = $this->differ->diff($oldVersion, $newVersion);

        self::assertCount(1, $result['added']);
        self::assertCount(0, $result['removed']);
        self::assertSame('Pikachu', $result['added'][0]['cardName']);
        self::assertSame(4, $result['added'][0]['quantity']);
    }

    public function testDiffWithRemovedCards(): void
    {
        $oldVersion = $this->createVersion([
            ['Pikachu', 'BW1', '42', 4, 'pokemon'],
        ]);
        $newVersion = $this->createVersion([]);

        $result = $this->differ->diff($oldVersion, $newVersion);

        self::assertCount(0, $result['added']);
        self::assertCount(1, $result['removed']);
        self::assertSame('Pikachu', $result['removed'][0]['cardName']);
        self::assertSame(4, $result['removed'][0]['quantity']);
    }

    public function testDiffWithChangedQuantity(): void
    {
        $oldVersion = $this->createVersion([
            ['Pikachu', 'BW1', '42', 4, 'pokemon'],
        ]);
        $newVersion = $this->createVersion([
            ['Pikachu', 'BW1', '42', 2, 'pokemon'],
        ]);

        $result = $this->differ->diff($oldVersion, $newVersion);

        self::assertCount(0, $result['added']);
        self::assertCount(0, $result['removed']);
        self::assertCount(1, $result['changed']);
        self::assertSame(4, $result['changed'][0]['oldQuantity']);
        self::assertSame(2, $result['changed'][0]['newQuantity']);
    }

    public function testDiffWithMixedChanges(): void
    {
        $oldVersion = $this->createVersion([
            ['Pikachu', 'BW1', '42', 4, 'pokemon'],
            ['Professor Oak', 'BW1', '100', 2, 'trainer'],
            ['Fire Energy', 'BW1', '150', 8, 'energy'],
        ]);
        $newVersion = $this->createVersion([
            ['Pikachu', 'BW1', '42', 3, 'pokemon'],
            ['Raichu', 'BW1', '43', 2, 'pokemon'],
            ['Fire Energy', 'BW1', '150', 8, 'energy'],
        ]);

        $result = $this->differ->diff($oldVersion, $newVersion);

        self::assertCount(1, $result['added']);
        self::assertSame('Raichu', $result['added'][0]['cardName']);

        self::assertCount(1, $result['removed']);
        self::assertSame('Professor Oak', $result['removed'][0]['cardName']);

        self::assertCount(1, $result['changed']);
        self::assertSame('Pikachu', $result['changed'][0]['cardName']);

        self::assertCount(1, $result['unchanged']);
        self::assertSame('Fire Energy', $result['unchanged'][0]['cardName']);
    }

    public function testDiffWithEmptyVersions(): void
    {
        $oldVersion = $this->createVersion([]);
        $newVersion = $this->createVersion([]);

        $result = $this->differ->diff($oldVersion, $newVersion);

        self::assertCount(0, $result['added']);
        self::assertCount(0, $result['removed']);
        self::assertCount(0, $result['changed']);
        self::assertCount(0, $result['unchanged']);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: int, 4: string}> $cards
     */
    private function createVersion(array $cards): DeckVersion
    {
        $version = new DeckVersion();

        foreach ($cards as [$name, $setCode, $cardNumber, $quantity, $cardType]) {
            $card = new DeckCard();
            $card->setCardName($name);
            $card->setSetCode($setCode);
            $card->setCardNumber($cardNumber);
            $card->setQuantity($quantity);
            $card->setCardType($cardType);
            $version->addCard($card);
        }

        return $version;
    }
}

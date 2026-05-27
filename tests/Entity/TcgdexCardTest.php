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

namespace App\Tests\Entity;

use App\Entity\TcgdexCard;
use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class TcgdexCardTest extends TestCase
{
    public function testGetAttackDamagesEnReturnsParallelArrayWithGetAttackNamesEn(): void
    {
        $card = $this->makeCard();
        $card->setAttacks([
            ['name' => ['en' => 'Tackle'], 'damage' => 20, 'cost' => ['Colorless']],
            ['name' => ['en' => 'Hyper Beam'], 'damage' => 80, 'cost' => ['Colorless', 'Colorless', 'Colorless']],
        ]);

        self::assertSame(['Tackle', 'Hyper Beam'], $card->getAttackNamesEn());
        self::assertSame([20, 80], $card->getAttackDamagesEn());
    }

    public function testGetAttackDamagesEnPreservesAlignmentWhenAttackHasNoEnglishName(): void
    {
        // Skip rule must match getAttackNamesEn — both arrays drop the missing-en entry.
        $card = $this->makeCard();
        $card->setAttacks([
            ['name' => ['en' => 'Bite'], 'damage' => 30],
            ['name' => ['fr' => 'Étincelle'], 'damage' => 50], // no 'en' → must be skipped in both arrays
            ['name' => ['en' => 'Tail Whip'], 'damage' => null],
        ]);

        self::assertSame(['Bite', 'Tail Whip'], $card->getAttackNamesEn());
        self::assertSame([30, null], $card->getAttackDamagesEn());
    }

    public function testGetAttackDamagesEnHandlesStringDamageVerbatim(): void
    {
        // TCGdex emits string damage like "30+" or "10×" — must be passed through, not coerced.
        $card = $this->makeCard();
        $card->setAttacks([
            ['name' => ['en' => 'Thunderpunch'], 'damage' => '30+'],
            ['name' => ['en' => 'Fury Attack'], 'damage' => '10×'],
        ]);

        self::assertSame(['30+', '10×'], $card->getAttackDamagesEn());
    }

    public function testGetAttackDamagesEnFallsBackToNullWhenDamageMissingOrUnsupported(): void
    {
        $card = $this->makeCard();
        $card->setAttacks([
            ['name' => ['en' => 'Status Move']], // no damage key at all
            ['name' => ['en' => 'Weird'], 'damage' => ['nested' => true]], // not int|string
        ]);

        self::assertSame([null, null], $card->getAttackDamagesEn());
    }

    public function testGetAttackDamagesEnEmptyWhenNoAttacks(): void
    {
        $card = $this->makeCard();
        $card->setAttacks([]);

        self::assertSame([], $card->getAttackDamagesEn());
    }

    private function makeCard(): TcgdexCard
    {
        $serie = new TcgdexSerie('test-serie');
        $serie->setName(['en' => 'Test Serie']);
        $set = new TcgdexSet('test-set', $serie);
        $set->setName(['en' => 'Test Set']);

        return new TcgdexCard('test-001', $set, '001');
    }
}

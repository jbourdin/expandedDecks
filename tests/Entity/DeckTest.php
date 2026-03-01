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

use App\Entity\Deck;
use PHPUnit\Framework\TestCase;

class DeckTest extends TestCase
{
    public function testShortTagGeneratedOnPrePersist(): void
    {
        $deck = new Deck();
        self::assertSame('', $deck->getShortTag());

        $deck->onPrePersist();

        self::assertNotSame('', $deck->getShortTag());
        self::assertSame(6, \strlen($deck->getShortTag()));
    }

    public function testShortTagNotOverwrittenOnPrePersist(): void
    {
        $deck = new Deck();
        $deck->onPrePersist();
        $firstTag = $deck->getShortTag();

        $deck->onPrePersist();

        self::assertSame($firstTag, $deck->getShortTag());
    }

    public function testShortTagCharsetValid(): void
    {
        $deck = new Deck();
        $deck->onPrePersist();

        self::assertMatchesRegularExpression('/^[A-HJ-NP-Z0-9]{6}$/', $deck->getShortTag());
    }

    public function testGenerateShortTagLength(): void
    {
        // Generate multiple tags to check length consistency
        for ($i = 0; $i < 20; ++$i) {
            $deck = new Deck();
            $deck->onPrePersist();
            self::assertSame(6, \strlen($deck->getShortTag()));
        }
    }
}

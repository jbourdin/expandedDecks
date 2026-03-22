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

namespace App\Tests\Service\DeckList;

use App\Service\DeckList\CardmarketExpansionNameMapper;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.11 — Export deck list for Cardmarket wishlist
 */
class CardmarketExpansionNameMapperTest extends TestCase
{
    private CardmarketExpansionNameMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new CardmarketExpansionNameMapper();
    }

    public function testKnownSetCodeReturnsExpansionName(): void
    {
        self::assertSame('Brilliant Stars', $this->mapper->getExpansionName('BRS'));
        self::assertSame('Lost Origin', $this->mapper->getExpansionName('LOR'));
        self::assertSame('Scarlet & Violet', $this->mapper->getExpansionName('SVI'));
        self::assertSame('Black & White', $this->mapper->getExpansionName('BLW'));
    }

    public function testUnknownSetCodeReturnsNull(): void
    {
        self::assertNull($this->mapper->getExpansionName('UNKNOWN'));
        self::assertNull($this->mapper->getExpansionName(''));
    }

    public function testSetCodeIsCaseSensitive(): void
    {
        self::assertNull($this->mapper->getExpansionName('brs'));
        self::assertSame('Brilliant Stars', $this->mapper->getExpansionName('BRS'));
    }
}

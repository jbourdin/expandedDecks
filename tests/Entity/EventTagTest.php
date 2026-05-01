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

use App\Entity\EventTag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F3.12 — Event tags
 */
final class EventTagTest extends TestCase
{
    public function testSetNameTrimsAndDerivesSlug(): void
    {
        $tag = new EventTag();
        $tag->setName('  League Cup  ');

        self::assertSame('League Cup', $tag->getName());
        self::assertSame('league-cup', $tag->getSlug());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $tag = new EventTag();

        self::assertInstanceOf(\DateTimeImmutable::class, $tag->getCreatedAt());
    }

    public function testEventsCollectionStartsEmpty(): void
    {
        $tag = new EventTag();

        self::assertCount(0, $tag->getEvents());
    }

    #[DataProvider('slugifyProvider')]
    public function testSlugify(string $input, string $expected): void
    {
        self::assertSame($expected, EventTag::slugify($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function slugifyProvider(): iterable
    {
        yield 'simple word' => ['League', 'league'];
        yield 'multi word with spaces' => ['Regional Championship', 'regional-championship'];
        yield 'collapses repeated separators' => ['weekend / saturday', 'weekend-saturday'];
        yield 'trims leading and trailing dashes' => ['  --hello-- ', 'hello'];
        yield 'preserves accents via unicode-aware regex' => ['Été français', 'été-français'];
        yield 'all punctuation collapses to empty' => ['!!!???', ''];
        yield 'empty input is preserved' => ['', ''];
    }
}

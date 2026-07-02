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

namespace App\Tests\Form\DataTransformer;

use App\Form\DataTransformer\KeyValueTransformer;
use PHPUnit\Framework\TestCase;

class KeyValueTransformerTest extends TestCase
{
    public function testTransformMapsAssociativeArrayToPairs(): void
    {
        $transformer = new KeyValueTransformer();

        self::assertSame(
            [
                ['key' => 'brand_name', 'value' => 'Expanded Decks'],
                ['key' => 'tagline', 'value' => 'Play more'],
            ],
            $transformer->transform(['brand_name' => 'Expanded Decks', 'tagline' => 'Play more']),
        );
    }

    public function testTransformReturnsEmptyListForEmptyMap(): void
    {
        $transformer = new KeyValueTransformer();

        self::assertSame([], $transformer->transform([]));
    }

    public function testReverseTransformMapsPairsBackToAssociativeArray(): void
    {
        $transformer = new KeyValueTransformer();

        self::assertSame(
            ['brand_name' => 'Expanded Decks', 'tagline' => 'Play more'],
            $transformer->reverseTransform([
                ['key' => 'brand_name', 'value' => 'Expanded Decks'],
                ['key' => 'tagline', 'value' => 'Play more'],
            ]),
        );
    }

    public function testReverseTransformTrimsKeysAndSkipsBlankKeys(): void
    {
        $transformer = new KeyValueTransformer();

        self::assertSame(
            ['brand_name' => 'Expanded Decks'],
            $transformer->reverseTransform([
                ['key' => '  brand_name  ', 'value' => 'Expanded Decks'],
                ['key' => '   ', 'value' => 'dropped — blank key'],
            ]),
        );
    }

    public function testReverseTransformSkipsNonArrayEntriesAndMissingKeys(): void
    {
        $transformer = new KeyValueTransformer();

        self::assertSame(
            ['ok' => 'kept'],
            $transformer->reverseTransform([
                'not-an-array',
                ['value' => 'no key present'],
                ['key' => 'ok', 'value' => 'kept'],
            ]),
        );
    }

    public function testReverseTransformReturnsEmptyArrayWhenValueIsNotArray(): void
    {
        $transformer = new KeyValueTransformer();

        self::assertSame([], $transformer->reverseTransform(null));
    }
}

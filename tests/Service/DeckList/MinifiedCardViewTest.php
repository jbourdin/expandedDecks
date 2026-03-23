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

use App\Service\DeckList\MinifiedCardView;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.8 — Minified deck list export
 */
final class MinifiedCardViewTest extends TestCase
{
    public function testSerializeAndDeserializeRoundTrip(): void
    {
        $grouped = [
            'pokemon' => [
                new MinifiedCardView('Pikachu', 4, 'SVI', '25', 'pokemon', null, 'https://example.com/pikachu.png', 'Static', 'Thunder Shock'),
                new MinifiedCardView('Charizard', 2, 'OBF', '6', 'pokemon', null, 'https://example.com/charizard.png', 'Burning Energy', 'Fire Blast / Inferno'),
            ],
            'trainer' => [
                new MinifiedCardView('Professor\'s Research', 4, 'SVI', '189', 'trainer', 'supporter', 'https://example.com/research.png'),
            ],
            'energy' => [
                new MinifiedCardView('Fire Energy', 10, 'MEE', '2', 'energy', null, 'https://example.com/fire.png'),
            ],
        ];

        $json = MinifiedCardView::serializeGrouped($grouped);
        $deserialized = MinifiedCardView::deserializeGrouped($json);

        self::assertCount(3, $deserialized);
        self::assertArrayHasKey('pokemon', $deserialized);
        self::assertArrayHasKey('trainer', $deserialized);
        self::assertArrayHasKey('energy', $deserialized);

        self::assertCount(2, $deserialized['pokemon']);
        self::assertCount(1, $deserialized['trainer']);
        self::assertCount(1, $deserialized['energy']);

        $pikachu = $deserialized['pokemon'][0];
        self::assertSame('Pikachu', $pikachu->getCardName());
        self::assertSame(4, $pikachu->getQuantity());
        self::assertSame('SVI', $pikachu->getSetCode());
        self::assertSame('25', $pikachu->getCardNumber());
        self::assertSame('pokemon', $pikachu->getCardType());
        self::assertNull($pikachu->getTrainerSubtype());
        self::assertSame('https://example.com/pikachu.png', $pikachu->getImageUrl());
        self::assertSame('Static', $pikachu->getAbilityNames());
        self::assertSame('Thunder Shock', $pikachu->getAttackNames());

        $charizard = $deserialized['pokemon'][1];
        self::assertSame('Burning Energy', $charizard->getAbilityNames());
        self::assertSame('Fire Blast / Inferno', $charizard->getAttackNames());

        $research = $deserialized['trainer'][0];
        self::assertSame('Professor\'s Research', $research->getCardName());
        self::assertSame('supporter', $research->getTrainerSubtype());
    }

    public function testSerializeGroupedWithEmptyArray(): void
    {
        $json = MinifiedCardView::serializeGrouped([]);

        self::assertSame('[]', $json);

        $deserialized = MinifiedCardView::deserializeGrouped($json);

        self::assertSame([], $deserialized);
    }

    public function testSerializeGroupedPreservesNullFields(): void
    {
        $grouped = [
            'energy' => [
                new MinifiedCardView('Fire Energy', 10, 'MEE', '2', 'energy', null, null),
            ],
        ];

        $json = MinifiedCardView::serializeGrouped($grouped);
        $deserialized = MinifiedCardView::deserializeGrouped($json);

        $card = $deserialized['energy'][0];
        self::assertNull($card->getTrainerSubtype());
        self::assertNull($card->getImageUrl());
    }

    public function testDeserializeGroupedDefaultsAbilityAndAttackNames(): void
    {
        // Simulate JSON that was serialized without ability/attack keys
        $json = json_encode([
            'pokemon' => [
                [
                    'n' => 'Pikachu',
                    'q' => 2,
                    's' => 'SVI',
                    'c' => '25',
                    't' => 'pokemon',
                    'ts' => null,
                    'i' => null,
                    // 'ab' and 'at' intentionally omitted
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $deserialized = MinifiedCardView::deserializeGrouped($json);
        $card = $deserialized['pokemon'][0];

        self::assertSame('', $card->getAbilityNames());
        self::assertSame('', $card->getAttackNames());
    }

    public function testSerializeGroupedProducesValidJson(): void
    {
        $grouped = [
            'trainer' => [
                new MinifiedCardView('Boss\'s Orders', 2, 'PAL', '172', 'trainer', 'supporter', 'https://example.com/boss.png'),
            ],
        ];

        $json = MinifiedCardView::serializeGrouped($grouped);

        // Should not throw
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
    }

    public function testDeserializeGroupedWithInvalidJsonThrowsException(): void
    {
        $this->expectException(\JsonException::class);

        MinifiedCardView::deserializeGrouped('not valid json');
    }

    public function testSerializeGroupedDoesNotEscapeSlashes(): void
    {
        $grouped = [
            'pokemon' => [
                new MinifiedCardView('Pikachu', 1, 'SVI', '25', 'pokemon', null, 'https://example.com/images/pikachu.png'),
            ],
        ];

        $json = MinifiedCardView::serializeGrouped($grouped);

        self::assertStringNotContainsString('\\/', $json);
        self::assertStringContainsString('https://example.com/images/pikachu.png', $json);
    }

    public function testSerializeGroupedWithEmptyStringsForAbilityAndAttack(): void
    {
        $grouped = [
            'energy' => [
                new MinifiedCardView('Fire Energy', 10, 'MEE', '2', 'energy', null, null, '', ''),
            ],
        ];

        $json = MinifiedCardView::serializeGrouped($grouped);
        $deserialized = MinifiedCardView::deserializeGrouped($json);

        $card = $deserialized['energy'][0];
        self::assertSame('', $card->getAbilityNames());
        self::assertSame('', $card->getAttackNames());
    }

    public function testRoundTripPreservesMultipleGroupsWithMultipleCards(): void
    {
        $grouped = [
            'pokemon' => [
                new MinifiedCardView('Pikachu', 4, 'SVI', '25', 'pokemon', null, null, 'Ability1', 'Attack1'),
                new MinifiedCardView('Raichu', 2, 'SVI', '26', 'pokemon', null, null, 'Ability2', 'Attack2'),
                new MinifiedCardView('Eevee', 1, 'SVI', '133', 'pokemon', null, null, '', 'Quick Attack'),
            ],
            'trainer' => [
                new MinifiedCardView('Nest Ball', 4, 'SVI', '181', 'trainer', 'item', null),
                new MinifiedCardView('Choice Belt', 2, 'PAL', '176', 'trainer', 'tool', null),
            ],
        ];

        $json = MinifiedCardView::serializeGrouped($grouped);
        $deserialized = MinifiedCardView::deserializeGrouped($json);

        self::assertCount(3, $deserialized['pokemon']);
        self::assertCount(2, $deserialized['trainer']);

        // Verify ordering is preserved
        self::assertSame('Pikachu', $deserialized['pokemon'][0]->getCardName());
        self::assertSame('Raichu', $deserialized['pokemon'][1]->getCardName());
        self::assertSame('Eevee', $deserialized['pokemon'][2]->getCardName());
        self::assertSame('item', $deserialized['trainer'][0]->getTrainerSubtype());
        self::assertSame('tool', $deserialized['trainer'][1]->getTrainerSubtype());
    }
}

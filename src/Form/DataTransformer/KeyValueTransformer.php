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

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * Transforms a flat key-value map into an indexed list of {key, value} pairs
 * for use with CollectionType, and back.
 *
 * @implements DataTransformerInterface<array<string, string>, list<mixed>>
 */
class KeyValueTransformer implements DataTransformerInterface
{
    /**
     * Entity → Form: {brand_name: "X"} → [{key: "brand_name", value: "X"}].
     *
     * @param array<string, string> $value
     *
     * @return list<array{key: string, value: string}>
     */
    public function transform(mixed $value): array
    {
        $pairs = [];

        /** @var array<string, string> $value */
        foreach ($value as $key => $val) {
            $pairs[] = ['key' => (string) $key, 'value' => (string) $val];
        }

        return $pairs;
    }

    /**
     * Form → Entity: [{key: "brand_name", value: "X"}] → {brand_name: "X"}.
     *
     * @return array<string, string>
     */
    public function reverseTransform(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $pair) {
            if (!\is_array($pair)) {
                continue;
            }

            /** @var array<string, mixed> $pair */
            $key = \is_string($pair['key'] ?? null) ? trim($pair['key']) : '';
            $val = \is_string($pair['value'] ?? null) ? $pair['value'] : '';

            if ('' !== $key) {
                $map[$key] = $val;
            }
        }

        return $map;
    }
}

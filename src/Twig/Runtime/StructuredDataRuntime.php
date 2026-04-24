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

namespace App\Twig\Runtime;

use App\Service\Seo\StructuredDataBuilder;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for rendering JSON-LD structured data.
 *
 * Wraps the StructuredDataBuilder and outputs JSON-encoded strings
 * ready for embedding in <script type="application/ld+json"> blocks.
 *
 * @see docs/features.md F18.27 — JSON-LD structured data
 */
class StructuredDataRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly StructuredDataBuilder $builder,
    ) {
    }

    public function getBuilder(): StructuredDataBuilder
    {
        return $this->builder;
    }

    /**
     * Encode a structured data array as a JSON-LD string.
     *
     * @param array<string, mixed> $data
     */
    public function jsonLd(array $data): string
    {
        $json = json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);

        return false !== $json ? $json : '{}';
    }
}

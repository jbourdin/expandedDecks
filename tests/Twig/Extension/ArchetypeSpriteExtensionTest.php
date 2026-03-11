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

namespace App\Tests\Twig\Extension;

use App\Twig\Extension\ArchetypeSpriteExtension;
use App\Twig\Runtime\ArchetypeSpriteRuntime;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F2.12 — Archetype sprite pictograms
 */
class ArchetypeSpriteExtensionTest extends TestCase
{
    public function testRegistersArchetypeSpritesFunction(): void
    {
        $extension = new ArchetypeSpriteExtension();
        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('archetype_sprites', $functions[0]->getName());
    }

    public function testFunctionCallsRuntimeRenderSprites(): void
    {
        $extension = new ArchetypeSpriteExtension();
        $functions = $extension->getFunctions();
        $callable = $functions[0]->getCallable();

        self::assertIsArray($callable);
        self::assertSame(ArchetypeSpriteRuntime::class, $callable[0]);
        self::assertSame('renderSprites', $callable[1]);
    }

    public function testFunctionUsesLazyRuntime(): void
    {
        $extension = new ArchetypeSpriteExtension();
        $functions = $extension->getFunctions();
        $callable = $functions[0]->getCallable();

        // Lazy runtime: callable is [ClassName, method] (not an instance)
        self::assertIsArray($callable);
        self::assertIsString($callable[0]);
    }
}

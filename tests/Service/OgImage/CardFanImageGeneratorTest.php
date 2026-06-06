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

namespace App\Tests\Service\OgImage;

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Service\CardImageResolver;
use App\Service\OgImage\CardFanImageGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @see docs/features.md F18.32 — Card-fan OG image builder
 */
final class CardFanImageGeneratorTest extends TestCase
{
    public function testGenerateThrowsOnEmptyPrintings(): void
    {
        $generator = new CardFanImageGenerator($this->createStub(CardImageResolver::class), new NullLogger());

        $this->expectException(\InvalidArgumentException::class);
        $generator->generate([]);
    }

    public function testGenerateProducesOgSizedPng(): void
    {
        $generator = $this->createGenerator(self::createTinyPng());

        $imageData = $generator->generate([$this->createPrinting('Pikachu'), $this->createPrinting('Charizard')]);

        self::assertTrue(str_starts_with($imageData, "\x89PNG"));
        $size = getimagesizefromstring($imageData);
        self::assertNotFalse($size);
        self::assertSame(1200, $size[0]);
        self::assertSame(630, $size[1]);
    }

    public function testGenerateKeepsCanvasSizeForAllSupportedCardCounts(): void
    {
        $generator = $this->createGenerator(self::createTinyPng());

        foreach ([2, 4, 6] as $cardCount) {
            $printings = [];
            for ($index = 0; $index < $cardCount; ++$index) {
                $printings[] = $this->createPrinting('Card '.$index);
            }

            $size = getimagesizefromstring($generator->generate($printings));
            self::assertNotFalse($size);
            self::assertSame(1200, $size[0], 'Width drifted for '.$cardCount.' cards.');
            self::assertSame(630, $size[1], 'Height drifted for '.$cardCount.' cards.');
        }
    }

    public function testGenerateDrawsPlaceholderWhenImageDownloadFails(): void
    {
        $generator = $this->createGenerator(false);

        $imageData = $generator->generate([$this->createPrinting('Pikachu'), $this->createPrinting('Charizard')]);

        $size = getimagesizefromstring($imageData);
        self::assertNotFalse($size);
        self::assertSame(1200, $size[0]);
        self::assertSame(630, $size[1]);
    }

    private function createGenerator(string|false $downloadResult): CardFanImageGenerator
    {
        $imageResolver = $this->createStub(CardImageResolver::class);
        $imageResolver->method('downloadImage')->willReturn($downloadResult);

        return new CardFanImageGenerator($imageResolver, new NullLogger());
    }

    private function createPrinting(string $cardName): CardPrinting
    {
        $identity = new CardIdentity();
        $identity->setName($cardName);

        $printing = new CardPrinting();
        $printing->setCardIdentity($identity);

        return $printing;
    }

    private static function createTinyPng(): string
    {
        $image = imagecreatetruecolor(10, 14);
        \assert(false !== $image);

        ob_start();
        imagepng($image);
        $data = ob_get_clean();
        \assert(false !== $data);

        return $data;
    }
}

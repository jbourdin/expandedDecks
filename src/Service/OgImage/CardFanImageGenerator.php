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

namespace App\Service\OgImage;

use App\Entity\CardPrinting;
use App\Service\CardImageResolver;
use Psr\Log\LoggerInterface;

/**
 * Composites a few card images into an OG-friendly "held hand" spread.
 *
 * Cards are laid out as a flat overlapping fan (each card partially covering
 * the previous one, rightmost fully visible) centered on a transparent
 * 1200×630 canvas. The transparent background is deliberate: social platforms
 * composite transparent OG images on their own backdrop, so the floating-card
 * look adapts per platform. Switch BACKGROUND_ALPHA/colors here if it ever
 * reads poorly.
 *
 * @see docs/features.md F18.32 — Card-fan OG image builder
 * @see docs/technicalities/og_image_builder.md
 */
class CardFanImageGenerator
{
    /** OG image canvas size (1.91:1, the ratio recommended by Open Graph consumers). */
    private const int CANVAS_WIDTH = 1200;
    private const int CANVAS_HEIGHT = 630;

    /** Card aspect ratio source values — matches the mosaic tile ratio (245×342). */
    private const int CARD_SOURCE_WIDTH = 245;
    private const int CARD_SOURCE_HEIGHT = 342;

    /** Rendered card height, leaving ~35px vertical margin top and bottom. */
    private const int CARD_HEIGHT = 560;

    /** Desired horizontal offset between cards, as a fraction of card width. */
    private const float DESIRED_STEP_RATIO = 0.45;

    /** Maximum total spread width, keeping a horizontal margin within the canvas. */
    private const int MAX_SPREAD_WIDTH = 1100;

    public function __construct(
        private readonly CardImageResolver $cardImageResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Render the card fan and return the PNG bytes.
     *
     * @param list<CardPrinting> $printings cards to spread, drawn left to right
     *                                      (the last printing ends up fully visible on top)
     */
    public function generate(array $printings): string
    {
        if ([] === $printings) {
            throw new \InvalidArgumentException('At least one card printing is required.');
        }

        $cardHeight = self::CARD_HEIGHT;
        $cardWidth = (int) round($cardHeight * self::CARD_SOURCE_WIDTH / self::CARD_SOURCE_HEIGHT);

        $cardCount = \count($printings);
        $desiredStep = (int) round($cardWidth * self::DESIRED_STEP_RATIO);
        $step = $cardCount > 1
            ? min($desiredStep, (int) floor((self::MAX_SPREAD_WIDTH - $cardWidth) / ($cardCount - 1)))
            : 0;

        $spreadWidth = $cardWidth + ($cardCount - 1) * $step;
        $offsetX = (int) round((self::CANVAS_WIDTH - $spreadWidth) / 2);
        $offsetY = (int) round((self::CANVAS_HEIGHT - $cardHeight) / 2);

        $canvas = $this->createCanvas(self::CANVAS_WIDTH, self::CANVAS_HEIGHT);

        foreach ($printings as $index => $printing) {
            $x = $offsetX + $index * $step;
            $this->drawCard($canvas, $printing, $x, $offsetY, $cardWidth, $cardHeight);
        }

        ob_start();
        imagepng($canvas);
        $imageData = ob_get_clean();

        if (false === $imageData || '' === $imageData) {
            throw new \RuntimeException('Failed to render card fan image to PNG.');
        }

        return $imageData;
    }

    /**
     * Create the canvas with a transparent background.
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function createCanvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if (false === $canvas) {
            throw new \RuntimeException('Failed to create GD canvas.');
        }

        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);

        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);

        if (false === $transparent) {
            throw new \RuntimeException('Failed to allocate transparent color.');
        }

        imagefill($canvas, 0, 0, $transparent);
        imagealphablending($canvas, true);

        return $canvas;
    }

    private function drawCard(\GdImage $canvas, CardPrinting $printing, int $x, int $y, int $width, int $height): void
    {
        $cardName = $printing->getCardIdentity()->getName();
        $imageData = $this->cardImageResolver->downloadImage($printing, 'high');

        $source = false !== $imageData ? @imagecreatefromstring($imageData) : false;

        if (false === $source) {
            $this->logger->warning('Failed to load card image for "{card}", drawing placeholder.', [
                'card' => $cardName,
            ]);
            $this->drawPlaceholder($canvas, $cardName, $x, $y, $width, $height);

            return;
        }

        imagecopyresampled(
            $canvas,
            $source,
            $x,
            $y,
            0,
            0,
            $width,
            $height,
            imagesx($source),
            imagesy($source),
        );
    }

    /**
     * Draw a neutral placeholder so card positions stay stable when an image fails.
     */
    private function drawPlaceholder(\GdImage $canvas, string $cardName, int $x, int $y, int $width, int $height): void
    {
        $grey = imagecolorallocate($canvas, 0xCC, 0xCC, 0xCC);
        $textColor = imagecolorallocate($canvas, 0x66, 0x66, 0x66);

        if (false === $grey || false === $textColor) {
            return;
        }

        imagefilledrectangle($canvas, $x, $y, $x + $width - 1, $y + $height - 1, $grey);

        // Draw card name centered (truncate if too long)
        $fontSize = 4; // GD built-in font (8x16 px)
        $maxChars = (int) floor($width / (imagefontwidth($fontSize) + 1));
        $displayName = mb_substr($cardName, 0, $maxChars);
        $textWidth = \strlen($displayName) * imagefontwidth($fontSize);
        $textX = $x + (int) (($width - $textWidth) / 2);
        $textY = $y + (int) (($height - imagefontheight($fontSize)) / 2);

        imagestring($canvas, $fontSize, $textX, $textY, $displayName, $textColor);
    }
}

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

namespace App\Service\Mosaic;

use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Service\CardImageResolver;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

/**
 * Generates a composite mosaic image of a deck's card list.
 *
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 * @see docs/technicalities/mosaic.md
 */
class MosaicGenerator
{
    private const int CARDS_PER_ROW = 8;
    private const int CARD_WIDTH = 245;
    private const int CARD_HEIGHT = 342;
    private const int PADDING = 12;
    private const int BADGE_SIZE = 50;
    private const int BADGE_MARGIN_BOTTOM = 14;
    private const int SHADOW_OFFSET_X = 3;
    private const int SHADOW_OFFSET_Y = 3;

    /** Background tile dimensions matching app.scss (96px x 80px). */
    private const int TILE_WIDTH = 96;
    private const int TILE_HEIGHT = 80;

    public function __construct(
        private readonly FilesystemOperator $mosaicStorage,
        private readonly LoggerInterface $logger,
        private readonly CardImageResolver $cardImageResolver,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Render the mosaic image for a DeckVersion and store it via Flysystem.
     *
     * @param string                  $variant           variant suffix for storage path (e.g. 'minified')
     * @param array<int, string>|null $imageUrlOverrides map of DeckCard::id → image URL to override
     *
     * @return string the storage path (relative to the Flysystem root)
     */
    public function generate(DeckVersion $version, string $variant = '', ?array $imageUrlOverrides = null): string
    {
        $cards = $this->sortCards($version);

        if ([] === $cards) {
            $this->logger->info('DeckVersion #{id} has no cards, skipping mosaic generation.', [
                'id' => $version->getId(),
            ]);

            return '';
        }

        $totalCards = \count($cards);
        $rows = (int) ceil($totalCards / self::CARDS_PER_ROW);
        $columnsInLastRow = $totalCards % self::CARDS_PER_ROW ?: self::CARDS_PER_ROW;

        /** @var int<1, max> $canvasWidth */
        $canvasWidth = self::CARDS_PER_ROW * self::CARD_WIDTH + (self::CARDS_PER_ROW + 1) * self::PADDING;
        /** @var int<1, max> $canvasHeight */
        $canvasHeight = $rows * self::CARD_HEIGHT + ($rows + 1) * self::PADDING;

        $canvas = $this->createCanvas($canvasWidth, $canvasHeight);

        foreach ($cards as $index => $card) {
            $row = (int) floor($index / self::CARDS_PER_ROW);
            $column = $index % self::CARDS_PER_ROW;
            $x = self::PADDING + $column * (self::CARD_WIDTH + self::PADDING);
            $y = self::PADDING + $row * (self::CARD_HEIGHT + self::PADDING);

            $this->drawCard($canvas, $card, $x, $y, $imageUrlOverrides);
        }

        // Center the last row if it has fewer cards than CARDS_PER_ROW
        if ($columnsInLastRow < self::CARDS_PER_ROW && $rows > 1) {
            $this->centerLastRow($canvas, $cards, $rows, $columnsInLastRow, $canvasWidth, $imageUrlOverrides);
        }

        $storagePath = $this->buildStoragePath($version, $variant);

        ob_start();
        imagepng($canvas);
        $imageData = ob_get_clean();

        if (false === $imageData || '' === $imageData) {
            throw new \RuntimeException('Failed to render mosaic image to PNG.');
        }

        $this->mosaicStorage->write($storagePath, $imageData);

        $this->logger->info('Mosaic generated for DeckVersion #{id} ({cards} cards, {path}).', [
            'id' => $version->getId(),
            'cards' => $totalCards,
            'path' => $storagePath,
        ]);

        return $storagePath;
    }

    /**
     * Render a mosaic from pre-built MosaicTile DTOs (used for minified mosaic with merged cards).
     *
     * @param list<MosaicTile> $tiles
     *
     * @return string the storage path
     */
    public function generateFromTiles(DeckVersion $version, array $tiles, string $variant = ''): string
    {
        if ([] === $tiles) {
            $this->logger->info('DeckVersion #{id} has no tiles, skipping minified mosaic generation.', [
                'id' => $version->getId(),
            ]);

            return '';
        }

        $totalCards = \count($tiles);
        $rows = (int) ceil($totalCards / self::CARDS_PER_ROW);
        $columnsInLastRow = $totalCards % self::CARDS_PER_ROW ?: self::CARDS_PER_ROW;

        /** @var int<1, max> $canvasWidth */
        $canvasWidth = self::CARDS_PER_ROW * self::CARD_WIDTH + (self::CARDS_PER_ROW + 1) * self::PADDING;
        /** @var int<1, max> $canvasHeight */
        $canvasHeight = $rows * self::CARD_HEIGHT + ($rows + 1) * self::PADDING;

        $canvas = $this->createCanvas($canvasWidth, $canvasHeight);

        foreach ($tiles as $index => $tile) {
            $row = (int) floor($index / self::CARDS_PER_ROW);
            $column = $index % self::CARDS_PER_ROW;
            $x = self::PADDING + $column * (self::CARD_WIDTH + self::PADDING);
            $y = self::PADDING + $row * (self::CARD_HEIGHT + self::PADDING);

            $this->drawTile($canvas, $tile, $x, $y);
        }

        // Center the last row if incomplete
        if ($columnsInLastRow < self::CARDS_PER_ROW && $rows > 1) {
            $this->centerLastRowTiles($canvas, $tiles, $rows, $columnsInLastRow, $canvasWidth);
        }

        $storagePath = $this->buildStoragePath($version, $variant);

        ob_start();
        imagepng($canvas);
        $imageData = ob_get_clean();

        if (false === $imageData || '' === $imageData) {
            throw new \RuntimeException('Failed to render mosaic image to PNG.');
        }

        $this->mosaicStorage->write($storagePath, $imageData);

        $this->logger->info('Mosaic (tiles) generated for DeckVersion #{id} ({tiles} tiles, {path}).', [
            'id' => $version->getId(),
            'tiles' => $totalCards,
            'path' => $storagePath,
        ]);

        return $storagePath;
    }

    private function drawTile(\GdImage $canvas, MosaicTile $tile, int $x, int $y): void
    {
        $imageData = false;

        // When a CardPrinting is available, use the fallback-aware resolver
        // which also persists working URLs on the printing entity.
        if (null !== $tile->printing) {
            $imageData = $this->cardImageResolver->downloadImage($tile->printing, 'low');
        } elseif (null !== $tile->imageUrl && '' !== $tile->imageUrl) {
            $imageData = @file_get_contents($tile->imageUrl);
        }

        if (false !== $imageData) {
            $this->drawCardImageFromData($canvas, $imageData, $tile->cardName, $x, $y);
        } else {
            $this->drawPlaceholder($canvas, $tile->cardName, $x, $y);
        }

        $this->drawQuantityBadge($canvas, $tile->quantity, $x, $y);
    }

    /**
     * @param list<MosaicTile> $tiles
     */
    private function centerLastRowTiles(\GdImage $canvas, array $tiles, int $rows, int $columnsInLastRow, int $canvasWidth): void
    {
        $lastRowWidth = $columnsInLastRow * self::CARD_WIDTH + ($columnsInLastRow - 1) * self::PADDING;
        $offsetX = (int) (($canvasWidth - $lastRowWidth) / 2);
        $lastRowY = self::PADDING + ($rows - 1) * (self::CARD_HEIGHT + self::PADDING);

        $backgroundColor = imagecolorallocate($canvas, 0xF5, 0xF5, 0xF7);

        if (false !== $backgroundColor) {
            imagefilledrectangle($canvas, 0, $lastRowY, $canvasWidth - 1, $lastRowY + self::CARD_HEIGHT + self::PADDING - 1, $backgroundColor);
        }

        $tilePath = $this->projectDir.'/assets/images/bg_fairy_quincunx.png';

        if (file_exists($tilePath)) {
            $bgTile = imagecreatefrompng($tilePath);

            if (false !== $bgTile) {
                for ($tileY = $lastRowY; $tileY < $lastRowY + self::CARD_HEIGHT + self::PADDING; $tileY += self::TILE_HEIGHT) {
                    for ($tileX = 0; $tileX < $canvasWidth; $tileX += self::TILE_WIDTH) {
                        $copyWidth = min(self::TILE_WIDTH, $canvasWidth - $tileX);
                        $copyHeight = min(self::TILE_HEIGHT, $lastRowY + self::CARD_HEIGHT + self::PADDING - $tileY);

                        if ($copyHeight > 0 && $copyWidth > 0) {
                            $sourceTileY = $tileY % self::TILE_HEIGHT;
                            imagecopy($canvas, $bgTile, $tileX, $tileY, $tileX % self::TILE_WIDTH, $sourceTileY, $copyWidth, min($copyHeight, self::TILE_HEIGHT - $sourceTileY));
                        }
                    }
                }
            }
        }

        $lastRowStartIndex = (int) (($rows - 1) * self::CARDS_PER_ROW);

        for ($i = 0; $i < $columnsInLastRow; ++$i) {
            $mosaicTile = $tiles[$lastRowStartIndex + $i];
            $x = $offsetX + $i * (self::CARD_WIDTH + self::PADDING);
            $this->drawTile($canvas, $mosaicTile, $x, $lastRowY);
        }
    }

    /**
     * Sort cards following the spec: Pokemon -> Trainer (supporter, item, tool, stadium) -> Energy.
     * Within each subgroup: quantity descending, then name ascending.
     *
     * @return list<DeckCard>
     */
    private function sortCards(DeckVersion $version): array
    {
        $typeOrder = ['pokemon' => 0, 'trainer' => 1, 'energy' => 2];
        $trainerSubtypeOrder = ['supporter' => 0, 'item' => 1, 'tool' => 2, 'stadium' => 3];

        $cards = $version->getCards()->toArray();

        usort($cards, static function (DeckCard $cardA, DeckCard $cardB) use ($typeOrder, $trainerSubtypeOrder): int {
            $typeA = $typeOrder[$cardA->getCardType()] ?? 3;
            $typeB = $typeOrder[$cardB->getCardType()] ?? 3;

            if ($typeA !== $typeB) {
                return $typeA <=> $typeB;
            }

            // Within trainers, sort by subtype: supporter -> item -> tool -> stadium
            if ('trainer' === $cardA->getCardType()) {
                $subtypeA = $trainerSubtypeOrder[strtolower((string) $cardA->getTrainerSubtype())] ?? 4;
                $subtypeB = $trainerSubtypeOrder[strtolower((string) $cardB->getTrainerSubtype())] ?? 4;

                if ($subtypeA !== $subtypeB) {
                    return $subtypeA <=> $subtypeB;
                }
            }

            if ($cardA->getQuantity() !== $cardB->getQuantity()) {
                return $cardB->getQuantity() <=> $cardA->getQuantity();
            }

            return $cardA->getCardName() <=> $cardB->getCardName();
        });

        return $cards;
    }

    /**
     * Create the canvas with the site background texture tiled.
     */
    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function createCanvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if (false === $canvas) {
            throw new \RuntimeException('Failed to create GD canvas.');
        }

        // Fill with site background color #f5f5f7
        $backgroundColor = imagecolorallocate($canvas, 0xF5, 0xF5, 0xF7);

        if (false === $backgroundColor) {
            throw new \RuntimeException('Failed to allocate background color.');
        }

        imagefill($canvas, 0, 0, $backgroundColor);

        // Tile the background texture
        $tilePath = $this->projectDir.'/assets/images/bg_fairy_quincunx.png';

        if (file_exists($tilePath)) {
            $tile = imagecreatefrompng($tilePath);

            if (false !== $tile) {
                for ($tileY = 0; $tileY < $height; $tileY += self::TILE_HEIGHT) {
                    for ($tileX = 0; $tileX < $width; $tileX += self::TILE_WIDTH) {
                        $copyWidth = min(self::TILE_WIDTH, $width - $tileX);
                        $copyHeight = min(self::TILE_HEIGHT, $height - $tileY);
                        imagecopy($canvas, $tile, $tileX, $tileY, 0, 0, $copyWidth, $copyHeight);
                    }
                }
            }
        }

        return $canvas;
    }

    /**
     * @param array<int, string>|null $imageUrlOverrides
     */
    private function drawCard(\GdImage $canvas, DeckCard $card, int $x, int $y, ?array $imageUrlOverrides = null): void
    {
        $cardId = $card->getId();
        $printing = $card->getCardPrinting();

        // Use override URL if provided, otherwise try fallback-aware download via CardImageResolver
        if (null !== $imageUrlOverrides && null !== $cardId && isset($imageUrlOverrides[$cardId])) {
            $imageData = @file_get_contents($imageUrlOverrides[$cardId]);
        } elseif (null !== $printing) {
            $imageData = $this->cardImageResolver->downloadImage($printing, 'low');
        } else {
            $imageData = false;
        }

        if (false !== $imageData) {
            $this->drawCardImageFromData($canvas, $imageData, $card->getCardName(), $x, $y);
        } else {
            $this->drawPlaceholder($canvas, $card->getCardName(), $x, $y);
        }

        $this->drawQuantityBadge($canvas, $card->getQuantity(), $x, $y);
    }

    private function drawCardImageFromData(\GdImage $canvas, string $imageData, string $cardName, int $x, int $y): void
    {
        $source = @imagecreatefromstring($imageData);

        if (false === $source) {
            $this->logger->warning('Failed to decode card image for "{card}".', [
                'card' => $cardName,
            ]);
            $this->drawPlaceholder($canvas, $cardName, $x, $y);

            return;
        }

        imagecopyresampled(
            $canvas,
            $source,
            $x,
            $y,
            0,
            0,
            self::CARD_WIDTH,
            self::CARD_HEIGHT,
            imagesx($source),
            imagesy($source),
        );
    }

    private function drawPlaceholder(\GdImage $canvas, string $cardName, int $x, int $y): void
    {
        $grey = imagecolorallocate($canvas, 0xCC, 0xCC, 0xCC);
        $textColor = imagecolorallocate($canvas, 0x66, 0x66, 0x66);

        if (false === $grey || false === $textColor) {
            return;
        }

        imagefilledrectangle($canvas, $x, $y, $x + self::CARD_WIDTH - 1, $y + self::CARD_HEIGHT - 1, $grey);

        // Draw card name centered (truncate if too long)
        $fontSize = 4; // GD built-in font (8x16 px)
        $maxChars = (int) floor(self::CARD_WIDTH / (imagefontwidth($fontSize) + 1));
        $displayName = mb_substr($cardName, 0, $maxChars);
        $textWidth = \strlen($displayName) * imagefontwidth($fontSize);
        $textX = $x + (int) ((self::CARD_WIDTH - $textWidth) / 2);
        $textY = $y + (int) ((self::CARD_HEIGHT - imagefontheight($fontSize)) / 2);

        imagestring($canvas, $fontSize, $textX, $textY, $displayName, $textColor);
    }

    private function drawQuantityBadge(\GdImage $canvas, int $quantity, int $cardX, int $cardY): void
    {
        $centerX = $cardX + (int) (self::CARD_WIDTH / 2);
        $centerY = $cardY + self::CARD_HEIGHT - self::BADGE_MARGIN_BOTTOM - (int) (self::BADGE_SIZE / 2);

        // Colors
        $shadowColor = imagecolorallocate($canvas, 0x66, 0x11, 0x16); // darker red for shadow
        $badgeColor = imagecolorallocate($canvas, 0xCC, 0x29, 0x36); // $ed-red
        $white = imagecolorallocate($canvas, 0xFF, 0xFF, 0xFF);

        if (false === $shadowColor || false === $badgeColor || false === $white) {
            return;
        }

        $radius = (int) (self::BADGE_SIZE / 2);

        // Draw shadow hexagon (offset bottom-right, layered under)
        $shadowPoints = [];

        for ($i = 0; $i < 6; ++$i) {
            $angle = \M_PI / 6 + $i * \M_PI / 3;
            $shadowPoints[] = (int) round($centerX + self::SHADOW_OFFSET_X + $radius * cos($angle));
            $shadowPoints[] = (int) round($centerY + self::SHADOW_OFFSET_Y + $radius * sin($angle));
        }

        imagefilledpolygon($canvas, $shadowPoints, $shadowColor);

        // Draw main hexagon
        $points = [];

        for ($i = 0; $i < 6; ++$i) {
            $angle = \M_PI / 6 + $i * \M_PI / 3;
            $points[] = (int) round($centerX + $radius * cos($angle));
            $points[] = (int) round($centerY + $radius * sin($angle));
        }

        imagefilledpolygon($canvas, $points, $badgeColor);

        // Draw quantity text centered in badge
        $text = (string) $quantity;
        $fontSize = 5; // GD built-in font (9x15 px)
        $textWidth = \strlen($text) * imagefontwidth($fontSize);
        $textHeight = imagefontheight($fontSize);
        $textX = $centerX - (int) ($textWidth / 2);
        $textY = $centerY - (int) ($textHeight / 2);

        imagestring($canvas, $fontSize, $textX, $textY, $text, $white);
    }

    /**
     * @param list<DeckCard>          $cards
     * @param array<int, string>|null $imageUrlOverrides
     */
    private function centerLastRow(\GdImage $canvas, array $cards, int $rows, int $columnsInLastRow, int $canvasWidth, ?array $imageUrlOverrides = null): void
    {
        $lastRowWidth = $columnsInLastRow * self::CARD_WIDTH + ($columnsInLastRow - 1) * self::PADDING;
        $offsetX = (int) (($canvasWidth - $lastRowWidth) / 2);
        $lastRowY = self::PADDING + ($rows - 1) * (self::CARD_HEIGHT + self::PADDING);

        // Clear last row area first (redraw background)
        $backgroundColor = imagecolorallocate($canvas, 0xF5, 0xF5, 0xF7);

        if (false !== $backgroundColor) {
            imagefilledrectangle($canvas, 0, $lastRowY, $canvasWidth - 1, $lastRowY + self::CARD_HEIGHT + self::PADDING - 1, $backgroundColor);
        }

        // Re-tile background for last row
        $tilePath = $this->projectDir.'/assets/images/bg_fairy_quincunx.png';

        if (file_exists($tilePath)) {
            $tile = imagecreatefrompng($tilePath);

            if (false !== $tile) {
                for ($tileY = $lastRowY; $tileY < $lastRowY + self::CARD_HEIGHT + self::PADDING; $tileY += self::TILE_HEIGHT) {
                    for ($tileX = 0; $tileX < $canvasWidth; $tileX += self::TILE_WIDTH) {
                        $copyWidth = min(self::TILE_WIDTH, $canvasWidth - $tileX);
                        $copyHeight = min(self::TILE_HEIGHT, $lastRowY + self::CARD_HEIGHT + self::PADDING - $tileY);

                        if ($copyHeight > 0 && $copyWidth > 0) {
                            // Use tile offset to maintain seamless pattern
                            $sourceTileY = $tileY % self::TILE_HEIGHT;
                            imagecopy($canvas, $tile, $tileX, $tileY, $tileX % self::TILE_WIDTH, $sourceTileY, $copyWidth, min($copyHeight, self::TILE_HEIGHT - $sourceTileY));
                        }
                    }
                }
            }
        }

        // Redraw cards centered
        $lastRowStartIndex = (int) (($rows - 1) * self::CARDS_PER_ROW);

        for ($i = 0; $i < $columnsInLastRow; ++$i) {
            $card = $cards[$lastRowStartIndex + $i];
            $x = $offsetX + $i * (self::CARD_WIDTH + self::PADDING);
            $this->drawCard($canvas, $card, $x, $lastRowY, $imageUrlOverrides);
        }
    }

    private function buildStoragePath(DeckVersion $version, string $variant = ''): string
    {
        $deckId = $version->getDeck()->getId();
        $versionId = $version->getId();
        $suffix = '' !== $variant ? '_'.$variant : '';

        return \sprintf('mosaic/%d/%d%s.png', $deckId, $versionId, $suffix);
    }
}

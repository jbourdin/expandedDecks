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

namespace App\Service\Label;

use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Generates a printable PDF label card for a deck.
 *
 * The label is TCG card-sized (63.5 × 88.9 mm) and contains a QR code
 * linking to the deck page, the deck name, owner name, archetype sprites,
 * and the short tag identifier.
 *
 * @see docs/features.md F5.7 — PDF label card (home printing)
 * @see docs/technicalities/pdf_label.md
 */
class PdfLabelGenerator
{
    private const int QR_CODE_SIZE_PX = 300;

    public function __construct(
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Generate a PDF label for the given deck.
     *
     * @return string the raw PDF binary content
     */
    public function generate(Deck $deck): string
    {
        $deckUrl = $this->urlGenerator->generate(
            'app_deck_show',
            ['short_tag' => $deck->getShortTag()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $qrCodeDataUri = $this->generateQrCode($deckUrl);
        $spriteDataUris = $this->buildSpriteDataUris($deck);

        // Extract base URL (scheme + host) for the footer
        $parsed = parse_url($deckUrl);
        $baseUrl = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');

        $html = $this->twig->render('label/pdf_label.html.twig', [
            'deck' => $deck,
            'qrCodeDataUri' => $qrCodeDataUri,
            'spriteDataUris' => $spriteDataUris,
            'baseUrl' => $baseUrl,
        ]);

        return $this->renderPdf($html);
    }

    /**
     * Generate a foldable PDF label: front (label) + back (deck list).
     *
     * Two card-sized panels side by side on landscape A4 (book layout).
     * Left = deck list, right = label. Fold along the center like a book
     * for a double-sided sleeve insert with both sides right-side up.
     *
     * @see docs/features.md F5.7 — PDF label card (home printing)
     *
     * @return string the raw PDF binary content
     */
    public function generateFoldable(Deck $deck): string
    {
        $currentVersion = $deck->getCurrentVersion();

        $deckUrl = $this->urlGenerator->generate(
            'app_deck_show',
            ['short_tag' => $deck->getShortTag()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $qrCodeDataUri = $this->generateQrCode($deckUrl);
        $spriteDataUris = $this->buildSpriteDataUris($deck);

        $parsed = parse_url($deckUrl);
        $baseUrl = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');

        $groupedCards = null !== $currentVersion
            ? $this->groupCards($currentVersion)
            : [];

        // Count total card lines to compute font size
        $totalLines = 0;
        foreach ($groupedCards as $cards) {
            $totalLines += \count($cards);
        }

        // Compute font size to fit: ~83mm usable height, footer ~2mm, section padding ~1mm per section
        // Available height ≈ 80mm. Line height = font_size × 1.4 (in pt→mm: 1pt ≈ 0.353mm)
        $sectionPaddingMm = \count($groupedCards) * 1.0;
        $availableHeightMm = 80.0 - $sectionPaddingMm;
        $lineHeightFactor = 1.4;
        $ptToMm = 0.353;

        if ($totalLines > 0) {
            $maxFontSizePt = $availableHeightMm / ($totalLines * $lineHeightFactor * $ptToMm);
            $decklistFontSize = min(7.0, max(4.0, round($maxFontSizePt, 1)));
        } else {
            $decklistFontSize = 6.0;
        }

        $html = $this->twig->render('label/pdf_label_foldable.html.twig', [
            'deck' => $deck,
            'qrCodeDataUri' => $qrCodeDataUri,
            'spriteDataUris' => $spriteDataUris,
            'baseUrl' => $baseUrl,
            'groupedCards' => $groupedCards,
            'decklistFontSize' => $decklistFontSize,
        ]);

        return $this->renderPdf($html, 'landscape');
    }

    /**
     * Group and sort deck cards by detailed type for compact list rendering.
     *
     * Trainers are split by subtype (supporter, item, tool, stadium).
     * Order: pokemon → supporter → item → tool → stadium → energy.
     *
     * @return array<string, list<DeckCard>>
     */
    private function groupCards(DeckVersion $version): array
    {
        $grouped = [];

        foreach ($version->getCards() as $card) {
            if ('trainer' === $card->getCardType() && null !== $card->getTrainerSubtype()) {
                $grouped[$card->getTrainerSubtype()][] = $card;
            } else {
                $grouped[$card->getCardType()][] = $card;
            }
        }

        $sortFn = static function (DeckCard $a, DeckCard $b): int {
            if ($a->getQuantity() !== $b->getQuantity()) {
                return $b->getQuantity() - $a->getQuantity();
            }

            return strcmp($a->getCardName(), $b->getCardName());
        };

        foreach ($grouped as &$cards) {
            usort($cards, $sortFn);
        }
        unset($cards);

        $ordered = [];
        foreach (['pokemon', 'supporter', 'item', 'tool', 'stadium', 'energy'] as $section) {
            if (isset($grouped[$section])) {
                $ordered[$section] = $grouped[$section];
            }
        }

        return $ordered;
    }

    private function generateQrCode(string $content): string
    {
        $builder = new Builder();
        $result = $builder->build(
            data: $content,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: self::QR_CODE_SIZE_PX,
            margin: 0,
        );

        return $result->getDataUri();
    }

    /**
     * Build base64 data URIs for archetype sprite images.
     *
     * Dompdf cannot access files via web-server relative paths,
     * so sprites must be embedded as data URIs.
     *
     * @return list<array{dataUri: string, name: string}>
     */
    private function buildSpriteDataUris(Deck $deck): array
    {
        $archetype = $deck->getArchetype();

        if (null === $archetype) {
            return [];
        }

        $slugs = $archetype->getPokemonSlugs();
        $sprites = [];

        foreach ($slugs as $slug) {
            $path = $this->projectDir.'/public/build/sprites/pokemon/'.$slug.'.png';

            if (!file_exists($path)) {
                continue;
            }

            $data = file_get_contents($path);

            if (false === $data) {
                continue;
            }

            $sprites[] = [
                'dataUri' => 'data:image/png;base64,'.base64_encode($data),
                'name' => ucwords(str_replace('-', ' ', $slug)),
            ];
        }

        return $sprites;
    }

    private function renderPdf(string $html, string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }
}

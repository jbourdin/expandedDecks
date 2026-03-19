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

    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}

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
use App\Entity\TcgdexCard;
use App\Entity\User;
use App\Repository\TcgdexCardRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Generates a tournament-ready A4 decklist PDF from a deck's card list.
 *
 * Two modes: personal (auto-filled player info) and anonymous (blank fields).
 *
 * @see docs/features.md F5.13 — Printable A4 decklist PDF
 */
class PdfDecklistGenerator
{
    /** @var array<string, string> cached set symbol data URIs within one request */
    private array $symbolCache = [];

    /** @var array<string, ?TcgdexCard> cached TcgdexCard lookups by tcgdexId */
    private array $tcgdexCardCache = [];

    public function __construct(
        private readonly Environment $twig,
        private readonly HttpClientInterface $httpClient,
        private readonly TranslatorInterface $translator,
        private readonly TcgdexCardRepository $tcgdexCardRepository,
    ) {
    }

    /**
     * Generate a personal decklist PDF with player info filled from the User entity.
     *
     * @see docs/features.md F5.13 — Printable A4 decklist PDF
     *
     * @return string the raw PDF binary content
     */
    public function generatePersonal(Deck $deck, User $user): string
    {
        return $this->generate($deck, $user);
    }

    /**
     * Generate an anonymous decklist PDF with blank player fields.
     *
     * @see docs/features.md F5.13 — Printable A4 decklist PDF
     *
     * @return string the raw PDF binary content
     */
    public function generateAnonymous(Deck $deck): string
    {
        return $this->generate($deck, null);
    }

    private function generate(Deck $deck, ?User $user): string
    {
        $currentVersion = $deck->getCurrentVersion();
        $locale = $this->resolveDeckLocale($deck);

        $groupedCards = null !== $currentVersion
            ? $this->groupCardsForDecklist($currentVersion)
            : ['pokemon' => [], 'trainerGroups' => [], 'energy' => []];

        $setSymbolDataUris = $this->fetchSetSymbolDataUris($this->collectSetSymbolUrls($groupedCards['pokemon']));
        $gravatarDataUri = null !== $user ? $this->fetchGravatarDataUri($user->getEmail()) : null;

        $templateData = $this->buildTemplateData($deck, $user, $groupedCards, $setSymbolDataUris, $gravatarDataUri, $locale);

        $html = $this->twig->render('label/pdf_decklist.html.twig', $templateData);

        return $this->renderPdf($html);
    }

    /**
     * Group cards into three top-level sections: pokemon, trainer (sub-grouped by subtype), energy.
     *
     * @return array{pokemon: list<DeckCard>, trainerGroups: array<string, list<DeckCard>>, energy: list<DeckCard>}
     */
    private function groupCardsForDecklist(DeckVersion $version): array
    {
        $pokemon = [];
        $trainerGroups = [];
        $energy = [];

        foreach ($version->getCards() as $card) {
            match ($card->getCardType()) {
                'pokemon' => $pokemon[] = $card,
                'trainer' => $trainerGroups[$this->resolveTrainerSubtype($card)][] = $card,
                'energy' => $energy[] = $card,
                default => $trainerGroups['trainer'][] = $card,
            };
        }

        $sortFunction = static function (DeckCard $a, DeckCard $b): int {
            if ($a->getQuantity() !== $b->getQuantity()) {
                return $b->getQuantity() - $a->getQuantity();
            }

            return strcmp($a->getCardName(), $b->getCardName());
        };

        usort($pokemon, $sortFunction);
        usort($energy, $sortFunction);

        $orderedTrainerGroups = [];
        foreach (['supporter', 'item', 'tool', 'stadium', 'technical machine', 'trainer'] as $subtype) {
            if (isset($trainerGroups[$subtype])) {
                usort($trainerGroups[$subtype], $sortFunction);
                $orderedTrainerGroups[$subtype] = $trainerGroups[$subtype];
            }
        }

        return [
            'pokemon' => $pokemon,
            'trainerGroups' => $orderedTrainerGroups,
            'energy' => $energy,
        ];
    }

    private function resolveTrainerSubtype(DeckCard $card): string
    {
        $subtype = $card->getTrainerSubtype();

        return null !== $subtype ? strtolower($subtype) : 'trainer';
    }

    /**
     * Collect unique set symbol URLs from Pokemon cards.
     *
     * @param list<DeckCard> $pokemonCards
     *
     * @return list<string>
     */
    private function collectSetSymbolUrls(array $pokemonCards): array
    {
        $urls = [];

        foreach ($pokemonCards as $card) {
            $symbolUrl = $this->resolveTcgdexCard($card)?->getSet()?->getSymbolUrl();
            if (null !== $symbolUrl && !isset($urls[$symbolUrl])) {
                $urls[$symbolUrl] = true;
            }
        }

        return array_keys($urls);
    }

    /**
     * Fetch remote set symbol images and base64-encode them for Dompdf.
     *
     * @param list<string> $urls
     *
     * @return array<string, string> map of URL to base64 data URI
     */
    private function fetchSetSymbolDataUris(array $urls): array
    {
        $result = [];

        foreach ($urls as $url) {
            if (isset($this->symbolCache[$url])) {
                $result[$url] = $this->symbolCache[$url];

                continue;
            }

            try {
                // TCGdex symbol URLs are base paths — append .png for the actual image
                $fetchUrl = str_ends_with($url, '.png') || str_ends_with($url, '.svg') ? $url : $url.'.png';
                $response = $this->httpClient->request('GET', $fetchUrl, ['timeout' => 3]);
                $contentType = $response->getHeaders()['content-type'][0] ?? 'image/png';
                $content = $response->getContent();

                // Normalize content type (remove charset etc.)
                if (str_contains($contentType, 'svg')) {
                    $contentType = 'image/svg+xml';
                } elseif (str_contains($contentType, 'png')) {
                    $contentType = 'image/png';
                }

                $dataUri = \sprintf('data:%s;base64,%s', $contentType, base64_encode($content));
                $this->symbolCache[$url] = $dataUri;
                $result[$url] = $dataUri;
            } catch (\Throwable) {
                // Graceful degradation: no symbol image, text-only fallback
            }
        }

        return $result;
    }

    private function fetchGravatarDataUri(string $email): ?string
    {
        $hash = md5(strtolower(trim($email)));
        $url = \sprintf('https://www.gravatar.com/avatar/%s?s=80&d=404', $hash);

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 3]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $content = $response->getContent();
            $contentType = $response->getHeaders()['content-type'][0] ?? 'image/jpeg';

            return \sprintf('data:%s;base64,%s', $contentType, base64_encode($content));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build all template variables for the decklist PDF.
     *
     * @param array{pokemon: list<DeckCard>, trainerGroups: array<string, list<DeckCard>>, energy: list<DeckCard>} $groupedCards
     * @param array<string, string>                                                                                $setSymbolDataUris
     *
     * @return array<string, mixed>
     */
    private function buildTemplateData(
        Deck $deck,
        ?User $user,
        array $groupedCards,
        array $setSymbolDataUris,
        ?string $gravatarDataUri,
        string $locale,
    ): array {
        $pokemonCards = $groupedCards['pokemon'];
        $trainerGroups = $groupedCards['trainerGroups'];
        $energyCards = $groupedCards['energy'];

        // Count section totals
        $pokemonCount = $this->sumQuantities($pokemonCards);
        $trainerCount = 0;
        foreach ($trainerGroups as $cards) {
            $trainerCount += $this->sumQuantities($cards);
        }
        $energyCount = $this->sumQuantities($energyCards);

        // Build card view data with English name resolution
        $pokemonRows = $this->buildCardRows($pokemonCards, $locale, true, $setSymbolDataUris);
        $trainerSections = [];
        foreach ($trainerGroups as $subtype => $cards) {
            $trainerSections[] = [
                'subtype' => $subtype,
                'label' => $this->translateSubtype($subtype, $locale),
                'rows' => $this->buildCardRows($cards, $locale, false, []),
            ];
        }
        $energyRows = $this->buildCardRows($energyCards, $locale, false, []);

        // Compute adapt-to-fit font size
        $fontSize = $this->computeFontSize($pokemonRows, $trainerSections, $energyRows);

        // Player trigram
        $trigram = null !== $user ? $this->buildTrigram($user) : null;

        return [
            'mode' => null !== $user ? 'personal' : 'anonymous',
            'playerName' => null !== $user ? $user->getFirstName().' '.$user->getLastName() : null,
            'playerId' => $user?->getPlayerId(),
            'yearOfBirth' => $user?->getYearOfBirth(),
            'trigram' => $trigram,
            'gravatarDataUri' => $gravatarDataUri,
            'format' => ucfirst($deck->getFormat()->value),
            'pokemonCount' => $pokemonCount,
            'pokemonRows' => $pokemonRows,
            'trainerCount' => $trainerCount,
            'trainerSections' => $trainerSections,
            'energyCount' => $energyCount,
            'energyRows' => $energyRows,
            'fontSize' => $fontSize,
            'labels' => $this->buildLabels($locale),
        ];
    }

    /**
     * Build card row data for the template.
     *
     * @param list<DeckCard>        $cards
     * @param array<string, string> $setSymbolDataUris
     *
     * @return list<array{name: string, englishName: ?string, quantity: int, setCode: string, cardNumber: string, symbolDataUri: ?string}>
     */
    private function buildCardRows(array $cards, string $locale, bool $includeSetInfo, array $setSymbolDataUris): array
    {
        $rows = [];

        foreach ($cards as $card) {
            $tcgdexCard = $this->resolveTcgdexCard($card);

            // Resolve the display name in the user's locale
            $displayName = $card->getCardName();
            $englishName = null;

            if ('en' !== $locale && null !== $tcgdexCard) {
                // Try to get the localized name (e.g. nameFr for French users)
                $localizedName = $this->getLocalizedCardName($tcgdexCard, $locale);
                if (null !== $localizedName) {
                    $displayName = $localizedName;
                }

                // Show English name as subline if it differs from the displayed name
                $nameEn = $tcgdexCard->getNameEn();
                if (null !== $nameEn && $nameEn !== $displayName) {
                    $englishName = $nameEn;
                }
            }

            $symbolDataUri = null;
            $ptcgCode = $card->getSetCode();
            if ($includeSetInfo) {
                $symbolUrl = $this->resolveTcgdexCard($card)?->getSet()?->getSymbolUrl();
                if (null !== $symbolUrl) {
                    $symbolDataUri = $setSymbolDataUris[$symbolUrl] ?? null;
                }
                $ptcgCode = $this->resolveTcgdexCard($card)?->getSet()?->getPtcgCode() ?? $card->getSetCode();
            }

            $rows[] = [
                'name' => $displayName,
                'englishName' => $englishName,
                'quantity' => $card->getQuantity(),
                'setCode' => $ptcgCode,
                'cardNumber' => $card->getCardNumber(),
                'symbolDataUri' => $includeSetInfo ? $symbolDataUri : null,
                'includeSetInfo' => $includeSetInfo,
            ];
        }

        return $rows;
    }

    /**
     * Compute font size to fit all cards on one A4 page.
     *
     * A4 usable height ≈ 277mm (297 - 2×10mm margins). Header ≈ 35mm.
     * Available for cards ≈ 242mm. Each section header ≈ 5mm. Each subtype header ≈ 4mm.
     *
     * @param list<array{englishName: ?string}>                    $pokemonRows
     * @param list<array{rows: list<array{englishName: ?string}>}> $trainerSections
     * @param list<array{englishName: ?string}>                    $energyRows
     */
    private function computeFontSize(array $pokemonRows, array $trainerSections, array $energyRows): float
    {
        // Count lines: each card = 1 line (English name is inline, not a subline)
        $totalLines = 0.0;

        $totalLines += \count($pokemonRows);

        foreach ($trainerSections as $section) {
            $totalLines += 0.5; // subtype header
            $totalLines += \count($section['rows']);
        }

        $totalLines += \count($energyRows);

        // Add section headers (Pokemon, Trainer, Energy = 3 headers)
        $sectionHeaderLines = 3.0;
        $totalLines += $sectionHeaderLines;

        // Available height: A4 (297mm) - margins (22mm) - header (40mm) = ~235mm
        $availableHeightMm = 235.0;
        $lineHeightFactor = 1.35;
        $ptToMm = 0.353;

        if ($totalLines > 0) {
            $maxFontSizePt = $availableHeightMm / ($totalLines * $lineHeightFactor * $ptToMm);

            return min(9.0, max(6.0, round($maxFontSizePt, 1)));
        }

        return 8.0;
    }

    /**
     * @param list<DeckCard> $cards
     */
    private function sumQuantities(array $cards): int
    {
        $total = 0;
        foreach ($cards as $card) {
            $total += $card->getQuantity();
        }

        return $total;
    }

    private function buildTrigram(User $user): string
    {
        $first = mb_strtoupper(mb_substr($user->getFirstName(), 0, 1));
        $lastName = $user->getLastName();
        $lastFirst = mb_strtoupper(mb_substr($lastName, 0, 1));
        $lastLast = mb_strlen($lastName) > 1 ? mb_strtoupper(mb_substr($lastName, -1, 1)) : '';

        return $first.$lastFirst.$lastLast;
    }

    /**
     * @return array<string, string>
     */
    private function buildLabels(string $locale): array
    {
        return [
            'playerName' => $this->translator->trans('app.decklist.player_name', locale: $locale),
            'playerId' => $this->translator->trans('app.decklist.player_id', locale: $locale),
            'yearOfBirth' => $this->translator->trans('app.decklist.year_of_birth', locale: $locale),
            'pokemon' => $this->translator->trans('app.decklist.section.pokemon', locale: $locale),
            'trainer' => $this->translator->trans('app.decklist.section.trainer', locale: $locale),
            'energy' => $this->translator->trans('app.decklist.section.energy', locale: $locale),
        ];
    }

    private function translateSubtype(string $subtype, string $locale): string
    {
        $key = 'app.decklist.subtype.'.$subtype;

        $translated = $this->translator->trans($key, locale: $locale);

        // If the translation key is not found, fall back to titlecase subtype
        return $translated !== $key ? $translated : ucfirst($subtype);
    }

    /**
     * Resolve the locale for card names based on the deck's language list.
     *
     * If the deck has exactly one language set, use it (e.g. "fr").
     * If multiple languages or none, default to English.
     */
    private function resolveDeckLocale(Deck $deck): string
    {
        $languages = $deck->getLanguages();

        return 1 === \count($languages) ? $languages[0] : 'en';
    }

    /**
     * Resolve the TcgdexCard for a DeckCard, using the entity relation first,
     * then falling back to a repository lookup via the tcgdexId string.
     */
    private function resolveTcgdexCard(DeckCard $card): ?TcgdexCard
    {
        $tcgdexCard = $card->getCardPrinting()?->getTcgdexCard();
        if (null !== $tcgdexCard) {
            return $tcgdexCard;
        }

        $tcgdexId = $card->getCardPrinting()?->getTcgdexId();
        if (null === $tcgdexId) {
            return null;
        }

        if (\array_key_exists($tcgdexId, $this->tcgdexCardCache)) {
            return $this->tcgdexCardCache[$tcgdexId];
        }

        $this->tcgdexCardCache[$tcgdexId] = $this->tcgdexCardRepository->find($tcgdexId);

        return $this->tcgdexCardCache[$tcgdexId];
    }

    /**
     * Get the card name in the user's locale from TCGdex data.
     *
     * Reads directly from the JSON name field for the specific locale key.
     * Returns null only if no entry exists for the requested locale,
     * letting the caller fall back to the original DeckCard.cardName.
     */
    private function getLocalizedCardName(TcgdexCard $tcgdexCard, string $locale): ?string
    {
        // Access the raw name array to check if the locale key exists explicitly
        $names = $tcgdexCard->getName();
        $localizedName = $names[$locale] ?? null;

        return \is_string($localizedName) ? $localizedName : null;
    }

    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}

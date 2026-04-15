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

namespace App\Controller;

use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Repository\ArchetypeRepository;
use App\Repository\DeckRepository;
use App\Service\ArchetypeDescriptionRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F2.10 — Archetype detail page
 */
class ArchetypeDetailController extends AbstractController
{
    /**
     * @see docs/features.md F2.10 — Archetype detail page
     * @see docs/features.md F7.11 — Draft state with preview
     * @see docs/features.md F9.6 — Archetype localization
     */
    #[Route('/archetypes/{slug}', name: 'app_archetype_show', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function show(
        string $slug,
        Request $request,
        ArchetypeRepository $archetypeRepository,
        DeckRepository $deckRepository,
        ArchetypeDescriptionRenderer $descriptionRenderer,
    ): Response {
        $archetype = $archetypeRepository->findOneBy(['slug' => $slug]);

        if (null === $archetype || null !== $archetype->getDeletedAt()) {
            throw $this->createNotFoundException();
        }

        $isPreview = $request->query->getBoolean('preview');

        if (!$archetype->isPublished() && !($isPreview && $this->isGranted('ROLE_ARCHETYPE_EDITOR'))) {
            throw $this->createNotFoundException();
        }

        $locale = $request->getLocale();
        $description = $archetype->getLocalizedDescription($locale);
        $htmlContent = null !== $description
            ? $descriptionRenderer->render($description, $locale)
            : null;

        $latestDecks = $deckRepository->findLatestPublicByArchetype($archetype);
        $totalDeckCount = $deckRepository->countPublicByArchetype($archetype);

        $variants = $deckRepository->findVariantsByArchetype($archetype);
        $variantsData = $this->buildVariantsData($variants, $descriptionRenderer, $locale);

        return $this->render('archetype/show.html.twig', [
            'archetype' => $archetype,
            'htmlContent' => $htmlContent,
            'latestDecks' => $latestDecks,
            'totalDeckCount' => $totalDeckCount,
            'variants' => $variants,
            'variantsData' => $variantsData,
        ]);
    }

    /**
     * Build serializable variant data for the React variant selector.
     *
     * @see docs/features.md F18.16 — Archetype detail: variant selector
     *
     * @param list<Deck> $variants
     *
     * @return list<array{id: int, name: string, canonical: bool, description: string|null, mosaicUrl: string|null, groupedCards: array<string, list<array{cardName: string, quantity: int, setCode: string, cardNumber: string, cardType: string, trainerSubtype: string|null, imageUrl: string|null}>>}>
     */
    private function buildVariantsData(array $variants, ArchetypeDescriptionRenderer $descriptionRenderer, string $locale): array
    {
        $data = [];

        foreach ($variants as $variant) {
            $version = $variant->getCurrentVersion();
            $groupedCards = [];
            $mosaicUrl = null;

            if (null !== $version) {
                $mosaicUrl = $version->getMosaicImageUrl();

                foreach ($version->getCards() as $card) {
                    $groupedCards[$card->getCardType()][] = $card;
                }

                foreach ($groupedCards as $type => &$cards) {
                    usort($cards, static function (DeckCard $cardA, DeckCard $cardB) use ($type): int {
                        if ('trainer' === $type) {
                            $subtypeOrder = ['supporter' => 0, 'item' => 1, 'tool' => 2, 'stadium' => 3];
                            $subtypeA = $subtypeOrder[strtolower((string) $cardA->getTrainerSubtype())] ?? 4;
                            $subtypeB = $subtypeOrder[strtolower((string) $cardB->getTrainerSubtype())] ?? 4;

                            if ($subtypeA !== $subtypeB) {
                                return $subtypeA <=> $subtypeB;
                            }
                        }

                        if ($cardA->getQuantity() !== $cardB->getQuantity()) {
                            return $cardB->getQuantity() <=> $cardA->getQuantity();
                        }

                        return strcmp($cardA->getCardName(), $cardB->getCardName());
                    });
                }
                unset($cards);
            }

            $orderedGroups = [];
            foreach (['pokemon', 'trainer', 'energy'] as $section) {
                if (isset($groupedCards[$section])) {
                    $serialized = [];
                    foreach ($groupedCards[$section] as $card) {
                        $serialized[] = [
                            'cardName' => $card->getCardName(),
                            'quantity' => $card->getQuantity(),
                            'setCode' => $card->getSetCode(),
                            'cardNumber' => $card->getCardNumber(),
                            'cardType' => $card->getCardType(),
                            'trainerSubtype' => $card->getTrainerSubtype(),
                            'imageUrl' => $card->getImageUrl(),
                        ];
                    }
                    $orderedGroups[$section] = $serialized;
                }
            }

            $description = $variant->getNotes();
            $htmlDescription = null !== $description && '' !== $description
                ? $descriptionRenderer->render($description, $locale)
                : null;

            /** @var int $variantId */
            $variantId = $variant->getId();

            $latestSet = $variant->getLatestSet();

            $data[] = [
                'id' => $variantId,
                'name' => $variant->getName(),
                'canonical' => $variant->isCanonical(),
                'outdated' => $variant->isOutdated(),
                'latestSetCode' => $latestSet?->getPtcgCode(),
                'latestSetName' => $latestSet?->getLocalizedName($locale),
                'sprites' => $variant->getPokemonSlugs(),
                'description' => $htmlDescription,
                'mosaicUrl' => $mosaicUrl,
                'rawList' => $version?->getRawList(),
                'groupedCards' => $orderedGroups,
            ];
        }

        // Sort outdated variants after current ones, preserving relative order within each group.
        usort($data, static fn (array $variantA, array $variantB): int => (int) $variantA['outdated'] <=> (int) $variantB['outdated']);

        return $data;
    }
}

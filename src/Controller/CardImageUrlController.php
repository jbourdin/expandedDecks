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

use App\Repository\DeckCardRepository;
use App\Service\Tcgdex\TcgdexApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Resolves a card reference (e.g. "UPR-100") to its TCGdex image URL.
 *
 * @see docs/features.md F17.8 — Insert card image from reference in rich text editor
 */
class CardImageUrlController extends AbstractController
{
    public function __construct(
        private readonly DeckCardRepository $deckCardRepository,
        private readonly TcgdexApiClient $tcgdexApiClient,
    ) {
    }

    #[Route('/api/card/image-url', name: 'app_card_image_url', methods: ['GET'])]
    #[IsGranted('ROLE_CMS_EDITOR')]
    public function __invoke(Request $request): JsonResponse
    {
        $reference = $request->query->getString('reference');
        if ('' === $reference) {
            return $this->json(['error' => 'Missing "reference" query parameter.'], Response::HTTP_BAD_REQUEST);
        }

        $lastHyphen = strrpos($reference, '-');
        if (false === $lastHyphen) {
            return $this->json(['error' => 'Invalid reference format. Expected "SET-NUMBER".'], Response::HTTP_BAD_REQUEST);
        }

        $setCode = substr($reference, 0, $lastHyphen);
        $cardNumber = substr($reference, $lastHyphen + 1);

        // Try local DB first (enriched cards with image URLs)
        $deckCard = $this->deckCardRepository->findOneBySetCodeAndCardNumber($setCode, $cardNumber);
        if (null !== $deckCard && null !== $deckCard->getImageUrl()) {
            return $this->json(['url' => $deckCard->getImageUrl()]);
        }

        // Fall back to TCGdex API
        $tcgdexCard = $this->tcgdexApiClient->findCard($setCode, $cardNumber);
        if (null !== $tcgdexCard && null !== $tcgdexCard->imageUrl) {
            return $this->json(['url' => $tcgdexCard->imageUrl]);
        }

        return $this->json(['error' => 'Card not found.'], Response::HTTP_NOT_FOUND);
    }
}

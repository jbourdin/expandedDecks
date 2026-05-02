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

use App\Repository\BannedCardRepository;
use App\Service\BannedCardImageResolver;
use App\Service\MarkdownRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public-facing list of cards currently banned in the Expanded format.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardController extends AbstractController
{
    /**
     * @see docs/features.md F6.14 — Banned cards public page
     */
    #[Route('/{_locale}/banned-cards', name: 'app_banned_card_list', methods: ['GET'], requirements: ['_locale' => 'en|fr'], priority: 10)]
    public function list(
        Request $request,
        BannedCardRepository $bannedCardRepository,
        BannedCardImageResolver $imageResolver,
        MarkdownRenderer $markdownRenderer,
    ): Response {
        $bannedCards = $bannedCardRepository->findActiveOrderedByEffectiveDate();
        $locale = $request->getLocale();

        $imageUrls = [];
        $renderedExplanations = [];
        foreach ($bannedCards as $card) {
            $id = $card->getId();
            if (null === $id) {
                continue;
            }

            $imageUrls[$id] = $imageResolver->resolveForBan($card, $locale);

            $explanation = $card->getExplanation();
            if (null !== $explanation && '' !== trim($explanation)) {
                $renderedExplanations[$id] = $markdownRenderer->render($explanation);
            }
        }

        return $this->render('banned_card/list.html.twig', [
            'bannedCards' => $bannedCards,
            'imageUrls' => $imageUrls,
            'renderedExplanations' => $renderedExplanations,
        ]);
    }
}

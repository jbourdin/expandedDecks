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
use App\Entity\User;
use App\Service\DeckFoundNotificationService;
use App\Service\FriendlyCaptchaVerifier;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F4.16 — Lost & found deck alert
 */
class DeckFoundController extends AbstractController
{
    #[Route('/api/deck/{short_tag}/found', name: 'app_deck_found_report', methods: ['POST'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}'])]
    public function report(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        Request $request,
        FriendlyCaptchaVerifier $captchaVerifier,
        DeckFoundNotificationService $notificationService,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        // Owner cannot report their own deck
        if ($user instanceof User && $deck->getOwnerOrFail()->getId() === $user->getId()) {
            return new JsonResponse(['error' => 'Cannot report your own deck.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $request->toArray();

        // Captcha verification
        $captchaResponse = \is_string($payload['captchaResponse'] ?? null) ? $payload['captchaResponse'] : '';
        if (!$captchaVerifier->verify($captchaResponse)) {
            return new JsonResponse(['error' => 'Captcha verification failed.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // CSRF verification
        $csrfToken = \is_string($payload['csrfToken'] ?? null) ? $payload['csrfToken'] : '';
        if (!$this->isCsrfTokenValid('deck-found-'.$deck->getShortTag(), $csrfToken)) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = \is_string($payload['message'] ?? null) ? mb_substr(trim($payload['message']), 0, 500) : '';
        if ('' === $message) {
            return new JsonResponse(['error' => 'Message is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $anonymous = (bool) ($payload['anonymous'] ?? false);

        $reporter = ($user instanceof User && !$anonymous) ? $user : null;

        $notificationService->notify($deck, $reporter, $message);

        return new JsonResponse(['success' => true]);
    }
}

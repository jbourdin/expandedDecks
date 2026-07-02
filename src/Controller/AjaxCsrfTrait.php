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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared CSRF validation for authenticated AJAX/fetch endpoints.
 *
 * These endpoints receive JSON or multipart bodies that are decoded wholesale,
 * so the token cannot live in the body. Instead the browser sends the shared
 * per-session token (rendered as `<meta name="csrf-token">` in base.html.twig,
 * token id `ajax`) via the `X-CSRF-Token` header, read by `assets/csrf.ts`.
 *
 * Intended for controllers extending {@see \Symfony\Bundle\FrameworkBundle\Controller\AbstractController},
 * which provides `isCsrfTokenValid()`.
 */
trait AjaxCsrfTrait
{
    /**
     * Returns a 403 JSON response when the AJAX CSRF token is missing or
     * invalid, or null when the request is authentic.
     */
    private function invalidAjaxCsrfResponse(Request $request): ?JsonResponse
    {
        if ($this->isCsrfTokenValid('ajax', $request->headers->get('X-CSRF-Token', ''))) {
            return null;
        }

        return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
    }
}

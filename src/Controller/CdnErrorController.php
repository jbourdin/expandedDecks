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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Renders error page HTML with a 200 status for CDN caching.
 *
 * Bunny CDN fetches this URL and caches the body to serve as its custom
 * error page. No exception is thrown, so Sentry is not triggered.
 */
class CdnErrorController extends AbstractController
{
    #[Route('/cdn-error/{code}', name: 'app_cdn_error', requirements: ['code' => '\d+'], methods: ['GET'])]
    public function __invoke(int $code): Response
    {
        $statusText = Response::$statusTexts[$code] ?? 'Unknown Error';

        return $this->render('@Twig/Exception/error.html.twig', [
            'status_code' => $code,
            'status_text' => $statusText,
        ]);
    }
}

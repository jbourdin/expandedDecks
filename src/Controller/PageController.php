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

use App\Repository\PageRepository;
use App\Service\MarkdownRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F11.3 — Page rendering & locale fallback
 */
class PageController extends AbstractController
{
    #[Route('/pages/{slug}', name: 'app_page_show', requirements: ['slug' => '[a-z0-9-]+'])]
    public function show(
        string $slug,
        Request $request,
        PageRepository $pageRepository,
        MarkdownRenderer $markdownRenderer,
    ): Response {
        $page = $pageRepository->findBySlug($slug);

        if (null === $page) {
            throw $this->createNotFoundException();
        }

        if (!$page->isPublished() && !$this->isGranted('ROLE_CMS_EDITOR')) {
            throw $this->createNotFoundException();
        }

        $locale = $request->getLocale();
        $translation = $page->getTranslation($locale);

        if (null === $translation) {
            throw $this->createNotFoundException();
        }

        $htmlContent = $markdownRenderer->render($translation->getContent());

        return $this->render('page/show.html.twig', [
            'page' => $page,
            'translation' => $translation,
            'htmlContent' => $htmlContent,
        ]);
    }
}

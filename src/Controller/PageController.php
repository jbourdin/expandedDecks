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

use App\Entity\MenuCategory;
use App\Repository\PageRepository;
use App\Service\MarkdownRenderer;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F11.3 — Page rendering & locale fallback
 */
class PageController extends AbstractController
{
    private const PER_PAGE = 10;

    /**
     * @see docs/features.md F11.2 — Menu categories
     */
    #[Route('/pages/category/{id}', name: 'app_page_category', requirements: ['id' => '\d+'])]
    public function category(
        MenuCategory $category,
        Request $request,
        PageRepository $pageRepository,
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $locale = $request->getLocale();

        $qb = $pageRepository->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->where('p.menuCategory = :category')
            ->andWhere('p.isPublished = true')
            ->setParameter('category', $category)
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('page/category.html.twig', [
            'category' => $category,
            'categoryPages' => $paginator,
            'locale' => $locale,
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

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

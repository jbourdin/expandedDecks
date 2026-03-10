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

namespace App\Tests\Functional;

use App\Entity\MenuCategory;
use App\Entity\Page;
use App\Repository\MenuCategoryRepository;
use App\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F11.1 — Content pages
 * @see docs/features.md F11.3 — Page rendering & locale fallback
 */
class PageRepositoryTest extends AbstractFunctionalTest
{
    private function getRepository(): PageRepository
    {
        /** @var PageRepository $repository */
        $repository = static::getContainer()->get(PageRepository::class);

        return $repository;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    // ---------------------------------------------------------------
    // findBySlug — localized slug
    // ---------------------------------------------------------------

    public function testFindBySlugFindsPageByLocalizedSlug(): void
    {
        $repository = $this->getRepository();

        // "bienvenue" is the French translation slug for the "welcome" page
        $page = $repository->findBySlug('bienvenue');

        self::assertNotNull($page);
        self::assertSame('welcome', $page->getSlug());
    }

    public function testFindBySlugFindsPageByCanonicalSlug(): void
    {
        $repository = $this->getRepository();

        $page = $repository->findBySlug('welcome');

        self::assertNotNull($page);
        self::assertSame('welcome', $page->getSlug());
    }

    public function testFindBySlugReturnsNullForNonExistentSlug(): void
    {
        $repository = $this->getRepository();

        $page = $repository->findBySlug('non-existent-slug-that-does-not-exist');

        self::assertNull($page);
    }

    public function testFindBySlugFallsBackToCanonicalWhenLocalizedNotFound(): void
    {
        $repository = $this->getRepository();

        // "borrowing-guide" has no French translation slug, so canonical slug should work
        $page = $repository->findBySlug('borrowing-guide');

        self::assertNotNull($page);
        self::assertSame('borrowing-guide', $page->getSlug());
    }

    public function testFindBySlugEagerLoadsTranslations(): void
    {
        $repository = $this->getRepository();

        $page = $repository->findBySlug('welcome');

        self::assertNotNull($page);
        self::assertGreaterThanOrEqual(1, $page->getTranslations()->count());
    }

    // ---------------------------------------------------------------
    // findPublishedByCategory
    // ---------------------------------------------------------------

    public function testFindPublishedByCategoryReturnsPublishedPages(): void
    {
        $repository = $this->getRepository();
        $newsCategory = $this->getNewsCategoryFromDatabase();

        $pages = $repository->findPublishedByCategory($newsCategory);

        self::assertNotEmpty($pages);
        foreach ($pages as $page) {
            self::assertTrue($page->isPublished(), 'Only published pages should be returned.');
        }
    }

    public function testFindPublishedByCategoryRespectsLimit(): void
    {
        $repository = $this->getRepository();
        $newsCategory = $this->getNewsCategoryFromDatabase();

        $pages = $repository->findPublishedByCategory($newsCategory, 2);

        self::assertLessThanOrEqual(2, \count($pages));
    }

    public function testFindPublishedByCategoryReturnsAllWhenNoLimit(): void
    {
        $repository = $this->getRepository();
        $newsCategory = $this->getNewsCategoryFromDatabase();

        $pages = $repository->findPublishedByCategory($newsCategory);

        // News category has 8 published pages in fixtures
        self::assertGreaterThanOrEqual(8, \count($pages));
    }

    public function testFindPublishedByCategoryOrdersByCreatedAtDescending(): void
    {
        $repository = $this->getRepository();
        $newsCategory = $this->getNewsCategoryFromDatabase();

        $pages = $repository->findPublishedByCategory($newsCategory);

        for ($index = 1; $index < \count($pages); ++$index) {
            self::assertGreaterThanOrEqual(
                $pages[$index]->getCreatedAt(),
                $pages[$index - 1]->getCreatedAt(),
                'Pages should be ordered by createdAt descending (newest first).',
            );
        }
    }

    public function testFindPublishedByCategoryEagerLoadsTranslations(): void
    {
        $repository = $this->getRepository();
        $newsCategory = $this->getNewsCategoryFromDatabase();

        $pages = $repository->findPublishedByCategory($newsCategory);

        self::assertNotEmpty($pages);
        // At least the first page should have translations loaded
        self::assertGreaterThanOrEqual(1, $pages[0]->getTranslations()->count());
    }

    // ---------------------------------------------------------------
    // countPublishedByCategory
    // ---------------------------------------------------------------

    public function testCountPublishedByCategoryReturnsCorrectCount(): void
    {
        $repository = $this->getRepository();
        $newsCategory = $this->getNewsCategoryFromDatabase();

        $count = $repository->countPublishedByCategory($newsCategory);

        self::assertGreaterThanOrEqual(8, $count);
    }

    public function testCountPublishedByCategoryMatchesFindCount(): void
    {
        $repository = $this->getRepository();
        $newsCategory = $this->getNewsCategoryFromDatabase();

        $count = $repository->countPublishedByCategory($newsCategory);
        $pages = $repository->findPublishedByCategory($newsCategory);

        self::assertSame(\count($pages), $count);
    }

    public function testCountPublishedByCategoryExcludesUnpublishedPages(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        // Get the rules category
        /** @var MenuCategoryRepository $categoryRepository */
        $categoryRepository = static::getContainer()->get(MenuCategoryRepository::class);
        $categories = $categoryRepository->findAllOrdered();

        // Find a category with known pages
        $rulesCategory = null;
        foreach ($categories as $category) {
            if ('Rules & Info' === $category->getName('en')) {
                $rulesCategory = $category;
                break;
            }
        }
        self::assertNotNull($rulesCategory, 'Rules & Info category should exist in fixtures.');

        $countBefore = $repository->countPublishedByCategory($rulesCategory);

        // Create an unpublished page in this category
        $unpublishedPage = new Page();
        $unpublishedPage->setSlug('unpublished-rules-page');
        $unpublishedPage->setMenuCategory($rulesCategory);
        $unpublishedPage->setIsPublished(false);
        $entityManager->persist($unpublishedPage);
        $entityManager->flush();

        $countAfter = $repository->countPublishedByCategory($rulesCategory);

        // Count should not change
        self::assertSame($countBefore, $countAfter);
    }

    // ---------------------------------------------------------------
    // createAdminListQueryBuilder
    // ---------------------------------------------------------------

    public function testCreateAdminListQueryBuilderWithoutSearchReturnsAllPages(): void
    {
        $repository = $this->getRepository();

        $queryBuilder = $repository->createAdminListQueryBuilder();
        /** @var list<Page> $pages */
        $pages = $queryBuilder->getQuery()->getResult();

        // Fixtures have multiple pages (welcome, news, rules, draft)
        self::assertGreaterThanOrEqual(10, \count($pages));
    }

    public function testCreateAdminListQueryBuilderWithSearchFiltersResults(): void
    {
        $repository = $this->getRepository();

        $queryBuilder = $repository->createAdminListQueryBuilder('welcome');
        /** @var list<Page> $pages */
        $pages = $queryBuilder->getQuery()->getResult();

        self::assertNotEmpty($pages);
        foreach ($pages as $page) {
            $matchesSlug = str_contains($page->getSlug(), 'welcome');
            $matchesTitle = false;
            foreach ($page->getTranslations() as $translation) {
                if (str_contains(strtolower($translation->getTitle()), 'welcome')) {
                    $matchesTitle = true;
                    break;
                }
            }
            self::assertTrue($matchesSlug || $matchesTitle, 'Filtered pages should match the search query.');
        }
    }

    public function testCreateAdminListQueryBuilderWithEmptySearchReturnsAll(): void
    {
        $repository = $this->getRepository();

        $allPages = $repository->createAdminListQueryBuilder()->getQuery()->getResult();
        $emptySearchPages = $repository->createAdminListQueryBuilder('')->getQuery()->getResult();

        self::assertSame(\count($allPages), \count($emptySearchPages));
    }

    public function testCreateAdminListQueryBuilderSearchByTranslationTitle(): void
    {
        $repository = $this->getRepository();

        // Search by a French title fragment
        $queryBuilder = $repository->createAdminListQueryBuilder('Bienvenue');
        /** @var list<Page> $pages */
        $pages = $queryBuilder->getQuery()->getResult();

        self::assertNotEmpty($pages, 'Should find pages by searching translation titles.');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function getNewsCategoryFromDatabase(): MenuCategory
    {
        /** @var MenuCategoryRepository $categoryRepository */
        $categoryRepository = static::getContainer()->get(MenuCategoryRepository::class);
        $categories = $categoryRepository->findAllOrdered();

        foreach ($categories as $category) {
            if ('News' === $category->getName('en')) {
                return $category;
            }
        }

        self::fail('News category not found in fixtures.');
    }
}

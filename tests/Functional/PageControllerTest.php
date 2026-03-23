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

use App\Repository\MenuCategoryRepository;

class PageControllerTest extends AbstractFunctionalTest
{
    public function testPublishedPageIsAccessible(): void
    {
        $this->client->request('GET', '/pages/welcome');

        self::assertResponseIsSuccessful();
    }

    public function testUnpublishedPageReturns404ForAnonymous(): void
    {
        $this->client->request('GET', '/pages/upcoming-features');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCategoryPageIsAccessible(): void
    {
        /** @var MenuCategoryRepository $repository */
        $repository = static::getContainer()->get(MenuCategoryRepository::class);
        $category = $repository->findOneBy([]);
        self::assertNotNull($category);

        $this->client->request('GET', \sprintf('/pages/category/%d', $category->getId()));

        self::assertResponseIsSuccessful();
    }

    public function testNonExistentPageReturns404(): void
    {
        $this->client->request('GET', '/pages/nonexistent-slug');

        self::assertResponseStatusCodeSame(404);
    }
}

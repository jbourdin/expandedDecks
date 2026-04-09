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
    /** Pages are on the content channel. */
    private const array CONTENT_HOST = ['HTTP_HOST' => 'expandedtalks.wip'];

    public function testPublishedPageIsAccessible(): void
    {
        $this->client->request('GET', '/pages/welcome', server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
    }

    public function testUnpublishedPageReturns404ForAnonymous(): void
    {
        $this->client->request('GET', '/pages/upcoming-features', server: self::CONTENT_HOST);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnpublishedPageReturns404ForAnonymousWithPreview(): void
    {
        $this->client->request('GET', '/pages/upcoming-features?preview=true', server: self::CONTENT_HOST);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnpublishedPageAccessibleByEditorWithPreview(): void
    {
        $this->client->request('GET', '/login', server: self::CONTENT_HOST);
        $this->client->submitForm('Login', [
            '_email' => 'admin@example.com',
            '_password' => 'password',
        ]);
        $this->client->request('GET', '/pages/upcoming-features?preview=true', server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-warning');
    }

    public function testUnpublishedPageReturns404ForEditorWithoutPreview(): void
    {
        $this->client->request('GET', '/login', server: self::CONTENT_HOST);
        $this->client->submitForm('Login', [
            '_email' => 'admin@example.com',
            '_password' => 'password',
        ]);
        $this->client->request('GET', '/pages/upcoming-features', server: self::CONTENT_HOST);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCategoryPageIsAccessible(): void
    {
        /** @var MenuCategoryRepository $repository */
        $repository = static::getContainer()->get(MenuCategoryRepository::class);
        $category = $repository->findOneBy([]);
        self::assertNotNull($category);

        $this->client->request('GET', \sprintf('/pages/category/%d', $category->getId()), server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
    }

    public function testNonExistentPageReturns404(): void
    {
        $this->client->request('GET', '/pages/nonexistent-slug', server: self::CONTENT_HOST);

        self::assertResponseStatusCodeSame(404);
    }
}

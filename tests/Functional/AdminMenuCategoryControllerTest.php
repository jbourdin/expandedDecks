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

class AdminMenuCategoryControllerTest extends AbstractFunctionalTest
{
    public function testMenuCategoryListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/menu-categories');

        self::assertResponseRedirects('/login');
    }

    public function testMenuCategoryListRequiresCmsEditorRole(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/admin/menu-categories');

        self::assertResponseStatusCodeSame(403);
    }

    public function testMenuCategoryListAccessibleForAdmin(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/menu-categories');

        self::assertResponseIsSuccessful();
    }

    public function testNewMenuCategoryFormAccessible(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/menu-categories/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }
}

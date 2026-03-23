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

class AdminPageControllerTest extends AbstractFunctionalTest
{
    public function testPageListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/pages');

        self::assertResponseRedirects('/login');
    }

    public function testPageListRequiresCmsEditorRole(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/admin/pages');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPageListAccessibleForAdmin(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/pages');

        self::assertResponseIsSuccessful();
    }

    public function testNewPageFormAccessible(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/pages/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }
}

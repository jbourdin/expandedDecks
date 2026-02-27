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

class SmokeTest extends AbstractFunctionalTest
{
    public function testHomepageReturnsOk(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testLoginPageReturnsOk(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testRegistrationPageReturnsOk(): void
    {
        $this->client->request('GET', '/register');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardRequiresAuth(): void
    {
        $this->client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }

    public function testDashboardAccessibleAfterLogin(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
    }
}

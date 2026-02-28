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

use App\Repository\EventRepository;

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

    public function testEventListRequiresAuth(): void
    {
        $this->client->request('GET', '/event');

        self::assertResponseRedirects('/login');
    }

    public function testEventListAccessibleAfterLogin(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event');

        self::assertResponseIsSuccessful();
    }

    public function testEventNewRequiresOrganizer(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/event/new');

        self::assertResponseStatusCodeSame(403);
    }

    public function testEventNewAccessibleForOrganizer(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEventEditRequiresOrganizer(): void
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy([]);
        self::assertNotNull($event);

        $this->loginAs('borrower@example.com');

        $this->client->request('GET', \sprintf('/event/%d/edit', $event->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testEventEditAccessibleForOrganizer(): void
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy([]);
        self::assertNotNull($event);

        $this->loginAs('admin@example.com');

        $this->client->request('GET', \sprintf('/event/%d/edit', $event->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }
}

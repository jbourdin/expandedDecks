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

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F18.29 — Locale-prefixed URL routing
 */
class LocaleSwitchControllerTest extends AbstractFunctionalTest
{
    public function testSwitchesToFrenchAndRedirects(): void
    {
        $this->client->request('GET', '/locale/fr?_redirect=/deck');

        self::assertResponseRedirects('/deck');
        self::assertSame('fr', $this->client->getRequest()->getSession()->get('_locale'));
    }

    public function testSwitchesToEnglishAndRedirects(): void
    {
        $this->client->request('GET', '/locale/en?_redirect=/events');

        self::assertResponseRedirects('/events');
        self::assertSame('en', $this->client->getRequest()->getSession()->get('_locale'));
    }

    public function testDefaultsRedirectToHomepage(): void
    {
        $this->client->request('GET', '/locale/fr');

        self::assertResponseRedirects('/');
    }

    public function testPreventsOpenRedirectWithAbsoluteUrl(): void
    {
        $this->client->request('GET', '/locale/fr?_redirect=https://evil.com');

        self::assertResponseRedirects('/');
    }

    public function testPreventsOpenRedirectWithProtocolRelativeUrl(): void
    {
        $this->client->request('GET', '/locale/fr?_redirect=//evil.com');

        self::assertResponseRedirects('/');
    }

    public function testUpdatesAuthenticatedUserPreference(): void
    {
        $this->loginAs('borrower@example.com');
        $this->client->request('GET', '/locale/fr?_redirect=/');

        self::assertResponseRedirects('/');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'borrower@example.com']);
        self::assertNotNull($user);
        self::assertSame('fr', $user->getPreferredLocale());
    }

    public function testRejectsUnsupportedLocale(): void
    {
        $this->client->request('GET', '/locale/de');

        self::assertResponseStatusCodeSame(404);
    }
}

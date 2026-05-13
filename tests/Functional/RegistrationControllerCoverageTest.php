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

use App\Entity\Channel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Additional coverage tests for RegistrationController uncovered branches.
 *
 * Covers the "already logged in + target path" redirect branch.
 *
 * @see docs/features.md F1.1 — Register a new account
 */
class RegistrationControllerCoverageTest extends AbstractFunctionalTest
{
    /**
     * When a logged-in user visits /register with a safe _target_path,
     * they should be redirected to that target path.
     */
    public function testRegisterRedirectsToTargetPathWhenLoggedIn(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/register?_target_path=/deck');

        self::assertResponseRedirects('/deck');
    }

    /**
     * When a logged-in user visits /register with an unsafe _target_path,
     * they should be redirected to the dashboard.
     */
    public function testRegisterIgnoresUnsafeTargetPathWhenLoggedIn(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/register?_target_path=//evil.com');

        self::assertResponseRedirects('/dashboard');
    }

    /**
     * When a logged-in user visits /register with a protocol-based target,
     * they should be redirected to the dashboard (not the external URL).
     */
    public function testRegisterIgnoresProtocolTargetPathWhenLoggedIn(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/register?_target_path=/foo://bar');

        self::assertResponseRedirects('/dashboard');
    }

    /**
     * When a logged-in user visits /register without a target path,
     * they should be redirected to the dashboard.
     */
    public function testRegisterRedirectsToDashboardWhenLoggedInWithoutTarget(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/register');

        self::assertResponseRedirects('/dashboard');
    }

    /**
     * When a logged-in user visits /register with a recursive _target_path,
     * they should be redirected to the dashboard (recursive targets are unsafe).
     */
    public function testRegisterIgnoresRecursiveTargetPathWhenLoggedIn(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/register?_target_path=/login%3F_target_path%3D/register');

        self::assertResponseRedirects('/dashboard');
    }

    /**
     * On a channel without the deck feature, a logged-in user hitting /register
     * with no target path lands on the public home (/) instead of the dashboard.
     *
     * Uses a custom channel where register stays enabled (so the controller is
     * reachable) but decks are disabled (so the dashboard would 404).
     *
     * @see docs/features.md F18.7 — Feature-gate middleware for deck, event, and borrow routes
     */
    public function testRegisterRedirectsToHomeWhenLoggedInOnChannelWithoutDecks(): void
    {
        $this->persistNoDecksChannel('register-no-decks.wip');

        $this->client->request('GET', 'https://register-no-decks.wip/login');
        $this->client->submitForm('Login', [
            '_email' => 'admin@example.com',
            '_password' => 'password',
        ]);
        self::assertResponseRedirects('https://register-no-decks.wip/');

        $this->client->request('GET', 'https://register-no-decks.wip/register');

        self::assertResponseRedirects('https://register-no-decks.wip/');
    }

    private function persistNoDecksChannel(string $domain): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $channel = (new Channel())
            ->setCode('no-decks-'.bin2hex(random_bytes(3)))
            ->setDomain($domain)
            ->setEnableDecks(false)
            ->setEnableRegister(true)
            ->setEnableEvents(false)
            ->setEnableBorrows(false)
            ->setEnableArchetypes(false)
            ->setEnableBannedCards(false)
            ->setLocales(['en']);
        $entityManager->persist($channel);
        $entityManager->flush();
    }
}

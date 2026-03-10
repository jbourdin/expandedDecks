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
}

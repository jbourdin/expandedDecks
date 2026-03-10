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
 * Additional coverage tests for SecurityController uncovered branches.
 *
 * @see docs/features.md F1.2 — Log in / Log out
 */
class SecurityControllerCoverageTest extends AbstractFunctionalTest
{
    /**
     * The logout() method is dead code — the firewall intercepts the request
     * before the controller is invoked. Calling it directly should throw
     * a LogicException.
     *
     * Covers SecurityController::logout() line 60.
     *
     * @see docs/features.md F1.2 — Log in / Log out
     */
    public function testLogoutThrowsLogicException(): void
    {
        $controller = new \App\Controller\SecurityController();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('intercepted by the logout key');

        $controller->logout();
    }

    /**
     * Login page with a safe target path saves it in session and renders
     * the login form.
     */
    public function testLoginPageSavesTargetPathInSession(): void
    {
        $this->client->request('GET', '/login?_target_path=/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    /**
     * Login page with a protocol-containing target path should be treated
     * as unsafe and NOT be saved — the login form renders normally.
     */
    public function testLoginPageIgnoresProtocolTargetPath(): void
    {
        $this->client->request('GET', '/login?_target_path=/foo://bar');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    /**
     * Logged-in user accessing login page with protocol target should
     * redirect to dashboard, not the unsafe target.
     */
    public function testLoginPageRedirectsToDashboardWhenLoggedInWithProtocolTarget(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/login?_target_path=/foo://evil');

        self::assertResponseRedirects('/dashboard');
    }
}

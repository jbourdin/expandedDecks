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

use App\Repository\UserRepository;
use Symfony\Component\Mime\Email;

/**
 * @see docs/features.md F1.1 — Register a new account
 * @see docs/features.md F1.2 — Email verification
 */
class AuthControllerTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // Login
    // ---------------------------------------------------------------

    public function testLoginPageRedirectsWhenLoggedIn(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/login');

        self::assertResponseRedirects('/dashboard');
    }

    public function testLoginWithValidCredentials(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            '_email' => 'admin@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
    }

    public function testLoginWithWrongPassword(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            '_email' => 'admin@example.com',
            '_password' => 'wrong-password',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-danger');
    }

    public function testUnverifiedUserCannotLogin(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            '_email' => 'unverified@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // Registration
    // ---------------------------------------------------------------

    public function testRegistrationPageRedirectsWhenLoggedIn(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/register');

        self::assertResponseRedirects('/dashboard');
    }

    public function testRegistrationFormPersistsUser(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'new-user@example.com',
            'registration_form[screenName]' => 'NewPlayer',
            'registration_form[firstName]' => 'New',
            'registration_form[lastName]' => 'Player',
            'registration_form[plainPassword][first]' => 'SecurePass123!',
            'registration_form[plainPassword][second]' => 'SecurePass123!',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects('/login');

        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        $user = $repo->findOneBy(['email' => 'new-user@example.com']);

        self::assertNotNull($user);
        self::assertSame('NewPlayer', $user->getScreenName());
        self::assertFalse($user->isVerified());
        self::assertNotNull($user->getVerificationToken());
    }

    public function testRegistrationSendsVerificationEmail(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'email-check@example.com',
            'registration_form[screenName]' => 'EmailCheck',
            'registration_form[firstName]' => 'Email',
            'registration_form[lastName]' => 'Check',
            'registration_form[plainPassword][first]' => 'SecurePass123!',
            'registration_form[plainPassword][second]' => 'SecurePass123!',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects('/login');
        self::assertEmailCount(1);

        /** @var Email $email */
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'to', 'email-check@example.com');
    }

    // ---------------------------------------------------------------
    // LastLoginListener
    // ---------------------------------------------------------------

    public function testLoginUpdatesLastLoginAt(): void
    {
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        $user = $repo->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($user);
        $previousLogin = $user->getLastLoginAt();

        $this->loginAs('admin@example.com');

        // Refresh from DB
        /** @var UserRepository $freshRepo */
        $freshRepo = static::getContainer()->get(UserRepository::class);
        $updated = $freshRepo->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($updated);
        self::assertNotNull($updated->getLastLoginAt());

        if (null !== $previousLogin) {
            self::assertGreaterThanOrEqual($previousLogin, $updated->getLastLoginAt());
        }
    }

    // ---------------------------------------------------------------
    // Homepage redirect
    // ---------------------------------------------------------------

    public function testHomepageRedirectsWhenLoggedIn(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/');

        self::assertResponseRedirects('/dashboard');
    }
}

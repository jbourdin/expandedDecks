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
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F1.7 — Password reset
 */
class PasswordResetControllerTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // Forgot password
    // ---------------------------------------------------------------

    public function testForgotPasswordPageRendersForm(): void
    {
        $this->client->request('GET', '/forgot-password');

        self::assertResponseIsSuccessful();
    }

    public function testForgotPasswordSendsEmail(): void
    {
        $this->client->request('POST', '/forgot-password', [
            'email' => 'admin@example.com',
        ]);

        self::assertResponseRedirects('/login');
        self::assertEmailCount(1);

        // User should now have a reset token
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        $user = $repo->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($user);
        self::assertNotNull($user->getResetToken());
        self::assertNotNull($user->getResetTokenExpiresAt());
    }

    public function testForgotPasswordAntiEnumeration(): void
    {
        $this->client->request('POST', '/forgot-password', [
            'email' => 'doesnotexist@example.com',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Same message regardless of whether the email exists
        self::assertSelectorTextContains('.alert-success', 'If an account exists');
        self::assertEmailCount(0);
    }

    public function testForgotPasswordIgnoresUnverifiedUser(): void
    {
        $this->client->request('POST', '/forgot-password', [
            'email' => 'unverified@example.com',
        ]);

        self::assertResponseRedirects('/login');
        self::assertEmailCount(0);
    }

    // ---------------------------------------------------------------
    // Reset password
    // ---------------------------------------------------------------

    public function testResetPasswordWithValidTokenShowsForm(): void
    {
        $token = $this->requestResetTokenForAdmin();

        $this->client->request('GET', \sprintf('/reset-password/%s', $token));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testResetPasswordChangesPassword(): void
    {
        $token = $this->requestResetTokenForAdmin();

        $this->client->request('GET', \sprintf('/reset-password/%s', $token));
        $this->client->submitForm('Reset password', [
            'reset_password_form[plainPassword][first]' => 'NewSecurePass123!',
            'reset_password_form[plainPassword][second]' => 'NewSecurePass123!',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Your password has been reset.');

        // Token should be cleared
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        $user = $repo->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($user);
        self::assertNull($user->getResetToken());
        self::assertNull($user->getResetTokenExpiresAt());

        // Can login with new password
        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            '_email' => 'admin@example.com',
            '_password' => 'NewSecurePass123!',
        ]);

        self::assertResponseRedirects();
    }

    public function testResetPasswordWithInvalidTokenShowsError(): void
    {
        $this->client->request('GET', '/reset-password/nonexistent-token');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid password reset link.');
    }

    public function testResetPasswordWithExpiredTokenShowsError(): void
    {
        $token = $this->requestResetTokenForAdmin();

        // Expire the token
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        $user = $repo->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($user);

        $user->setResetTokenExpiresAt(new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC')));

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->flush();

        $this->client->request('GET', \sprintf('/reset-password/%s', $token));

        self::assertResponseRedirects('/forgot-password');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'This password reset link has expired.');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Triggers the forgot-password flow and returns the generated reset token.
     */
    private function requestResetTokenForAdmin(): string
    {
        $this->client->request('POST', '/forgot-password', [
            'email' => 'admin@example.com',
        ]);

        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        $user = $repo->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($user);

        $token = $user->getResetToken();
        self::assertNotNull($token);

        return $token;
    }
}

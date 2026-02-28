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
 * @see docs/features.md F1.2 — Email verification
 */
class VerificationControllerTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // Verify token
    // ---------------------------------------------------------------

    public function testVerifyWithValidTokenActivatesUser(): void
    {
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        $user = $repo->findOneBy(['email' => 'unverified@example.com']);
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());

        $this->client->request('GET', '/verify/test-verification-token');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Your email has been verified.');

        // Refresh from DB
        /** @var UserRepository $freshRepo */
        $freshRepo = static::getContainer()->get(UserRepository::class);
        $verified = $freshRepo->findOneBy(['email' => 'unverified@example.com']);
        self::assertNotNull($verified);
        self::assertTrue($verified->isVerified());
        self::assertNull($verified->getVerificationToken());
    }

    public function testVerifyWithInvalidTokenShowsError(): void
    {
        $this->client->request('GET', '/verify/nonexistent-token');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid verification link.');
    }

    public function testVerifyWithExpiredTokenShowsError(): void
    {
        // Set the token expiry to the past
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        $user = $repo->findOneBy(['email' => 'unverified@example.com']);
        self::assertNotNull($user);

        $user->setTokenExpiresAt(new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC')));

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->flush();

        $this->client->request('GET', '/verify/test-verification-token');

        self::assertResponseRedirects('/verify/resend');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'This verification link has expired.');
    }

    // ---------------------------------------------------------------
    // Resend verification
    // ---------------------------------------------------------------

    public function testResendVerificationPageRendersForm(): void
    {
        $this->client->request('GET', '/verify/resend');

        self::assertResponseIsSuccessful();
    }

    public function testResendVerificationSendsEmail(): void
    {
        $this->client->request('POST', '/verify/resend', [
            'email' => 'unverified@example.com',
        ]);

        self::assertResponseRedirects('/login');
        self::assertEmailCount(1);
    }

    public function testResendVerificationAntiEnumeration(): void
    {
        // Nonexistent email should show the same success message (no email sent)
        $this->client->request('POST', '/verify/resend', [
            'email' => 'doesnotexist@example.com',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'If an account exists');
        self::assertEmailCount(0);
    }

    public function testResendVerificationIgnoresAlreadyVerifiedUser(): void
    {
        $this->client->request('POST', '/verify/resend', [
            'email' => 'admin@example.com',
        ]);

        self::assertResponseRedirects('/login');
        self::assertEmailCount(0);
    }
}

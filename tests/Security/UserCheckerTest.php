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

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @see docs/features.md F1.2 — Email verification
 */
class UserCheckerTest extends TestCase
{
    private UserChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserChecker();
    }

    // ---------------------------------------------------------------
    // checkPreAuth
    // ---------------------------------------------------------------

    public function testCheckPreAuthPassesForVerifiedUser(): void
    {
        $user = new User();
        $user->setEmail('verified@example.com');
        $user->setIsVerified(true);

        $this->checker->checkPreAuth($user);

        // No exception means the check passed
        self::assertTrue(true);
    }

    public function testCheckPreAuthThrowsForUnverifiedUser(): void
    {
        $user = new User();
        $user->setEmail('unverified@example.com');
        $user->setIsVerified(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Your email address has not been verified');

        $this->checker->checkPreAuth($user);
    }

    public function testCheckPreAuthThrowsForAnonymizedUser(): void
    {
        $user = new User();
        $user->setEmail('deleted@example.com');
        $user->setIsVerified(true);
        $user->setIsAnonymized(true);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('This account has been deactivated');

        $this->checker->checkPreAuth($user);
    }

    public function testCheckPreAuthThrowsForDeletedUser(): void
    {
        $user = new User();
        $user->setEmail('deleted@example.com');
        $user->setIsVerified(true);
        $user->setDeletedAt(new \DateTimeImmutable());

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('This account has been deactivated');

        $this->checker->checkPreAuth($user);
    }

    public function testCheckPreAuthSkipsNonUserInstance(): void
    {
        $user = $this->createStub(UserInterface::class);

        $this->checker->checkPreAuth($user);

        // No exception means the check was skipped for non-User objects
        self::assertTrue(true);
    }

    // ---------------------------------------------------------------
    // checkPostAuth
    // ---------------------------------------------------------------

    public function testCheckPostAuthDoesNothingForVerifiedUser(): void
    {
        $user = new User();
        $user->setEmail('verified@example.com');
        $user->setIsVerified(true);

        $this->checker->checkPostAuth($user);

        // No exception — method is a no-op
        self::assertTrue(true);
    }

    public function testCheckPostAuthDoesNothingForAnonymizedUser(): void
    {
        $user = new User();
        $user->setEmail('anonymized@example.com');
        $user->setIsAnonymized(true);

        $this->checker->checkPostAuth($user);

        // No exception — method is a no-op
        self::assertTrue(true);
    }

    public function testCheckPostAuthDoesNothingForNonUserInstance(): void
    {
        $user = $this->createStub(UserInterface::class);

        $this->checker->checkPostAuth($user);

        // No exception — method is a no-op
        self::assertTrue(true);
    }
}

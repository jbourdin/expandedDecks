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

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @see docs/features.md F1.2 — Email verification
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isAnonymized()) {
            throw new CustomUserMessageAccountStatusException('This account has been deactivated.');
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Your email address has not been verified. Please check your inbox or resend the verification email.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}

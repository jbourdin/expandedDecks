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

namespace App\Service\Event;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Issue and rotate the per-user calendar feed token used by F3.14.
 *
 * The token is a 32-byte URL-safe random string stored on the User. It is
 * cryptographically unguessable, so the calendar feed URL behaves like a
 * capability — knowing the URL is enough to subscribe, no login required.
 *
 * @see docs/features.md F3.14 — iCal agenda feed
 */
final class PersonalCalendarTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Return the user's existing calendar token, generating one on first use.
     */
    public function ensureToken(User $user): string
    {
        $existing = $user->getCalendarToken();

        if (null !== $existing && '' !== $existing) {
            return $existing;
        }

        return $this->regenerateToken($user);
    }

    /**
     * Issue a brand-new calendar token, invalidating the previous URL.
     */
    public function regenerateToken(User $user): string
    {
        $token = $this->generateToken();

        $user->setCalendarToken($token);
        $this->entityManager->flush();

        return $token;
    }

    public function findUserByToken(string $token): ?User
    {
        if ('' === $token) {
            return null;
        }

        return $this->userRepository->findOneByCalendarToken($token);
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}

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

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @see docs/features.md F1.8 — Account deletion & data export (GDPR)
 */
class AccountDeletionController extends AbstractAppController
{
    #[Route('/confirm-deletion/{token}', name: 'app_confirm_deletion', methods: ['GET'])]
    public function confirmDeletion(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $user = $userRepository->findOneBy(['deletionToken' => $token]);

        if (null === $user) {
            $this->addFlash('danger', 'app.profile.deletion_invalid_link');

            return $this->redirectToRoute('app_login');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (null !== $user->getDeletionTokenExpiresAt() && $user->getDeletionTokenExpiresAt() < $now) {
            $this->addFlash('danger', 'app.profile.deletion_link_expired');

            return $this->redirectToRoute('app_login');
        }

        $user->anonymize();

        $em->flush();

        // Log out the current session
        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        $this->addFlash('success', 'app.profile.deletion_confirmed');

        return $this->redirectToRoute('app_login');
    }
}

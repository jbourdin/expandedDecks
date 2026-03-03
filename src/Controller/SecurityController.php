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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * @see docs/features.md F1.2 — Log in / Log out
 */
class SecurityController extends AbstractController
{
    use TargetPathTrait;

    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $targetPath = $request->query->getString('_target_path');
        $safeTarget = ('' !== $targetPath && $this->isSafeRedirectPath($targetPath)) ? $targetPath : null;

        if ($this->getUser()) {
            return $this->redirect($safeTarget ?? $this->generateUrl('app_dashboard'));
        }

        if (null !== $safeTarget) {
            $this->saveTargetPath($request->getSession(), 'main', $safeTarget);
        }

        return $this->render('security/login.html.twig', [
            'last_email' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    private function isSafeRedirectPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '://');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the logout key on the firewall.');
    }
}

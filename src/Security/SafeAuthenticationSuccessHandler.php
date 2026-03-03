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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;

/**
 * Sanitizes _target_path to prevent open-redirect attacks.
 *
 * If the request contains an unsafe _target_path (absolute URL, protocol-relative, etc.),
 * it is removed so the parent handler falls through to session/default.
 *
 * @see docs/features.md F1.2 — Log in / Log out
 */
class SafeAuthenticationSuccessHandler extends DefaultAuthenticationSuccessHandler
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $this->sanitizeTargetPath($request);

        return parent::onAuthenticationSuccess($request, $token);
    }

    private function sanitizeTargetPath(Request $request): void
    {
        foreach ([$request->request, $request->query] as $bag) {
            $targetPath = $bag->getString('_target_path');
            if ('' !== $targetPath && !$this->isSafeRedirectPath($targetPath)) {
                $bag->remove('_target_path');
            }
        }
    }

    private function isSafeRedirectPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '://');
    }
}

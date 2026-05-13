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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Strips any `_target_path` that would redirect outside of the current site,
 * then defers to the parent handler. When no safe target path is provided,
 * the default target is chosen per channel so users on channels without the
 * deck feature don't land on the deck dashboard (which 404s there).
 *
 * @see docs/features.md F1.2 — Log in / Log out
 * @see docs/features.md F18.7 — Feature-gate middleware for deck, event, and borrow routes
 */
class SafeAuthenticationSuccessHandler extends DefaultAuthenticationSuccessHandler
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        HttpUtils $httpUtils,
        private readonly LoginRedirectResolver $resolver,
        array $options = [],
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpUtils, $options, $logger);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $this->sanitizeTargetPath($request);
        $this->options['default_target_path'] = $this->resolver->defaultRouteName();

        return parent::onAuthenticationSuccess($request, $token);
    }

    private function sanitizeTargetPath(Request $request): void
    {
        foreach ([$request->request, $request->query] as $bag) {
            $targetPath = $bag->getString('_target_path');
            if ('' !== $targetPath && !$this->resolver->isSafePath($targetPath)) {
                $bag->remove('_target_path');
            }
        }
    }
}

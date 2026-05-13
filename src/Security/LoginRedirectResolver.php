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

use App\Service\Channel\ChannelContext;

/**
 * Centralizes post-login redirect decisions:
 *
 *  - {@see isSafePath()} rejects any value that would escape the current site
 *    (absolute URLs, protocol-relative URLs, nested `_target_path` payloads).
 *  - {@see defaultRouteName()} picks the channel-appropriate landing route when
 *    no explicit `_target_path` was provided. Channels with the deck feature
 *    disabled land on the public home page (the dashboard 404s there via
 *    {@see \App\EventListener\ChannelFeatureGateListener}).
 *
 * @see docs/features.md F1.2 — Log in / Log out
 * @see docs/features.md F18.7 — Feature-gate middleware for deck, event, and borrow routes
 */
final readonly class LoginRedirectResolver
{
    public function __construct(
        private ChannelContext $channelContext,
    ) {
    }

    public function isSafePath(string $path): bool
    {
        if ('' === $path) {
            return false;
        }

        return str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '://')
            && !$this->containsNestedTargetPath($path);
    }

    /**
     * Route name to redirect to when no explicit target path was provided.
     */
    public function defaultRouteName(): string
    {
        return $this->channelContext->getChannel()->getEnableDecks()
            ? 'app_dashboard'
            : 'app_home';
    }

    private function containsNestedTargetPath(string $path): bool
    {
        $decoded = $path;

        do {
            $previous = $decoded;
            $decoded = urldecode($decoded);
        } while ($decoded !== $previous);

        return str_contains($decoded, '_target_path');
    }
}

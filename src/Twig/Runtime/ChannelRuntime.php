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

namespace App\Twig\Runtime;

use App\Entity\Channel;
use App\Service\Channel\ChannelContext;
use App\Service\Channel\ChannelLocaleVisibility;
use App\Service\Channel\ChannelUrlGenerator;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @see docs/features.md F18.3 — Twig channel context and global template variables
 */
class ChannelRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly ChannelUrlGenerator $channelUrlGenerator,
        private readonly ChannelLocaleVisibility $localeVisibility,
    ) {
    }

    public function getCurrentChannel(): Channel
    {
        return $this->channelContext->getChannel();
    }

    /**
     * Draft locales of the current channel the current user may browse
     * (F9.16). Only call from session-cookie-gated template paths: the
     * visibility check consults the security token.
     *
     * @see docs/features.md F9.16 — Channel locale management
     *
     * @return list<string>
     */
    public function visibleDraftLocales(): array
    {
        return $this->localeVisibility->visibleDraftLocales($this->channelContext->getChannel());
    }

    public function isChannel(string $code): bool
    {
        return $this->channelContext->getChannel()->getCode() === $code;
    }

    /**
     * Generate a URL targeting a specific channel by code.
     *
     * @param array<string, mixed> $parameters
     */
    public function channelUrl(string $channelCode, string $routeName, array $parameters = []): string
    {
        return $this->channelUrlGenerator->forChannel($channelCode, $routeName, $parameters);
    }

    /**
     * Generate a URL targeting the channel that provides a given feature.
     *
     * Features: 'decks', 'events', 'borrows', 'register', 'archetypes'.
     *
     * @param array<string, mixed> $parameters
     */
    public function featureUrl(string $feature, string $routeName, array $parameters = []): string
    {
        return $this->channelUrlGenerator->forFeature($feature, $routeName, $parameters);
    }

    /**
     * Generate an absolute canonical URL for the channel that provides a feature.
     *
     * Always returns an absolute URL, even on the same channel.
     *
     * @see docs/features.md F18.25 — Canonical URLs on all public pages
     *
     * @param array<string, mixed> $parameters
     */
    public function canonicalUrl(string $feature, string $routeName, array $parameters = []): string
    {
        return $this->channelUrlGenerator->canonicalUrl($feature, $routeName, $parameters);
    }

    /**
     * Generate an absolute canonical URL on the current channel.
     *
     * @see docs/features.md F18.25 — Canonical URLs on all public pages
     *
     * @param array<string, mixed> $parameters
     */
    public function selfCanonicalUrl(string $routeName, array $parameters = []): string
    {
        return $this->channelUrlGenerator->selfCanonicalUrl($routeName, $parameters);
    }

    /**
     * Returns the current channel's theme name, or null if unavailable.
     * Safe to call on error pages and CLI.
     */
    public function channelTheme(): ?string
    {
        try {
            return $this->channelContext->getChannel()->getThemeName();
        } catch (\LogicException) {
            return null;
        }
    }

    public function channelParam(string $key, string $default = ''): string
    {
        try {
            return $this->channelContext->getChannel()->getParameter($key, $default);
        } catch (\LogicException) {
            return $default;
        }
    }

    /**
     * Expand a raw URL to an absolute one rooted on the current channel
     * domain. Already-absolute URLs and empty strings pass through unchanged.
     * Used by the OG/Twitter Card image meta tags so crawlers can resolve
     * editor-uploaded image paths like `/api/editor/image/...`.
     *
     * @see docs/features.md F18.28 — Open Graph and Twitter Card meta tags
     */
    public function channelAbsoluteUrl(string $url): string
    {
        return $this->channelUrlGenerator->absolutizeUrl($url);
    }
}

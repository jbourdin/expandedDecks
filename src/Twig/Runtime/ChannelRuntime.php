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
    ) {
    }

    public function getCurrentChannel(): Channel
    {
        return $this->channelContext->getChannel();
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
     * Read a parameter from the current channel with a fallback default.
     *
     * Returns the default if no channel is resolved (CLI, error pages, etc.).
     */
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
}

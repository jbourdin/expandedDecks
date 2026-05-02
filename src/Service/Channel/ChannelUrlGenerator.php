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

namespace App\Service\Channel;

use App\Entity\Channel;
use App\Repository\ChannelRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generates URLs that point to the correct channel domain.
 *
 * When a link targets a feature hosted on a different channel (e.g. a deck
 * link on the content channel), this service produces an absolute URL with
 * the target channel's domain. Same-channel links return regular paths.
 *
 * @see docs/features.md F18.5 — Channel-aware route generation service
 */
class ChannelUrlGenerator
{
    /** @var array<string, ?Channel> */
    private array $featureCache = [];

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly ChannelRepository $channelRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Generate a URL for a specific channel code.
     *
     * Returns a relative path if the target channel is the current one,
     * or an absolute URL with the target channel's domain otherwise.
     *
     * @param array<string, mixed> $parameters
     */
    public function forChannel(string $channelCode, string $routeName, array $parameters = []): string
    {
        $currentChannel = $this->channelContext->getChannel();

        if ($currentChannel->getCode() === $channelCode) {
            return $this->urlGenerator->generate($routeName, $parameters);
        }

        $targetChannel = $this->channelRepository->findByCode($channelCode);

        if (null === $targetChannel) {
            return $this->urlGenerator->generate($routeName, $parameters);
        }

        return $this->generateAbsoluteUrl($targetChannel, $routeName, $parameters);
    }

    /**
     * Generate a URL targeting the channel that provides a given feature.
     *
     * Supported features: 'decks', 'events', 'borrows', 'register', 'archetypes'.
     *
     * @param array<string, mixed> $parameters
     */
    public function forFeature(string $feature, string $routeName, array $parameters = []): string
    {
        $currentChannel = $this->channelContext->getChannel();

        if ($this->channelHasFeature($currentChannel, $feature)) {
            return $this->urlGenerator->generate($routeName, $parameters);
        }

        $targetChannel = $this->findChannelForFeature($feature);

        if (null === $targetChannel) {
            return $this->urlGenerator->generate($routeName, $parameters);
        }

        return $this->generateAbsoluteUrl($targetChannel, $routeName, $parameters);
    }

    /**
     * Generate an absolute canonical URL for the channel that provides a feature.
     *
     * Unlike forFeature(), this always returns an absolute URL — even when
     * the current channel hosts the feature. Canonical URLs must be absolute
     * and must point to the authoritative channel for the content type.
     *
     * @see docs/features.md F18.25 — Canonical URLs on all public pages
     *
     * @param array<string, mixed> $parameters
     */
    public function canonicalUrl(string $feature, string $routeName, array $parameters = []): string
    {
        $targetChannel = $this->findChannelForFeature($feature);

        if (null === $targetChannel) {
            $targetChannel = $this->channelContext->getChannel();
        }

        return $this->generateAbsoluteUrl($targetChannel, $routeName, $parameters);
    }

    /**
     * Generate an absolute canonical URL on the current channel.
     *
     * Used for pages that are channel-specific (e.g. homepage, CMS pages
     * assigned to a channel) where the canonical is simply the current
     * channel's own domain.
     *
     * @see docs/features.md F18.25 — Canonical URLs on all public pages
     *
     * @param array<string, mixed> $parameters
     */
    public function selfCanonicalUrl(string $routeName, array $parameters = []): string
    {
        return $this->generateAbsoluteUrl($this->channelContext->getChannel(), $routeName, $parameters);
    }

    /**
     * Whether a channel can serve pages for a given feature.
     *
     * When a flag is false, the feature is disabled on that channel and
     * feature_url() will generate a cross-domain link to a channel that has it.
     */
    private function channelHasFeature(Channel $channel, string $feature): bool
    {
        return match ($feature) {
            'decks' => $channel->getEnableDecks(),
            'events' => $channel->getEnableEvents(),
            'borrows' => $channel->getEnableBorrows(),
            'register' => $channel->getEnableRegister(),
            'archetypes' => $channel->getEnableArchetypes(),
            'banned-cards' => $channel->getEnableBannedCards(),
            default => true,
        };
    }

    private function findChannelForFeature(string $feature): ?Channel
    {
        if (\array_key_exists($feature, $this->featureCache)) {
            return $this->featureCache[$feature];
        }

        $channels = $this->channelRepository->findAll();

        foreach ($channels as $channel) {
            if ($this->channelHasFeature($channel, $feature)) {
                $this->featureCache[$feature] = $channel;

                return $channel;
            }
        }

        $this->featureCache[$feature] = null;

        return null;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generateAbsoluteUrl(Channel $targetChannel, string $routeName, array $parameters): string
    {
        $path = $this->urlGenerator->generate($routeName, $parameters);
        $scheme = $this->urlGenerator->getContext()->getScheme();

        return $scheme.'://'.$targetChannel->getDomain().$path;
    }
}

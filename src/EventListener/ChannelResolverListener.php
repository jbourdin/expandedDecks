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

namespace App\EventListener;

use App\Repository\ChannelRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the current Channel from the request's Host header and stores
 * it as a request attribute for ChannelContext to read.
 *
 * Runs at priority 6: after the firewall (priority 8) and before the
 * LocaleListener (priority 4).
 *
 * @see docs/features.md F18.2 — Channel resolver: request-to-channel matching via domain
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class ChannelResolverListener
{
    private const string DEFAULT_CHANNEL_CODE = 'app';

    public function __construct(
        private ChannelRepository $channelRepository,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $host = $event->getRequest()->getHost();
        $channel = $this->channelRepository->findByDomain($host);

        if (null === $channel) {
            $channel = $this->channelRepository->findByCode(self::DEFAULT_CHANNEL_CODE);
        }

        if (null === $channel) {
            throw new \RuntimeException(\sprintf('No channel found for host "%s" and no default channel "%s" exists.', $host, self::DEFAULT_CHANNEL_CODE));
        }

        $event->getRequest()->attributes->set('_channel', $channel);
    }
}

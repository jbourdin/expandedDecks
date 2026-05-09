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
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the current Channel resolved from the request hostname.
 *
 * Follows Sylius's ChannelContext pattern: the ChannelResolverListener
 * stores the resolved Channel as a request attribute, and this service
 * retrieves it from the RequestStack.
 *
 * @see docs/features.md F18.2 — Channel resolver: request-to-channel matching via domain
 */
final readonly class ChannelContext
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Returns the Channel for the current HTTP request.
     *
     * Reads from the *main* request rather than the current one so the
     * channel remains accessible during exception rendering (Symfony's
     * ErrorListener handles errors via a sub-request whose attribute bag
     * is replaced, dropping `_channel`) and inside ESI / render(controller)
     * fragments that inherit the parent request's channel.
     *
     * @throws \LogicException if called outside a request context (e.g. CLI)
     *                         or if no channel was resolved by the listener
     */
    public function getChannel(): Channel
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request) {
            throw new \LogicException('ChannelContext cannot be used outside an HTTP request.');
        }

        $channel = $request->attributes->get('_channel');

        if (!$channel instanceof Channel) {
            throw new \LogicException('No channel resolved for the current request. Is ChannelResolverListener registered?');
        }

        return $channel;
    }
}

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
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @see docs/features.md F18.3 — Twig channel context and global template variables
 */
class ChannelRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ChannelContext $channelContext,
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
}

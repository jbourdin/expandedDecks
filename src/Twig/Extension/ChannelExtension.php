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

namespace App\Twig\Extension;

use App\Twig\Runtime\ChannelRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @see docs/features.md F18.3 — Twig channel context and global template variables
 */
class ChannelExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('current_channel', [ChannelRuntime::class, 'getCurrentChannel']),
            new TwigFunction('is_channel', [ChannelRuntime::class, 'isChannel']),
            new TwigFunction('channel_url', [ChannelRuntime::class, 'channelUrl']),
            new TwigFunction('feature_url', [ChannelRuntime::class, 'featureUrl']),
            new TwigFunction('channel_param', [ChannelRuntime::class, 'channelParam']),
        ];
    }
}

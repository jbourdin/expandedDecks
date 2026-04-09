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

use App\Entity\Channel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Returns 404 for routes whose feature is disabled on the current channel.
 *
 * Runs at priority 5: after the channel resolver (10) and firewall (8),
 * before the locale listener (4). Admin routes and /login are always exempt.
 *
 * @see docs/features.md F18.7 — Feature-gate middleware for deck, event, and borrow routes
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
final readonly class ChannelFeatureGateListener
{
    /** @var list<array{prefix: string, feature: string}> */
    private const array GATE_RULES = [
        ['prefix' => '/deck', 'feature' => 'enableDecks'],
        ['prefix' => '/borrow', 'feature' => 'enableBorrows'],
        ['prefix' => '/borrows', 'feature' => 'enableBorrows'],
        ['prefix' => '/event', 'feature' => 'enableEvents'],
        ['prefix' => '/dashboard', 'feature' => 'enableDecks'],
        ['prefix' => '/register', 'feature' => 'enableRegister'],
    ];

    /** @var list<string> */
    private const array EXEMPT_PREFIXES = [
        '/admin',
        '/login',
        '/profile',
        '/forgot-password',
        '/reset-password',
        '/_',
    ];

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $channel = $request->attributes->get('_channel');

        if (!$channel instanceof Channel) {
            return;
        }

        $path = $request->getPathInfo();

        foreach (self::EXEMPT_PREFIXES as $exempt) {
            if (str_starts_with($path, $exempt)) {
                return;
            }
        }

        foreach (self::GATE_RULES as $rule) {
            if (str_starts_with($path, $rule['prefix']) && !$this->isFeatureEnabled($channel, $rule['feature'])) {
                throw new NotFoundHttpException(\sprintf('Feature "%s" is disabled on channel "%s".', $rule['feature'], $channel->getCode()));
            }
        }
    }

    private function isFeatureEnabled(Channel $channel, string $feature): bool
    {
        return match ($feature) {
            'enableDecks' => $channel->getEnableDecks(),
            'enableEvents' => $channel->getEnableEvents(),
            'enableBorrows' => $channel->getEnableBorrows(),
            'enableRegister' => $channel->getEnableRegister(),
            'enableArchetypes' => $channel->getEnableArchetypes(),
            default => true,
        };
    }
}

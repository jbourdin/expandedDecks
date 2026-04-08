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
use App\Repository\ChannelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the current Channel from the request's Host header and stores
 * it as a request attribute for ChannelContext to read.
 *
 * Runs at priority 10: after the router (priority 32) but before the
 * firewall (priority 8), so that security decisions can be channel-aware.
 *
 * @see docs/features.md F18.2 — Channel resolver: request-to-channel matching via domain
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final readonly class ChannelResolverListener
{
    private const string DEFAULT_CHANNEL_CODE = 'app';

    public function __construct(
        private ChannelRepository $channelRepository,
        private EntityManagerInterface $entityManager,
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
            $channel = $this->createDefaultChannel($host);
        }

        $event->getRequest()->attributes->set('_channel', $channel);
    }

    /**
     * Lazily creates a default channel when none exist in the database.
     *
     * This avoids a hard crash on fresh installs before fixtures have been loaded.
     */
    private function createDefaultChannel(string $host): Channel
    {
        $channel = (new Channel())
            ->setCode(self::DEFAULT_CHANNEL_CODE)
            ->setDomain($host)
            ->setEnableDecks(true)
            ->setEnableRegister(true)
            ->setEnableEvents(true)
            ->setEnableBorrows(true)
            ->setEnableArchetypes(true);

        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        return $channel;
    }
}

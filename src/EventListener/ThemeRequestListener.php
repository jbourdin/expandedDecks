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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Loader\FilesystemLoader;

/**
 * Prepends the channel's theme directory to Twig's loader paths.
 *
 * Runs at priority 9: after the channel resolver (10) and before the
 * firewall (8). If a template exists in the theme directory, Twig uses
 * it instead of the default.
 *
 * @see docs/features.md F18.28 — Per-channel theme system
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
final readonly class ThemeRequestListener
{
    public function __construct(
        #[Autowire(service: 'twig.loader.native_filesystem')]
        private FilesystemLoader $twigLoader,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Reset loader paths to the default templates directory on every request.
        // prependPath() is permanent on the shared FilesystemLoader — without this
        // reset, a theme path prepended for one channel leaks to subsequent requests
        // handled by the same PHP-FPM worker process.
        $defaultPath = $this->projectDir.'/templates';
        $this->twigLoader->setPaths([$defaultPath]);

        $channel = $event->getRequest()->attributes->get('_channel');

        if (!$channel instanceof Channel) {
            return;
        }

        $themeName = $channel->getThemeName();

        if (null === $themeName || '' === $themeName) {
            return;
        }

        $themePath = $this->projectDir.'/templates/themes/'.$themeName;

        if (!is_dir($themePath)) {
            return;
        }

        $this->twigLoader->prependPath($themePath);
    }
}

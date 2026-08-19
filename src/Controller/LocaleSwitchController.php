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

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\User;
use App\Service\Channel\ChannelLocaleVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Switches the session locale and redirects back to the referring page.
 *
 * Used by the navbar locale switcher on non-editorial routes (decks, events,
 * auth, etc.) where the locale is session-based rather than URL-based.
 * On locale-prefixed editorial routes, the switcher links directly to the
 * alternate-locale URL instead of going through this endpoint.
 *
 * @see docs/features.md F18.29 — Locale-prefixed URL routing
 */
class LocaleSwitchController extends AbstractController
{
    /**
     * @see docs/features.md F18.29 — Locale-prefixed URL routing
     */
    #[Route('/locale/{_locale}', name: 'app_locale_switch', requirements: ['_locale' => 'en|fr'], methods: ['GET'])]
    public function __invoke(Request $request, string $_locale, EntityManagerInterface $entityManager, ChannelLocaleVisibility $localeVisibility): RedirectResponse
    {
        // The target must be visible on the current channel: published, or a
        // draft locale the current user is allowed to browse (F9.16).
        $channel = $request->attributes->get('_channel');
        if ($channel instanceof Channel
            && !\in_array($_locale, $channel->getLocales(), true)
            && !$localeVisibility->canSeeDraftLocale($channel, $_locale)) {
            return new RedirectResponse('/');
        }

        $request->getSession()->set('_locale', $_locale);

        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setPreferredLocale($_locale);
            $entityManager->flush();
        }

        $redirect = $request->query->get('_redirect', '/');

        // Prevent open redirects: only allow relative paths
        if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $redirect = '/';
        }

        return new RedirectResponse($redirect);
    }
}

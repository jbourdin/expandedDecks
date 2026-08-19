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
use App\Entity\User;
use App\Service\Channel\ChannelLocaleVisibility;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Sets the request locale based on (in order of priority):
 * 1. Route-level _locale parameter (e.g. /{_locale}/archetypes)
 * 2. Authenticated user's preferredLocale (only consulted when a session cookie is present)
 * 3. Existing session locale (only consulted when a session cookie is present)
 * 4. Accept-Language header detection
 * 5. Default locale (en)
 *
 * Runs after the firewall (priority 8) so the authenticated user is available,
 * and uses LocaleSwitcher to propagate the locale to the Translator and all
 * other locale-aware services (since Symfony's LocaleAwareListener already ran
 * at priority 15).
 *
 * **Session-allocation contract:** this listener never *writes* to the session.
 * `LocaleSwitchController` and `ProfileController` are the only places that
 * persist `_locale` — they fire on an explicit user action, by which point
 * the request has already produced a session cookie. The session is only
 * *read* when a session cookie is present, so anonymous cookieless requests
 * stay 100% session-free here and can be CDN-cached safely.
 *
 * @see docs/features.md F9.1 — User language preference
 * @see docs/features.md F18.29 — Locale-prefixed URL routing
 * @see docs/features.md F9.18 — Admin-managed channel locales
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
class LocaleListener
{
    /**
     * Channel-less requests (admin, API, tests without a resolved channel)
     * have no locale registry to consult; this floor keeps them working.
     */
    private const array FALLBACK_LOCALES = ['en', 'fr'];
    private const string DEFAULT_LOCALE = 'en';

    public function __construct(
        private readonly Security $security,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly ChannelLocaleVisibility $localeVisibility,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $channel = $request->attributes->get('_channel');
        $channelLocales = $channel instanceof Channel ? $channel->getLocales() : self::FALLBACK_LOCALES;

        // Route-level _locale takes precedence (e.g. /{_locale}/archetypes).
        // Symfony's router already set this attribute; honour it so the URL
        // always dictates the rendering language. No session touch here so
        // cookieless visitors stay cacheable.
        $routeLocale = $request->attributes->get('_locale');
        if (\is_string($routeLocale) && '' !== $routeLocale) {
            if (\in_array($routeLocale, $channelLocales, true)) {
                $this->setLocale($request, $routeLocale);

                return;
            }

            // Draft locales (F9.16) are browsable only by the people preparing
            // them; everyone else is redirected to the published equivalent
            // instead of receiving duplicate content under a hidden URL. The
            // visibility check touches the security token, so it is gated on
            // an existing session cookie to keep anonymous requests cacheable.
            if ($channel instanceof Channel && \in_array($routeLocale, $channel->getDraftLocales(), true)
                && $this->hasSessionCookie($request) && $this->localeVisibility->canSeeDraftLocale($channel, $routeLocale)) {
                $this->setLocale($request, $routeLocale);

                return;
            }

            // Unknown locale (the router accepts any ISO-shaped code, F9.18)
            // or unauthorized draft: same treatment, 302 to the published
            // equivalent — never duplicate content under a hidden URL.
            $event->setResponse(new RedirectResponse($this->publishedLocaleUrl($request, $channelLocales), Response::HTTP_FOUND));

            return;
        }

        // Only consult the security token / session bag when a session cookie or
        // remember-me cookie says the visitor has prior state. Without either,
        // we MUST stay off the session entirely (Security::getUser() would touch
        // it via SessionTokenStorage) so the response avoids `Set-Cookie` and
        // can sit behind a CDN.
        if ($this->hasSessionCookie($request)) {
            // Authorized users may hold a draft locale as their session/profile
            // language while working on it (F9.16).
            $allowedLocales = $channel instanceof Channel
                ? [...$channelLocales, ...$this->localeVisibility->visibleDraftLocales($channel)]
                : $channelLocales;

            $user = $this->security->getUser();

            if ($user instanceof User) {
                $this->setLocale($request, $this->constrainToChannel($user->getPreferredLocale(), $allowedLocales));

                return;
            }

            $sessionLocale = $request->getSession()->get('_locale');
            if (\is_string($sessionLocale)) {
                $this->setLocale($request, $this->constrainToChannel($sessionLocale, $allowedLocales));

                return;
            }
        }

        $this->setLocale($request, $this->constrainToChannel(
            $this->detectFromAcceptLanguage($request->headers->get('Accept-Language', ''), $channelLocales),
            $channelLocales,
        ));
    }

    /**
     * Same route, first published locale — where non-authorized visitors of a
     * draft-locale URL land.
     *
     * @param list<string> $channelLocales
     */
    private function publishedLocaleUrl(Request $request, array $channelLocales): string
    {
        $route = $request->attributes->get('_route');
        /** @var array<string, mixed> $routeParams */
        $routeParams = $request->attributes->get('_route_params', []);
        $publishedLocale = $channelLocales[0] ?? self::DEFAULT_LOCALE;

        if (!\is_string($route)) {
            return '/';
        }

        // Query parameters survive the locale swap (unknown route params are
        // emitted as query string by the generator).
        return $this->urlGenerator->generate($route, array_merge($request->query->all(), $routeParams, ['_locale' => $publishedLocale]));
    }

    /**
     * Return the locale if the channel supports it, otherwise the channel's first locale.
     *
     * @param list<string> $channelLocales
     */
    private function constrainToChannel(string $locale, array $channelLocales): string
    {
        if (\in_array($locale, $channelLocales, true)) {
            return $locale;
        }

        return $channelLocales[0] ?? self::DEFAULT_LOCALE;
    }

    private function setLocale(Request $request, string $locale): void
    {
        $request->setLocale($locale);
        $this->localeSwitcher->setLocale($locale);
    }

    private function hasSessionCookie(Request $request): bool
    {
        // Read the configured session cookie name from the storage rather than
        // hard-coding `PHPSESSID`: production uses the PHP default, the test
        // env uses `MOCKSESSID` via the mock file session factory, and any
        // future `framework.session.name` override would silently break a
        // literal check. Session::getName() does NOT start the session — it
        // only returns the cookie name from the storage's metadata bag.
        $sessionName = $request->getSession()->getName();

        return $request->cookies->has($sessionName) || $request->cookies->has('REMEMBERME');
    }

    /**
     * @param list<string> $channelLocales
     */
    private function detectFromAcceptLanguage(string $header, array $channelLocales): string
    {
        if ('' === $header) {
            return self::DEFAULT_LOCALE;
        }

        $best = self::DEFAULT_LOCALE;
        $bestQ = 0.0;

        foreach (explode(',', $header) as $part) {
            $segments = explode(';', trim($part));
            $lang = strtolower(trim($segments[0]));
            $q = 1.0;

            foreach ($segments as $segment) {
                $segment = trim($segment);
                if (str_starts_with($segment, 'q=')) {
                    $q = (float) substr($segment, 2);
                }
            }

            // Match primary language subtag (e.g., "fr-FR" → "fr")
            $primary = explode('-', $lang)[0];

            if (\in_array($primary, $channelLocales, true) && $q > $bestQ) {
                $bestQ = $q;
                $best = $primary;
            }
        }

        return $best;
    }
}

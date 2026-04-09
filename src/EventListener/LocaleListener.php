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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Sets the request locale based on (in order of priority):
 * 1. Authenticated user's preferredLocale
 * 2. Existing session locale
 * 3. Accept-Language header detection
 * 4. Default locale (en)
 *
 * Runs after the firewall (priority 8) so the authenticated user is available,
 * and uses LocaleSwitcher to propagate the locale to the Translator and all
 * other locale-aware services (since Symfony's LocaleAwareListener already ran
 * at priority 15).
 *
 * @see docs/features.md F9.1 — User language preference
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
class LocaleListener
{
    private const array SUPPORTED_LOCALES = ['en', 'fr'];
    private const string DEFAULT_LOCALE = 'en';

    public function __construct(
        private readonly Security $security,
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $channel = $request->attributes->get('_channel');
        $channelLocales = $channel instanceof Channel ? $channel->getLocales() : self::SUPPORTED_LOCALES;

        $user = $this->security->getUser();

        if ($user instanceof User) {
            $locale = $this->constrainToChannel($user->getPreferredLocale(), $channelLocales);
            $this->applyLocale($request, $locale);

            return;
        }

        $sessionLocale = $request->getSession()->get('_locale');
        if (\is_string($sessionLocale) && \in_array($sessionLocale, self::SUPPORTED_LOCALES, true)) {
            $locale = $this->constrainToChannel($sessionLocale, $channelLocales);
            $this->applyLocale($request, $locale);

            return;
        }

        $locale = $this->constrainToChannel(
            $this->detectFromAcceptLanguage($request->headers->get('Accept-Language', '')),
            $channelLocales,
        );
        $this->applyLocale($request, $locale);
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

    private function applyLocale(Request $request, string $locale): void
    {
        $request->setLocale($locale);
        $request->getSession()->set('_locale', $locale);
        $this->localeSwitcher->setLocale($locale);
    }

    private function detectFromAcceptLanguage(string $header): string
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

            if (\in_array($primary, self::SUPPORTED_LOCALES, true) && $q > $bestQ) {
                $bestQ = $q;
                $best = $primary;
            }
        }

        return $best;
    }
}

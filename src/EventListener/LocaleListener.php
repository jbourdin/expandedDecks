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

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the request locale based on (in order of priority):
 * 1. Authenticated user's preferredLocale
 * 2. Existing session locale
 * 3. Accept-Language header detection
 * 4. Default locale (en)
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
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $user = $this->security->getUser();

        if ($user instanceof User) {
            $locale = $user->getPreferredLocale();
            $request->setLocale($locale);
            $request->getSession()->set('_locale', $locale);

            return;
        }

        $sessionLocale = $request->getSession()->get('_locale');
        if (\is_string($sessionLocale) && \in_array($sessionLocale, self::SUPPORTED_LOCALES, true)) {
            $request->setLocale($sessionLocale);

            return;
        }

        $locale = $this->detectFromAcceptLanguage($request->headers->get('Accept-Language', ''));
        $request->setLocale($locale);
        $request->getSession()->set('_locale', $locale);
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

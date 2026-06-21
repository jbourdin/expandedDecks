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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds baseline security/trust response headers to every main response.
 *
 * The "safe" headers (nosniff, Referrer-Policy, frame protection,
 * Permissions-Policy, and HSTS on secure requests) are enforced. The
 * Content-Security-Policy ships in REPORT-ONLY mode: the app still renders
 * inline `<head>` scripts (theme color scheme, etc.), so a strict script-src
 * would break them — report-only surfaces what needs a nonce before we can
 * enforce. Enforcement is a tracked follow-up.
 *
 * @see docs/standards/security.md
 * @see docs/features.md F19.9 — Security/trust response headers
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class SecurityHeadersListener
{
    /**
     * HSTS lifetime. Intentionally moderate to start; ramp up (and consider
     * preload) once we are confident every subdomain is HTTPS-only.
     */
    private const int HSTS_MAX_AGE = 86400;

    public function __construct(
        // Optional CSP violation sink (e.g. a Sentry security endpoint). Null
        // by default — when unset, no report-uri is emitted.
        #[Autowire('%env(default::SECURITY_CSP_REPORT_URI)%')]
        private ?string $cspReportUri = null,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $headers = $event->getResponse()->headers;

        // Safe on every response.
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Deny powerful features by default. Camera is denied too: there is no
        // live camera QR scanner yet. When one ships it should re-enable camera
        // as `camera=(self)` on the app channel only (the content channel never
        // needs it).
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        // HSTS only over HTTPS (browsers ignore it on plain HTTP anyway).
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age='.self::HSTS_MAX_AGE.'; includeSubDomains');
        }

        // CSP only meaningfully applies to HTML documents; ship report-only.
        // A rendered HTML Response has no Content-Type yet at kernel.response
        // (the text/html default is applied later in prepare()), so treat an
        // empty/absent type as HTML. JSON/XML/image responses set theirs
        // explicitly and are excluded.
        $contentType = (string) $headers->get('Content-Type', '');
        if ('' === $contentType || str_contains($contentType, 'text/html')) {
            $headers->set('Content-Security-Policy-Report-Only', $this->buildContentSecurityPolicy());
        }
    }

    private function buildContentSecurityPolicy(): string
    {
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            // Strict on scripts (where injection matters): inline <head> scripts
            // will be reported until they carry a nonce — that is the signal we
            // want before enforcing.
            "script-src 'self'",
            // Bootstrap/Mantine inject inline styles; lower risk, allowed for now.
            "style-src 'self' 'unsafe-inline'",
            // Card art (TCGdex), sprites, editor uploads, and CDN come from many
            // hosts; broad for the report-only baseline.
            'img-src \'self\' data: https:',
            "font-src 'self' data:",
            "connect-src 'self' https:",
        ];

        if (null !== $this->cspReportUri && '' !== $this->cspReportUri) {
            $directives[] = 'report-uri '.$this->cspReportUri;
        }

        return implode('; ', $directives);
    }
}

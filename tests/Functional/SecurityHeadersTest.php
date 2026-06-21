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

namespace App\Tests\Functional;

/**
 * @see docs/features.md F19.9 — Security/trust response headers
 */
class SecurityHeadersTest extends AbstractFunctionalTest
{
    public function testPublicHtmlResponseCarriesSecurityHeaders(): void
    {
        // /login is a stable public HTML page (no channel/locale redirect).
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $headers = $this->client->getResponse()->headers;

        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        self::assertSame('SAMEORIGIN', $headers->get('X-Frame-Options'));

        // All powerful features denied by default (no live camera scanner yet).
        self::assertStringContainsString('camera=()', (string) $headers->get('Permissions-Policy'));
        self::assertStringContainsString('geolocation=()', (string) $headers->get('Permissions-Policy'));

        // CSP ships report-only (non-blocking); the enforcing header must be absent.
        $csp = (string) $headers->get('Content-Security-Policy-Report-Only');
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("script-src 'self'", $csp);
        self::assertStringContainsString("frame-ancestors 'self'", $csp);
        self::assertNull($headers->get('Content-Security-Policy'));
    }
}

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

namespace App\Service\Security;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Per-request CSP nonce, shared between the templates (inline `<script
 * nonce>` attributes via the `csp_nonce()` Twig function) and the
 * Content-Security-Policy response header. Stored on the main request's
 * attributes so the same value is guaranteed across both consumers and
 * never leaks between requests.
 *
 * @see docs/features.md F19.9 — Security/trust response headers
 */
final readonly class ContentSecurityPolicyNonceProvider
{
    private const string REQUEST_ATTRIBUTE = '_csp_script_nonce';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function getNonce(): string
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            // No request context (CLI rendering, e.g. emails): a throwaway
            // value is fine — there is no CSP header to match against.
            return base64_encode(random_bytes(16));
        }

        $nonce = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        if (!\is_string($nonce)) {
            $nonce = base64_encode(random_bytes(16));
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $nonce);
        }

        return $nonce;
    }
}

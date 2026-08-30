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

namespace App\Twig\Extension;

use App\Service\Security\ContentSecurityPolicyNonceProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `csp_nonce()`: the per-request nonce inline `<script>` tags must carry to
 * satisfy the Content-Security-Policy script-src directive.
 *
 * @see docs/features.md F19.9 — Security/trust response headers
 */
class SecurityExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContentSecurityPolicyNonceProvider $nonceProvider,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', $this->nonceProvider->getNonce(...)),
        ];
    }
}

/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Read the per-session CSRF token rendered for authenticated users in
 * `<meta name="csrf-token">` (see base.html.twig). Sent as the `X-CSRF-Token`
 * header on state-changing fetch() calls and validated server-side with the
 * `ajax` token id (see AjaxCsrfTrait).
 */
export const csrfToken = (): string =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/**
 * Header object to spread into a fetch() `headers` for authenticated,
 * state-changing requests: `headers: { ...csrfHeader(), ... }`.
 */
export const csrfHeader = (): Record<string, string> => ({ 'X-CSRF-Token': csrfToken() });

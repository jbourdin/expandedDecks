# Error Pages

> **Audience:** Developer, AI Agent · **Scope:** Technical Reference

← Back to [Main Documentation](../docs.md)

## Overview

Custom error pages with Pokemon-themed messages and sprites. Errors are handled differently depending on the request type and environment.

## Request Type Handling

The `ExceptionListener` (`src/EventListener/ExceptionListener.php`) intercepts all exceptions at priority -64 and routes them by format:

| Request type | Response |
|---|---|
| **XHR / JSON** | JSON body `{"error": "...", "status": 429}`. In non-prod, includes `message` and `trace` fields. Correct HTTP status code. |
| **Non-HTML** (images, CSS, fonts) | Empty body with correct HTTP status code. |
| **HTML (dev/test)** | Renders `templates/exception/dev.html.twig` — Pokemon sprite + message, then exception class, file:line, and full stack trace. |
| **HTML (prod)** | Falls through to Symfony's `TwigErrorRenderer` which uses `templates/bundles/TwigBundle/Exception/error.html.twig` — Pokemon sprite + translated message + "Back to home" button. |

## Error Page Sprites & Messages

| Code | Pokemon | Sprite file | EN message |
|---|---|---|---|
| 403 | Snorlax | `snorlax.png` | "This area is blocked by a Snorlax. You don't have permission to pass!" |
| 404 | Ditto | `ditto.png` | "Ditto tried to transform into this page, but it doesn't seem to exist!" |
| 429 | Maushold | `maushold-family-of-four.png` | "Too many requests! The Maushold family keeps inviting themselves in." |
| 500 | Porygon | `porygon.png` | "A wild bug appeared! Our team is working on catching it." |
| Other | Psyduck | `psyduck.png` | "Something unexpected happened. The attack missed!" |

Translation keys: `app.error.{403,404,429,500,generic,back_home}` in `translations/messages.{en,fr}.xlf`.

## Templates

| Template | Purpose |
|---|---|
| `templates/base_error.html.twig` | Lightweight base layout for error pages. Mirrors `base.html.twig` visually (navbar, footer, Bootstrap CSS via Encore) but has **no service dependencies** — no `menu_categories()`, `path()`, `gravatar()`, or user session checks. This prevents cascading failures when the error itself is caused by a broken service. |
| `templates/bundles/TwigBundle/Exception/error.html.twig` | Prod error page. Extends `base_error.html.twig`. Shows status code, status text, sprite, translated message, and home link. |
| `templates/exception/dev.html.twig` | Dev error page. Extends `base_error.html.twig`. Shows the same sprite + message, then exception details (class, file:line, previous exception, stack trace). |

## Test & CDN Routes

| Route | Controller | Purpose | HTTP status |
|---|---|---|---|
| `/test-error/{code}` | `TestErrorController` | Throws a real `HttpException`. For dev testing. **Logs to Sentry.** | The given error code |
| `/cdn-error/{code}` | `CdnErrorController` | Renders the prod error template without throwing. For Bunny CDN to fetch and cache. **Does not log to Sentry.** | 200 |

## CDN Integration

Bunny CDN can serve custom error pages when it triggers errors at the edge (rate limiting, WAF blocks). It fetches the page from `/cdn-error/{code}` on the origin, caches the 200 response body, and serves it with the actual error status code.

See `expandedDecksInfra/configureError.md` for CDN configuration details.

## Sentry

Sentry's exception listener runs at a higher priority than `ExceptionListener`. Real exceptions are always captured by Sentry before the error page is rendered. The `/cdn-error/{code}` route does not throw exceptions and therefore does not trigger Sentry.

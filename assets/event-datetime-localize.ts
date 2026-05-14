/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Localize event datetimes to the visitor's browser timezone.
 *
 * Twig renders each event date/time inside `<time datetime="…" data-event-timezone="…">` in the
 * event's own timezone. When the visitor's browser timezone differs, this script rewrites the
 * visible text to the browser-local form and exposes the original event-timezone string via the
 * `title` attribute so the canonical event time remains discoverable on hover.
 *
 * @see docs/features.md F3.25 — Browser-local datetime on event pages
 */

function detectBrowserTimezone(): string | null {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
    } catch {
        return null;
    }
}

function buildFormatOptions(timezone: string, dateOnly: boolean): Intl.DateTimeFormatOptions {
    const baseOptions: Intl.DateTimeFormatOptions = {
        timeZone: timezone,
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    };
    if (dateOnly) {
        return baseOptions;
    }
    return {
        ...baseOptions,
        hour: '2-digit',
        minute: '2-digit',
    };
}

function localize(): void {
    const browserTimezone = detectBrowserTimezone();
    if (browserTimezone === null) {
        return;
    }

    const locale = document.documentElement.lang || 'en';
    const elements = document.querySelectorAll<HTMLTimeElement>('time[data-event-timezone]');

    elements.forEach((element) => {
        const eventTimezone = element.dataset.eventTimezone;
        const isoDatetime = element.getAttribute('datetime');
        if (!eventTimezone || !isoDatetime || eventTimezone === browserTimezone) {
            return;
        }

        const parsed = new Date(isoDatetime);
        if (Number.isNaN(parsed.getTime())) {
            return;
        }

        const dateOnly = element.hasAttribute('data-date-only');
        const eventLocal = element.textContent?.trim() ?? '';
        const formattedDateTime = new Intl.DateTimeFormat(locale, buildFormatOptions(browserTimezone, dateOnly)).format(parsed);
        const browserLocal = `${formattedDateTime} ${browserTimezone}`;

        element.textContent = browserLocal;
        if (eventLocal !== '') {
            element.setAttribute('title', eventLocal);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', localize, { once: true });
} else {
    localize();
}

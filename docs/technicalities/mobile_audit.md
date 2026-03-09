# Mobile UX Audit — F10.1

> **Audience:** Developer · **Scope:** Frontend / UX

← Back to [Main Documentation](../docs.md) | [CLAUDE.md](../../CLAUDE.md)

## Context

The application uses Bootstrap 5 with no custom media queries (`app.scss` has zero `@media` rules). All responsive behavior relies on Bootstrap defaults. This audit identifies mobile usability issues and proposes fixes.

**Tested viewport:** 375px (iPhone SE / small Android) and 768px (tablet).

---

## Issues

### M1 — Borrow inbox tables are unusable on small screens

**Severity:** High
**Affected:** `templates/borrow/_inbox.html.twig`

The borrow inbox renders a `<table>` with 5 columns (deck name, borrower/lender, dates, status, actions) plus up to 3 action buttons per row. On mobile, `table-responsive` enables horizontal scrolling, but users must scroll right to see the action buttons — the most important interactive element.

**Fix:**
- Replace the table with a stacked card layout on mobile using Bootstrap's `d-md-table` / `d-md-none` pattern: render a card list for `< md` and the table for `>= md`.
- Each card shows deck name as title, status as badge, dates below, and action buttons full-width at the bottom.

---

### M2 — Dashboard borrow/lend tables repeat the same problem

**Severity:** High
**Affected:** `templates/home/dashboard.html.twig`

The dashboard shows 4 tables (pending borrows, active borrows, pending lends, active lends) all with horizontal scroll. A user managing borrows on mobile must scroll horizontally in each table independently.

**Fix:**
- Same card-based layout as M1 for `< md` breakpoint.
- Consider collapsing the 4 sections into a tabbed interface on mobile to reduce vertical scrolling.

---

### M3 — Deck catalog filter bar takes excessive vertical space

**Severity:** Medium
**Affected:** `templates/deck/list.html.twig`

The filter bar has 5 inputs in a `row` with `col-md-3` / `col-md-2` classes. On mobile (`< md`), each input stacks to full width, consuming ~300px of vertical space before any deck card is visible.

**Fix:**
- Wrap filters in a collapsible `<details>` or Bootstrap `collapse` component on mobile, with a "Filters" toggle button.
- Show only the search input by default; other filters (archetype, format, status, sort) collapse behind the toggle.

---

### M4 — Card hover images overflow viewport

**Severity:** Medium
**Affected:** `templates/deck/show.html.twig`, CSS `.card-hover-img`

Card names in the deck list table show a Pokemon card image on hover via absolutely-positioned `.card-hover-img`. On mobile:
1. Hover doesn't exist (touch devices) — images never appear.
2. If triggered (long-press or hybrid device), the 250px-wide image can overflow the viewport edge.

**Fix:**
- On touch devices (`< md`), replace hover with a tap-to-show modal or popover centered on screen.
- Use Bootstrap's `d-none d-md-block` on the hover image container and add a tap icon/button for mobile that opens a centered overlay.

---

### M5 — Action buttons too small for touch targets

**Severity:** Medium
**Affected:** All templates using `btn-sm`

`btn-sm` renders at ~30-32px height. Apple's HIG recommends 44px minimum touch targets. Buttons like "Accept", "Decline", "Return" in borrow rows are difficult to tap accurately.

**Fix:**
- Use `btn-sm` only for `>= md` breakpoint. On mobile, use default button sizing.
- Alternatively, add `py-2` padding to action buttons on mobile via a utility class conditional on breakpoint.

---

### M6 — Event show page information density

**Severity:** Medium
**Affected:** `templates/event/show.html.twig`

The event detail page packs event info tables, borrow request sections, staff management, and engagement lists into a single long page. On mobile, the info tables scroll horizontally, and the page requires extensive vertical scrolling.

**Fix:**
- Use accordion sections (`<details>` or Bootstrap accordion) for secondary content (staff list, engagement details).
- Convert the event info key-value table to a stacked definition list (`<dl>`) layout on mobile.

---

### M7 — Notification bell popover overflows narrow screens

**Severity:** Low
**Affected:** `assets/components/NotificationBell.tsx` (React island in `base.html.twig`)

The Mantine `Popover` rendering the notification list has a fixed width around 360px. On a 375px viewport, this leaves almost no margin and may clip or overflow.

**Fix:**
- Set the popover width to `min(360px, calc(100vw - 32px))` or use Mantine's `width="target"` prop on mobile.
- Alternatively, render notifications as a full-screen drawer (`Drawer` component) on `< md`.

---

### M8 — Dashboard stat cards cramped at col-6

**Severity:** Low
**Affected:** `templates/home/dashboard.html.twig`

Stats cards use `col-6 col-md-3`, showing 2 per row on mobile. With large numbers or long labels (e.g., "Pending Requests: 12"), text may wrap awkwardly or overflow the card.

**Fix:**
- Switch to `col-12 col-sm-6 col-md-3` so stats stack vertically on the smallest screens.
- Or keep `col-6` but add `text-truncate` and a tooltip for overflow.

---

## Implementation Plan

| Priority | Issues | Effort | Approach |
|----------|--------|--------|----------|
| 1 | M1, M2 | Medium | Create a `_borrow_card.html.twig` partial for mobile card layout; use `d-none d-md-block` / `d-md-none` to swap between table and cards |
| 2 | M3 | Low | Add Bootstrap collapse around filter inputs with a toggle button |
| 3 | M5 | Low | Add responsive padding utility or conditional button sizing |
| 4 | M4 | Medium | Replace hover images with tap-to-show modal on mobile |
| 5 | M6 | Medium | Accordion sections for event show page secondary content |
| 6 | M7 | Low | Responsive popover width or drawer on mobile |
| 7 | M8 | Low | Adjust grid classes for stat cards |

### Guiding Principles

- **No custom media queries** — continue using Bootstrap utility classes and responsive breakpoints exclusively.
- **Progressive enhancement** — desktop experience stays unchanged; mobile gets adapted layouts.
- **Touch-first** — all interactive elements must meet 44px minimum touch target on mobile.
- **Minimize JS** — prefer CSS/Bootstrap solutions over JavaScript for responsive behavior.

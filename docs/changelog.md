# Changelog

> **Audience:** Developer, AI Agent · **Scope:** Reference

← Back to [Main Documentation](docs.md) | [README](../README.md)

All notable changes to this project are documented in this file.
Format inspired by [Keep a Changelog](https://keepachangelog.com/).

Features reference the [Feature List](features.md) by ID.
Items marked *(partial)* have scaffolding or basic functionality but are not yet complete end-to-end.

---

## [Unreleased]

*Nothing yet.*

---

## [0.4.0] — 2026-03-05

Phase 7 progress — Engagement, Results & Discovery: event lifecycle completion, visibility controls, engagement states, event discovery, event notifications with per-type user preferences.

### Event Management

- **F3.7** — Register played deck for event *(completed)*: tournament result fields (`placement`, `matchRecord`) on `EventDeckEntry`, with full CRUD on the event show page.
- **F3.10** — Cancel an event *(completed)*: cancellation action with async cascading borrow cancellation via Messenger (`CancelEventBorrowsMessage`). Pending and approved borrows are cancelled; lent/overdue borrows are preserved.
- **F3.11** — Event visibility: public, private, and invitation-only events. `EventVisibility` enum with `visibility` column on `Event`. Invitation-only events restrict player registration to invited users.
- **F3.13** — Player engagement states *(completed)*: full engagement lifecycle — interested, registered (playing/spectating), invited, withdrawn. Invitation-only flag, invite action for organizers, and preserved invitation status across mode switches.
- **F3.15** — Event discovery: public discovery page listing upcoming public events with search, available to all users (including unauthenticated).
- **F3.20** — Mark event as finished *(completed)*: finish action sets `finishedAt`, blocks new borrow requests and registrations for finished events.

### Notifications

- **F8.2** — Event notifications *(completed)*: email and in-app notifications for staff assignment, event updates, event cancellation, and user invitations. Templated emails with recipient locale support.
- **F8.3** — Notification preferences: per-type email/in-app settings on `/profile/notifications`. JSON column on `User` (null = all enabled, backwards-compatible). Checkbox table grouped by category (Borrow / Event) with column-toggle headers. All notification services (`BorrowService`, `BorrowNotificationEmailService`, `EventNotificationService`, `StaffCustodyService`) check user preferences before sending.

### Cross-Cutting

- Notification preferences link added to user dropdown menu
- EN + FR translations for all new features (~350 new translation keys)
- 425 tests, PHPStan level 10

---

## [0.3.0] — 2026-03-04

Phase 6 — Localization: multi-language support (en/fr), timezone-aware display, user profile page, and Gravatar navbar avatar.

### Localization

- **F9.1** — User language preference: Symfony locale listener detects user locale from session, user preference, or `Accept-Language` header. All UI strings render in the active locale.
- **F9.2** — User timezone display: `user_datetime` / `user_date` Twig filters convert UTC timestamps to the user's timezone with locale-aware formatting via `IntlDateFormatter`. Event dates display in the event's timezone with a tooltip showing the user's local time when different.
- **F9.3** — Application translation: all ~300 user-facing strings extracted to XLIFF catalogues (`messages.en.xlf`, `messages.fr.xlf`). Covers templates, controller flash messages, form labels, and email templates/subjects. Emails render in the recipient's preferred locale.
- **F9.4** — UTC datetime storage: event form uses `model_timezone` / `view_timezone` for automatic UTC conversion. PHP timezone set to UTC via `.symfony.local.yaml`.

### User Management

- **F1.3** — User profile page: edit screen name, player ID, preferred locale, and timezone. Locale changes apply immediately via `LocaleSwitcher`.
- **F1.11** — Gravatar avatar & navbar dropdown: 32px Gravatar avatar (64px Retina source) in the navbar with a Bootstrap dropdown menu (Dashboard, Profile, Logout).

### Cross-Cutting

- Abstract base controller (`AbstractAppController`) with auto-translating `addFlash()` for all controllers
- Bootstrap Icons and tooltip initialization for timezone display
- 363 tests, PHPStan level 10

---

## [0.2.0] — 2026-03-04

Borrow workflow maturity: staff custody chain, conflict management, owner inbox, and UI refinements.

### Deck Library

- **F2.13** — Inline deck list import on creation: owners can import a deck list directly during deck creation, removing the need for a separate import step.

### Event Management

- **F3.21** — Clear deck selection on withdrawal: when a participant withdraws from an event or switches participation mode, their deck selection is automatically cleared.
- **F4.13** — Event-scoped autocompletes: user search fields in staff assignment and borrow workflows are scoped to event participants for faster results.

### Borrow Workflow

- **F4.5** — Borrow history: paginated borrow and lend lists with full deck detail (was *(partial)* in 0.1.0).
- **F4.9** — Staff deck custody tracking: staff members assigned to an event can manage delegated decks on the event page. Delegated staff can also cancel borrows on behalf of the deck owner.
- **F4.10** — Owner borrow inbox: grouped-by-event layout with inline approve/deny/cancel actions (was *(partial)* in 0.1.0).
- **F4.11** — Multiple pending borrow requests: a deck can receive multiple pending borrow requests per event, allowing the owner to compare and choose. When a borrow is approved or a walk-up lend is created, all other pending borrows for the same deck at that event are automatically declined via `DeclineCompetingBorrowsMessage` (async `borrow_lifecycle` Messenger transport).
- **F4.14** — Staff custody handover tracking: owners confirm handing a delegated deck to staff; staff confirm returning it. Tracks `staffReceivedAt`/`staffReceivedBy` and `staffReturnedAt`/`staffReturnedBy` on `EventDeckRegistration`. Full chain-of-custody visibility: owner → staff → borrower → staff → owner. Guard conditions: staff cannot hand off or walk-up lend a delegated deck until the owner confirms physical handover; delegation cannot be revoked while the deck is with staff.
- **F4.14** — Custody return rules: staff cannot mark a deck as returned to owner while it is currently lent to a borrower (must collect it first). When staff returns the deck, remaining active borrows (returned, pending, approved) are auto-closed. New owner reclaim action: the owner can mark "returned to me" at any time, closing both the custody tracking and all active borrows (including lent/overdue) in one step. Borrowers with active lent/overdue borrows are notified.
- **F4.14** — Deck selection UI: own decks that are currently lent or handed over to staff are shown as disabled rows in the Deck Selection card (with "Lent" / "With staff" badges) instead of being hidden. A "Browse decks" link invites the owner to borrow an alternative.

### Dashboard & Homepage

- **F7.1** — Dashboard: "See all" link added to My Decks section.

### Cross-Cutting

- Transaction rollback for functional test isolation (performance improvement)
- PHPUnit test suite expanded (46+ test methods, 1 600+ assertions)

---

## [0.1.0] — 2026-03-03

First tagged release. Covers the core domain: authentication, deck library, event management, full borrow workflow with notifications, and card data pipeline.

### Auth & Foundation

- **F1.1** — User registration & authentication (email, screen name, player ID, target-path redirect)
- **F1.2** — Email verification (token-based activation link)
- **F1.4** — Role-based access control (admin, organizer, player, per-event staff)
- **F1.7** — Password reset (tokenized email flow)
- **F9.4** — UTC datetime storage

### Deck Library

- **F2.1** — Register a deck (name, archetype, format, auto-generated short tag)
- **F2.2** — Import deck list via copy-paste (PTCG text format, parsed & validated)
- **F2.3** — Deck detail view (card list, availability, card image hovers, public short-tag URL)
- **F2.4** — Deck catalog (browse, search, archetype/event/owner filters, paginated)
- **F2.5** — Deck availability status (available, lent, reserved, retired)
- **F2.8** — Update deck list (new version, preserves history)
- **F2.6** — Deck archetype management *(partial)* — name/slug catalogue with autocomplete; published descriptions, sprites, and editor role not yet implemented

### Event Management

- **F3.1** — Create an event (full form with tournament structure, entry fee, sync CTA placeholder)
- **F3.2** — Event listing (upcoming/past, publicly accessible)
- **F3.3** — Event detail view (tournament info, borrow requests, deck assignments)
- **F3.4** — Register participation (playing or spectating modes)
- **F3.5** — Assign event staff team (multi-field search with autocomplete)
- **F3.9** — Edit an event
- **F3.7** — Register played deck for event *(partial)* — `EventDeckEntry` creation works; placement and match record entry not yet implemented
- **F3.10** — Cancel an event *(partial)* — cancellation with cascading pre-handoff borrows; UI polish pending
- **F3.20** — Mark event as finished *(partial)* — sets `finishedAt`; overdue triggers not yet wired

### Borrow Workflow

- **F4.1** — Request to borrow a deck for an event
- **F4.2** — Approve / deny borrow request
- **F4.3** — Confirm deck hand-off (lend) — manual owner/staff confirmation
- **F4.4** — Confirm deck return
- **F4.7** — Cancel a borrow request (borrower or owner)
- **F4.8** — Staff-delegated lending (per-deck, per-event opt-in)
- **F4.11** — Borrow conflict detection (hard block on overlapping approved/lent, soft warning on pending)
- **F4.12** — Walk-up lending (direct lend at event, skips request/approval)
- **F4.5** — Borrow history *(partial)* — per-deck history visible; per-user history view not yet built *(completed in 0.2.0)*
- **F4.10** — Owner borrow inbox *(partial)* — basic view exists; grouped-by-event layout pending *(completed in 0.2.0)*

### Card Data & Validation

- **F6.1** — Parse PTCG text format (PHP `DeckListParser`, regex-based)
- **F6.2** — Card validation via TCGdex (async Messenger enrichment pipeline)
- **F6.3** — Expanded format validation (Black & White onward + ban list)
- **F6.4** — Display card images (high-res from TCGdex, hover overlay, energy fallbacks)

### Notifications

- **F8.1** — Borrow workflow notifications (email + in-app at each state transition)
- **F8.2** — Event notifications *(partial)* — scaffolding exists; full engagement-state triggers pending

### Dashboard & Homepage

- **F7.1** — Dashboard *(partial)* — basic layout with staffing and events cards; full widget set pending
- **F10.2** — Anonymous homepage *(partial)* — public landing with event list and deck catalog CTAs; full design pending

### Infrastructure

- **F1.8** — Account deletion & data export *(partial)* — soft-delete with anonymization scaffolded; confirmation email and JSON export not yet implemented
- **F2.7** — Retire / reactivate a deck *(partial)* — status transitions exist; UI controls pending
- **F9.1** — User language preference *(partial)* — locale field on User entity; preference UI and full i18n not yet applied *(completed in 0.3.0)*
- **F9.2** — User timezone *(partial)* — timezone field on User entity; display conversion not yet applied *(completed in 0.3.0)*

### Cross-Cutting

- PHPUnit test suite (unit + functional, 34+ test methods)
- PHP coverage reporting in CI (pcov + GitHub Action PR comments)
- Vitest frontend unit tests (@testing-library/react)
- PHPStan level 10, PHP-CS-Fixer @Symfony ruleset
- Docker Compose development environment (MySQL 8)

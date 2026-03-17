# Changelog

> **Audience:** Developer, AI Agent · **Scope:** Reference

← Back to [Main Documentation](docs.md) | [README](../README.md)

All notable changes to this project are documented in this file.
Format inspired by [Keep a Changelog](https://keepachangelog.com/).

Features reference the [Feature List](features.md) by ID.
Items marked *(partial)* have scaffolding or basic functionality but are not yet complete end-to-end.

---

## [Unreleased]

---

## [1.0.0-beta.4] — 2026-03-17

Fourth beta — Sentry noise reduction and favicon redirect.

### Bug Fixes

- **Sentry AccessDeniedException filter** — `BeforeSendCallback` now drops `Symfony\Component\Security\Core\Exception\AccessDeniedException`, which bypassed the existing `HttpExceptionInterface` 4xx filter because it is thrown before the kernel converts it to a 403.
- **Favicon redirect** — added a 301 redirect from `/favicon.ico` to `/favicon.svg` to eliminate 404 noise from browsers and bots requesting the default favicon path.
- **Favicon route fix** — removed ambiguous empty `route` default that caused a `RuntimeException` in `RedirectController`.

### Testing & Quality

- Unit test for `AccessDeniedException` filtering in `BeforeSendCallback`.

---

## [1.0.0-beta.3] — 2026-03-17

Third beta — production observability improvements and version tracking.

### Infrastructure

- **Sentry 4xx suppression** — `BeforeSendCallback` drops all HTTP 4xx exceptions from Sentry issues. Monolog `excluded_http_codes` expanded to cover all common 4xx codes (400, 401, 403, 404, 405, 409, 410, 422, 429). Sentry structured logs (`sentry_logs`) wrapped in `fingers_crossed` handlers (buffering from info, triggering on error) with the same 4xx exclusions.
- **Sentry structured logs** — enabled via `enable_logs: true` in sentry-symfony config.
- **Sentry smoke-test routes** — `/health/sentry-logs` and `/health/sentry-error` for manual verification of Sentry integration.
- **Custom error page** — branded error template for 403, 404, and 500 responses.
- **Static favicon** — gray Fairy-type energy SVG at `public/favicon.svg`, eliminating 404 noise from browser requests.
- **APP_VERSION env var** — set at Docker build time via `--build-arg APP_VERSION=$(git describe --tags --always)`. Used as Sentry `release` and displayed in the footer.

### Documentation

- Full documentation consistency audit (Symfony/React version references, feature IDs).

### Testing & Quality

- Unit tests for `BeforeSendCallback` (4xx drop, 5xx keep, null hint edge cases).
- Banned cards sync service extracted and tested (`BannedCardsSyncService`).
- Test quality: replaced mocks with stubs where no expectations are set.

---

## [1.0.0-beta.2] — 2026-03-16

Second beta — deployment hardening, production observability, and infrastructure improvements. Sentry integration, Doctrine-based async messaging, APCu caching, technical admin dashboard, and container fixes.

### Infrastructure

- **F14.1–F14.6** — Deployment readiness features *(completed)*: per-transport Messenger DSN configuration, configurable session storage (database-backed by default), health check endpoints (liveness + readiness), production multi-stage Dockerfile with FrankenPHP, configurable mail sender and admin email. Interactive `app:create-admin` console command for initial setup.
- **F14.7** — Sentry error tracking *(new)*: `sentry/sentry-symfony` integration for production error tracking. `SENTRY_DSN` env var controls the connection (empty = disabled). Captures unhandled exceptions, Messenger worker errors, and Monolog error-level logs. Performance tracing configurable via `SENTRY_TRACES_SAMPLE_RATE` (default: 0). Disabled in dev/test.
- Switched async messaging from SQS webhook to Doctrine transport + cron job — eliminates external queue dependency.
- APCu cache adapter in production for in-process caching with FrankenPHP workers.
- Trusted `X-Forwarded-Host` header from CDN proxy.
- Multiple Dockerfile fixes for serverless container deployment.
- Database-backed sessions in all environments for horizontal scaling.

### Admin

- Technical admin dashboard with enrichment and banned cards sync actions (accessible to `ROLE_TECHNICAL_ADMIN`).

### Testing & Quality

- Coverage improvements for `BorrowNotificationEmailService` and `CreateAdminCommand`.
- On-demand coverage workflow, `/cover-pr` and `/cover-more` slash commands via Codecov.
- Integration test proving webhook cannot re-dispatch async messages.

### Documentation

- F9.6 archetype localization feature and content pages documented.
- F6.5-fix added to roadmap for banned cards sync refactor.
- `/ci`, `/pr`, `/next` slash commands added to tooling.

### Cross-Cutting

- 82 features documented (81 done + F14.7 new)
- PHPStan level 10, full CI pipeline

---

## [1.0.0-beta.1] — 2026-03-12

First beta of the 1.0.0 release — Phase 9 completion. Archetype ecosystem fully built out, dashboard action reminders, deck activity pagination, version history, and retire/reactivate workflow.

### Deck Library

- **F2.6** — Deck archetype management *(completed)*: full admin CRUD for archetypes with name, slug, published flag, description (Markdown), Pokémon slugs for sprites, and playstyle tags. Dedicated `ROLE_ARCHETYPE_EDITOR` role.
- **F2.7** — Retire / reactivate a deck *(completed)*: deck owners can retire a deck (auto-cancels pending borrows with warning dialog) and reactivate it later.
- **F2.9** — Deck version history *(completed)*: view all deck versions with side-by-side card comparison, card image hover, and enriched fixtures.
- **F2.10** — Archetype detail page *(completed)*: public page with Markdown description, custom tags (deck links, card images), cached rendering, and latest decks list.
- **F2.11** — Archetype backlinking *(completed)*: decks link to their archetype detail page across all views.
- **F2.12** — Archetype sprite pictograms *(completed)*: Pokémon box sprites displayed next to deck names across the entire UI (catalog, detail, dashboard, events, borrows, results). Build-time sprite download via PokéSprite fork.
- **F2.15** — Archetype playstyle tags *(completed)*: free-text tags on archetypes (e.g. "Aggro", "Control", "Toolbox") managed via Mantine TagsInput in admin form.
- **F2.16** — Archetype catalog *(completed)*: public browse page with card grid, multi-select tag filtering (OR logic), sort by name or deck count, sprites, and tag badges.
- **F2.17** — Deck catalog archetype filter UX *(completed)*: replaced text search with a searchable sprite dropdown (Mantine Combobox) showing all published archetypes with their sprites.
- **F2.18** — Admin archetype create/edit form *(completed)*: dedicated admin form for creating and editing archetypes with all fields.
- **F5.12** — Deck show activity pagination *(completed)*: deck detail page shows only the 5 most recent activity entries with a "See more" link.

### Dashboard & UX

- **F7.4** — Dashboard action reminders *(completed)*: warning widget showing borrows to return, pending requests to review, and events needing deck selection. Action links scroll to relevant page sections via anchors.

### Documentation

- Roadmap restructured: completed features removed from phase tables, 28 remaining features organized into 7 logical phases (A–G) with PDF labels before Zebra labels.

### Cross-Cutting

- Phase 9 progress: 17/34 done, 17 remaining
- All archetype features complete (F2.6, F2.10, F2.11, F2.12, F2.15, F2.16, F2.17, F2.18)
- 75 total features implemented

## [0.8.0] — 2026-03-10

Quality & i18n release — comprehensive test coverage, lint tooling, translation of all remaining controller/form strings, and dead code removal.

### Internationalization

- **F9.3** — Application translation *(completed)*: translate all remaining controller flash messages and form labels to use translation keys. Introduce `AbstractAppController` base class with `addTranslatedFlash()` helper to enforce translated flash messages project-wide.

### Quality & Testing

- **Test coverage**: 83.6% → 92.24% line coverage. Added 50+ test files covering entities, repositories, controllers, services, event listeners, message handlers, Twig runtimes, and security components. All repositories, services, and listeners at 100% line coverage.
- **Dead code removal**: removed unreachable unpublish guard in `DeckController` (form-disabled field already prevents the branch). Cleaned up orphaned translation keys.

### Tooling

- **Lint tooling**: added `make lint-all` target orchestrating all linters and fixers in dependency order: `lint-yaml` → `lint-i18n` → `cs-fix` → `eslint-fix` → `stylelint-fix` → `lint-container` → `phpstan`.
- New Make targets: `lint-yaml`, `lint-i18n` (XLIFF syntax + translation content), `lint-container`, `stylelint`, `stylelint-fix`, `eslint-fix`.
- Installed `stylelint` + `stylelint-config-standard-scss` for SCSS linting.
- Updated CLAUDE.md pre-commit checklist with all new lint targets.

## [0.7.0] — 2026-03-09

Phase 8 completion — Admin, Homepage & Polish: banned card list, mobile UX responsiveness pass with swipeable card gallery, localized validation messages, coding standards documentation.

### Card Data & Validation

- **F6.5** — Banned card list management *(completed)*: CLI command `app:banned-cards:sync` fetches the official Pokemon TCG banned card list from pokemon.com and syncs it to the database (add/remove/unchanged). `DeckListValidator` now checks imported deck lists against the banned card list. Sync runs automatically at the end of `make fixtures`. Cards identified by setCode + cardNumber for deduplication.

### Mobile UX

- **F10.1** — Mobile UX review *(completed)*: comprehensive mobile responsiveness pass. Borrow tables (inbox, dashboard, deck show) use card-based layout on mobile (`< md`) instead of horizontal-scroll tables. Deck catalog filters collapse behind a toggle on mobile. Card hover images replaced with tap-to-show swipeable modal on touch devices (prev/next buttons, touch swipe, keyboard arrows, quantity in title). Background scroll blocked while modal is open on iOS. Action buttons meet 44px touch target. Event info tables converted to definition lists. Notification bell redirects to notification list on mobile. Navbar items right-aligned on mobile. Dashboard stat cards stack vertically on smallest screens.

### Deck Library

- Swipeable card image gallery: mobile modal navigates through all deck card images in list order with touch swipe, prev/next buttons, and keyboard arrow support. Modal title shows "qty x card name".

### Internationalization

- Deck list parser and validator error messages are now localized via translation keys instead of hardcoded English strings.

### Documentation

- Expanded Make commands reference in CLAUDE.md with all targets, descriptions, and usage guidance.
- Added JavaScript/TypeScript coding conventions, naming rules, and additional PHP rules to coding standards.
- Mobile UX audit document (`docs/technicalities/mobile_audit.md`).

### Cross-Cutting

- 509 tests, 2154 assertions, PHPStan level 10
- Phase 8 fully complete (6/6 features done)

---

## [0.6.0] — 2026-03-09

Phase 8 progress — Admin, Homepage & Polish: admin user management, GDPR account deletion & data export, in-app notification center, dashboard enhancements. Major framework upgrade: Symfony 7.2 → 8.0, React 18 → 19.

### Admin & User Management

- **F7.2** — User management *(completed)*: admin user list with search and pagination, user detail page with role assignment (ROLE_ADMIN, ROLE_ORGANIZER, ROLE_CMS_EDITOR, ROLE_ARCHETYPE_EDITOR), disable/enable toggle, and account anonymization.
- **F1.8** — Account deletion & data export *(completed)*: users can export their data as JSON (profile, decks with raw lists, borrows, engagements, staff assignments) and request account deletion with email confirmation (24h token). Deletion is blocked if the user has unsettled borrows. Confirmation anonymizes the account (email stored as bcrypt hash for traceability), disables login, and logs the user out. Centralized `User::anonymize()` method shared by admin and self-service flows.

### Notifications

- **F8.4** — In-app notification center *(completed)*: React-based notification bell with unread count badge, polling, mark-as-read, and mark-all-read. Dropdown menu with notification list and timestamps.

### Dashboard

- **F7.1** — Dashboard enhancements: admin overview stats banner (total users, decks, events, active borrows), personal event stats for organizers and staff, stat card links to scoped list pages.

### Borrow Workflow

- Managed borrows inbox (`/lends?scope=managed`): cancel button now shown when the logged-in user is the borrower. Hand-off button hidden when deck is delegated to staff but staff hasn't physically received it (shows "Awaiting custody" badge). Scope preserved on redirect after actions.

### Infrastructure

- **Symfony 7.2 → 8.0**: full major upgrade via 7.4 bridge. Fixes: `UserCheckerInterface::checkPostAuth()` signature, route config `.xml` → `.php`, auto-generated `reference.php` excluded from CS-Fixer.
- **React 18 → 19**: updated `act()` wrapping in async tests, `eslint-plugin-react-hooks` 5 → 7 with new `set-state-in-effect` rule.
- **Dependency updates**: Mantine 8.3.16, webpack-cli 6, globals 17, regenerator-runtime 0.14, phpstan-symfony 2.0.15.
- Constraint widened to `^8.0` for automatic minor Symfony upgrades.
- 507 tests, 2123 assertions, PHPStan level 10

---

## [0.5.0] — 2026-03-08

Phase 7 completion — Engagement, Results & Discovery: deck event status overview, tournament results with monospace short-ID badges, and Pokemon event page sync.

### Deck Library

- **F2.14** — Deck event status overview *(completed)*: deck detail page now shows a summary of the deck's participation across events — engagement state, borrow status, and tournament placement at a glance.

### Event Management

- **F3.17** — Tournament results *(completed)*: dedicated results page with placement and match records. Player short-IDs displayed as monospace badges. First/last name shown alongside screen name.
- **F3.18** — Sync from Pokemon event page *(completed)*: import event metadata (name, date, location, structure) from a Pokemon event page URL. Maps tournament structures (League Challenge → swiss), handles unicode decoding, and includes functional test coverage.

### Cross-Cutting

- 460 tests, PHPStan level 10
- Phase 7 fully complete (10/10 features done)

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

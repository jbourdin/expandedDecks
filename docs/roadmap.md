# Implementation Roadmap

> **Audience:** Developer, AI Agent · **Scope:** Planning, Reference

← Back to [Main Documentation](docs.md) | [Feature List](features.md) | [README](../README.md)

This roadmap orders every feature from the [Feature List](features.md) into sequential implementation phases. Features within each phase can be developed in parallel; phases should be completed roughly in order because later phases depend on earlier ones.

**Ordering criteria:** dependency constraints first, then priority (High → Medium → Low), then logical grouping for coherent deliverables.

### Implementation states

Each feature carries a **State** that must be kept up to date as work progresses:

| State           | Meaning |
|-----------------|---------|
| **Done**        | Feature is fully implemented and functional |
| **Partial**     | Scaffolding exists (entity, fields, enum, or basic controller) but the feature is not yet usable end-to-end |
| **Not started** | No meaningful implementation exists |

> **Maintenance rule:** when a PR changes the implementation state of a feature, update this file in the same PR.

---

## Phase 1 — Auth & Foundation

> Accounts, roles, and datetime infrastructure. Everything else depends on authenticated users.

| ID   | Feature                            | Priority | State       | Depends on |
|------|------------------------------------|----------|-------------|------------|
| F1.1 | User registration & authentication | High     | Done        | —          |
| F1.2 | Email verification                 | High     | Done        | F1.1       |
| F1.4 | Role-based access control          | High     | Done        | F1.1       |
| F1.7 | Password reset                     | High     | Done        | F1.1       |
| F9.4 | UTC datetime storage               | High     | Done        | —          |

**Progress: 5/5 done**

**Deliverable:** Users can register, verify email, log in, reset passwords. RBAC in place for all future access checks. All datetimes stored in UTC.

---

## Phase 2 — Deck Registration & Card Pipeline

> Core deck domain: parsing, validation, registration, and versioning. No UI beyond basic forms.

| ID   | Feature                        | Priority | State | Depends on       |
|------|--------------------------------|----------|-------|------------------|
| F6.1 | Parse PTCG text format         | High     | Done  | —                |
| F6.2 | Card validation via TCGdex     | High     | Done  | F6.1             |
| F6.3 | Expanded format validation     | High     | Done  | F6.2             |
| F2.1 | Register a deck                | High     | Done  | F1.1             |
| F2.2 | Import deck list (copy-paste)  | High     | Done  | F2.1, F6.1–F6.3 |
| F2.5 | Deck availability status       | High     | Done  | F2.1             |
| F2.8 | Update deck list (new version) | High     | Done  | F2.2             |

**Progress: 7/7 done**

**Deliverable:** Owners can register decks, import/update deck lists with full card validation, and track availability status.

---

## Phase 3 — Events & Staff

> Event lifecycle: creation, editing, participation, staff, and venues.

| ID   | Feature                            | Priority | State       | Depends on |
|------|------------------------------------|----------|-------------|------------|
| F3.1 | Create an event                    | High     | Done        | F1.4       |
| F3.9 | Edit an event                      | High     | Not started | F3.1       |
| F3.5 | Assign event staff team            | High     | Partial     | F3.1, F1.4 |
| F3.4 | Register participation to an event | Medium   | Not started | F3.1, F1.1 |
| F3.8 | League/Store management            | Medium   | Partial     | —          |

**Progress: 1/5 done · 2 partial · 2 not started**

**Deliverable:** Organizers can create/edit events, assign staff, and link leagues. Players can register participation (playing or spectating).

---

## Phase 4 — Borrow Workflow & Notifications

> The core lending cycle: request → approve → hand-off → return. In this first version the **deck owner confirms movements directly** (on borrow and on handing back) — no label scanning required. Staff delegation, conflict detection, and borrow notifications are included.

| ID    | Feature                         | Priority | State       | Depends on           |
|-------|---------------------------------|----------|-------------|----------------------|
| F4.1  | Request to borrow a deck        | High     | Partial     | F2.5, F3.4           |
| F4.2  | Approve / deny borrow request   | High     | Partial     | F4.1                 |
| F4.3  | Confirm deck hand-off (lend)    | High     | Partial     | F4.2                 |
| F4.4  | Confirm deck return             | High     | Partial     | F4.3                 |
| F4.8  | Staff-delegated lending         | High     | Partial     | F4.1–F4.4, F3.5      |
| F4.11 | Borrow conflict detection       | High     | Not started | F4.1                 |
| F8.1  | Borrow workflow notifications   | High     | Partial     | F4.1–F4.4            |

**Progress: 0/7 done · 6 partial · 1 not started**

**Deliverable:** Full borrow lifecycle with owner-confirmed hand-off and return, staff delegation, temporal conflict detection, and email/in-app notifications at each state transition. Label scanning (F5.3) can be wired in later as an alternative confirmation method.

---

## Phase 5 — Core Views & Navigation

> Rich read views, catalogs, and dashboards that surface data from Phases 1–4.

| ID    | Feature                      | Priority | State       | Depends on       |
|-------|------------------------------|----------|-------------|------------------|
| F2.3  | Deck detail view             | Medium   | Done        | F2.2             |
| F2.4  | Deck catalog (browse & search) | Medium | Not started | F2.1, F2.5       |
| F6.4  | Display card images          | Medium   | Done        | F6.2             |
| F3.2  | Event listing                | Medium   | Partial     | F3.1             |
| F3.3  | Event detail view            | Medium   | Not started | F3.1             |
| F4.5  | Borrow history               | Medium   | Not started | F4.1–F4.4        |
| F4.7  | Cancel a borrow request      | Medium   | Partial     | F4.1, F4.2       |
| F4.9  | Staff deck custody tracking  | Medium   | Not started | F4.8             |
| F4.10 | Owner borrow inbox           | Medium   | Not started | F4.1, F4.2       |

**Progress: 2/9 done · 2 partial · 5 not started**

**Deliverable:** Browsable deck catalog, deck detail with card image hovers, event listing/detail, borrow history, cancellation, staff custody dashboard, and the owner's borrow inbox.

---

## Phase 6 — Localization

> Multi-language and timezone support. Applies retroactively to all existing UI.

| ID   | Feature                    | Priority | State       | Depends on |
|------|----------------------------|----------|-------------|------------|
| F9.1 | User language preference   | Medium   | Partial     | F1.1       |
| F9.2 | User timezone              | Medium   | Partial     | F1.1, F9.4 |
| F9.3 | Application translation    | Medium   | Not started | —          |
| F1.3  | User profile               | Medium   | Not started | F1.1       |
| F1.11 | Gravatar avatar & navbar user menu | Medium | Not started | F1.1 |

**Progress: 0/5 done · 2 partial · 3 not started**

**Deliverable:** All UI strings translatable (en/fr), user-selectable locale and timezone, a profile page showing owned decks, borrow history, and upcoming events, and a Gravatar-powered navbar avatar with user dropdown menu.

---

## Phase 7 — Engagement, Results & Discovery

> Richer event lifecycle: visibility, engagement states, tournament results, event sync, and discovery.

| ID    | Feature                          | Priority | State       | Depends on       |
|-------|----------------------------------|----------|-------------|------------------|
| F3.11 | Event visibility                 | Medium   | Not started | F3.1             |
| F3.13 | Player engagement states         | Medium   | Not started | F3.4             |
| F3.7  | Register played deck for event   | Medium   | Partial     | F3.4, F2.2       |
| F3.17 | Tournament results               | Medium   | Not started | F3.7             |
| F3.10 | Cancel an event                  | Medium   | Partial     | F3.1, F4.1       |
| F3.15 | Event discovery                  | Medium   | Not started | F3.11, F3.13     |
| F8.2  | Event notifications              | Medium   | Partial     | F3.1, F3.13      |
| F3.18 | Sync from Pokemon event page     | Medium   | Not started | F3.1, F3.9       |

**Progress: 0/8 done · 3 partial · 5 not started**

**Deliverable:** Public/private/invitation-only events, player engagement states (interested → registered), tournament results with privacy, event cancellation with cascading borrows, event discovery page, event notifications, and Pokemon event page sync.

---

## Phase 8 — Admin, Homepage & Polish

> Administration tools, public-facing homepage, and GDPR compliance.

| ID    | Feature                          | Priority | State       | Depends on           |
|-------|----------------------------------|----------|-------------|----------------------|
| F7.1  | Dashboard                        | Medium   | Partial     | F1.4                 |
| F7.2  | User management                  | Medium   | Not started | F1.4                 |
| F6.5  | Banned card list management      | Medium   | Not started | F6.3                 |
| F8.4  | In-app notification center       | Medium   | Not started | F8.1                 |
| F10.1 | Mobile UX review                 | Medium   | Not started | F6.4, F2.3           |
| F10.2 | Anonymous homepage               | Medium   | Partial     | F3.2, F2.4, F3.17    |
| F1.8  | Account deletion & data export   | Medium   | Partial     | F1.1                 |

**Progress: 0/7 done · 3 partial · 4 not started**

**Deliverable:** Admin dashboard and user management, banned card list, notification center, mobile responsiveness pass, public homepage, and GDPR account deletion/export.

---

## Phase 9 — Content, Archetypes & Low Priority

> CMS pages, archetype ecosystem, calendar feeds, and remaining low-priority features (excluding labels).

### Archetypes

| ID    | Feature                        | Priority | State       | Depends on       |
|-------|--------------------------------|----------|-------------|------------------|
| F2.6  | Deck archetype management      | Low      | Not started | F2.1, F1.4       |
| F2.10 | Archetype detail page          | Low      | Not started | F2.6             |
| F2.11 | Archetype backlinking          | Low      | Not started | F2.10            |
| F2.12 | Archetype sprite pictograms    | Low      | Not started | F2.6             |

### CMS Content Pages

| ID    | Feature                        | Priority | State       | Depends on       |
|-------|--------------------------------|----------|-------------|------------------|
| F11.1 | Content pages                  | Low      | Not started | F1.4, F9.1       |
| F11.2 | Menu categories                | Low      | Not started | F11.1            |
| F11.3 | Page rendering & locale fallback | Low    | Not started | F11.1, F9.1      |

### Event Extras

| ID    | Feature                        | Priority | State       | Depends on       |
|-------|--------------------------------|----------|-------------|------------------|
| F3.12 | Event series                   | Low      | Not started | F3.11            |
| F3.14 | iCal agenda feed               | Low      | Not started | F3.13            |
| F3.16 | Public iCal feed               | Low      | Not started | F3.11            |
| F3.6  | Tournament ID verification     | Low      | Not started | F3.18            |

### Auth Hardening

| ID    | Feature                            | Priority | State       | Depends on |
|-------|------------------------------------|----------|-------------|------------|
| F1.9  | Login with screen name or email    | Low      | Not started | F1.1       |
| F1.10 | Password strength scoring (zxcvbn) | Low      | Not started | F1.1       |
| F1.5  | MFA with TOTP (planned)            | Low      | Not started | F1.1       |
| F1.6  | Pokemon SSO (to investigate)       | Low      | Not started | F1.1       |

### Remaining Features

| ID    | Feature                        | Priority | State       | Depends on       |
|-------|--------------------------------|----------|-------------|------------------|
| F2.7  | Retire / reactivate a deck     | Low      | Partial     | F2.5             |
| F2.9  | Deck version history           | Medium   | Not started | F2.8             |
| F6.6  | Visual deck list (card mosaic) | Low      | Not started | F6.4             |
| F4.6  | Overdue tracking               | Low      | Not started | F4.4             |
| F7.3  | Audit log                      | Low      | Not started | —                |
| F8.3  | Notification preferences       | Low      | Not started | F8.1             |

**Progress: 0/21 done · 1 partial · 20 not started**

**Deliverable:** Auth hardening (flexible login, password strength scoring, MFA, Pokemon SSO). Managed archetype catalogue with detail pages, sprite pictograms, and backlinking across the UI. CMS content pages with Markdown, translations, and menu categories. Event series, iCal feeds, deck version history, card mosaic view, overdue tracking, notification preferences, and audit log.

---

## Phase 10 — Labels & Scanning

> Zebra label printing, barcode/QR scanning, and alternative label options. Deferred until hardware (Zebra printer) and label specifications are finalized. Once available, label scanning can be wired into the borrow hand-off (F4.3) and return (F4.4) as an alternative confirmation method alongside the manual owner confirmation shipped in Phase 4.

| ID   | Feature                           | Priority | State       | Depends on |
|------|-----------------------------------|----------|-------------|------------|
| F5.1 | Generate ZPL label for a deck     | Low      | Not started | F2.1       |
| F5.2 | Push label to printer via PrintNode | Low    | Not started | F5.1       |
| F5.3 | Scan label to identify deck       | Low      | Not started | F5.1       |
| F5.4 | Reprint label                     | Low      | Not started | F5.2       |
| F5.5 | PrintNode printer management      | Medium   | Not started | F5.2       |
| F5.6 | Camera QR scan (mobile fallback)  | Medium   | Not started | F5.3       |
| F5.7 | PDF label card (home printing)    | Medium   | Not started | F5.1       |

**Progress: 0/7 done · 7 not started**

**Deliverable:** Print Zebra labels for deck boxes, push to printer via PrintNode, scan barcodes/QR codes to identify decks and trigger borrow actions. Camera fallback for mobile, PDF labels for home printing, and printer management UI.

---

## Cross-Cutting: Testing Infrastructure

> Test framework setup and continuous quality assurance.

| Item                                  | State       | Notes                                  |
|---------------------------------------|-------------|----------------------------------------|
| PHP unit tests (PHPUnit)              | Done        | Service-layer tests, 34 methods        |
| PHP coverage in CI (pcov)             | Done        | PR comment via GitHub Action            |
| PHP functional tests (WebTestCase)    | Done        | Base class + smoke tests               |
| Frontend unit tests (Vitest)          | Done        | Vitest + @testing-library/react        |
| E2E tests (Playwright)               | Not started | Future: browser-based end-to-end tests |

---

## Summary

| Phase | Name                              | Done | Partial | Not started | Total |
|-------|-----------------------------------|------|---------|-------------|-------|
| 1     | Auth & Foundation                 | 5    | 0       | 0           | 5     |
| 2     | Deck Registration & Card Pipeline | 7    | 0       | 0           | 7     |
| 3     | Events & Staff                    | 1    | 2       | 2           | 5     |
| 4     | Borrow Workflow & Notifications   | 0    | 6       | 1           | 7     |
| 5     | Core Views & Navigation           | 2    | 2       | 5           | 9     |
| 6     | Localization                      | 0    | 2       | 3           | 5     |
| 7     | Engagement, Results & Discovery   | 0    | 3       | 5           | 8     |
| 8     | Admin, Homepage & Polish          | 0    | 3       | 4           | 7     |
| 9     | Content, Archetypes & Low Priority | 0   | 1       | 20          | 21    |
| 10    | Labels & Scanning                 | 0    | 0       | 7           | 7     |
|       | **Total**                         | **15** | **17** | **49**      | **81** |

All 81 features from [features.md](features.md) are represented exactly once.

# Implementation Roadmap

> **Audience:** Developer, AI Agent · **Scope:** Planning, Reference

← Back to [Main Documentation](docs.md) | [Feature List](features.md) | [Changelog](changelog.md) | [README](../README.md)

This roadmap lists **remaining features** to be implemented, grouped into logical phases. Completed features are documented in the [Feature List](features.md) and tracked in the [Changelog](changelog.md).

**Ordering criteria:** dependency constraints first, then priority (High → Medium → Low), then logical grouping for coherent deliverables.

---

## Completed Features

The following features have been fully implemented across phases 0–9. See [features.md](features.md) for full descriptions and [changelog.md](changelog.md) for release history.

F1.1, F1.2, F1.3, F1.4, F1.7, F1.8, F1.11,
F2.1, F2.2, F2.3, F2.4, F2.5, F2.6, F2.7, F2.8, F2.9, F2.10, F2.11, F2.12, F2.13, F2.14, F2.15, F2.16, F2.17, F2.18,
F3.1, F3.2, F3.3, F3.4, F3.5, F3.7, F3.9, F3.10, F3.11, F3.13, F3.15, F3.17, F3.18, F3.20, F3.21,
F4.1, F4.2, F4.3, F4.4, F4.5, F4.7, F4.8, F4.9, F4.10, F4.11, F4.12, F4.13, F4.14,
F5.12,
F6.1, F6.2, F6.3, F6.4, F6.5,
F7.1, F7.2, F7.4,
F8.1, F8.2, F8.3, F8.4,
F9.1, F9.2, F9.3, F9.4,
F10.1, F10.2,
F11.1, F11.2, F11.3,
F14.1, F14.2, F14.3, F14.4, F14.5, F14.6

**Total: 81 features done.**

---

## Phase 0 — Deployment Readiness (target: 1.0.0-beta.2)

> Infrastructure features required for the first live server release. Configurable transports, session storage, and workerless async via SQS webhook consumption.

| ID     | Feature                                   | Priority | Depends on | Status |
|--------|-------------------------------------------|----------|------------|--------|
| F14.1  | Per-transport Messenger DSN configuration | High     | —          | Done   |
| F14.2  | Configurable session storage driver       | High     | —          | Done   |
| F14.3  | SQS-compatible webhook message consumer   | High     | F14.1      | Removed (replaced by Doctrine transport + cron job) |
| F14.4  | Health check endpoint                     | High     | —          | Done   |
| F14.5  | Production Dockerfile                     | High     | —          | Done   |
| F14.6  | Configurable mail sender and admin email  | High     | —          | Done   |

**Progress: 6/6 done**

**Deliverable:** Each Messenger transport independently configurable via env vars. Session storage switchable between filesystem, Redis, and PDO. SQS webhook endpoint eliminates the need for long-running workers in production — messages are pushed over HTTPS and processed on demand. Health check endpoints for container orchestration liveness/readiness probes. Multi-stage Dockerfile for production container image. All external service connections (mail sender, admin recipient, mailer DSN, trusted proxies) configurable via environment variables.

---

## Fixes

> Bug fixes and refactors needed for production correctness.

| ID     | Feature                                           | Priority | Depends on | Status |
|--------|---------------------------------------------------|----------|------------|--------|
| F6.5-fix | Refactor banned cards sync into a service       | High     | F6.5       |        |

**F6.5-fix:** The `BannedCardsSyncCommand` contains all the parsing/sync logic inline. The technical admin controller (`AdminTechnicalController`) currently shells out to `symfony console app:banned-cards:sync` via `Process`, which fails in the serverless container (no `symfony` CLI binary). Extract the sync logic into a `BannedCardsSyncService` callable from both the CLI command and the controller directly.

---

## Phase A — UX Polish & Overdue Tracking

> Quick wins that improve daily usage: overdue alerts, bookmarks, aggregate views, and visual enhancements.

| ID    | Feature                                 | Priority | Depends on       |
|-------|-----------------------------------------|----------|------------------|
| F4.6  | Overdue tracking                        | Low      | F4.4             |
| F7.5  | Registered decks aggregate view         | Low      | F7.1             |
| F6.6  | Visual deck list (card mosaic)          | Low      | F6.4             |
| F13.1 | Bookmark a deck                         | Low      | F2.4             |
| F13.2 | Bookmark an event                       | Low      | F3.2             |
| F13.3 | Bookmark an archetype                   | Low      | F2.16            |

**Progress: 0/6 done**

**Deliverable:** Overdue tracking with automated reminders, bookmarks for quick access to decks/events/archetypes, registered decks aggregate view for organizers, and a visual card mosaic alternative for deck lists.

---

## Phase B — Event Enrichment

> Event tags, iCal feeds, and tournament verification to improve event discoverability and calendar integration.

| ID    | Feature                        | Priority | Depends on       |
|-------|--------------------------------|----------|------------------|
| F3.12 | Event tags                     | Low      | F3.1             |
| F3.14 | iCal agenda feed               | Low      | F3.13            |
| F3.16 | Public iCal feed               | Low      | F3.11            |
| F3.6  | Tournament ID verification     | Low      | F3.18            |

**Progress: 0/4 done**

**Deliverable:** Freeform event tags for grouping and filtering, personal and public iCal feeds for calendar sync, and tournament ID verification against official Pokemon systems.

---

## Phase C — PDF Labels & Camera Scanning

> Home-printable PDF labels with QR codes, and camera-based scanning. No Zebra hardware required — accessible to all users immediately.

| ID    | Feature                           | Priority | Depends on |
|-------|-----------------------------------|----------|------------|
| F5.7  | PDF label card (home printing)    | Medium   | —          |
| F5.6  | Camera QR scan (mobile fallback)  | Medium   | F5.7       |

**Progress: 0/2 done**

**Deliverable:** Generate downloadable PDF labels (TCG card-sized, with QR code) for any deck, printable on any home printer. Scan these QR codes via the device camera to identify decks and trigger borrow actions. No special hardware needed.

---

## Phase D — Zebra Labels & HID Scanning

> Professional Zebra label printing via PrintNode and USB barcode/HID scanning for high-throughput events.

| ID   | Feature                           | Priority | Depends on |
|------|-----------------------------------|----------|------------|
| F5.1 | Generate ZPL label for a deck     | High     | —          |
| F5.2 | Push label to printer via PrintNode | High   | F5.1       |
| F5.3 | Scan label to identify deck (HID) | High     | F5.1       |
| F5.4 | Reprint label                     | Low      | F5.2       |
| F5.5 | PrintNode printer management      | Medium   | F5.2       |

**Progress: 0/5 done**

**Deliverable:** Generate ZPL labels for Zebra printers, push print jobs via PrintNode cloud API, scan deck labels with USB HID barcode readers for fast identification and borrow actions. Reprint and printer management included.

---

## Phase E — Auth Hardening & Delegation

> Flexible login, password scoring, friend delegation, and MFA for improved security and usability.

| ID    | Feature                                 | Priority | Depends on       |
|-------|-----------------------------------------|----------|------------------|
| F1.9  | Login with screen name or email         | Low      | F1.1             |
| F1.10 | Password strength scoring (zxcvbn)      | Low      | F1.1             |
| F4.15 | Friend delegation for borrow completion | Low      | F4.2, F4.3, F3.4 |
| F1.5  | MFA with TOTP (planned)                 | Low      | F1.1             |

**Progress: 0/4 done**

**Deliverable:** Login with screen name or email, zxcvbn password strength scoring, TOTP-based multi-factor authentication, and per-event friend delegation for borrow management.

---

## Phase F — Play Pokemon QR Integration

> Scan Play! Pokemon QR codes for player identification, quick account creation, and league investigation. Depends on scanning infrastructure from Phase C or D.

| ID    | Feature                                        | Priority | Depends on       |
|-------|------------------------------------------------|----------|------------------|
| F1.12 | Play Pokemon QR scan for player identification | Medium   | F1.1, F5.6/F5.3  |
| F1.6  | Pokemon SSO (to investigate)                   | Low      | F1.1             |
| F3.19 | League deduction from Pokemon ID               | Low      | F1.1             |

**Progress: 0/3 done**

**Deliverable:** Scan Play! Pokemon QR codes for instant player identification, quick account creation, and staff assignment. Investigate Pokemon SSO and league deduction from player ID.

---

## Phase G — Operational Excellence

> Audit log, translation collaboration, and security/conflict hardening. Best done after all major features are in place.

| ID     | Feature                                  | Priority | Depends on               |
|--------|------------------------------------------|----------|--------------------------|
| F7.3   | Audit log                                | Low      | —                        |
| F9.5   | Weblate integration                      | Low      | F9.3                     |
| F12.1  | Controller role & context security audit | High     | All controllers          |
| F12.2  | Optimistic state conflict detection      | Medium   | All state-changing actions |

**Progress: 0/4 done**

**Deliverable:** Comprehensive audit log, collaborative translation via Weblate, security audit of all controller actions, and optimistic locking to prevent race conditions.

---

## Cross-Cutting: Testing Infrastructure

| Item                                  | State       | Notes                                  |
|---------------------------------------|-------------|----------------------------------------|
| PHP unit tests (PHPUnit)              | Done        | Service-layer tests, 34 methods        |
| PHP coverage in CI (pcov)             | Done        | PR comment via GitHub Action            |
| PHP functional tests (WebTestCase)    | Done        | Base class + smoke tests               |
| Frontend unit tests (Vitest)          | Done        | Vitest + @testing-library/react        |
| E2E tests (Playwright)               | Not started | Future: browser-based end-to-end tests |

---

## Summary

| Phase | Name                            | Features | Target       |
|-------|---------------------------------|----------|--------------|
| 0     | Deployment Readiness            | 6 (Done) | 1.0.0-beta.2 |
| A     | UX Polish & Overdue Tracking    | 6        |              |
| B     | Event Enrichment                | 4        |              |
| C     | PDF Labels & Camera Scanning    | 2        |              |
| D     | Zebra Labels & HID Scanning     | 5        |              |
| E     | Auth Hardening & Delegation     | 4        |              |
| F     | Play Pokemon QR Integration     | 3        |              |
| G     | Operational Excellence          | 4        |              |
|       | **Total remaining**             | **28**   |              |

81 features done · 28 remaining.

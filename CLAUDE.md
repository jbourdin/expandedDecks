# Claude AI Context

> **Audience:** Developer, AI Agent · **Scope:** Coding Standards, Workflow, Reference

## Project Overview

**Expanded Decks** is a Symfony application for managing a shared library of physical Pokemon TCG decks (Expanded format). It tracks deck ownership, event-based borrowing, and deck lists (imported via copy-paste of PTCG text format, validated against TCGdex). It includes Zebra label printing for physical deck box identification and scanning.

**Stack:** PHP 8.5 | Symfony 7.2 | React.js | MySQL 8 | Docker | PrintNode | TCGdex | ptcgo-parser

## CLI Commands: Always Use Symfony Wrapper

| Use this              | NOT this          |
|-----------------------|-------------------|
| `symfony console ...` | `bin/console ...` |
| `symfony composer ...` | `composer ...`   |
| `symfony php ...`     | `php ...`         |
| `symfony php bin/phpunit` | `bin/phpunit` |

## Naming Conventions

> Full details: [docs/standards/naming.md](docs/standards/naming.md)

| Element           | Convention        | Example                          |
|-------------------|-------------------|----------------------------------|
| Classes           | PascalCase        | `DeckLibrary`                    |
| Methods           | camelCase         | `requestBorrow()`                |
| Properties        | camelCase         | `$deckOwner`                     |
| Constants         | UPPER_SNAKE_CASE  | `BORROW_STATUS_PENDING`          |
| Services (YAML)   | snake_case        | `app.deck_library`              |
| Routes            | snake_case        | `app_deck_show`                  |
| Templates         | snake_case        | `deck/show.html.twig`            |
| Translations      | dot.notation      | `app.deck.status.available`      |
| Test methods      | camelCase         | `testDeckCanBeBorrowed()`        |
| Enums             | PascalCase        | `BorrowStatus`                   |

## File Headers (Copyright & License)

> Full details: [docs/standards/file_headers.md](docs/standards/file_headers.md)

Every source file **MUST** include a copyright and license header as the first comment block.

**PHP files** (after `declare(strict_types=1);`):
```php
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
```

**TypeScript / JavaScript files**:
```typescript
/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
```

**Twig templates** (optional but recommended for non-trivial templates):
```twig
{#
 # This file is part of the Expanded Decks project.
 #
 # (c) Expanded Decks contributors
 #
 # For the full copyright and license information, please view the LICENSE
 # file that was distributed with this source code.
 #}
```

PHP-CS-Fixer enforces this header automatically via the `header_comment` rule.

## Coding Standards

> Full details: [docs/standards/coding.md](docs/standards/coding.md)

- **PSR-12** baseline with **@Symfony** PHP-CS-Fixer ruleset
- `declare(strict_types=1);` required in **all** PHP files
- `void` return type mandatory on methods without return
- **PHPStan Level 10** (max)
- Ordered imports: classes first, then functions, then constants (alphabetically sorted)
- Short array syntax `[]`, visibility required on all constants/methods/properties
- Constructor injection, autowiring, thin controllers
- Doctrine entities use PHP 8 attributes (not annotations)
- Symfony best practices: service autowiring, param binding, env vars for config
- **Feature traceability**: methods implementing a documented feature rule **MUST** reference the feature ID in their PHPDoc (`@see`) or JSDoc. This links code back to the feature specification.

PHP example:
```php
/**
 * @see docs/features.md F4.3 — Confirm deck hand-off (lend)
 */
public function confirmHandOff(Deck $deck, Borrow $borrow): void
```

JS example:
```javascript
/**
 * @see docs/features.md F5.3 — Scan label to identify deck
 * @see docs/technicalities/scanner.md
 */
function useScannerDetection(onScan) {
```

## Version Control

> Full details: [docs/standards/version_control.md](docs/standards/version_control.md)

### Gitflow Workflow

> **CRITICAL: NEVER commit directly to `main` or `develop`.** Always create a feature/fix/docs branch first (`git checkout -b <prefix>/<name>`), commit there, then open a Pull Request.

- All changes go through **Pull Requests** with code review
- Branch prefixes:

| Prefix       | Purpose                      | Example                          |
|--------------|------------------------------|----------------------------------|
| `feature/`   | New functionality            | `feature/deck-borrow-workflow`   |
| `fix/`       | Bug fixes                    | `fix/return-date-calculation`    |
| `refactor/`  | Code improvements            | `refactor/borrow-service`        |
| `docs/`      | Documentation                | `docs/api-integration`           |
| `chore/`     | Maintenance tasks            | `chore/update-dependencies`      |
| `hotfix/`    | Emergency production fixes   | `hotfix/critical-bug`            |
| `release/`   | Release preparation          | `release/1.0.0`                  |

### Commit Messages (Conventional Commits)

Format: `<type>(<scope>): <short description>`

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `perf`

Scopes: `deck`, `borrow`, `event`, `user`, `label`, `api`, `auth`, `infra`

Examples:
```
feat(deck): add deck list paste and validation
fix(borrow): correct return date validation
docs(readme): update feature list
chore(infra): add Docker Compose for MySQL
```

### Pull Request Naming

Title format: `<emoji> <type>: <short description>` (under 70 chars, imperative mood)

| Emoji              | Type     | Branch pattern           |
|--------------------|----------|--------------------------|
| `:sparkles:`       | Feature  | `feature/* > develop`    |
| `:bug:`            | Bugfix   | `fix/* > develop`        |
| `:recycle:`        | Refactor | `refactor/* > develop`   |
| `:memo:`           | Docs     | `docs/* > develop`       |
| `:white_check_mark:` | Tests | `test/* > develop`       |
| `:wrench:`         | Config   | `chore/* > develop`      |
| `:rocket:`         | Release  | `develop > main`         |
| `:ambulance:`      | Hotfix   | `hotfix/* > main`        |

### Pre-Commit Checklist

Run **before every commit/push**:

```bash
make cs-fix     # Fix code style (PHP-CS-Fixer)
make phpstan    # Static analysis
make test       # Run test suite
```

### Release Process

> Full details: [docs/standards/release_process.md](docs/standards/release_process.md)

1. Create `release/x.y.z` branch from `develop`
2. Update `docs/changelog.md` — add `## [x.y.z] — YYYY-MM-DD` section
3. Commit, push, open PR to `main` (title: `:rocket: Release: x.y.z`)
4. Wait for CI to pass, merge (merge commit, **not** squash)
5. Create GitHub release: `gh release create vx.y.z --target main --title "vx.y.z" --notes-file <changelog-section>`
6. Back-merge `main` into `develop`
7. Delete the release branch

## Key Commands

| Command             | Description                       |
|---------------------|-----------------------------------|
| `make install`      | Full project installation          |
| `make start`        | Start dev server + Docker services |
| `make stop`         | Stop all services                  |
| `make test`         | Run all tests                      |
| `make phpstan`      | Static analysis                    |
| `make cs-fix`       | Fix code style                     |
| `make migrations`   | Execute Doctrine migrations        |
| `make fixtures`     | Load fixture data                  |

## External APIs

- **TCGdex API** — multilingual Pokemon TCG card database (card metadata, types, subtypes, images)
- Package: `@tcgdex/sdk` (npm) — no API key needed
- Used for card validation and image display
- **PrintNode API** — cloud printing service to push ZPL payloads to Zebra printers
- API client service: `App\Service\PrintNode\ApiClient`
- Zebra printer runs a local PrintNode client; the app sends print jobs via the PrintNode REST API

## Async Messaging

Symfony Messenger with domain-separated transports (see `config/packages/messenger.yaml`):

| Transport            | Queue               | Messages                                             |
|----------------------|---------------------|------------------------------------------------------|
| `transactional_email`| `transactionalEmail`| `SendEmailMessage` (Symfony Mailer)                  |
| `deck_enrichment`    | `deck_enrichment`   | `EnrichDeckVersionMessage` (TCGdex card enrichment)  |
| `notification`       | `notification`      | `ChatMessage`, `SmsMessage` (Symfony Notifier)       |
| `borrow_lifecycle`   | `borrow_lifecycle`  | `DeclineCompetingBorrowsMessage`, `CancelEventBorrowsMessage` |

Pattern: services dispatch messages → handlers process them asynchronously. Each transport has independent retry (3 retries, ×2 multiplier). Failed messages route to the `failed` transport.

## Translations

- **Format:** XLIFF (`.xlf`) in `translations/`
- **Key pattern:** `app.<domain>.<context>.<key>` (e.g. `app.borrow.status.pending`)
- **Supported locales:** `en` (default), `fr`
- Emails render in the recipient's `preferredLocale`
- React translations live in `assets/translations/` as JSON, loaded via `react-i18next`

## Documentation

Entry point: **[docs/docs.md](docs/docs.md)** — full technical documentation index.

### Documentation Map

**Features & Planning**
- [docs/features.md](docs/features.md) — Full feature catalogue with IDs and priorities
- [docs/roadmap.md](docs/roadmap.md) — Implementation roadmap across 12 phases
- [docs/changelog.md](docs/changelog.md) — Release history with implemented features per version

**Data Models**
- [docs/models/user.md](docs/models/user.md) — User entity, roles, auth flows, GDPR
- [docs/models/deck.md](docs/models/deck.md) — Deck, DeckVersion, DeckCard, Archetype
- [docs/models/event.md](docs/models/event.md) — Event, EventStaff, EventEngagement
- [docs/models/borrow.md](docs/models/borrow.md) — Borrow entity and state machine
- [docs/models/notification.md](docs/models/notification.md) — Notification entity and types
- [docs/models/cms.md](docs/models/cms.md) — CMS pages and menu categories

**Standards** (detailed versions of rules in this file)
- [docs/standards/coding.md](docs/standards/coding.md) — Coding standards
- [docs/standards/naming.md](docs/standards/naming.md) — Naming conventions
- [docs/standards/version_control.md](docs/standards/version_control.md) — Version control & branching
- [docs/standards/documentation.md](docs/standards/documentation.md) — Documentation standards
- [docs/standards/file_headers.md](docs/standards/file_headers.md) — Copyright & license headers
- [docs/standards/release_process.md](docs/standards/release_process.md) — Release checklist

**Frontend**
- [docs/frontend.md](docs/frontend.md) — Frontend architecture (Twig+Bootstrap layout, Mantine for React islands)

**Technical Deep-Dives**
- [docs/technicalities/scanner.md](docs/technicalities/scanner.md) — USB HID scanner detection
- [docs/technicalities/camera_scanner.md](docs/technicalities/camera_scanner.md) — Camera QR scanner
- [docs/technicalities/pdf_label.md](docs/technicalities/pdf_label.md) — PDF label card generation

### Documentation Rules

- Documentation **MUST** be updated in the same PR as code changes
- Every doc file includes audience/scope header and a back-link to parent
- File naming: `snake_case.md` exclusively
- Max depth: 3 levels from `docs/` root
- Complex features: entrypoint `.md` + subdirectory `/`

### Roadmap & Changelog Maintenance

- `docs/roadmap.md` **MUST** be updated in the same PR when a feature's state changes (Not started → Partial → Done)
- Update the per-phase progress line and Summary table counts accordingly
- `docs/changelog.md` entry is required for every tagged release

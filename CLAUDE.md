# Claude AI Context

> **Audience:** Developer, AI Agent · **Scope:** Coding Standards, Workflow, Reference

## Project Overview

**Expanded Decks** is a Symfony application for managing a shared library of physical Pokemon TCG decks (Expanded format). It tracks deck ownership, event-based borrowing, and integrates with the Limitless TCG API for deck list data. It includes Zebra label printing for physical deck box identification and scanning.

**Stack:** PHP 8.5 | Symfony 7.2 | React.js | MySQL 8 | Docker | PrintNode

## CLI Commands: Always Use Symfony Wrapper

| Use this              | NOT this          |
|-----------------------|-------------------|
| `symfony console ...` | `bin/console ...` |
| `symfony composer ...` | `composer ...`   |
| `symfony php ...`     | `php ...`         |
| `symfony php bin/phpunit` | `bin/phpunit` |

## Naming Conventions

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
feat(deck): add deck import from Limitless TCG API
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

- **Limitless TCG API** — used for deck list data (card lists, archetypes)
- API client service: `App\Service\LimitlessTcg\ApiClient`
- Always cache API responses to avoid rate limiting
- **PrintNode API** — cloud printing service to push ZPL payloads to Zebra printers
- API client service: `App\Service\PrintNode\ApiClient`
- Zebra printer runs a local PrintNode client; the app sends print jobs via the PrintNode REST API

## Documentation

For detailed documentation beyond this file, see:

- **[README.md](README.md)** — Project overview and feature summary
- **[docs/docs.md](docs/docs.md)** — Full technical documentation (entry point)
- **[docs/features.md](docs/features.md)** — Full feature list with priorities
- **[docs/technicalities/](docs/technicalities/)** — Technical deep-dives (scanner detection, etc.)

### Documentation Rules

- Documentation **MUST** be updated in the same PR as code changes
- Every doc file includes audience/scope header and a back-link to parent
- File naming: `snake_case.md` exclusively
- Max depth: 3 levels from `docs/` root
- Complex features: entrypoint `.md` + subdirectory `/`

# Plan: Symfony 7.2 Skeleton with Docker, Makefile & CI

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Planning

← Back to [Main Documentation](docs.md) | [README](../README.md)

## Context

The expandedDecks project currently only contains documentation files (CLAUDE.md, README.md, docs/, LICENSE). We need to bootstrap the Symfony application, Docker infrastructure, development tooling, and CI pipeline so that feature development can begin on a solid foundation.

**Available locally:** PHP 8.5, Symfony CLI 5.16, Composer 2.9, Node 25

## Approach

Work on a `chore/symfony-skeleton` branch off `develop`. Create the Symfony skeleton in a temp directory to preserve existing files, then merge it into the project.

## Steps

### 1. Symfony Skeleton

1. `symfony composer create-project symfony/skeleton:"7.2.*"` into `/tmp`, copy generated files (bin/, config/, public/, src/, composer.json, composer.lock, symfony.lock) into the project — preserving docs, CLAUDE.md, README.md, LICENSE, .git
2. `symfony composer require webapp` (Twig, Security, Form, Validator, Mailer, etc.)
3. `symfony composer require --dev` PHPStan 2.x, PHP-CS-Fixer, PHPUnit, phpstan-symfony, phpstan-doctrine
4. `symfony composer require symfony/webpack-encore-bundle`

### 2. Docker Setup (4 files)

| File | Description |
|------|-------------|
| `docker/php/Dockerfile` | PHP 8.5-FPM Alpine + `install-php-extensions` (pdo_mysql, intl, opcache, zip, apcu) + Composer from official image |
| `docker/nginx/default.conf` | Standard Symfony Nginx config, `fastcgi_pass php:9000`, `$realpath_root` |
| `docker-compose.yml` | Services: php, nginx (:8080), database (MySQL 8, healthcheck), node (24-alpine, `profiles: tools`) |
| `.dockerignore` | .git, vendor, node_modules, var, docs |

### 3. Config Files (7 files)

| File | Description |
|------|-------------|
| `.env` | Modify skeleton default: `DATABASE_URL` for MySQL 8 (`127.0.0.1:3306`), project-specific |
| `.env.test` | Test env: test `DATABASE_URL`, `SYMFONY_DEPRECATIONS_HELPER=999999` |
| `.gitignore` | Merge Symfony defaults + .idea, .DS_Store, .claude, docker/data, node_modules, public/build |
| `phpstan.neon` | Level 10, paths: src, includes: symfony + doctrine extension.neon + rules.neon |
| `.php-cs-fixer.dist.php` | @Symfony + @Symfony:risky, `declare_strict_types`, `void_return`, `header_comment` (after_declare_strict), `ordered_imports` (class/function/const) |
| `phpunit.xml.dist` | PHPUnit 11, bootstrap `tests/bootstrap.php`, test suite in tests/ |
| `tests/bootstrap.php` | Dotenv bootEnv, with copyright header |

### 4. Frontend Scaffold (5 files)

| File | Description |
|------|-------------|
| `package.json` | React 18, TypeScript 5, Encore, ESLint 9, ts-loader, react types |
| `webpack.config.js` | Encore: React preset, TypeScript loader, entry `assets/app.tsx` |
| `tsconfig.json` | Strict, `jsx: "react-jsx"`, `noEmit: true`, `moduleResolution: "bundler"` |
| `eslint.config.mjs` | ESLint 9 flat config: typescript-eslint + react + react-hooks, `react-in-jsx-scope: off` |
| `assets/app.tsx` | Minimal React 18 entry point (`createRoot` into `#app`), copyright header |

Update `templates/base.html.twig` with `encore_entry_link_tags('app')` / `encore_entry_script_tags('app')` + `<div id="app">`.

### 5. Makefile

Targets using `symfony` CLI wrapper (matching CLAUDE.md convention):

| Group | Targets |
|-------|---------|
| Project | `help` (default), `install`, `start`, `stop` |
| Database | `migrations`, `fixtures` |
| Assets | `assets`, `assets.watch` |
| Quality | `test`, `phpstan`, `cs-fix`, `cs-check`, `eslint` |

### 6. GitHub Actions CI (`.github/workflows/ci.yml`)

Two parallel jobs on every PR and push to main/develop:

- **`php-quality`**: Setup PHP 8.5 + MySQL 8 service → Composer install (cached) → cache:warmup → PHP-CS-Fixer dry-run → PHPStan → PHPUnit
- **`frontend-quality`**: Setup Node 24 → npm ci (cached) → ESLint → TypeScript check (`tsc --noEmit`) → Encore production build

### 7. Verify & PR

- Run `cs-fix` on skeleton PHP files (adds `declare(strict_types=1)` + copyright headers)
- Run all make targets locally to confirm everything works
- Commit on `chore/symfony-skeleton` branch
- Push and open PR to `develop`

## Prerequisites (already committed)

The following documentation was created before the skeleton implementation:

- `CLAUDE.md` — AI context with coding standards, file headers, CLI conventions
- `README.md` — Project overview, stack, feature summary
- `LICENSE` — Apache License 2.0
- `docs/features.md` — Full feature list (32 features across 7 domains, including staff-delegated lending)
- `docs/credits.md` — External references and articles
- `docs/initial_plan.md` — This file
- `docs/models/user.md` — User entity with email verification, roles (global only, staff is per-event)
- `docs/models/event.md` — Event and EventStaff entities with per-event staff assignment
- `docs/models/deck.md` — Deck, DeckCard entities with paste-only import and card data stack
- `docs/models/borrow.md` — Borrow entity with full state machine (direct + staff-delegated)
- `docs/technicalities/scanner.md` — Barcode scanner HID detection strategy
- `docs/standards/coding.md` — PHP & JS coding standards
- `docs/standards/naming.md` — Naming conventions and namespace structure
- `docs/standards/version_control.md` — Gitflow, conventional commits, PR rules
- `docs/standards/documentation.md` — Documentation structure and templates
- `docs/standards/file_headers.md` — Copyright and license header format

### Key Domain Concepts

The feature list defines 3 global roles + per-event staff:

| Role       | Scope | Capabilities |
|------------|-------|-------------|
| **Player** | Global | Register decks, request borrows, attend events |
| **Organizer** | Global | Create events, assign staff teams |
| **Admin**  | Global | Full access, user management, audit log |
| **Staff**  | Per-event | Receive decks from owners, lend/collect on their behalf — assigned per event via `EventStaff` |

The borrow workflow supports two modes:

- **Direct** (F4.1–F4.4): Owner approves, hands off, and collects the deck personally
- **Staff-delegated** (F4.8): Owner opts in per deck per event — staff acts as intermediary (owner → staff → borrower → staff → owner). Owners can keep costly decks under personal control.

### Card Data Stack

Deck lists are imported via copy-paste of standard PTCG text format (no editor, no Limitless dependency):

| Layer | Tool | Role |
|-------|------|------|
| Parse | `ptcgo-parser` (npm) | Converts PTCG text → structured JS objects |
| Card data | TCGdex (`@tcgdex/sdk`) | Card metadata, types, subtypes, images (multilingual) |
| Validation | Custom service | Expanded legality: set range (BLW onward), banned list, card counts |
| Display | Custom React component | Categorized list (Pokemon / Trainer by subtype / Energy) with image hover |

## Key Decisions

- **`install-php-extensions`** over `docker-php-ext-install` — cleaner Alpine builds, auto-manages build deps
- **Node service with `profiles: tools`** — not started by default `docker compose up`, invoked with `docker compose run --rm node`
- **React + ReactDOM as devDependencies** — bundled by Webpack, not served at runtime
- **CI uses `php`/`composer` directly** (not `symfony` wrapper) — Symfony CLI not available in CI runners
- **`header_comment` location: `after_declare_strict`** — matches CLAUDE.md pattern exactly

## Files to Create (skeleton implementation)

Skeleton: `bin/console`, `public/index.php`, `config/*`, `src/Kernel.php`, `composer.json`, `composer.lock`, `symfony.lock`
Docker: `docker-compose.yml`, `docker/php/Dockerfile`, `docker/nginx/default.conf`, `.dockerignore`
Config: `.env`, `.env.test`, `.gitignore`, `phpstan.neon`, `.php-cs-fixer.dist.php`, `phpunit.xml.dist`, `tests/bootstrap.php`
Frontend: `package.json`, `package-lock.json`, `webpack.config.js`, `tsconfig.json`, `eslint.config.mjs`, `assets/app.tsx`
Twig: `templates/base.html.twig` (modified)
Tooling: `Makefile`, `.github/workflows/ci.yml`

## Verification

```bash
make start          # Docker services come up, app responds on :8080
make cs-check       # PHP-CS-Fixer passes (strict_types + headers on all PHP)
make phpstan        # PHPStan level 10 passes
make test           # PHPUnit passes (0 tests, 0 assertions)
make assets         # Webpack Encore builds public/build/app.js
make eslint         # ESLint passes on assets/
```

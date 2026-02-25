# Plan: Symfony 7.2 Skeleton with Docker, Makefile & CI

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Planning

← Back to [Main Documentation](docs.md) | [README](../README.md)

## Context

The expandedDecks project currently only contains documentation files (CLAUDE.md, README.md, docs/, LICENSE). We need to bootstrap the Symfony application, Docker infrastructure, development tooling, and CI pipeline so that feature development can begin on a solid foundation.

## Approach

Work on a `chore/symfony-skeleton` branch off `develop`. Create the Symfony skeleton in a temp directory to preserve existing files, then merge it into the project.

## Steps

### 1. Symfony Skeleton

1. `composer create-project symfony/skeleton:"7.2.*"` into `/tmp`, copy generated files (bin/, config/, public/, src/, composer.json, .env, .gitignore) into the project — preserving docs, CLAUDE.md, README.md, LICENSE, .git
2. `composer require webapp` (Twig, Security, Form, Validator, Mailer, etc.)
3. `composer require --dev` PHPStan 2.x, PHP-CS-Fixer, PHPUnit, phpstan-symfony, phpstan-doctrine
4. `composer require symfony/webpack-encore-bundle`

### 2. Docker Setup (4 files)

| File | Description |
|------|-------------|
| `docker/php/Dockerfile` | PHP 8.3-FPM Alpine + pdo_mysql, intl, opcache + Composer |
| `docker/nginx/default.conf` | Standard Symfony Nginx config, fastcgi to php:9000 |
| `docker-compose.yml` | Services: php, nginx (:8080), database (MySQL 8, healthcheck), node (20-alpine, run-only) |
| `.dockerignore` | .git, vendor, node_modules, var |

### 3. Config Files (6 files)

| File | Description |
|------|-------------|
| `.env` | Modify skeleton default: DATABASE_URL for MySQL 8, project-specific |
| `.env.test` | Test env: APP_SECRET, test DATABASE_URL |
| `.gitignore` | Merge Symfony defaults + .idea, .DS_Store, .claude, docker data |
| `phpstan.neon` | Level 10, paths: src, includes: symfony + doctrine extensions |
| `.php-cs-fixer.dist.php` | @Symfony + @Symfony:risky, strict_types, void_return, ordered imports |
| `phpunit.xml.dist` | PHPUnit 11, bootstrap tests/bootstrap.php, test suite in tests/ |

### 4. Frontend Scaffold (5 files)

| File | Description |
|------|-------------|
| `package.json` | React 18, TypeScript 5, Encore 4, ESLint 9, ts-loader, react types |
| `webpack.config.js` | Encore: React preset, TypeScript loader, entry `assets/app.tsx` |
| `tsconfig.json` | Strict, ESNext module, react-jsx |
| `eslint.config.mjs` | Flat config: recommended + typescript-eslint + react + react-hooks |
| `assets/app.tsx` | Minimal React entry point rendering into `#app` |

### 5. Makefile

Targets using `symfony` CLI wrapper (matching CLAUDE.md convention):
- **Project:** `install`, `start`, `stop`
- **Database:** `migrations`, `fixtures`
- **Assets:** `assets`, `assets.watch`
- **Quality:** `test`, `phpstan`, `cs-fix`, `cs-check`, `eslint`
- **Help:** `help` (default, auto-generated from comments)

### 6. GitHub Actions CI (`.github/workflows/ci.yml`)

Two parallel jobs on every PR and push to main/develop:

**`php-quality`**: Setup PHP 8.3 + MySQL 8 service → Composer install (cached) → PHP-CS-Fixer dry-run → PHPStan → PHPUnit

**`frontend-quality`**: Setup Node 20 → yarn install (cached) → ESLint → TypeScript check (`tsc --noEmit`)

### 7. Verify & PR

- Run all make targets locally to confirm everything works
- Commit on `chore/symfony-skeleton` branch
- Push and open PR to `develop`

## Files Created/Modified (29 files)

Skeleton: `bin/console`, `public/index.php`, `config/*`, `src/Kernel.php`, `composer.json`, `composer.lock`, `symfony.lock`
Docker: `docker-compose.yml`, `docker/php/Dockerfile`, `docker/nginx/default.conf`, `.dockerignore`
Config: `.env`, `.env.test`, `.gitignore`, `phpstan.neon`, `.php-cs-fixer.dist.php`, `phpunit.xml.dist`
Frontend: `package.json`, `webpack.config.js`, `tsconfig.json`, `eslint.config.mjs`, `assets/app.tsx`
Tooling: `Makefile`, `.github/workflows/ci.yml`

## Verification

```bash
make start          # Docker services come up, app responds on :8080
make cs-check       # PHP-CS-Fixer passes
make phpstan        # PHPStan level 10 passes
make test           # PHPUnit passes
make assets         # Webpack Encore builds
make eslint         # ESLint passes
```

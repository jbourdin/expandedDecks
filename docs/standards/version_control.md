# Version Control

> **Audience:** Developer · **Scope:** Workflow, Git

← Back to [Main Documentation](../docs.md) | [CLAUDE.md](../../CLAUDE.md)

## Gitflow Workflow

> **CRITICAL: NEVER commit directly to `main` or `develop`.** Always create a feature/fix/docs branch first, commit there, then open a Pull Request.

### Branch Structure

```
main          ← production-ready code (protected, PR-only)
  └── develop ← integration branch (PR-only)
        ├── feature/*    ← new functionality
        ├── fix/*        ← bug fixes
        ├── refactor/*   ← code improvements
        ├── docs/*       ← documentation
        ├── chore/*      ← maintenance tasks
        └── test/*       ← test additions
```

### Branch Prefixes

| Prefix       | Purpose                      | Base branch | Merges into | Example                          |
|--------------|------------------------------|-------------|-------------|----------------------------------|
| `feature/`   | New functionality            | `develop`   | `develop`   | `feature/deck-borrow-workflow`   |
| `fix/`       | Bug fixes                    | `develop`   | `develop`   | `fix/return-date-calculation`    |
| `refactor/`  | Code improvements            | `develop`   | `develop`   | `refactor/borrow-service`        |
| `docs/`      | Documentation                | `develop`   | `develop`   | `docs/api-integration`           |
| `chore/`     | Maintenance tasks            | `develop`   | `develop`   | `chore/update-dependencies`      |
| `test/`      | Test additions               | `develop`   | `develop`   | `test/borrow-workflow`           |
| `hotfix/`    | Emergency production fixes   | `main`      | `main`      | `hotfix/critical-bug`            |
| `release/`   | Release preparation          | `develop`   | `main`      | `release/1.0.0`                  |

## Commit Messages

### Format (Conventional Commits)

```
<type>(<scope>): <short description>
```

- **Subject line**: imperative mood, lowercase, no trailing period, max 72 characters
- **Body** (optional): explain "why" not "what", wrap at 72 characters

### Types

| Type       | Purpose                        |
|------------|--------------------------------|
| `feat`     | New feature                    |
| `fix`      | Bug fix                        |
| `docs`     | Documentation only             |
| `style`    | Code style (formatting, etc.)  |
| `refactor` | Code restructuring (no behavior change) |
| `test`     | Adding or updating tests       |
| `chore`    | Maintenance (deps, CI, config) |
| `perf`     | Performance improvement        |

### Scopes

| Scope    | Domain                        |
|----------|-------------------------------|
| `deck`   | Deck library                  |
| `borrow` | Borrow workflow               |
| `event`  | Event management              |
| `user`   | User management               |
| `label`  | Zebra label printing          |
| `api`    | External API integrations     |
| `auth`   | Authentication & authorization|
| `infra`  | Infrastructure & tooling      |

### Examples

```
feat(deck): add deck list paste and validation
fix(borrow): correct return date validation
docs(readme): update feature list
chore(infra): add Docker Compose for MySQL
test(borrow): add unit tests for borrow approval
refactor(deck): extract archetype matching to dedicated service
```

## Pull Requests

### Title Format

`<emoji> <type>: <short description>` — under 70 characters, imperative mood.

| Emoji                | Type     | Branch pattern           |
|----------------------|----------|--------------------------|
| `:sparkles:`         | Feature  | `feature/* → develop`    |
| `:bug:`              | Bugfix   | `fix/* → develop`        |
| `:recycle:`          | Refactor | `refactor/* → develop`   |
| `:memo:`             | Docs     | `docs/* → develop`       |
| `:white_check_mark:` | Tests   | `test/* → develop`       |
| `:wrench:`           | Config   | `chore/* → develop`      |
| `:rocket:`           | Release  | `develop → main`         |
| `:ambulance:`        | Hotfix   | `hotfix/* → main`        |

### Rules

- One feature or fix per PR — keep small and focused
- Branch must be up-to-date with target branch before merge
- PR description explains the "why", not just the "what"
- CI must pass (PHP-CS-Fixer, PHPStan, ESLint, PHPUnit)

## Pre-Commit Checklist

Run **before every commit/push**:

```bash
make cs-fix     # Fix code style (PHP-CS-Fixer)
make phpstan    # Static analysis
make test       # Run test suite
make eslint     # Lint frontend code
```

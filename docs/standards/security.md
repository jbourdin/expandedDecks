# Security Scanning

> **Audience:** Developer, AI Agent · **Scope:** Dependency vulnerability scanning

← Back to [Standards](../docs.md#standards)

---

## Overview

The project uses automated dependency vulnerability scanning for both PHP and JavaScript ecosystems. Scans run locally via Make targets, in CI on every push/PR, and on a weekly schedule via GitHub Dependabot.

## Local Scanning

| Command          | Description                                  |
|------------------|----------------------------------------------|
| `make audit`     | Run both PHP and JS dependency audits        |
| `make audit.php` | Run PHP dependency audit only (`composer audit`) |
| `make audit.js`  | Run JS dependency audit only (`npm audit`)   |

These commands exit with a non-zero code when vulnerabilities are found, making them suitable for pre-commit checks.

## CI Integration

The **Security Audit** job in `.github/workflows/ci.yml` runs `composer audit` and `npm audit` on every push to `main`/`develop` and on every pull request. It runs in parallel with existing quality jobs and requires no database or build step.

If the job fails, it means one or more dependencies have known CVEs that must be addressed before merging.

## Dependabot

GitHub Dependabot is configured in `.github/dependabot.yml` to scan both ecosystems weekly:

- **Composer** (PHP) — checks `composer.lock` against the [PHP Security Advisories Database](https://github.com/FriendsOfPHP/security-advisories)
- **npm** (JavaScript) — checks `package-lock.json` against the [GitHub Advisory Database](https://github.com/advisories)

Dependabot opens PRs against `develop` (per Gitflow) when new vulnerabilities are disclosed.

## Composer Audit Blocking

The `composer.json` config includes `"block-insecure": true`, which causes `composer install` and `composer update` to fail if any installed package has a known vulnerability. This acts as a hard gate beyond just CI — developers cannot install dependencies locally without resolving advisories first.

## npm Overrides

When a transitive dependency has a vulnerability but its direct parent hasn't released a fix, use npm `overrides` in `package.json` to force a patched version:

```json
{
    "overrides": {
        "vulnerable-package": "^x.y.z"
    }
}
```

Verify compatibility by running `make assets` and `make test.front` after adding an override.

## Responding to Vulnerabilities

1. Run `make audit` to identify affected packages
2. For PHP: `symfony composer update <package>` to pull the patched version
3. For JS: `npm audit fix` for safe upgrades, or add an `overrides` entry for transitive deps
4. Verify with `make audit`, `make assets`, and `make test` / `make test.front`
5. Commit and push — the CI Security Audit job confirms the fix

# Coding Standards

> **Audience:** Developer · **Scope:** Coding Standards

← Back to [Main Documentation](../docs.md) | [CLAUDE.md](../../CLAUDE.md)

## PHP

### Baseline

- **PSR-12** coding style with the **@Symfony** PHP-CS-Fixer ruleset
- PHP-CS-Fixer config: `.php-cs-fixer.dist.php` at project root
- Run `make cs-fix` to auto-fix, `make cs-check` for dry-run

### Strict Typing

- `declare(strict_types=1);` is **required** in every PHP file
- `void` return type is **mandatory** on methods that return nothing
- Strict comparison operators (`===`, `!==`) only — no loose comparisons

### Imports

Ordered by category, then alphabetically:

1. Classes
2. Functions
3. Constants

```php
use App\Entity\Deck;
use App\Service\BorrowManager;
use Symfony\Component\HttpFoundation\Response;

use function sprintf;

use const App\BORROW_STATUS_PENDING;
```

### Static Analysis

- **PHPStan Level 10** (maximum)
- Config: `phpstan.neon` at project root
- Extensions: `phpstan-symfony`, `phpstan-doctrine`
- Run: `make phpstan`

### Symfony Conventions

- **Constructor injection** with autowiring — no `get()` from container
- **Thin controllers**: business logic belongs in services, not controllers
- **Doctrine entities** use PHP 8 attributes (not annotations)
- **Service configuration**: autowiring + param binding in `services.yaml`
- **Environment variables** for all external configuration (no hardcoded values)
- **Visibility required** on all class constants, methods, and properties

### Testing

- **PHPUnit** for unit and functional tests
- Test method naming: `camelCase` with `test` prefix (`testDeckCanBeBorrowed()`)
- Config: `phpunit.xml.dist` at project root
- Run: `make test`

## TypeScript / JavaScript

### Baseline

- **TypeScript** for all frontend code (strict mode enabled in `tsconfig.json`)
- **ESLint** for linting — flat config format (`eslint.config.mjs`)
- **React** as the UI framework, via Webpack Encore

### React

- Functional components with hooks — no class components
- Custom hooks prefixed with `use` (`useScannerDetection`, `useDeckSearch`)
- Props typed via TypeScript interfaces

### Build

- **Webpack Encore** for asset compilation
- Entry point: `assets/app.tsx`
- Run: `make assets` (build), `make assets.watch` (dev watch mode)

## Feature Traceability

Methods implementing a documented feature **MUST** reference the feature ID via `@see` in their PHPDoc or JSDoc. This links code back to the specification.

```php
/**
 * @see docs/features.md F4.3 — Confirm deck hand-off (lend)
 */
public function confirmHandOff(Deck $deck, Borrow $borrow): void
```

```typescript
/**
 * @see docs/features.md F5.3 — Scan label to identify deck
 * @see docs/technicalities/scanner.md
 */
function useScannerDetection(onScan: (value: string) => void) {
```

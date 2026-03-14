---
description: Analyze test coverage of added/modified lines in the current PR
allowed-tools: Bash, Read, Glob, Grep, Agent
---

# PR Coverage Analysis

Evaluate whether the added or modified lines in the current PR are covered by automated tests.

## Instructions

### 1. Identify changed files

Run in parallel:
- `git branch --show-current` — get the current branch
- `gh pr view --json baseRefName` — get the base branch

Then run:
- `git diff <base-branch>...HEAD --name-only` — list all changed files
- `git diff <base-branch>...HEAD` — get the full diff

### 2. Categorize changed files

Split files into:
- **PHP source files** (`src/**/*.php`) — these should be covered by PHPUnit
- **Frontend files** (`assets/**/*.ts`, `assets/**/*.tsx`) — these should be covered by Vitest
- **Config/infra files** (`.env*`, `config/**`, `Dockerfile`, `Makefile`, etc.) — not directly testable but may affect testable behavior
- **Test files** (`tests/**`) — these ARE the tests, skip coverage analysis
- **Documentation** (`docs/**`, `*.md`) — skip

### 3. Analyze PHP coverage

For each changed PHP source file:

1. Read the file to understand what was added/modified.
2. Extract the changed lines from the diff (lines starting with `+`, excluding file headers).
3. Search for existing tests:
   - Check `tests/` for test files matching the class name (e.g., `FooService.php` → `FooServiceTest.php`)
   - Use `Grep` to find references to the class/method in test files
4. For each changed method or significant code block, determine:
   - **Covered**: A test file exists that exercises this code path
   - **Partially covered**: Tests exist for the class but don't cover the new/changed code paths
   - **Not covered**: No tests found for this code

### 4. Analyze frontend coverage

For each changed frontend file:

1. Check for matching test files in `assets/` (e.g., `Foo.tsx` → `Foo.test.tsx`)
2. Search for imports of the changed module in test files
3. Classify as covered/partially covered/not covered

### 5. Generate coverage report

Run `make coverage` to generate a clover XML report, then analyze it:
- Parse `var/coverage/clover.xml` for the changed files
- Extract line-level coverage data for modified lines

If `make coverage` fails (e.g., no database), fall back to the static analysis from steps 3–4.

### 6. Present results

Output a structured report:

```
## PR Coverage Report

### Summary
- X/Y changed source files have test coverage
- N lines added/modified, M lines covered by tests

### Per-file breakdown

| File | Lines changed | Coverage | Test file(s) |
|------|--------------|----------|-------------|
| src/Service/Foo.php | +15 | ✅ Covered | tests/Service/FooTest.php |
| src/Controller/Bar.php | +30 | ⚠️ Partial | tests/Functional/BarTest.php (missing new route) |
| src/Entity/Baz.php | +5 | ❌ None | — |

### Uncovered code

For each uncovered or partially covered file, list:
- The specific methods/blocks that lack coverage
- What kind of test would cover them (unit, functional, integration)
- Priority: High (business logic), Medium (controller/routing), Low (config/glue code)
```

## Important

- Do NOT write or modify any code. This skill is read-only analysis.
- Do NOT create test files. Use `/cover-more` for that.
- Focus on meaningful coverage gaps, not boilerplate (getters/setters, constructors with no logic).
- If the phpunit.xml.dist test suites don't include certain test directories, flag this as a configuration issue.

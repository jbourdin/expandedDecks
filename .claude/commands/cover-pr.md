---
description: Analyze test coverage of added/modified lines in the current PR
allowed-tools: Bash, Read, Glob, Grep, Agent, Skill
---

# PR Coverage Analysis

Evaluate whether the added or modified lines in the current PR are covered by automated tests, using the Codecov report from CI.

## Instructions

### 1. Pre-flight checks

**Check for uncommitted or unpushed changes:**
```bash
git status
git rev-list --count @{upstream}..HEAD 2>/dev/null
```

If there are uncommitted changes (staged, unstaged, or untracked source files) or unpushed commits, **stop** and tell the user: "There are uncommitted or unpushed changes. Run `/pr` first so CI runs on the latest code."

**Check for an existing PR:**
```bash
gh pr view --json number,baseRefName
```

If no PR exists, **stop** and tell the user: "No PR found for this branch. Run `/pr` first."

### 2. Wait for CI

Run `/ci` to wait for all CI checks to pass on the current PR. This ensures the Codecov report is up to date with the latest push.

If CI fails, `/ci` will handle investigation and fixes. Once CI is green, continue.

### 3. Fetch the Codecov report

Run:
```
gh api repos/{owner}/{repo}/issues/{pr-number}/comments --jq '.[].body'
```

Look for the comment from Codecov (starts with `## [Codecov]`). Extract:
- **Patch coverage percentage** — the overall coverage of changed lines
- **Files with missing lines** — the table listing files, their patch %, and missing line counts
- **Overall project coverage change** (if available)

If no Codecov comment exists yet, tell the user: "Codecov report not available yet. Try again in a moment."

### 4. Get the changed source files

Run:
- `git diff <base-branch>...HEAD --name-only` — list all changed files
- `git diff <base-branch>...HEAD --stat -- src/` — summary of source changes

Categorize files into:
- **PHP source files** (`src/**/*.php`) — should be covered by PHPUnit
- **Frontend files** (`assets/**/*.ts`, `assets/**/*.tsx`) — should be covered by Vitest
- **Config/infra/docs** — skip coverage analysis
- **Test files** (`tests/**`) — these ARE the tests, skip

### 5. Cross-reference Codecov with test files

For each PHP source file listed in the Codecov report as having missing lines:

1. Search for matching test files in `tests/` (e.g., `FooService.php` → `FooServiceTest.php`)
2. If tests exist but Codecov shows 0% coverage, check `phpunit.xml.dist` — the test directory may not be included in any test suite (this means tests exist but CI never runs them)
3. If no tests exist at all, flag as uncovered

### 6. Check test suite configuration

Read `phpunit.xml.dist` and verify all test directories under `tests/` are included in a test suite. Compare with:
```
ls tests/
```
Flag any directories that exist but are not listed in any `<testsuite>` — these tests are invisible to CI coverage.

### 7. Present results

Output a structured report:

```
## PR Coverage Report (from Codecov)

### Codecov Summary
- **Patch coverage:** X% (Y lines missing)
- **Project coverage:** Z% (delta)

### Per-file breakdown

| File | Patch % | Missing lines | Test file(s) | Issue |
|------|---------|---------------|-------------|-------|
| src/Service/Foo.php | 100% | 0 | tests/Service/FooTest.php | — |
| src/Controller/Bar.php | 0% | 33 | tests/Controller/BarTest.php | Suite config: tests/Controller/ not in phpunit.xml.dist |
| src/Command/Baz.php | 0% | 50 | — | No tests written |

### Issues found

1. **Test suite configuration:** [list directories missing from phpunit.xml.dist]
2. **Uncovered files:** [list files with no tests at all]
3. **Tests exist but not run in CI:** [list files where tests exist but the directory isn't in a suite]

### Recommendations
- [specific actionable items, e.g., "Add tests/Controller/ to the unit suite in phpunit.xml.dist"]
- [e.g., "Write unit tests for CreateAdminCommand using CommandTester"]
```

## Important

- Do NOT write or modify any code. This skill is read-only analysis.
- Do NOT create test files. Use `/cover-more` for that.
- Focus on meaningful coverage gaps, not boilerplate (getters/setters, constructors with no logic).
- The Codecov report is the source of truth for line-level coverage — prefer it over local `make coverage` runs.

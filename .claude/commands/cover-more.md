---
description: Find least covered code and write tests to improve coverage
allowed-tools: Bash, Read, Write, Edit, Glob, Grep, Agent
---

# Improve Test Coverage

Identify the least covered parts of the codebase and write tests to improve coverage, using Codecov data from CI as the source of truth.

## Instructions

### 1. Get coverage data

Use the on-demand **Coverage** workflow to get a full coverage report for the current branch:

```bash
# Trigger the coverage workflow on the current branch
gh workflow run coverage.yml --field branch=$(git branch --show-current)

# Wait for it to complete
gh run list --workflow=coverage.yml --limit=1 --json databaseId,status --jq '.[0]'
# Poll with: gh run watch <run-id>
```

Once complete, download the coverage artifacts:
```bash
gh run download <run-id> --name coverage-report --dir var/coverage/
```

This gives you:
- `var/coverage/clover.xml` — machine-readable line-level coverage
- `var/coverage/coverage-text.txt` — human-readable per-class percentages

Parse `coverage-text.txt` for the per-class breakdown. Use `clover.xml` for line-level detail when needed.

**Fallback** — if the workflow is not available or fails:
- Check the latest Codecov PR comment: `gh api repos/{owner}/{repo}/issues/{pr-number}/comments --jq '.[].body'`
- Or run locally: `make coverage` (requires pcov + database)

### 2. Check test suite configuration

Read `phpunit.xml.dist` and compare with actual test directories:
```
ls tests/
```

Verify all directories are included in a `<testsuite>`. If any are missing (e.g., `tests/Controller/`, `tests/Command/`, `tests/DBAL/`), **add them to the appropriate suite as a first fix** — this alone can significantly improve reported coverage.

### 3. Identify coverage gaps

From the Codecov data or local coverage report, find:
1. **Classes with 0% coverage** — completely untested
2. **Classes with < 50% coverage** — partially tested
3. **Classes with 50–80% coverage** — could benefit from additional tests

Sort by priority:
- **High**: Services, message handlers, event listeners, security voters — business logic
- **Medium**: Controllers, commands — integration points
- **Low**: Entities (mostly getters/setters), Twig extensions, simple DTOs

### 4. Select targets

Pick the **top 3–5 highest-priority uncovered or under-covered classes**. For each:

1. Read the source file to understand what it does
2. Read any existing test file if one exists
3. Identify which methods/branches are not covered
4. Determine the best test approach:
   - **Unit test**: For pure logic, services with injectable dependencies
   - **Functional test**: For controllers, routes, security checks
   - **Integration test**: For database queries, message dispatching

### 5. Write tests

For each target, write or extend tests following project conventions:

- File location: match the source structure under `tests/` (e.g., `src/Service/Foo.php` → `tests/Service/FooTest.php`)
- Class style: `final class`, extends `TestCase`, uses `MockObject` for dependencies
- Method naming: `testMethodNameDescribesScenario()`
- Include the copyright header
- Add `@see` docblock referencing the feature ID if applicable
- Use `self::assert*` (static calls)
- Mock external dependencies, don't test framework internals

### 6. Validate

After writing tests:

1. Run `make cs-fix` to fix code style
2. Run `make phpstan` to verify type safety
3. Run the new tests individually: `symfony php bin/phpunit tests/Path/To/NewTest.php`
4. Run `make test.unit` to verify nothing is broken

### 7. Report results

Present a summary:

```
## Coverage Improvement Report

### Test suite fixes
- Added tests/Controller/ and tests/Command/ to phpunit.xml.dist unit suite

### New/extended tests

| Test file | Covers | Methods added | Lines covered |
|-----------|--------|---------------|--------------|
| tests/Service/FooTest.php | src/Service/Foo.php | 3 | +45 |

### Expected coverage impact
- Files fixed: [list files that should now show coverage in Codecov]
- Note: Push and wait for CI to see updated Codecov numbers

### Remaining gaps
- [list any significant untested code that was intentionally skipped and why]
```

## Important

- **DO commit the new tests** — they are deliverables.
- Follow all coding standards from CLAUDE.md (headers, naming, PHPStan level 10).
- Do NOT modify source code to make it testable — only write tests for the code as it is.
- Do NOT test trivial code (auto-generated getters/setters, empty constructors).
- Do NOT add tests for code that is inherently untestable without infrastructure (e.g., Doctrine migrations, Kernel bootstrap).
- Prefer focused, fast unit tests over slow functional tests when both are viable.
- The Codecov report from CI is the source of truth — prefer it over local coverage when available.

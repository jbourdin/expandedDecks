---
description: Find least covered code and write tests to improve coverage
allowed-tools: Bash, Read, Write, Edit, Glob, Grep, Agent
---

# Improve Test Coverage

Identify the least covered parts of the codebase and write tests to improve coverage.

## Instructions

### 1. Generate coverage data

Run `make coverage` to produce `var/coverage/clover.xml` and `--coverage-text` output.

If `make coverage` fails (e.g., database unavailable), run unit tests only:
```
symfony php -d pcov.enabled=1 bin/phpunit --testsuite unit --coverage-clover var/coverage/clover.xml --coverage-text
```

Capture the text output — it shows per-class coverage percentages.

### 2. Check test suite configuration

Read `phpunit.xml.dist` and verify all test directories under `tests/` are included in a test suite. If any directories are missing (e.g., `tests/Controller/`, `tests/Command/`, `tests/DBAL/`), flag them and add them to the appropriate suite as a first fix.

### 3. Identify coverage gaps

Parse the coverage text output to find:
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
5. Optionally re-run `make coverage` and compare before/after percentages

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

### Coverage change
- Before: X% overall (Y% for targeted files)
- After: X% overall (Y% for targeted files)

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

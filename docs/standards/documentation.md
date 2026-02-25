# Documentation Standards

> **Audience:** Developer · **Scope:** Documentation

← Back to [Main Documentation](../docs.md) | [CLAUDE.md](../../CLAUDE.md)

## General Rules

- Documentation **MUST** be updated in the same PR as code changes — never defer to a separate PR
- Every documentation file includes an **audience/scope header** and a **back-link** to its parent document
- Documentation is written in **Markdown** (CommonMark specification)

## File Organization

### Naming

- All doc files use **`snake_case.md`** exclusively (no PascalCase, kebab-case, README.md exceptions, or index.md)
- Maximum depth: **3 levels** from `docs/` root

### Structure

```
docs/
├── docs.md                    # Main entry point for documentation
├── features.md                # Complete feature list with IDs and priorities
├── credits.md                 # External references and articles
├── initial_plan.md            # Initial project plan
├── standards/                 # Coding and workflow standards
│   ├── coding.md              # PHP & JS coding standards
│   ├── naming.md              # Naming conventions
│   ├── version_control.md     # Gitflow, commits, PRs
│   ├── documentation.md       # This file
│   └── file_headers.md        # Copyright & license headers
└── technicalities/            # Technical deep-dives
    └── scanner.md             # Barcode scanner HID detection
```

### Feature Documentation Pattern

For **simple features**: single file in the appropriate section.

For **complex features**: entrypoint `.md` file + subdirectory `/` with detailed docs.

```
docs/features/borrow.md           # Overview of borrow workflow
docs/features/borrow/             # Detailed sub-docs
├── state_machine.md              # Borrow state transitions
└── notifications.md              # Borrow notification rules
```

## Document Template

Every documentation file should follow this template:

```markdown
# Title

> **Audience:** Developer | AI Agent | Organizer · **Scope:** Feature | Reference | Architecture

← Back to [Parent Document](../parent.md) | [Main Documentation](../docs.md)

## Content

...
```

### Audience Tags

| Tag        | Who                                          |
|------------|----------------------------------------------|
| Developer  | Human developers working on the project      |
| AI Agent   | AI assistants (Claude, Copilot, etc.)        |
| Player     | End users who play Pokemon TCG               |
| Organizer  | Users who organize events                    |

### Scope Tags

| Tag           | What                                       |
|---------------|--------------------------------------------|
| Overview      | High-level project description             |
| Features      | Feature specifications                     |
| Reference     | Lookup tables, conventions, constants      |
| Architecture  | System design and technical decisions      |
| Planning      | Roadmaps and implementation plans          |
| Coding Standards | Code style, linting, formatting rules  |
| Workflow      | Git, CI/CD, deployment processes           |

## Cross-Referencing

- All internal links use **relative paths**
- Every doc file (except `docs/docs.md`) **MUST** include a back-link to its parent
- Code references use `@see` in PHPDoc/JSDoc to link to documentation (see [coding.md](coding.md))

## PHPDoc / JSDoc

- Use `@see docs/features.md F4.3` to link methods to their feature specification
- Use `@see docs/technicalities/scanner.md` to link to technical deep-dives
- Keep inline code comments minimal — prefer self-documenting code with doc references

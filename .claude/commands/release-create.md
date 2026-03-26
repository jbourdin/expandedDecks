---
description: Suggest a release version based on existing tags and diff, then create the release branch, changelog, and PR
allowed-tools: Bash, Read, Edit, Glob, Grep, AskUserQuestion
---

# Release Creation Workflow

Create a new release branch with changelog entry and PR, guided by version suggestion.

**Argument:** `$ARGUMENTS` — if it contains `beta`, create a beta pre-release. If it contains a specific version number (e.g. `1.2.0`), use that directly.

## Instructions

### 1. Parse arguments

- If `$ARGUMENTS` contains a semver version (e.g. `1.2.0`, `1.0.0-beta.5`), use it directly as the target version — skip to step 4.
- If `$ARGUMENTS` contains `beta` (case-insensitive), the release will be a beta pre-release.
- Otherwise, the release is a stable release.

### 2. Determine current version and compute suggestions

Run in parallel:
- `git tag --sort=-v:refname | head -20` — list recent tags to find the latest version
- `git log $(git describe --tags --abbrev=0)..HEAD --oneline` — commits since last tag
- `git diff $(git describe --tags --abbrev=0)..HEAD --stat` — file-level diff summary

From the latest tag, determine the current version. Then compute the **three candidate versions**:

**For a stable release:**
- **Patch**: increment patch (e.g. `1.2.3` → `1.2.4`)
- **Minor**: increment minor, reset patch (e.g. `1.2.3` → `1.3.0`)
- **Major**: increment major, reset minor and patch (e.g. `1.2.3` → `2.0.0`)

If the latest tag is a beta (e.g. `v1.0.0-beta.4`), stable candidates should be based on the base version: patch = `1.0.0`, minor = `1.1.0`, major = `2.0.0`.

**For a beta release:**
- If the latest tag is already a beta of the same base version (e.g. `v1.0.0-beta.4`), the default suggestion is the next beta increment (`1.0.0-beta.5`).
- Otherwise, offer: `<patch>-beta.1`, `<minor>-beta.1`, `<major>-beta.1`.

### 3. Suggest version to the user

Analyze the commits since the last tag to make a recommendation:
- If there are only bug fixes (`fix(` commits), recommend **patch**.
- If there are new features (`feat(` commits), recommend **minor**.
- If there are breaking changes (commits with `!` or `BREAKING CHANGE`), recommend **major**.
- If continuing a beta series, recommend the **next beta increment**.

Present the options to the user using AskUserQuestion. Format:

```
Based on X commits since vY.Y.Y:
- N fix commits, N feat commits, ...

Suggested versions:
  1. [recommended] <patch version>
  2. <minor version>
  3. <major version>

Or type a custom version number.
```

Mark the recommended option with `[recommended]`. Wait for the user's answer. Accept `1`, `2`, `3`, or a custom version string.

### 4. Guard rails

- **CRITICAL:** Verify we are on the `develop` branch. If not, **stop** and tell the user.
- Verify the chosen version tag (`v<version>`) does not already exist: `git tag -l "v<version>"`. If it exists, **stop** and tell the user.

### 5. Create release branch

```bash
git checkout -b release/<version>
```

### 6. Generate changelog entry

Read `docs/changelog.md` to understand the existing format. Then read the commit log since the last tag to draft a changelog section.

Group commits into categories following the existing changelog format:
- **Features** — `feat(` commits
- **Bug Fixes** — `fix(` commits
- **Infrastructure** — `chore(infra)`, `perf(` commits
- **Documentation** — `docs(` commits
- **Testing & Quality** — `test(` commits
- **Refactoring** — `refactor(` commits

Only include categories that have commits. Write a one-line summary after the version heading.

Insert the new section in `docs/changelog.md` **below the `## [Unreleased]` section and its `---` separator**, before the previous release entry. Use the Edit tool.

Today's date for the entry: use the current date from the system context.

### 7. Commit, push, and create PR

1. Stage the changelog: `git add docs/changelog.md`
2. Commit: `docs(changelog): add <version> release notes`
3. Push: `git push -u origin release/<version>`
4. Create PR targeting `main`:

```bash
gh pr create --base main --title ":rocket: Release: <version>" --body "$(cat <<'EOF'
## Summary
- Release <version> — see docs/changelog.md for details.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

### 8. Report result

Print the PR URL and remind the user of the remaining release steps:
- Wait for CI to pass
- Merge the PR (merge commit, not squash)
- Create GitHub release with `gh release create` (use `--latest` if the version is higher than the current latest)
- Back-merge `main` into `develop`
- These steps can be done with `/ci`

## Important

- **NEVER add `Co-Authored-By` or AI attribution trailers to commit messages.**
- **NEVER merge the PR.** This skill only creates the release branch, changelog, and PR.
- Follow all conventions from CLAUDE.md and `docs/standards/release_process.md`.
- Always use HEREDOC for commit messages and PR bodies.
- Use `git commit` with HEREDOC, not `-m` for multiline messages.

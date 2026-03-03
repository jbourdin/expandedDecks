# Release Process

> **Audience:** Developer, AI Agent · **Scope:** Workflow

← Back to [Main Documentation](../docs.md) | [Version Control](version_control.md)

## Release Workflow (Gitflow)

### Steps

1. **Create release branch** from `develop`:

   ```bash
   git checkout develop && git pull origin develop
   git checkout -b release/x.y.z
   ```

2. **Update changelog** — add an entry in [`docs/changelog.md`](../changelog.md) following the format described in [Documentation Standards](documentation.md#changelog):

   ```bash
   # Edit docs/changelog.md: add ## [x.y.z] — YYYY-MM-DD section
   ```

3. **Commit & push** the release branch:

   ```bash
   git add docs/changelog.md
   git commit -m "docs: add changelog for vx.y.z"
   git push -u origin release/x.y.z
   ```

4. **Open a Pull Request** targeting `main`:

   ```bash
   gh pr create --title ":rocket: Release: x.y.z" --body "Release x.y.z — see docs/changelog.md for details."
   ```

5. **Wait for CI** to pass:

   ```bash
   gh pr checks <number> --watch
   ```

6. **Merge the PR** (merge commit, not squash or rebase):

   ```bash
   gh pr merge <number> --merge
   ```

7. **Pull main** locally:

   ```bash
   git checkout main && git pull origin main
   ```

8. **Create a GitHub release** with tag:

   ```bash
   # First release — use the full changelog file:
   gh release create vx.y.z --target main --title "vx.y.z" --notes-file docs/changelog.md

   # Subsequent releases — extract only the relevant section:
   # Copy the ## [x.y.z] section from docs/changelog.md into a temp file, then:
   gh release create vx.y.z --target main --title "vx.y.z" --notes-file /tmp/release_notes.md
   ```

9. **Back-merge `main` into `develop`**:

   ```bash
   git checkout develop && git pull origin develop
   git merge main
   git push origin develop
   ```

10. **Clean up** the release branch:

    ```bash
    git branch -d release/x.y.z
    # Remote branch is auto-deleted on PR merge, or manually:
    git push origin --delete release/x.y.z
    ```

## Versioning

This project follows [Semantic Versioning](https://semver.org/):

| Segment | Incremented when…                                      |
|---------|---------------------------------------------------------|
| MAJOR   | Incompatible changes to public interfaces or data model |
| MINOR   | New features added in a backwards-compatible manner     |
| PATCH   | Backwards-compatible bug fixes                          |

**Tag format:** `vx.y.z` (e.g. `v0.1.0`, `v1.2.3`).

## Verification Checklist

After completing a release, verify:

```bash
# GitHub release exists with correct notes
gh release view vx.y.z

# Git tag is present
git tag -l 'vx.y.*'

# Merge commit on main
git log main --oneline -3

# Back-merge on develop
git log develop --oneline -3
```

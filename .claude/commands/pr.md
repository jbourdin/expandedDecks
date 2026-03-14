---
description: Commit, push, and create or update the Pull Request for the current branch
allowed-tools: Bash, Read, Glob, Grep
---

# Pull Request Workflow

Commit any uncommitted work, push to remote, and create or update the PR — all in one step.

## Instructions

### 1. Assess current state

Run in parallel:
- `git status` — check for uncommitted changes (staged or unstaged, untracked files). Never use `-uall`.
- `git log --oneline -1` — get the latest commit
- `git branch --show-current` — get the current branch name

**Guard rails:**
- If the branch is `main` or `develop`, **stop immediately** and tell the user: "You are on `<branch>`. Create a feature branch first (`git checkout -b <prefix>/<name>`)."
- Determine the **base branch**: if the branch starts with `hotfix/` or `release/`, base is `main`; otherwise base is `develop`.

### 2. Commit uncommitted changes (if any)

If there are staged or unstaged changes or untracked source files:

1. Run `git diff` and `git diff --cached` to review changes, and `git log --oneline -5` to match commit message style.
2. Stage relevant files by name (never `git add -A` or `git add .`). Do not stage files that likely contain secrets (`.env`, credentials, etc.).
3. Create a commit following Conventional Commits format (`<type>(<scope>): <short description>`).
4. **NEVER add `Co-Authored-By` or any AI attribution trailer to commit messages.** The human user is the sole author.
5. Use a HEREDOC for the commit message.

If there are no changes to commit, skip this step.

### 3. Push to remote

Run in parallel:
- `git rev-list --count @{upstream}..HEAD 2>/dev/null` — check for unpushed commits (may fail if no upstream)
- `git remote get-url origin` — verify remote exists

If there are unpushed commits or no upstream is set:
- Push with `git push -u origin HEAD`

If already up to date, skip this step.

### 4. Create or update the Pull Request

Check if a PR already exists for this branch:
- `gh pr view --json number,title,url 2>/dev/null`

#### If no PR exists — create one:

1. Run `git log --oneline $(git merge-base HEAD <base-branch>)..HEAD` to see all commits since divergence.
2. Run `git diff <base-branch>...HEAD --stat` for a summary of changed files.
3. Draft a title following project conventions: `<emoji> <type>: <short description>` (under 70 chars, imperative mood). Use the emoji table from CLAUDE.md (`:sparkles:` for feature, `:bug:` for fix, etc.).
4. Create the PR:

```
gh pr create --base <base-branch> --title "<title>" --body "$(cat <<'EOF'
## Summary
<bullet points covering ALL commits>

## Test plan
- [ ] ...

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

#### If a PR already exists — update it:

1. Run `git log --oneline $(git merge-base HEAD <base-branch>)..HEAD` to see **all** commits on the branch (not just the latest).
2. Run `git diff <base-branch>...HEAD --stat` for the full diff summary.
3. Regenerate the PR body to cover **all commits** on the branch, not just new ones.
4. Update the PR:

```
gh pr edit --title "<title>" --body "$(cat <<'EOF'
## Summary
<bullet points covering ALL commits on the branch>

## Test plan
- [ ] ...

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

### 5. Report result

Print the PR URL so the user can review it.

## Important

- **NEVER merge the PR.** This skill only commits, pushes, and creates/updates PRs.
- **NEVER run `gh pr merge`** under any circumstances.
- Follow all commit message, branch naming, and PR conventions from CLAUDE.md.
- Always use HEREDOC for commit messages and PR bodies.

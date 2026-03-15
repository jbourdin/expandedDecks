---
description: Watch CI until green then ask to merge, or investigate failures and propose fixes
allowed-tools: Bash, Read, Glob, Grep, Agent
---

# CI Watch & Merge Workflow

Monitor CI checks on the current PR until they pass, then ask for merge approval. If any check fails, investigate the failure and propose a fix.

## Instructions

### 1. Identify the PR

Run:
- `gh pr view --json number,title,url,headRefName,baseRefName`

If no PR exists for the current branch, **stop** and tell the user: "No PR found for this branch. Run `/pr` first."

### 2. Watch CI checks

Run `gh pr checks <number> --watch` to wait for all checks to complete.

### 3. Handle the outcome

#### If all checks pass:

Tell the user: "All CI checks passed on PR #`<number>`. Ready to merge — should I proceed?"

- **Wait for explicit user confirmation** before merging.
- Once confirmed, merge with `gh pr merge <number> --merge`.
- **NEVER merge without user confirmation.**

#### If any check fails:

1. Report which checks failed with their URLs.
2. Fetch the failed job logs using `gh run view <run-id> --log-failed` to identify the root cause.
3. If the logs are too large or unclear, read the relevant source files to understand the context.
4. Propose a fix:
   - If the fix is straightforward (linting, code style, type error), apply it directly, commit, and push. Then go back to **step 2** to watch the new CI run.
   - If the fix is non-trivial or ambiguous, explain the failure and propose options to the user without making changes.

## Important

- **NEVER merge without explicit user confirmation.**
- **NEVER add `Co-Authored-By` or any AI attribution trailer to commit messages.**
- Follow all commit message conventions from CLAUDE.md.
- Use a HEREDOC for any commit messages.

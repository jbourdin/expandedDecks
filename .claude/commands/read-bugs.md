---
description: Review open bug issues and decide where to place them on the roadmap or handle immediately
allowed-tools: Bash, Read, Grep, AskUserQuestion
---

# Read Bugs

Fetch open bug issues from GitHub, present them to the user, and triage each one into the appropriate project board column or start working on it immediately.

## Instructions

### 1. Fetch open bug issues

Run:
```bash
gh issue list --repo jbourdin/expandedDecks --label bug --state open --json number,title,body,createdAt,labels,milestone,assignees --jq '.'
```

If no open bugs exist, tell the user: "No open bug issues found." and stop.

### 2. Check project board status for each bug

Run:
```bash
gh project item-list 1 --owner jbourdin --format json --limit 200
```

For each open bug issue, check if it is already on the project board and what its current status is (Backlog, Next, In Progress, etc.). If the bug is not on the board yet, note it as "Not on board".

### 3. Present all bugs

For each bug, display:

```
### 🐛 #<number> — <title>
- **Created:** <date>
- **Board status:** <status or "Not on board">
- **Milestone:** <milestone or "None">
- **Description:**
> <body, truncated to ~200 chars if very long>
```

### 4. Triage each bug

For each bug that is **not yet triaged** (status is "Not on board" or "Backlog"), ask the user what to do using AskUserQuestion:

```
What should we do with #<number> — "<title>"?
```

Options:
1. **Handle now** — Move to "In Progress" and start working on it immediately
2. **Next** — Move to the "Next" column (high priority, do soon)
3. **Backlog** — Move to or keep in "Backlog" (will be done later)
4. **Close** — Close the issue (not a real bug or won't fix)

### 5. Execute the user's decision

For each bug, based on the user's choice:

**Handle now:**
1. Add to the project board if not already there:
   ```bash
   gh project item-add 1 --owner jbourdin --url https://github.com/jbourdin/expandedDecks/issues/<number>
   ```
2. Move to "In Progress":
   ```bash
   gh project item-edit --project-id PVT_kwHOABmPPc4BSa9t --id <ITEM_ID> --field-id PVTSSF_lAHOABmPPc4BSa9tzg_9eC4 --single-select-option-id 9c44dd90
   ```
3. Tell the user: "Bug #<number> moved to In Progress. Ready to start working on it."

**Next:**
1. Add to the project board if not already there.
2. Move to "Next":
   ```bash
   gh project item-edit --project-id PVT_kwHOABmPPc4BSa9t --id <ITEM_ID> --field-id PVTSSF_lAHOABmPPc4BSa9tzg_9eC4 --single-select-option-id 787fe735
   ```
3. Tell the user: "Bug #<number> moved to Next."

**Backlog:**
1. Add to the project board if not already there.
2. Move to "Backlog":
   ```bash
   gh project item-edit --project-id PVT_kwHOABmPPc4BSa9t --id <ITEM_ID> --field-id PVTSSF_lAHOABmPPc4BSa9tzg_9eC4 --single-select-option-id 05530d67
   ```
3. Tell the user: "Bug #<number> moved to Backlog."

**Close:**
1. Close the issue:
   ```bash
   gh issue close <number> --reason "not planned"
   ```
2. Tell the user: "Bug #<number> closed."

### 6. Summary

After all bugs are triaged, print a summary table:

| Bug | Title | Decision |
|-----|-------|----------|
| #... | ... | Handle now / Next / Backlog / Closed |

If any bugs were set to "Handle now", ask: "Ready to start working on the first bug?"

## Important

- **NEVER start fixing a bug without the user choosing "Handle now".**
- Bugs already in "In Progress", "Awaiting Validation", "Testing", or "Ready for Release" are considered already triaged — skip them in the triage step but still list them.
- Always add the issue to the project board before attempting to change its status.

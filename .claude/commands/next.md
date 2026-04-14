---
description: Recommend the next feature to implement based on roadmap priorities and dependencies
allowed-tools: Bash, Read, Grep
---

# Next Feature Recommendation

Query the GitHub Project board Kanban. Prioritize **in-flight work** (issues already started) before recommending new features from the "Next" column.

## Instructions

### Step 0 — Board hygiene

Before recommending features, clean up stale board state:

1. **Fetch all project items** from the GitHub Project board (project #1, owner `jbourdin`):

   ```bash
   gh project item-list 1 --owner jbourdin --format json --limit 300
   ```

2. **Identify stale items**: find all items where `content.type == "Issue"` and `status` is NOT `"Done"` (i.e. in Next, In Progress, Awaiting Validation, Testing, or Ready for Release). For each, check whether the GitHub issue is actually closed:

   ```bash
   gh issue view <NUMBER> --repo jbourdin/expandedDecks --json state --jq '.state'
   ```

   Batch the checks to avoid excessive API calls (e.g. loop over all suspect issue numbers in one script).

3. **Move closed issues to Done**:

   ```bash
   gh project item-edit --project-id PVT_kwHOABmPPc4BSa9t --id <ITEM_ID> --field-id PVTSSF_lAHOABmPPc4BSa9tzg_9eC4 --single-select-option-id e7c00188
   ```

4. **Archive all Done items**:

   ```bash
   gh project item-archive 1 --owner jbourdin --id <ITEM_ID>
   ```

5. **Report** what was cleaned up (e.g. "Moved 3 closed issues to Done and archived 12 Done items") before proceeding to the recommendation.

---

### Step 1 — Assess in-flight work

Re-fetch the board after cleanup. Collect all **open** issues in columns beyond "Next" — i.e. **In Progress**, **Awaiting Validation**, **Testing**, and **Ready for Release**.

For each in-flight issue, evaluate its current state:

1. **Check for an open or merged PR** referencing the issue:

   ```bash
   gh pr list --repo jbourdin/expandedDecks --search "<ISSUE_NUMBER>" --state all --json number,title,state,mergedAt --limit 5
   ```

2. **Check for code references** (feature ID mentions like `F18.23`):

   ```bash
   grep -r "F<ID>" src/ templates/ assets/ --include="*.php" --include="*.twig" --include="*.ts" --include="*.tsx" -l
   ```

3. **Classify each issue** into one of:
   - **Should be closed**: has a merged PR and code is present in the codebase → suggest closing the issue and moving to Done
   - **Blocked / stalled**: has been in its column with no recent PR activity → flag it to the user
   - **Actively in progress**: has an open (unmerged) PR → report it as current work
   - **Needs testing**: PR merged but issue is in Awaiting Validation or Testing → remind the user to verify

4. **Present in-flight issues first**, before any new recommendations:

---

### 🔄 In-flight Work

For each in-flight issue, show:

| Status | Issue | Feature | Assessment | Action needed |
|--------|-------|---------|------------|---------------|
| In Progress | #... | ... | Merged PR #... found, code present | Close issue → Done |
| Awaiting Validation | #... | ... | No PR found, stalled | Needs attention |
| Testing | #... | ... | PR #... merged | Awaiting manual test |

If any issues should be closed, ask the user for confirmation before closing them.

**In-flight issues take priority**: if there are issues in "In Progress" or "Awaiting Validation" that still need work, recommend continuing those before picking up new work from "Next".

---

### Step 2 — Fetch and rank "Next" column

Filter to items where `status == "Next"` and `content.type == "Issue"`.

Rank using this sort order:
   1. **Priority label**: `priority: high` > `priority: medium` > `priority: low` > unlabelled
   2. **Milestone phase**: earlier phase first (Phase A before Phase B, etc.)
   3. **Issue number**: lower number first (older issues first)

### Step 3 — Present recommendation

**Read `docs/features.md`** and retrieve the full feature description for:
   - The **top recommendation** (rank #1)
   - The next **2–3 runner-ups** (if any exist in the Next column)

Present the results in this format:

---

### 🎯 Recommended Next Feature

**[Feature ID] — [Feature Name]** (issue #[number])
- **Priority:** [High/Medium/Low]
- **Milestone:** [Phase name]
- **GitHub issue:** [URL]

**Full description from features.md:**
> [paste the full feature description here]

---

### Runner-ups

For each runner-up (up to 3), show:

| Rank | Issue | Feature | Priority | Milestone |
|------|-------|---------|----------|-----------|
| 2    | #...  | ...     | ...      | ...       |
| 3    | #...  | ...     | ...      | ...       |

Include a one-line summary of each runner-up from features.md.

---

5. If **both** the in-flight list and the "Next" column are empty, say so and suggest the user triage issues from the Backlog column into Next.

6. **When the user picks a feature to start**, move the issue to the **"In Progress"** column on the project board:

   ```bash
   gh project item-edit --project-id PVT_kwHOABmPPc4BSa9t --id <ITEM_ID> --field-id PVTSSF_lAHOABmPPc4BSa9tzg_9eC4 --single-select-option-id 9c44dd90
   ```

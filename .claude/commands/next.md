---
description: Recommend the next feature to implement based on roadmap priorities and dependencies
allowed-tools: Bash, Read, Grep
---

# Next Feature Recommendation

Query the GitHub Project board Kanban and recommend the highest-priority issue from the **"Next"** column.

## Instructions

1. **Fetch all items in the "Next" column** from the GitHub Project board (project #1, owner `jbourdin`):

   ```bash
   gh project item-list 1 --owner jbourdin --format json --limit 100
   ```

   Filter to items where `status == "Next"` and `content.type == "Issue"`.

2. **Rank the issues** using this sort order:
   1. **Priority label**: `priority: high` > `priority: medium` > `priority: low` > unlabelled
   2. **Milestone phase**: earlier phase first (Phase A before Phase B, etc.)
   3. **Issue number**: lower number first (older issues first)

3. **Read `docs/features.md`** and retrieve the full feature description for:
   - The **top recommendation** (rank #1)
   - The next **2–3 runner-ups** (if any exist in the Next column)

4. **Present the results** in this format:

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

5. If the **"Next" column is empty**, say so and suggest the user triage issues from the Backlog column into Next.

6. **When the user picks a feature to start**, move the issue to the **"In Progress"** column on the project board:

   ```bash
   gh project item-edit --project-id PVT_kwHOABmPPc4BSa9t --id <ITEM_ID> --field-id PVTSSF_lAHOABmPPc4BSa9tzg_9eC4 --single-select-option-id 9c44dd90
   ```

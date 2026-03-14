---
description: Recommend the next feature to implement based on roadmap priorities and dependencies
allowed-tools: Read
---

# Next Feature Recommendation

Analyze the project roadmap and feature list to recommend the next feature to implement.

## Instructions

1. **Read `docs/roadmap.md`** and extract:
   - The list of **completed feature IDs** from the "Completed Features" section
   - All **remaining features** from each phase table (A through G), capturing: ID, Feature name, Priority, Depends on, and Phase letter

2. **Resolve actionable features** — a feature is **actionable** if ALL of its dependencies are already in the completed list (or the dependency column is `—`):
   - Dependencies are comma-separated (e.g. `F4.2, F4.3, F3.4`) — ALL must be completed
   - Dependencies with `/` are alternatives (e.g. `F5.6/F5.3`) — at least ONE must be completed
   - Special dependencies like "All controllers" or "All state-changing actions" should be treated as **not yet met** (these are meta-dependencies that require human judgment)

3. **Rank actionable features** using this sort order:
   1. **Phase**: earlier phase first. Phases may be numbered or lettered — numbered phases (0, 1, 2, …) come before lettered phases (A, B, C, …), and each group is sorted naturally (0 < 1 < 2 < … < A < B < C < …)
   2. **Priority**: High > Medium > Low
   3. **Feature ID**: lower number first (F1.x before F2.x, then F1.1 before F1.2)

   **Exception — blocked-blocking promotion:** if a feature in a later phase **blocks** a feature in an earlier phase (i.e., the earlier-phase feature depends on it), promote the blocking feature to the rank of the earliest phase it unblocks. This ensures dependency chains are resolved in the order needed by the phase roadmap.

4. **Read `docs/features.md`** and retrieve the full description for:
   - The **top recommendation** (rank #1)
   - The next **2–3 runner-ups**

5. **Present the results** in this format:

---

### 🎯 Recommended Next Feature

**[Feature ID] — [Feature Name]**
- **Phase:** [Phase letter] — [Phase name]
- **Priority:** [High/Medium/Low]
- **Dependencies:** [list or "None"]

**Full description from features.md:**
> [paste the full feature description here]

---

### Runner-ups

For each runner-up (2–3), show:

| Rank | ID | Feature | Phase | Priority | Dependencies |
|------|----|---------|-------|----------|-------------|
| 2 | ... | ... | ... | ... | ... |
| 3 | ... | ... | ... | ... | ... |

Include a one-line summary of each runner-up from features.md.

---

### Blocked Features

List any features that are **not yet actionable** because their dependencies are incomplete. Group by what's blocking them (i.e., which missing dependency). Only include features whose dependencies are partially met (at least one dep completed) — skip features that are deeply blocked.

# Implementation Roadmap

> **Audience:** Developer, AI Agent · **Scope:** Planning, Reference

← Back to [Main Documentation](docs.md) | [Feature List](features.md) | [Changelog](changelog.md) | [README](../README.md)

The [GitHub Project board](https://github.com/users/jbourdin/projects/1) is the roadmap for the Expanded Decks project. All issue prioritization, status, and phase assignment live there — this document provides an overview and links to the relevant views.

## Project Board

The Kanban board organizes work into seven columns:

| Column                    | Meaning                                                    |
|---------------------------|------------------------------------------------------------|
| **Backlog**               | Accepted work, not yet scheduled                           |
| **Next**                  | Prioritized and ready to pick up                           |
| **In Progress**           | Actively being worked on                                   |
| **Awaiting Validation**   | PR open, awaiting CI and code review                       |
| **Testing**               | PR merged, awaiting manual verification                    |
| **Ready for Release**     | Tested and approved, included in next release              |
| **Done**                  | Released                                                   |

**Views:**

- [Full board](https://github.com/users/jbourdin/projects/1) — Kanban with all columns
- [All open issues](https://github.com/jbourdin/expandedDecks/issues) — filterable by label and milestone

## Milestones (Phases)

Each milestone corresponds to a thematic phase of work. Issues are assigned to milestones on creation. Phases are grouped into priority tiers reflecting the current product direction: **content and gameplay experience first**, borrowing and operational refinements later.

### Tier 1 — Content, Discovery & Core Scanning

Priority: content attracts users, SEO makes it findable, search helps them navigate, scanning enables event-day workflows.

| Phase | Name                                | Milestone link |
|-------|-------------------------------------|----------------|
| 01    | PDF Labels & Camera Scanning        | [Phase 01](https://github.com/jbourdin/expandedDecks/milestone/3) |
| 02    | Homepage                            | [Phase 02](https://github.com/jbourdin/expandedDecks/milestone/14) |
| 03    | SEO & Indexability                  | [Phase 03](https://github.com/jbourdin/expandedDecks/milestone/16) |
| 04    | Search                              | [Phase 04](https://github.com/jbourdin/expandedDecks/milestone/15) |

### Tier 2 — Gameplay & Event Experience

Priority: enrich events, physical label management, and freeplay matchmaking.

| Phase | Name                                | Milestone link |
|-------|-------------------------------------|----------------|
| 05    | Zebra Labels & HID Scanning         | [Phase 05](https://github.com/jbourdin/expandedDecks/milestone/4) |
| 06    | Event Enrichment                    | [Phase 06](https://github.com/jbourdin/expandedDecks/milestone/2) |
| 07    | Freeplay Matchmaking                | [Phase 07](https://github.com/jbourdin/expandedDecks/milestone/10) |

### Tier 3 — Ecosystem & Integration

Priority: REST API, MCP server, and LLM-friendly endpoints for third-party tools and AI agents.

| Phase | Name                                | Milestone link |
|-------|-------------------------------------|----------------|
| 08    | API Access                          | [Phase 08](https://github.com/jbourdin/expandedDecks/milestone/11) |

### Tier 4 — Operational (defer until pain is felt)

Priority: these address edge cases and operational polish. The borrowing process already works well — refine as pain points surface.

| Phase | Name                                | Milestone link |
|-------|-------------------------------------|----------------|
| 09    | UX Polish & Overdue Tracking        | [Phase 09](https://github.com/jbourdin/expandedDecks/milestone/1) |
| 10    | Operational Excellence              | [Phase 10](https://github.com/jbourdin/expandedDecks/milestone/7) |
| 11    | Auth Hardening & Delegation         | [Phase 11](https://github.com/jbourdin/expandedDecks/milestone/5) |
| 12    | Multi-Organizer Events              | [Phase 12](https://github.com/jbourdin/expandedDecks/milestone/9) |
| 13    | Lost & Found                        | [Phase 13](https://github.com/jbourdin/expandedDecks/milestone/8) |
| 14    | Play Pokemon QR Integration         | [Phase 14](https://github.com/jbourdin/expandedDecks/milestone/6) |

### Completed

| Phase | Name                                | Milestone link |
|-------|-------------------------------------|----------------|
| 0     | Soft Deletion                       | [Phase 0](https://github.com/jbourdin/expandedDecks/milestone/12) |
| —     | Content Editing Experience          | [Phase L](https://github.com/jbourdin/expandedDecks/milestone/13) |

## Feature Catalogue

All features (implemented and planned) are described in [features.md](features.md) with unique IDs (e.g. `F6.11`). Each GitHub issue title starts with its feature ID for traceability.

## Release History

See [changelog.md](changelog.md) for per-release feature lists and dates.

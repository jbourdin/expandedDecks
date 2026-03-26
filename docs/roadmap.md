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

Each milestone corresponds to a thematic phase of work. Issues are assigned to milestones on creation.

| Phase | Name                                | Milestone link |
|-------|-------------------------------------|----------------|
| 0     | Soft Deletion                       | [Phase 0](https://github.com/jbourdin/expandedDecks/milestone/12) |
| A     | UX Polish & Overdue Tracking        | [Phase A](https://github.com/jbourdin/expandedDecks/milestone/1) |
| B     | Event Enrichment                    | [Phase B](https://github.com/jbourdin/expandedDecks/milestone/2) |
| C     | PDF Labels & Camera Scanning        | [Phase C](https://github.com/jbourdin/expandedDecks/milestone/3) |
| D     | Zebra Labels & HID Scanning         | [Phase D](https://github.com/jbourdin/expandedDecks/milestone/4) |
| E     | Auth Hardening & Delegation         | [Phase E](https://github.com/jbourdin/expandedDecks/milestone/5) |
| F     | Play Pokemon QR Integration         | [Phase F](https://github.com/jbourdin/expandedDecks/milestone/6) |
| G     | Operational Excellence              | [Phase G](https://github.com/jbourdin/expandedDecks/milestone/7) |
| H     | Lost & Found                        | [Phase H](https://github.com/jbourdin/expandedDecks/milestone/8) |
| I     | Multi-Organizer Events              | [Phase I](https://github.com/jbourdin/expandedDecks/milestone/9) |
| J     | Freeplay Matchmaking                | [Phase J](https://github.com/jbourdin/expandedDecks/milestone/10) |
| K     | API Access                          | [Phase K](https://github.com/jbourdin/expandedDecks/milestone/11) |

## Feature Catalogue

All features (implemented and planned) are described in [features.md](features.md) with unique IDs (e.g. `F6.11`). Each GitHub issue title starts with its feature ID for traceability.

## Release History

See [changelog.md](changelog.md) for per-release feature lists and dates.

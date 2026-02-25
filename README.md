# Expanded Decks

> **Audience:** Developer, Player, Organizer · **Scope:** Overview

A web application for managing a shared library of physical Pokemon TCG decks in the **Expanded format**. It enables a community of players to register their decks, declare upcoming events, request to borrow decks, and track the full lending lifecycle — from request to return.

Deck lists are imported via **copy-paste of standard PTCG text format**, parsed and validated against **TCGdex**. Physical deck boxes are identified via **Zebra-printed labels** with scannable codes for fast check-in/check-out.

## Stack

- **Backend:** PHP 8.5, Symfony 7.2, Doctrine ORM
- **Frontend:** React.js (via Symfony UX / Webpack Encore)
- **Database:** MySQL 8
- **Infrastructure:** Docker, Docker Compose
- **External APIs:** TCGdex (card data, images), PrintNode (cloud printing)
- **Hardware:** Zebra label printer (ZPL via PrintNode)

## Features

| Domain | Summary |
|--------|---------|
| **F1 — User Management** | Registration with email verification, screen name, player ID. Global roles: player, organizer, admin. Per-event staff. MFA and Pokemon SSO planned. |
| **F2 — Deck Library** | Register physical decks, import lists via copy-paste (PTCG text format), browse catalog, track availability. |
| **F3 — Event Management** | Declare events, list upcoming/past events, register participation, assign event staff. |
| **F4 — Borrow Workflow** | Request, approve, hand-off, return — full lending lifecycle with staff-delegated lending, custody tracking, history, and overdue tracking. |
| **F5 — Zebra Label Printing** | Generate ZPL labels, push to Zebra printer via PrintNode, scan barcodes for deck identification. |
| **F6 — Card Data & Validation** | Parse PTCG text (`ptcgo-parser`), validate via TCGdex, Expanded format rules, card image display. |
| **F7 — Administration** | Dashboard, user management, audit log. |

See the **[full feature list](docs/features.md)** for detailed descriptions and priorities.

---

## Documentation

| Document                                      | Description                              |
|-----------------------------------------------|------------------------------------------|
| [CLAUDE.md](CLAUDE.md)                        | AI context: coding standards & workflow   |
| [docs/docs.md](docs/docs.md)                  | Technical documentation entry point       |
| [docs/features.md](docs/features.md)          | Full feature list with priorities         |

## License

This project is licensed under the [Apache License 2.0](LICENSE).

## External References

- [TCGdex](https://tcgdex.dev/) — Multilingual Pokemon TCG card database
- [ptcgo-parser](https://github.com/Hamatti/ptcgo-parser) — PTCG text format parser
- [Symfony Best Practices](https://symfony.com/doc/current/best_practices.html)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [Gitflow Workflow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow)
- [Zebra ZPL Programming Guide](https://www.zebra.com/us/en/support-downloads/knowledge-articles/ait/zpl-command-information-and-cross-reference.html)

# Expanded Decks

> **Audience:** Developer, Player, Organizer · **Scope:** Overview

A web application for managing a shared library of physical Pokemon TCG decks in the **Expanded format**. It enables a community of players to register their decks, declare upcoming events, request to borrow decks, and track the full lending lifecycle — from request to return.

Deck lists are synchronized with the **Limitless TCG** API, and physical deck boxes are identified via **Zebra-printed labels** with scannable codes for fast check-in/check-out.

## Stack

- **Backend:** PHP 8.5, Symfony 7.2, Doctrine ORM
- **Frontend:** React.js (via Symfony UX / Webpack Encore)
- **Database:** MySQL 8
- **Infrastructure:** Docker, Docker Compose
- **External APIs:** Limitless TCG (deck lists, card data), PrintNode (cloud printing)
- **Hardware:** Zebra label printer (ZPL via PrintNode)

## Features

| Domain | Summary |
|--------|---------|
| **F1 — User Management** | Registration, authentication, roles (player, organizer, staff, admin), user profiles. |
| **F2 — Deck Library** | Register physical decks, import lists from Limitless TCG, browse catalog, track availability. |
| **F3 — Event Management** | Declare events, list upcoming/past events, register participation, assign event staff. |
| **F4 — Borrow Workflow** | Request, approve, hand-off, return — full lending lifecycle with staff-delegated lending, custody tracking, history, and overdue tracking. |
| **F5 — Zebra Label Printing** | Generate ZPL labels, push to Zebra printer via PrintNode, scan barcodes for deck identification. |
| **F6 — Limitless TCG Integration** | Search, import, and sync deck lists; display card images. |
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

- [Limitless TCG](https://limitlesstcg.com) — Deck list source
- [Symfony Best Practices](https://symfony.com/doc/current/best_practices.html)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [Gitflow Workflow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow)
- [Zebra ZPL Programming Guide](https://www.zebra.com/us/en/support-downloads/knowledge-articles/ait/zpl-command-information-and-cross-reference.html)

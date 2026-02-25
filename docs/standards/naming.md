# Naming Conventions

> **Audience:** Developer · **Scope:** Coding Standards

← Back to [Main Documentation](../docs.md) | [CLAUDE.md](../../CLAUDE.md)

## Overview

| Element           | Convention        | Example                          |
|-------------------|-------------------|----------------------------------|
| Classes           | PascalCase        | `DeckLibrary`                    |
| Interfaces        | PascalCase        | `DeckRepositoryInterface`        |
| Enums             | PascalCase        | `BorrowStatus`                   |
| Methods           | camelCase         | `requestBorrow()`                |
| Properties        | camelCase         | `$deckOwner`                     |
| Constants         | UPPER_SNAKE_CASE  | `BORROW_STATUS_PENDING`          |
| Services (YAML)   | snake_case        | `app.deck_library`              |
| Routes            | snake_case        | `app_deck_show`                  |
| Templates         | snake_case        | `deck/show.html.twig`            |
| Translations      | dot.notation      | `app.deck.status.available`      |
| Test methods      | camelCase         | `testDeckCanBeBorrowed()`        |
| React components  | PascalCase        | `DeckCard`, `BorrowModal`        |
| React hooks       | camelCase (use*)  | `useScannerDetection`            |
| CSS classes       | kebab-case        | `deck-card`, `borrow-status`     |
| Doc files         | snake_case        | `file_headers.md`                |
| Git branches      | kebab-case        | `feature/deck-borrow-workflow`   |

## PHP Namespace Structure

```
App\
├── Controller\        # HTTP controllers (thin, delegate to services)
├── Entity\            # Doctrine entities (PHP 8 attributes)
├── Enum\              # PHP enums (BorrowStatus, DeckStatus, UserRole)
├── Repository\        # Doctrine repositories
├── Service\           # Business logic services
│   ├── CardData\      # TCGdex card validation and data
│   └── PrintNode\     # PrintNode API client
├── Form\              # Symfony form types
├── Security\          # Voters, authenticators
└── Twig\              # Twig extensions
```

## Route Naming

Pattern: `app_{entity}_{action}`

```
app_deck_index        GET    /decks
app_deck_show         GET    /decks/{id}
app_deck_create       GET    /decks/new
app_deck_store        POST   /decks
app_deck_edit         GET    /decks/{id}/edit
app_deck_update       PUT    /decks/{id}
app_deck_delete       DELETE /decks/{id}
```

## Translation Keys

Pattern: `app.{domain}.{context}.{key}`

```yaml
app.deck.status.available: "Available"
app.deck.status.lent: "Lent"
app.borrow.action.request: "Request to borrow"
app.event.label.date: "Event date"
```

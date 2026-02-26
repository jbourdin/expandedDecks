# Plan: Doctrine Model Layer

> **Audience:** Developer, AI Agent · **Scope:** Data Model Implementation
> **Parent:** [docs.md](../docs.md)

## Context

Greenfield implementation — no entities, enums, or migrations exist yet. This creates the full data model (4 enums, 10 entities, 10 repositories) and an initial migration, establishing the foundation for all feature work.

## Branch

`feature/doctrine-entities` off `develop` — single PR for the entire model layer (all entities are interdependent, no value in splitting).

## Implementation Order

Respects the dependency graph (entities reference each other via ManyToOne/ManyToMany).

### Step 1 — Enums (4 files)

Create `src/Enum/` directory and all backed string enums:

| File | Cases |
|------|-------|
| `src/Enum/DeckStatus.php` | Available, Reserved, Lent, Retired |
| `src/Enum/BorrowStatus.php` | Pending, Approved, Lent, Returned, ReturnedToOwner, Cancelled, Overdue |
| `src/Enum/TournamentStructure.php` | Swiss, SwissTopCut, SingleElimination, RoundRobin |
| `src/Enum/NotificationType.php` | BorrowRequested, BorrowApproved, BorrowDenied, BorrowHandedOff, BorrowReturned, BorrowOverdue, BorrowCancelled, StaffAssigned, EventUpdated, EventCancelled, EventReminder |

### Step 2 — User + UserRepository

### Step 3 — League + LeagueRepository

### Step 4 — Deck + DeckRepository

### Step 5 — DeckVersion + DeckVersionRepository

### Step 6 — DeckCard + DeckCardRepository

### Step 7 — Event + EventRepository

### Step 8 — EventStaff + EventStaffRepository

### Step 9 — Borrow + BorrowRepository

### Step 10 — EventDeckEntry + EventDeckEntryRepository

### Step 11 — Notification + NotificationRepository

### Step 12 — Validation & Migration

## Key Patterns

- **PHP 8 attributes** for all ORM mapping (no annotations/XML)
- **String-backed enums** with `#[ORM\Column(enumType: ...)]`
- **DateTimeImmutable** for all datetime fields
- **`#[ORM\PrePersist]`** lifecycle callback sets `createdAt`; `#[ORM\PreUpdate]` sets `updatedAt` where applicable
- **`@var Collection<int, Entity>`** PHPDoc on all collection properties (PHPStan L10)
- **Assert attributes** for validation (NotBlank, Length, Email, Url, Timezone, Positive, etc.)
- **`@see docs/features.md F...`** on each class for feature traceability
- **Copyright header** on every file (PHP-CS-Fixer enforces automatically)
- Repositories extend `ServiceEntityRepository<T>` with constructor only (query methods added later with features)

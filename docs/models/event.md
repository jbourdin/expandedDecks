# Event Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\Event`

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `name`             | `string(150)`      | No       | Event name (e.g. "Paris League Challenge Q1 2026"). |
| `eventId`          | `string(50)`       | Yes      | Free text identifier. Recommended to use the official Pokemon sanctioned tournament ID when applicable. |
| `format`           | `string(30)`       | No       | Play format. Default: `"Expanded"`. Could also be `"Standard"`, `"Unlimited"`, etc. |
| `date`             | `DateTimeImmutable` | No      | Event date (start). |
| `endDate`          | `DateTimeImmutable` | Yes     | Event end date. Defaults to same as `date` for single-day events. Used for overdue tracking (F4.6). |
| `location`         | `string(255)`      | No       | Venue name and/or address. |
| `description`      | `text`             | Yes      | Optional free-text description or notes about the event. |
| `organizer`        | `User`             | No       | The user who created the event (must have `ROLE_ORGANIZER` or `ROLE_ADMIN`). |
| `createdAt`        | `DateTimeImmutable` | No      | Event creation timestamp. |

### Constraints

- `name`: required, 3–150 characters
- `eventId`: optional, unique when provided. Free text — recommended to match the official Pokemon tournament ID (e.g. `"0000123456"`)
- `format`: required, from a predefined list of allowed values
- `date`: required, must be in the future at creation time
- `endDate`: if provided, must be >= `date`

### Event ID & Tournament Verification

The `eventId` field is **free text** intended to hold the official Pokemon sanctioned tournament ID. This allows:
- Cross-referencing with official tournament data
- Verifying that a deck was used at a real event
- Potential future integration with Pokemon tournament APIs

> **Future consideration (F3.6):** Investigate whether the Pokemon tournament system exposes an API to verify that the organizer creating the event is the actual owner/TO of the referenced tournament ID. This would prevent unauthorized event creation with someone else's tournament ID.

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `organizer`        | ManyToOne    | `User`         | User who created this event |
| `participants`     | ManyToMany   | `User`         | Players registered to attend |
| `staff`            | OneToMany    | `EventStaff`   | Staff members assigned to this event |
| `borrows`          | OneToMany    | `Borrow`       | Borrow requests linked to this event |
| `deckEntries`      | OneToMany    | [`EventDeckEntry`](deck.md#entity-appentityeventdeckentry) | Deck versions registered for this event (F3.7) |

---

## Entity: `App\Entity\EventStaff`

Join entity modeling the **per-event staff assignment**. A user is not globally "staff" — they are staff for a specific event, assigned by the event organizer.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `event`            | `Event`            | No       | The event this assignment belongs to. |
| `user`             | `User`             | No       | The user assigned as staff. |
| `assignedBy`       | `User`             | No       | The organizer/admin who made the assignment. |
| `assignedAt`       | `DateTimeImmutable` | No      | When the assignment was made. |

### Constraints

- Unique constraint on (`event`, `user`) — a user can only be staff once per event
- `user` must have a verified account (`isVerified = true`)
- `assignedBy` must be the event organizer or an admin

### Behavior

When a user is assigned as staff for an event, they gain the ability to:
- Receive decks from owners who opted in to staff delegation (F4.8)
- Approve borrow requests for delegated decks
- Lend delegated decks to borrowers (F4.3)
- Collect decks back from borrowers (F4.4)
- View their staff custody dashboard for that event (F4.9)

These permissions are **scoped to the event** — the same user has no staff capabilities at other events unless also assigned there.

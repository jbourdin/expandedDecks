# Event Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\League`

Represents a recurring Pokemon TCG organized play location — typically a local game store hosting a league. Multiple events can be linked to the same league.

### Fields

| Field            | Type               | Nullable | Description |
|------------------|--------------------|----------|-------------|
| `id`             | `int` (auto)       | No       | Primary key |
| `name`           | `string(150)`      | No       | League or store name (e.g. "Paris TCG League"). |
| `website`        | `string(255)`      | Yes      | Main website URL. |
| `address`        | `string(255)`      | Yes      | Physical address. |
| `contactDetails` | `json`             | Yes      | Flexible key-value: `{"email": "...", "phone": "...", "discord": "..."}`. |
| `createdAt`      | `DateTimeImmutable` | No      | Creation timestamp. |

### Constraints

- `name`: required, 2–150 characters
- `contactDetails`: when provided, must be a JSON object (not array)

### Relations

| Relation | Type      | Target  | Description |
|----------|-----------|---------|-------------|
| `events` | OneToMany | `Event` | Events hosted at this league |

---

## Entity: `App\Entity\Event`

### Fields

| Field                  | Type               | Nullable | Description |
|------------------------|--------------------|----------|-------------|
| `id`                   | `int` (auto)       | No       | Primary key |
| `name`                 | `string(150)`      | No       | Event name (e.g. "Paris League Challenge Q1 2026"). |
| `eventId`              | `string(50)`       | Yes      | Free text identifier. Recommended to use the official Pokemon sanctioned tournament ID when applicable. |
| `format`               | `string(30)`       | No       | Play format. Default: `"Expanded"`. Could also be `"Standard"`, `"Unlimited"`, etc. |
| `date`                 | `DateTimeImmutable` | No      | Event date (start). |
| `endDate`              | `DateTimeImmutable` | Yes     | Event end date. Defaults to same as `date` for single-day events. Used for overdue tracking (F4.6). |
| `location`             | `string(255)`      | Yes      | Venue name and/or address. Nullable — can be derived from the league's address when a league is linked. |
| `description`          | `text`             | Yes      | Optional free-text description or notes about the event. |
| `organizer`            | `User`             | No       | The user who created the event (must have `ROLE_ORGANIZER` or `ROLE_ADMIN`). |
| `league`               | `League`           | Yes      | The league/store hosting this event. Null for independent events. |
| `registrationLink`     | `string(255)`      | No       | External registration URL (this project doesn't handle registration). |
| `tournamentStructure`  | `string(30)`       | No       | Tournament format. See `TournamentStructure` enum below. |
| `minAttendees`         | `int`              | Yes      | Minimum number of attendees for the event to take place. |
| `maxAttendees`         | `int`              | Yes      | Maximum number of attendees (capacity). |
| `roundDuration`        | `int`              | Yes      | Duration in minutes for main rounds (swiss rounds, or rounds in single elim / round robin). |
| `topCutRoundDuration`  | `int`              | Yes      | Duration in minutes for top cut rounds. Only applicable for `swiss_top_cut`. |
| `entryFeeAmount`       | `int`              | Yes      | Entry fee in cents of the currency. Null = free event. |
| `entryFeeCurrency`     | `string(3)`        | Yes      | ISO 4217 currency code (e.g. `"EUR"`, `"USD"`). Required when `entryFeeAmount` is set. |
| `isDecklistMandatory`  | `bool`             | No       | Whether submitting a decklist on this platform is mandatory for participants. Default: `false`. |
| `createdAt`            | `DateTimeImmutable` | No      | Event creation timestamp. |
| `cancelledAt`          | `DateTimeImmutable` | Yes     | When the event was cancelled. Null = active event. Set via F3.10. |

### Tournament Structure Enum: `App\Enum\TournamentStructure`

| Value                | Description |
|----------------------|-------------|
| `swiss`              | Swiss rounds only (e.g. League Challenges). |
| `swiss_top_cut`      | Swiss rounds followed by a top-cut single-elimination bracket (e.g. League Cups, Regionals). |
| `single_elimination` | Single-elimination bracket throughout. |
| `round_robin`        | Round-robin (every player plays every other player). |

### Constraints

- `name`: required, 3–150 characters
- `eventId`: optional, unique when provided. Free text — recommended to match the official Pokemon tournament ID (e.g. `"0000123456"`)
- `format`: required, from a predefined list of allowed values
- `date`: required, must be in the future at creation time
- `endDate`: if provided, must be >= `date`
- `location`: **nullable** (was required). When null and a league is linked, the league's address serves as the effective location.
- `registrationLink`: required, valid URL format
- `tournamentStructure`: required, must be a valid `TournamentStructure` value
- `minAttendees`: optional, >= 1 when provided
- `maxAttendees`: optional, >= `minAttendees` when both provided
- `roundDuration`: optional, >= 1 (minutes)
- `topCutRoundDuration`: optional, >= 1, only valid when `tournamentStructure` is `swiss_top_cut`
- `entryFeeAmount` and `entryFeeCurrency`: both null (free) or both set (paid). `entryFeeAmount` >= 0.
- `cancelledAt`: once set, cannot be cleared (cancellation is irreversible). A cancelled event cannot be edited further (F3.10).

### Cancellation Behavior

When an event is cancelled (F3.10):
1. `cancelledAt` is set to the current timestamp
2. All `pending` and `approved` borrows linked to this event are automatically cancelled
3. All participants and deck owners with active borrows are notified (F8.2)
4. The event remains visible in listings for historical reference, but is clearly marked as cancelled
5. No further edits, borrow requests, or participation changes are allowed

### Event ID & Tournament Verification

The `eventId` field is **free text** intended to hold the official Pokemon sanctioned tournament ID. This allows:
- Cross-referencing with official tournament data
- Verifying that a deck was used at a real event
- Potential future integration with Pokemon tournament APIs

> **Future consideration (F3.6):** Investigate whether the Pokemon tournament system exposes an API to verify that the organizer creating the event is the actual owner/TO of the referenced tournament ID. This would prevent unauthorized event creation with someone else's tournament ID.

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `league`           | ManyToOne    | `League`       | The league/store hosting this event |
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

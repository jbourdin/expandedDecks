# Event Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\Event`

### Fields

| Field                  | Type               | Nullable | Description |
|------------------------|--------------------|----------|-------------|
| `id`                   | `int` (auto)       | No       | Primary key |
| `name`                 | `string(150)`      | No       | Event name (e.g. "Paris Expanded Cup Q1 2026"). |
| `eventId`              | `string(50)`       | Yes      | Free text identifier. Recommended to use the official Pokemon sanctioned tournament ID when applicable. |
| `format`               | `string(30)`       | No       | Play format. Default: `"Expanded"`. Could also be `"Standard"`, `"Unlimited"`, etc. |
| `date`                 | `DateTimeImmutable` | No      | Event date (start). |
| `endDate`              | `DateTimeImmutable` | Yes     | Event end date. Defaults to same as `date` for single-day events. Used for overdue tracking (F4.6). |
| `timezone`             | `string(50)`       | No       | IANA timezone for the event's local time (e.g. `"Europe/Paris"`). Default: `"UTC"`. Event dates are stored in UTC and displayed in this timezone. See F9.4. |
| `location`             | `string(255)`      | Yes      | Venue name and/or address. Nullable — can be derived from the league's address when a league is linked. |
| `description`          | `text`             | Yes      | Optional free-text description or notes about the event. |
| `organizer`            | `User`             | No       | The user who created the event (must have `ROLE_ORGANIZER` or `ROLE_ADMIN`). |
| `registrationLink`     | `string(255)`      | No       | External registration URL (this project doesn't handle registration). |
| `tournamentStructure`  | `string(30)`       | No       | Tournament format. See `TournamentStructure` enum below. |
| `minAttendees`         | `int`              | Yes      | Minimum number of attendees for the event to take place. |
| `maxAttendees`         | `int`              | Yes      | Maximum number of attendees (capacity). |
| `roundDuration`        | `int`              | Yes      | Duration in minutes for main rounds (swiss rounds, or rounds in single elim / round robin). |
| `topCutRoundDuration`  | `int`              | Yes      | Duration in minutes for top cut rounds. Only applicable for `swiss_top_cut`. |
| `entryFeeAmount`       | `int`              | Yes      | Entry fee in cents of the currency. Null = free event. |
| `entryFeeCurrency`     | `string(3)`        | Yes      | ISO 4217 currency code (e.g. `"EUR"`, `"USD"`). Required when `entryFeeAmount` is set. |
| `visibility`           | `EventVisibility`  | No       | Event discoverability mode: `public`, `series`, or `invitation_only`. Default: `public`. See F3.11 and `EventVisibility` enum below. |
| `isDecklistMandatory`  | `bool`             | No       | Whether submitting a decklist on this platform is mandatory for participants. Default: `false`. |
| `createdAt`            | `DateTimeImmutable` | No      | Event creation timestamp. |
| `cancelledAt`          | `DateTimeImmutable` | Yes     | When the event was cancelled. Null = active event. Set via F3.10. |
| `finishedAt`           | `DateTimeImmutable` | Yes     | When the event was marked as finished by the organizer. Null = event not yet finished. Set via F3.20. |

### Tournament Structure Enum: `App\Enum\TournamentStructure`

| Value                | Description |
|----------------------|-------------|
| `swiss`              | Swiss rounds only (e.g. small local tournaments). |
| `swiss_top_cut`      | Swiss rounds followed by a top-cut single-elimination bracket (e.g. League Cups, Regionals). |
| `single_elimination` | Single-elimination bracket throughout. |
| `round_robin`        | Round-robin (every player plays every other player). |

### Constraints

- `name`: required, 3–150 characters
- `eventId`: optional, unique when provided. Free text — recommended to match the official Pokemon tournament ID (e.g. `"0000123456"`)
- `format`: required, from a predefined list of allowed values
- `date`: required, must be in the future at creation time
- `endDate`: if provided, must be >= `date`
- `location`: **nullable**.
- `registrationLink`: required, valid URL format
- `tournamentStructure`: required, must be a valid `TournamentStructure` value
- `minAttendees`: optional, >= 1 when provided
- `maxAttendees`: optional, >= `minAttendees` when both provided
- `roundDuration`: optional, >= 1 (minutes)
- `topCutRoundDuration`: optional, >= 1, only valid when `tournamentStructure` is `swiss_top_cut`
- `entryFeeAmount` and `entryFeeCurrency`: both null (free) or both set (paid). `entryFeeAmount` >= 0.
- `cancelledAt`: once set, cannot be cleared (cancellation is irreversible). A cancelled event cannot be edited further (F3.10).
- `finishedAt`: once set, cannot be cleared (finishment is irreversible). A finished event cannot be un-finished, cancelled, or edited further (F3.20). Mutually exclusive with `cancelledAt` — an event cannot be both cancelled and finished.
- `timezone`: required, must be a valid IANA timezone identifier. Default: `"UTC"`.

### Cancellation Behavior

When an event is cancelled (F3.10):
1. `cancelledAt` is set to the current timestamp
2. **Pre-handoff borrows are cancelled:** all `pending` and `approved` borrows linked to this event are automatically moved to `cancelled` status and their deck status reverts to `available`
3. **Already-lent decks are untouched:** borrows in `lent` or `overdue` status remain active — the physical deck is out and must still be returned by the borrower through the normal return flow (F4.4). These borrows continue through their lifecycle independently of the event's cancelled state
4. All participants and affected deck owners are notified (F8.2). Owners of lent decks receive a specific notification that the event was cancelled but their deck is still out
5. The event remains visible in listings for historical reference, but is clearly marked as cancelled
6. No further edits, borrow requests, or participation changes are allowed

### Finishment Behavior

> **@see** docs/features.md F3.20 — Mark event as finished

When an event is marked as finished (F3.20):
1. `finishedAt` is set to the current timestamp
2. **Overdue reminders triggered:** all borrows in `lent` status for this event are flagged — borrowers receive immediate overdue reminders (email + in-app per F8.3 preferences) for unreturned decks
3. **Event closed:** no new borrows, registrations, engagement state changes, or edits are allowed
4. **Tournament results unlocked:** organizer and staff can now enter final standings, match records, and placements (F3.17)
5. The event remains visible in listings and is marked as finished (distinct from cancelled)
6. A finished event **cannot be un-finished** — this is an irreversible terminal state

#### Finished vs Cancelled

| Aspect              | Finished (F3.20)                        | Cancelled (F3.10)                      |
|---------------------|-----------------------------------------|----------------------------------------|
| Meaning             | Event completed normally                | Event didn't happen                    |
| Pre-handoff borrows | Unchanged (should already be lent)      | Automatically cancelled                |
| Lent decks          | Flagged as overdue, reminders sent      | Remain active, owners notified         |
| Tournament results  | Unlocked for entry                      | N/A                                    |
| Further edits       | Blocked                                 | Blocked                                |
| Reversible          | No                                      | No                                     |

---

### Event ID & Tournament Verification

The `eventId` field is **free text** intended to hold the official Pokemon sanctioned tournament ID. This allows:
- Cross-referencing with official tournament data
- Verifying that a deck was used at a real event
- Potential future integration with Pokemon tournament APIs

> **Future consideration (F3.6):** Investigate whether the Pokemon tournament system exposes an API to verify that the organizer creating the event is the actual owner/TO of the referenced tournament ID. This would prevent unauthorized event creation with someone else's tournament ID.

### Timezone Display

> **@see** docs/features.md F9.4 — UTC datetime storage

- Event times are displayed in the event's `timezone` for **all users**, regardless of their personal timezone setting
- When the user's timezone (F9.2) differs from the event's timezone, an optional user-relative hint is appended — e.g. "10:00 CET (16:00 your time)"
- The event creation form defaults the timezone picker to the user's own timezone (`User.timezone`, F9.2), which the organizer can override

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `organizer`        | ManyToOne    | `User`         | User who created this event |
| `engagements`      | OneToMany    | `EventEngagement` | Player engagement states for this event (F3.13) |
| `staff`            | OneToMany    | `EventStaff`   | Staff members assigned to this event |
| `borrows`          | OneToMany    | `Borrow`       | Borrow requests linked to this event |
| `deckEntries`      | OneToMany    | [`EventDeckEntry`](deck.md#entity-appentityeventdeckentry) | Deck versions registered for this event (F3.7) |
| `deckRegistrations`| OneToMany    | `EventDeckRegistration` | Per-deck delegation preferences for this event (F4.8) |

---

## Entity: `App\Entity\EventDeckRegistration`

Records per-deck-per-event availability. A deck owner first **registers** their deck at an event (making it available for borrowing), then optionally **delegates** handling to event staff. Registration and delegation are separate concerns with independent toggles:

- **Register** (`toggle-registration`): creates or removes the `EventDeckRegistration`. Unregistering is blocked if there is an active borrow for the deck at this event.
- **Delegate** (`toggle-delegation`): flips `delegateToStaff` on an existing registration. Requires the deck to be registered first.

When `delegateToStaff` is true, any new borrow for this deck at this event will auto-inherit `isDelegatedToStaff = true`, allowing staff to approve, hand off, and confirm return without the owner's intervention. Only decks with a registration appear in the "Browse available decks" page for other participants.

> **@see** docs/features.md F4.8 — Staff-delegated lending

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `event`            | `Event`            | No       | The event this registration belongs to. |
| `deck`             | `Deck`             | No       | The deck being registered. |
| `delegateToStaff`  | `bool`             | No       | Whether staff can handle this deck (approve, hand off, return). Default: `false`. |
| `registeredAt`     | `DateTimeImmutable` | No      | When the owner registered this deck for the event. |

### Constraints

- Unique constraint on (`event`, `deck`) — a deck can only be registered once per event
- Only the deck owner can create or toggle the registration
- Cannot be created/toggled for cancelled or finished events

### Relations

| Relation    | Type      | Target  | Description |
|-------------|-----------|---------|-------------|
| `event`     | ManyToOne | `Event` | The event |
| `deck`      | ManyToOne | `Deck`  | The deck |

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

---

## Entity: `App\Entity\EventEngagement`

> **@see** docs/features.md F3.13 — Player engagement states

Join entity modeling a player's relationship with an event. Replaces the former `Event.participants` ManyToMany — engagement is richer than a simple attendance flag.

### Fields

| Field              | Type                  | Nullable | Description |
|--------------------|-----------------------|----------|-------------|
| `id`               | `int` (auto)          | No       | Primary key |
| `event`            | `Event`               | No       | The event. |
| `user`             | `User`                | No       | The player. |
| `state`            | `EngagementState`     | No       | Current engagement state. See enum below. |
| `participationMode`| `ParticipationMode`   | Yes      | `playing` or `spectating`. Set when the state is `registered_playing` or `registered_spectating`. Null when only `interested` or `invited`. |
| `invitedBy`        | `User`                | Yes      | The organizer/staff who sent the invitation. Null for self-declared states (`interested`, registered). |
| `createdAt`        | `DateTimeImmutable`   | No       | When the engagement was first created. |
| `updatedAt`        | `DateTimeImmutable`   | No       | When the state was last changed. |

### Engagement State Enum: `App\Enum\EngagementState`

| Value                  | Set by           | Description |
|------------------------|------------------|-------------|
| `interested`           | Player (self)    | Player marked interest — event appears in their agenda and iCal feed (F3.14). No commitment to attend. |
| `invited`              | Staff / Organizer| Grants visibility for invitation-only events (F3.11). Acts as both access grant and interest marker. |
| `registered_playing`   | Player (self)    | Player committed to attend and compete. Prerequisite for borrowing a deck (F4.1). |
| `registered_spectating`| Player (self)    | Player committed to attend as spectator. Can lend decks but cannot borrow. |

### Participation Mode Enum: `App\Enum\ParticipationMode`

| Value        | Description |
|--------------|-------------|
| `playing`    | Intends to compete. Can borrow and lend decks. |
| `spectating` | Attends without playing. Can lend decks, cannot borrow. |

### Constraints

- Unique constraint on (`event`, `user`) — one engagement per player per event
- `invited` state can only be set by event staff or organizer
- A player can transition: `interested` → `registered_*`, `invited` → `registered_*`, `registered_*` → `interested` (un-register)
- `invited` is sticky: once invited, the `invitedBy` field is preserved even if the player later registers or reverts to `interested`
- Changing participation mode (`playing` ↔ `spectating`) is allowed while the event hasn't ended
- When an event is cancelled (F3.10), engagements are frozen — no further state changes allowed

### Relations

| Relation    | Type      | Target  | Description |
|-------------|-----------|---------|-------------|
| `event`     | ManyToOne | `Event` | The event |
| `user`      | ManyToOne | `User`  | The player |
| `invitedBy` | ManyToOne | `User`  | The inviter (nullable) |

---

## Enum: `App\Enum\EventVisibility`

> **@see** docs/features.md F3.11 — Event visibility

| Value             | Description |
|-------------------|-------------|
| `public`          | Visible to all visitors, listed in the event finder (F3.15) and agendas. Default. |
| `series`          | Not independently discoverable. Visible only from the parent series page (F3.12). |
| `invitation_only` | Visible only to users with an `invited` or `registered` engagement state. The `invited` state (F3.13) doubles as access grant. |

The `Event` entity gains a `visibility` field of type `EventVisibility` (default: `public`). This replaces any previous binary visibility concept.

# Borrow Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\Borrow`

Represents the full lifecycle of a deck borrow request — from request to return. Supports both direct (owner-to-borrower) and staff-delegated workflows.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `deck`             | `Deck`             | No       | The physical deck being borrowed. |
| `deckVersion`      | `DeckVersion`      | No       | The version of the deck at the time of the borrow. Records the exact card list. |
| `borrower`         | `User`             | No       | The user requesting to borrow the deck. |
| `event`            | `Event`            | No       | The event this borrow is for. |
| `status`           | `string(30)`       | No       | Current borrow status. See Status enum below. Default: `"pending"`. |
| `isDelegatedToStaff` | `bool`           | No       | Auto-set from `EventDeckRegistration.delegateToStaff` at borrow creation time (F4.8). When true, event staff can approve, deny, hand off, and confirm return. Default: `false`. |
| `requestedAt`      | `DateTimeImmutable` | No      | When the borrow request was made. |
| `approvedAt`       | `DateTimeImmutable` | Yes     | When the request was approved (by owner or staff). |
| `approvedBy`       | `User`             | Yes      | Who approved the request (owner or staff member). |
| `handedOffAt`      | `DateTimeImmutable` | Yes     | When the deck was physically handed to the borrower. |
| `handedOffBy`      | `User`             | Yes      | Who handed the deck off (owner or staff member). |
| `returnedAt`       | `DateTimeImmutable` | Yes     | When the deck was returned by the borrower. |
| `returnedTo`       | `User`             | Yes      | Who received the deck back (owner or staff member). |
| `returnedToOwnerAt`| `DateTimeImmutable` | Yes     | (Staff-delegated only) When the staff returned the deck to the owner. |
| `cancelledAt`      | `DateTimeImmutable` | Yes     | When the borrow was cancelled (if applicable). |
| `cancelledBy`      | `User`             | Yes      | Who cancelled (borrower, owner, or staff). |
| `notes`            | `text`             | Yes      | Optional notes (e.g. reason for cancellation, condition remarks). |

### Status Enum: `App\Enum\BorrowStatus`

| Value               | Description |
|---------------------|-------------|
| `pending`           | Request submitted, awaiting approval. |
| `approved`          | Request approved by owner (or staff if delegated). Deck not yet handed off. |
| `lent`              | Deck physically handed to the borrower. |
| `returned`          | Borrower returned the deck (to owner or staff). |
| `returned_to_owner` | (Staff-delegated only) Staff returned the deck to the owner. Final state for delegated borrows. |
| `cancelled`         | Request was cancelled before hand-off. |
| `overdue`           | Deck not returned after the event end date + grace period. |

### State Machine

#### Direct Workflow (owner handles everything)

```
pending → approved → lent → returned
    │         │        ▲
    └─────────┴──→ cancelled
                       │
              Walk-up (F4.12)
```

Cancellation is only possible **before hand-off** (`pending` or `approved`). Once a deck is physically `lent`, the borrow must go through the return flow — even if the event is cancelled (F3.10).

#### Walk-up Path (F4.12)

```
[no prior request] ──→ lent → returned
```

A walk-up lend creates the `Borrow` entity directly in `lent` state — the `pending` and `approved` steps are skipped entirely. The `requestedAt`, `approvedAt`, and `handedOffAt` timestamps are all set to `now()`. The `approvedBy` and `handedOffBy` fields reference the user who initiated the walk-up lend (owner or staff). This path is used for on-the-day lending when no prior borrow request exists (see F4.12).

#### Staff-Delegated Workflow

```
pending → approved → lent → returned → returned_to_owner
    │         │        ▲
    └─────────┴──→ cancelled
                       │
              Walk-up (F4.12)
```

Walk-up lending also applies to staff-delegated decks: when a staff member initiates a walk-up lend for a delegated deck, the borrow is created in `lent` state with `isDelegatedToStaff = true`. The return flow continues through `returned → returned_to_owner` as normal.

#### Overdue (applies to both workflows)

```
lent ──(event end + grace period)──→ overdue → returned
```

### Transition Rules

| From               | To                  | Who can trigger | Condition |
|--------------------|---------------------|-----------------|-----------|
| `pending`          | `approved`          | Owner or staff (if deck registered with delegation at this event) | Deck must be `available` or `reserved` |
| `pending`          | `cancelled`         | Borrower, owner, or staff (if delegated) | — |
| `approved`         | `lent`              | Owner or staff (if delegated) | Ideally confirmed by scanning deck label |
| `approved`         | `cancelled`         | Borrower, owner, or staff | Before hand-off only |
| *(walk-up)*        | `lent`              | Owner, organizer, or staff | Walk-up lend (F4.12). Deck must be `available`. No prior `Borrow` entity — created directly in `lent`. |
| `lent`             | `returned`          | Owner or staff (if delegated) | Ideally confirmed by scanning deck label |
| `lent`             | `overdue`           | System (automatic) | Event end date + grace period exceeded, or event marked as finished (F3.20) |
| `overdue`          | `returned`          | Owner or staff (if delegated) | — |
| `returned`         | `returned_to_owner` | Staff or owner | Staff-delegated only. Final step. |

### Constraints

- A user cannot borrow their own deck
- A user must be a participant of the event to request a borrow (F3.4). For walk-up lends (F4.12), the borrower is **auto-registered** as event participant if not already
- A deck can only have one active borrow **per event** (`pending`, `approved`, or `lent`). Cross-event conflicts are detected at approval time — see [Conflict Detection](#conflict-detection) below.
- `isDelegatedToStaff`: derived from `EventDeckRegistration.delegateToStaff` at request/walk-up creation time (F4.8). The owner controls delegation at the event level (per deck, per event), not per-borrow
- **Walk-up lend (F4.12):** the initiator (owner, organizer, or staff) must themselves be engaged in the event (spectating, interested, or participating). The deck must be `available`. When the initiator is engaged in multiple active events, they must select the target event

### Conflict Detection

> **@see** docs/features.md F4.11 — Borrow conflict detection

When a deck is requested or approved for an event, the system checks for **temporal overlaps** with other borrows for the same deck at different events.

#### Overlap Rule

Two events overlap when:

```
event_A.date < event_B.endDate AND event_B.date < event_A.endDate
```

For **single-day events** (where `endDate` is null), `endDate` is treated as equal to `date` — meaning the event occupies the full day.

#### Conflict Severity Matrix

| Existing borrow status | Action attempted       | Result        | Detail |
|------------------------|------------------------|---------------|--------|
| `approved` or `lent`   | Approve new borrow     | **Blocked**   | Hard block — the deck is already committed for an overlapping event. New approval is prevented. |
| `approved` or `lent`   | Submit new request     | **Warning**   | Warning shown to the borrower at request time. The request can still be submitted. |
| `pending`              | Approve new borrow     | **Warning**   | Warning shown to the owner. Approval is allowed — the owner decides which request to prioritize. |
| `pending`              | Submit new request     | **Allowed**   | No conflict — multiple pending requests for overlapping events are permitted. |

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `deck`             | ManyToOne    | `Deck`         | The physical deck being borrowed |
| `deckVersion`      | ManyToOne    | `DeckVersion`  | The exact card list version at borrow time |
| `borrower`         | ManyToOne    | `User`         | Who is borrowing |
| `event`            | ManyToOne    | `Event`        | For which event |
| `approvedBy`       | ManyToOne    | `User`         | Who approved |
| `handedOffBy`      | ManyToOne    | `User`         | Who handed off |
| `returnedTo`       | ManyToOne    | `User`         | Who collected the return |
| `cancelledBy`      | ManyToOne    | `User`         | Who cancelled |

### Deck Status Synchronization

Borrow status transitions automatically update the Deck status:

| Borrow transition          | Deck status change |
|----------------------------|--------------------|
| `pending → approved`       | `available → reserved` |
| `approved → lent`          | `reserved → lent` |
| *(walk-up)* `→ lent`       | `available → lent` (direct, skips `reserved`) |
| `lent → returned`          | `lent → available` (direct) or stays `lent` (staff-delegated, until returned_to_owner) |
| `returned → returned_to_owner` | `lent → available` (staff-delegated final) |
| `any → cancelled`          | Revert to `available` (if was `reserved`) |

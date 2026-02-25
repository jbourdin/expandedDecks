# Borrow Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\Borrow`

Represents the full lifecycle of a deck borrow request — from request to return. Supports both direct (owner-to-borrower) and staff-delegated workflows.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `deck`             | `Deck`             | No       | The deck being borrowed. |
| `borrower`         | `User`             | No       | The user requesting to borrow the deck. |
| `event`            | `Event`            | No       | The event this borrow is for. |
| `status`           | `string(30)`       | No       | Current borrow status. See Status enum below. Default: `"pending"`. |
| `isDelegatedToStaff` | `bool`           | No       | Whether the owner delegated this deck to event staff for this event. Default: `false`. |
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
    │         │        │
    └─────────┴────────┴──→ cancelled
```

#### Staff-Delegated Workflow

```
pending → approved → lent → returned → returned_to_owner
    │         │        │        │
    └─────────┴────────┴────────┴──→ cancelled
```

#### Overdue (applies to both workflows)

```
lent ──(event end + grace period)──→ overdue → returned
```

### Transition Rules

| From               | To                  | Who can trigger | Condition |
|--------------------|---------------------|-----------------|-----------|
| `pending`          | `approved`          | Owner or staff (if delegated) | Deck must be `available` or `reserved` |
| `pending`          | `cancelled`         | Borrower, owner, or staff | — |
| `approved`         | `lent`              | Owner or staff (if delegated) | Ideally confirmed by scanning deck label |
| `approved`         | `cancelled`         | Borrower, owner, or staff | Before hand-off only |
| `lent`             | `returned`          | Owner or staff (if delegated) | Ideally confirmed by scanning deck label |
| `lent`             | `overdue`           | System (automatic) | Event end date + grace period exceeded |
| `overdue`          | `returned`          | Owner or staff (if delegated) | — |
| `returned`         | `returned_to_owner` | Staff or owner | Staff-delegated only. Final step. |

### Constraints

- A user cannot borrow their own deck
- A user must be a participant of the event to request a borrow (F3.4)
- A deck can only have one active borrow at a time (`pending`, `approved`, or `lent`)
- `isDelegatedToStaff`: set when the owner opts in to delegation (F4.8), cannot be changed after approval

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `deck`             | ManyToOne    | `Deck`         | The borrowed deck |
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
| `lent → returned`          | `lent → available` (direct) or stays `lent` (staff-delegated, until returned_to_owner) |
| `returned → returned_to_owner` | `lent → available` (staff-delegated final) |
| `any → cancelled`          | Revert to `available` (if was `reserved`) |

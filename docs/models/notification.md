# Notification Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\Notification`

Stores in-app notifications for users. Each notification is addressed to a single recipient and links back to the entity that triggered it via the `context` JSON field. Email notifications are dispatched separately (via Symfony Mailer + Messenger) and are not stored in this entity.

### Fields

| Field       | Type                | Nullable | Description |
|-------------|---------------------|----------|-------------|
| `id`        | `int` (auto)        | No       | Primary key |
| `recipient` | `User`              | No       | The user this notification is for. |
| `type`      | `string(50)`        | No       | Notification type. See `NotificationType` enum below. |
| `title`     | `string(255)`       | No       | Short notification title for display. |
| `message`   | `text`              | No       | Notification body text. |
| `context`   | `json`              | Yes      | Contextual data for linking/rendering: `{"borrowId": 42, "eventId": 7, "deckId": 12}`. |
| `isRead`    | `bool`              | No       | Whether the user has read this notification. Default: `false`. |
| `readAt`    | `DateTimeImmutable`  | Yes     | When the notification was marked as read. |
| `createdAt` | `DateTimeImmutable`  | No      | When the notification was created. |

### Notification Type Enum: `App\Enum\NotificationType`

| Value                | Trigger feature | Description |
|----------------------|-----------------|-------------|
| `borrow_requested`   | F4.1            | A new borrow request was submitted for a deck you own. |
| `borrow_approved`    | F4.2            | Your borrow request was approved. |
| `borrow_denied`      | F4.2            | Your borrow request was denied. |
| `borrow_handed_off`  | F4.3            | A deck was handed off (lend confirmed). |
| `borrow_returned`    | F4.4            | A borrowed deck was returned. |
| `borrow_overdue`     | F4.6            | A borrowed deck is overdue for return. |
| `borrow_cancelled`   | F4.7            | A borrow was cancelled by the other party. |
| `staff_assigned`     | F3.5            | You were assigned as staff for an event. |
| `event_updated`      | F3.9            | An event you're participating in was updated. |
| `event_cancelled`    | F3.10           | An event was cancelled. |
| `event_reminder`     | F8.2            | Reminder: an event with active borrows is tomorrow. |

### Constraints

- `recipient`: required, must reference a valid `User`
- `type`: required, must be a valid `NotificationType` value
- `title`: required, 1–255 characters
- `message`: required, non-empty
- `context`: when provided, must be a JSON object (not array). Keys should reference existing entity types (`borrowId`, `eventId`, `deckId`)
- `isRead`: defaults to `false`
- `readAt`: must be null when `isRead` is `false`; set automatically when `isRead` transitions to `true`

### Relations

| Relation    | Type      | Target entity | Description |
|-------------|-----------|---------------|-------------|
| `recipient` | ManyToOne | `User`        | The user this notification is addressed to |

The `User` entity has a corresponding `notifications` OneToMany back-relation (see [User model](user.md)).

### Indexing

- Index on (`recipient`, `isRead`, `createdAt` DESC) for the in-app notification center query (unread first, then recent)
- Index on (`recipient`, `createdAt` DESC) for the full notification history

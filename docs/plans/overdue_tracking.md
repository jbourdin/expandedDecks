# F4.6 — Overdue Tracking Specification

> **Audience:** Developer, AI Agent · **Scope:** F4.6, F3.20 · **Back:** [features](../features.md) | [borrow model](../models/borrow.md) | [event model](../models/event.md)

## Overview

Two-phase approach to deck return tracking at events. The organizer first signals that the event is winding down ("ending phase"), then finishes the event. Each phase triggers notifications and progressively locks the event.

## New Event Lifecycle Phase: Ending Phase

### New Field

`Event.endingPhaseAt` — `DateTimeImmutable`, nullable.

### Trigger

Organizer clicks **"Start ending phase"** on the event page.

**Available when:** event is not cancelled, not finished, and `endingPhaseAt` is null.

**Irreversible** — once started, cannot be undone (same pattern as `finishedAt`).

### Effects on Trigger

1. **Cancel pre-handoff borrows:** all `pending` and `approved` borrows for this event are automatically cancelled (borrowers notified). Same cascading logic as F3.10 cancellation, scoped to pre-handoff borrows only.
2. **Lock new lending:** new borrow requests, approvals, and walk-up lends are blocked. Returns and custody handovers remain open.
3. **First notification — borrowers:** "The ending phase has started for [event]. Please return [deck name] to [owner name / the staff table]." (email + in-app)
4. **First notification — owners with unreturned decks:** "[Event] has entered its ending phase. [N] of your decks are still out with borrowers." (email + in-app)

## Banners on Event Page (During Ending Phase)

| Viewer | Condition | Banner |
|--------|-----------|--------|
| Borrower with `lent` borrow | Always during ending phase | "Please return [deck] to [owner / staff table]" |
| Owner with delegated decks | Has decks in staff custody (`returned`, not `returned_to_owner`) | "X of your decks are available for pickup at organizer custody, Y are still awaiting return from borrowers" |
| Owner with non-delegated decks | Has unreturned decks | "Y of your decks are still awaiting return from borrowers" |
| Organizer / staff | Always during ending phase | "Ending phase — X decks returned, Y still out with borrowers" (across all decks at the event) |

## Enhanced "Finish Event" (F3.20)

When the organizer finishes the event (existing action, now typically done after ending phase):

1. All remaining `lent` borrows transition to **`overdue`** status
2. **Second notification — borrowers** (urgent): "[Deck name] is now overdue. Please return it to [owner / staff] immediately." (email + in-app)
3. **Second notification — owners:** "[N] of your decks are overdue at [event]." (email + in-app)
4. **Custody pickup notification — owners of delegated decks** with decks in staff custody: "Your decks are waiting for you at organizer custody for [event]." (email + in-app)
5. Event locked (existing behavior), tournament results unlocked

## Event Lifecycle Summary

```
Event active
    |
    +-- "Start ending phase" --> endingPhaseAt set
    |       --> cancel pending/approved borrows
    |       --> lock new lending
    |       --> 1st reminder: "please return decks"
    |       --> banners appear
    |
    +-- "Finish event" --> finishedAt set
            --> all lent --> overdue
            --> 2nd reminder: "deck is overdue"
            --> custody pickup notification to owners
            --> event fully locked
```

Both actions are independent — finishing without ending phase first is allowed (both effects fire together).

## Updated Notification Matrix

| Trigger | Recipient | Channel |
|---------|-----------|---------|
| Ending phase started | Borrower with `lent` borrow | Email + in-app |
| Ending phase started | Owner with unreturned decks | Email + in-app |
| Event finished — deck overdue | Borrower with `overdue` borrow | Email + in-app |
| Event finished — deck overdue | Owner with overdue deck | Email + in-app |
| Event finished — custody pickup | Owner with decks in staff custody | Email + in-app |

## Implementation Notes

### Entity Changes

- `Event`: add `endingPhaseAt` field (nullable `DateTimeImmutable`)
- Migration: `ALTER TABLE event ADD ending_phase_at DATETIME DEFAULT NULL`

### Guards

- **Ending phase guard:** check `endingPhaseAt` is null, `finishedAt` is null, `cancelledAt` is null
- **Lending guards:** borrow request creation, approval, walk-up lend, and hand-off must check `endingPhaseAt` is null (or `finishedAt` is null for existing finish guard)
- **Finish guard (existing):** unchanged — finish is allowed whether or not ending phase was started

### Async Messages

- `CancelPreHandoffBorrowsMessage(eventId)` — cancel all `pending` and `approved` borrows (reuse or extend `CancelEventBorrowsMessage` from F3.10)
- `SendEndingPhaseRemindersMessage(eventId)` — send first-round notifications to borrowers and owners
- `SendOverdueNotificationsMessage(eventId)` — send second-round notifications on finish (overdue + custody pickup)

### Banner Data

The controller must compute and pass to the template:
- Count of `lent` borrows for the current user (as borrower) at this event
- Count of user's decks in staff custody (`returned`, delegated, not `returned_to_owner`)
- Count of user's decks still out (`lent` or `overdue`)
- Global counts for organizer/staff view (total returned vs still out)

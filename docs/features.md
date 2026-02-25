# Feature List

> **Audience:** Developer, Player, Organizer · **Scope:** Features, Reference

← Back to [Main Documentation](docs.md) | [README](../README.md)

The frontend is built with **React.js** (via Symfony UX / Webpack Encore) for all interactive UI components.

---

## F1 — User Management

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F1.1   | User registration & authentication   | High     | Players register with email, screen name, and player ID (Pokemon TCG player ID). Email must be verified via a token-based activation link before the account becomes active. Global roles: player, organizer, admin. Staff is a per-event assignment (see F3.5). |
| F1.2   | Email verification                   | High     | On registration, a verification token is sent by email. The account remains inactive until the user clicks the activation link. Token expires after a configurable delay. |
| F1.3   | User profile                         | Medium   | Display screen name, player ID, owned decks, borrow history, and upcoming event participation. |
| F1.4   | Role-based access control            | High     | Global roles: admin, organizer, player. Staff is a **per-event assignment**, not a global role (see F3.5). Admin can manage users. Organizers can create events and assign staff. Players can register decks and request borrows. |
| F1.5   | MFA with TOTP (planned)              | Low      | Multi-factor authentication using TOTP (Google Authenticator, Authy, etc.). Not in initial release — planned for a future iteration. |
| F1.6   | Pokemon SSO (to investigate)         | Low      | Investigate feasibility of integrating Pokemon Company SSO for seamless player identification. Requires outreach to The Pokemon Company to assess API availability and authorization. |
| F1.7   | Password reset                       | High     | Standard forgot-password flow: user requests a reset link by email, receives a tokenized URL, and sets a new password. Mirrors the existing verification token pattern (`verificationToken` / `tokenExpiresAt`). Only one active reset token per user at a time. |

## F2 — Deck Library

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F2.1   | Register a deck                      | High     | A user registers a physical deck they own, assigning it a name, archetype, and format (Expanded). |
| F2.2   | Import deck list (copy-paste)        | High     | User pastes a deck list in standard PTCG text format. The system parses it (`ptcgo-parser`), validates each card against TCGdex, checks Expanded legality (Black & White onward + banned list), and creates a new `DeckVersion` with the parsed cards. The raw text is preserved on the version for reference. `Deck.currentVersion` is updated to the newly created version. |
| F2.3   | Deck detail view                     | Medium   | Display deck info: owner, current version's archetype and card list (categorized: Pokemon / Trainer by subtype / Energy, sorted by quantity then name), availability status, languages, borrow history, and version history with the ability to view past versions. Mouse over a card name shows the card image (from TCGdex). |
| F2.4   | Deck catalog (browse & search)       | Medium   | List all registered decks with filters: archetype, owner, availability, format. |
| F2.5   | Deck availability status             | High     | Each deck has a real-time status: available, lent, reserved, retired. |
| F2.6   | Deck archetype management            | Low      | Admin-managed list of archetypes (e.g. "Lugia VSTAR", "Mew VMAX") for consistent categorization. |
| F2.7   | Retire / reactivate a deck           | Low      | Owner can mark a deck as retired (no longer available) or reactivate it. |
| F2.8   | Update deck list (new version)       | High     | Owner pastes an updated deck list → creates a new `DeckVersion`. `Deck.currentVersion` moves to the new version. Previous versions are preserved for history. Archetype, languages, and estimated value can be updated per version. |
| F2.9   | Deck version history                 | Medium   | View all past versions of a deck: version number, archetype, creation date, and card list. Compare versions to see what changed (cards added/removed/quantity changed). |

## F3 — Event Management

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F3.1   | Create an event                      | High     | An organizer (or admin) declares an upcoming event with name, event ID, date, location, format, registration link, tournament structure (`TournamentStructure` enum), entry fee (cents + ISO 4217 currency), min/max attendees, round durations, decklist requirement, and optional league link. |
| F3.2   | Event listing                        | Medium   | Browse upcoming and past events with date, location, and participant count. |
| F3.3   | Event detail view                    | Medium   | Show event info (tournament structure, entry fee, league info, registration link, round durations, attendee limits, decklist requirement), list of borrow requests, and deck assignments for that event. |
| F3.4   | Register participation to an event   | Medium   | A player declares they intend to attend an event (prerequisite to requesting a borrow). |
| F3.5   | Assign event staff team              | High     | An organizer assigns staff members to an event. Staff role is **per event** (not a global role). Staff can then act as intermediaries for deck lending at that event only. |
| F3.6   | Tournament ID verification (to investigate) | Low | Investigate whether the Pokemon tournament system exposes an API to verify that the organizer is the actual TO of the referenced tournament ID. |
| F3.7   | Register played deck for event       | Medium   | A player (deck owner or borrower) records which deck version they played at an event, creating an `EventDeckEntry`. This is separate from borrowing — it tracks tournament deck registration for history and traceability. |
| F3.8   | League/Store management              | Medium   | Create and manage leagues/stores with name, website, address, and contact details. Events can be linked to a league for recurring venue tracking. |
| F3.9   | Edit an event                        | High     | An organizer can update an event's details (name, date, location, tournament structure, entry fee, attendee limits, round durations, registration link, league link, decklist requirement) as long as the event has not ended. Participants are notified of material changes (date, location, cancellation — see F8.2). |
| F3.10  | Cancel an event                      | Medium   | An organizer can cancel an event. Cancellation cascades: all `pending` and `approved` borrows for this event are automatically cancelled, and participants/owners are notified (F8.2). Cancelled events remain visible (for history) but are clearly marked. A cancelled event cannot be un-cancelled or edited further. |

## F4 — Borrow Workflow

| ID     | Feature                                  | Priority | Description |
|--------|------------------------------------------|----------|-------------|
| F4.1   | Request to borrow a deck for an event    | High     | A participant requests a specific deck (or archetype preference) for a declared event. |
| F4.2   | Approve / deny borrow request            | High     | The deck owner reviews and approves or denies the request. |
| F4.3   | Confirm deck hand-off (lend)             | High     | At the event, the lend is confirmed — deck status changes to "lent". Ideally done by scanning the deck label. Can be performed by the owner or by event staff (see F4.8). |
| F4.4   | Confirm deck return                      | High     | After the event, the return is confirmed — deck status changes back to "available". Ideally done by scanning the deck label. Can be performed by the owner or by event staff (see F4.8). |
| F4.5   | Borrow history                           | Medium   | Full history of borrows per deck and per user: who borrowed what, when, for which event. |
| F4.6   | Overdue tracking                         | Low      | Flag decks that haven't been returned within a configurable delay after the event end date. |
| F4.7   | Cancel a borrow request                  | Medium   | A borrower can cancel their pending request, or an owner can revoke an approved request before hand-off. |
| F4.8   | Staff-delegated lending                  | High     | A deck owner can opt-in to delegate deck handling to the event staff **per deck, per event**. The owner chooses which decks to delegate (e.g. keeping costly or sentimental decks under personal control). When delegated, the workflow becomes: (1) owner hands the deck to staff before/at the event, (2) staff receives and holds the deck, (3) staff approves borrow requests and lends the deck to the borrower, (4) staff collects the deck back from the borrower, (5) staff returns the deck to the owner after the event. Each step is tracked and can be confirmed by scanning the deck label. Non-delegated decks follow the standard owner-to-borrower workflow (F4.1–F4.4). |
| F4.9   | Staff deck custody tracking             | Medium   | Track which staff member currently holds which decks. The staff dashboard shows all decks in their custody for a given event, with pending borrow requests to fulfill. |
| F4.10  | Owner borrow inbox                      | Medium   | A dedicated view for deck owners showing all actionable borrow requests across all their decks. Displays pending requests (requiring approval), approved requests (awaiting hand-off), and active lends — grouped by upcoming event. Provides quick approve/deny actions inline. This is the owner's primary pre-event preparation screen. |

## F5 — Zebra Label Printing (via PrintNode)

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F5.1   | Generate ZPL label for a deck        | High     | Generate a Zebra Programming Language (ZPL) label containing: deck ID, deck name, owner name, and a barcode/QR code encoding the deck ID. |
| F5.2   | Push label to printer via PrintNode  | High     | Send the generated ZPL payload to a Zebra printer through the PrintNode cloud API. The Zebra printer runs a local PrintNode client. |
| F5.3   | Scan label to identify deck          | High     | Scan a deck box label (barcode/QR) using a USB HID barcode reader in the browser to pull up the deck detail or trigger a lend/return action. See [Scanner Technicality](technicalities/scanner.md). |
| F5.4   | Reprint label                        | Low      | Reprint a label for a deck (e.g. after box replacement). |
| F5.5   | PrintNode printer management         | Medium   | Configure PrintNode API key and select target printer from available PrintNode printers. |
| F5.6   | Camera QR scan (mobile fallback)     | Medium   | Tap scan button to open device camera and scan deck label QR code. Uses `html5-qrcode`. Same lookup/action as HID scanner (F5.3). See [Camera Scanner Technicality](technicalities/camera_scanner.md). |

## F6 — Card Data & Validation

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F6.1   | Parse PTCG text format               | High     | Parse pasted deck lists using `ptcgo-parser` (npm) into structured card objects (name, set code, card number, quantity). |
| F6.2   | Card validation via TCGdex           | High     | Validate each parsed card against TCGdex (`@tcgdex/sdk`): confirm card exists, resolve card type (pokemon/trainer/energy) and trainer subtype (supporter/item/tool/stadium). |
| F6.3   | Expanded format validation           | High     | Custom validator: all cards must be from Black & White (BLW) series onward, not on the banned list, 60 cards total, max 4 copies of any card (except basic energy). |
| F6.4   | Display card images                  | Medium   | Show card images on hover in the deck detail view (fetched from TCGdex, cached client-side). |

## F7 — Administration

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F7.1   | Dashboard                            | Medium   | Admin overview: total decks, active borrows, upcoming events, overdue returns. |
| F7.2   | User management                      | Medium   | Admin CRUD for user accounts and role assignment. |
| F7.3   | Audit log                            | Low      | Log significant actions (deck registered, borrow approved, return confirmed) for traceability. |

## F8 — Notifications

Both email (via Symfony Mailer + Messenger async transport) and in-app (stored in DB, displayed in UI).

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F8.1   | Borrow workflow notifications        | High     | Notify relevant parties at each borrow state transition. See notification matrix below. |
| F8.2   | Event notifications                  | Medium   | Notify participants and deck owners of event changes and reminders. See notification matrix below. |
| F8.3   | Notification preferences             | Low      | Users can opt out of specific notification types (per-type toggle). Email and in-app channels are controlled independently. Critical notifications (overdue, event cancellation) cannot be disabled. |
| F8.4   | In-app notification center           | Medium   | A notification bell/inbox in the UI showing unread and recent notifications. Notifications can be marked as read individually or in bulk. Links to the relevant entity (borrow, event, deck). |

### Notification Matrix — F8.1 Borrow Workflow

| Trigger                          | Recipient                              | Channel        |
|----------------------------------|----------------------------------------|----------------|
| New borrow request (F4.1)        | Deck owner (or staff if delegated)     | Email + in-app |
| Request approved (F4.2)          | Borrower                               | Email + in-app |
| Request denied (F4.2)            | Borrower                               | Email + in-app |
| Deck handed off (F4.3)           | Borrower, owner                        | In-app         |
| Deck returned (F4.4)             | Owner                                  | In-app         |
| Deck overdue (F4.6)              | Owner, borrower                        | Email + in-app |
| Borrow cancelled (F4.7)          | Other party (owner or borrower)        | Email + in-app |

### Notification Matrix — F8.2 Event Notifications

| Trigger                          | Recipient                                          | Channel        |
|----------------------------------|----------------------------------------------------|----------------|
| Staff assigned to event (F3.5)   | Staff member                                       | Email + in-app |
| Event details changed (F3.9)     | All participants                                   | Email + in-app |
| Event cancelled (F3.10)          | All participants + deck owners with borrows        | Email + in-app |
| Event reminder (1 day before)    | Participants with active borrows                   | Email          |

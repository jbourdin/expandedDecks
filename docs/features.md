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

## F5 — Zebra Label Printing (via PrintNode)

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F5.1   | Generate ZPL label for a deck        | High     | Generate a Zebra Programming Language (ZPL) label containing: deck ID, deck name, owner name, and a barcode/QR code encoding the deck ID. |
| F5.2   | Push label to printer via PrintNode  | High     | Send the generated ZPL payload to a Zebra printer through the PrintNode cloud API. The Zebra printer runs a local PrintNode client. |
| F5.3   | Scan label to identify deck          | High     | Scan a deck box label (barcode/QR) using a USB HID barcode reader in the browser to pull up the deck detail or trigger a lend/return action. See [Scanner Technicality](technicalities/scanner.md). |
| F5.4   | Reprint label                        | Low      | Reprint a label for a deck (e.g. after box replacement). |
| F5.5   | PrintNode printer management         | Medium   | Configure PrintNode API key and select target printer from available PrintNode printers. |

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

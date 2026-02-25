# Feature List

> **Audience:** Developer, Player, Organizer · **Scope:** Features, Reference

← Back to [Main Documentation](docs.md) | [README](../README.md)

The frontend is built with **React.js** (via Symfony UX / Webpack Encore) for all interactive UI components.

---

## F1 — User Management

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F1.1   | User registration & authentication   | High     | Players can create an account and log in. Basic roles: player, organizer, admin. |
| F1.2   | User profile                         | Medium   | Display user info, owned decks, borrow history, and upcoming event participation. |
| F1.3   | Role-based access control            | High     | Admin can manage users. Organizers can create events. Players can register decks and request borrows. |

## F2 — Deck Library

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F2.1   | Register a deck                      | High     | A user registers a physical deck they own, assigning it a name, archetype, and format (Expanded). |
| F2.2   | Import deck list from Limitless TCG  | High     | Fetch and attach a full card list to a deck using the Limitless TCG API (by list URL or manual search). |
| F2.3   | Deck detail view                     | Medium   | Display deck info: owner, archetype, card list, availability status, borrow history. |
| F2.4   | Deck catalog (browse & search)       | Medium   | List all registered decks with filters: archetype, owner, availability, format. |
| F2.5   | Deck availability status             | High     | Each deck has a real-time status: available, lent, reserved, retired. |
| F2.6   | Deck archetype management            | Low      | Admin-managed list of archetypes (e.g. "Lugia VSTAR", "Mew VMAX") for consistent categorization. |
| F2.7   | Retire / reactivate a deck           | Low      | Owner can mark a deck as retired (no longer available) or reactivate it. |

## F3 — Event Management

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F3.1   | Create an event                      | High     | An organizer (or admin) declares an upcoming event with name, date, location, and format. |
| F3.2   | Event listing                        | Medium   | Browse upcoming and past events with date, location, and participant count. |
| F3.3   | Event detail view                    | Medium   | Show event info, list of borrow requests, and deck assignments for that event. |
| F3.4   | Register participation to an event   | Medium   | A player declares they intend to attend an event (prerequisite to requesting a borrow). |

## F4 — Borrow Workflow

| ID     | Feature                                  | Priority | Description |
|--------|------------------------------------------|----------|-------------|
| F4.1   | Request to borrow a deck for an event    | High     | A participant requests a specific deck (or archetype preference) for a declared event. |
| F4.2   | Approve / deny borrow request            | High     | The deck owner reviews and approves or denies the request. |
| F4.3   | Confirm deck hand-off (lend)             | High     | At the event, the lend is confirmed — deck status changes to "lent". Ideally done by scanning the deck label. |
| F4.4   | Confirm deck return                      | High     | After the event, the return is confirmed — deck status changes back to "available". Ideally done by scanning the deck label. |
| F4.5   | Borrow history                           | Medium   | Full history of borrows per deck and per user: who borrowed what, when, for which event. |
| F4.6   | Overdue tracking                         | Low      | Flag decks that haven't been returned within a configurable delay after the event end date. |
| F4.7   | Cancel a borrow request                  | Medium   | A borrower can cancel their pending request, or an owner can revoke an approved request before hand-off. |

## F5 — Zebra Label Printing (via PrintNode)

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F5.1   | Generate ZPL label for a deck        | High     | Generate a Zebra Programming Language (ZPL) label containing: deck ID, deck name, owner name, and a barcode/QR code encoding the deck ID. |
| F5.2   | Push label to printer via PrintNode  | High     | Send the generated ZPL payload to a Zebra printer through the PrintNode cloud API. The Zebra printer runs a local PrintNode client. |
| F5.3   | Scan label to identify deck          | High     | Scan a deck box label (barcode/QR) using a USB HID barcode reader in the browser to pull up the deck detail or trigger a lend/return action. See [Scanner Technicality](technicalities/scanner.md). |
| F5.4   | Reprint label                        | Low      | Reprint a label for a deck (e.g. after box replacement). |
| F5.5   | PrintNode printer management         | Medium   | Configure PrintNode API key and select target printer from available PrintNode printers. |

## F6 — Limitless TCG Integration

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F6.1   | Search deck lists on Limitless TCG   | High     | Query the Limitless TCG API to find deck lists by archetype, tournament, or player. |
| F6.2   | Import and store deck list           | High     | Import a deck list (card names, quantities, set codes) and persist it locally linked to a deck. |
| F6.3   | Display card images                  | Medium   | Show card images in the deck detail view (via Pokemon TCG API or cached). |
| F6.4   | Sync / update a deck list            | Low      | Re-fetch a deck list from Limitless TCG to update local data if the source was modified. |

## F7 — Administration

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F7.1   | Dashboard                            | Medium   | Admin overview: total decks, active borrows, upcoming events, overdue returns. |
| F7.2   | User management                      | Medium   | Admin CRUD for user accounts and role assignment. |
| F7.3   | Audit log                            | Low      | Log significant actions (deck registered, borrow approved, return confirmed) for traceability. |

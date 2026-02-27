# Feature List

> **Audience:** Developer, Player, Organizer · **Scope:** Features, Reference

← Back to [Main Documentation](docs.md) | [README](../README.md)

The frontend is built with **React.js** (via Symfony UX / Webpack Encore) for all interactive UI components.

---

## F1 — User Management

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F1.1   | User registration & authentication   | High     | Players register with email, first name, last name, screen name, and player ID (Pokemon TCG player ID). Email must be verified via a token-based activation link before the account becomes active. Global roles: player, organizer, admin. Staff is a per-event assignment (see F3.5). First name and last name are used for the public "FirstName L." display in tournament results (F3.17); the screen name is the primary display name for authenticated views. |
| F1.2   | Email verification                   | High     | On registration, a verification token is sent by email. The account remains inactive until the user clicks the activation link. Token expires after a configurable delay. |
| F1.3   | User profile                         | Medium   | Display screen name, player ID, owned decks, borrow history, and upcoming event participation. |
| F1.4   | Role-based access control            | High     | Global roles: admin, organizer, player. Staff is a **per-event assignment**, not a global role (see F3.5). Admin can manage users. Organizers can create events and assign staff. Players can register decks and request borrows. |
| F1.5   | MFA with TOTP (planned)              | Low      | Multi-factor authentication using TOTP (Google Authenticator, Authy, etc.). Not in initial release — planned for a future iteration. |
| F1.6   | Pokemon SSO (to investigate)         | Low      | Investigate feasibility of integrating Pokemon Company SSO for seamless player identification. Requires outreach to The Pokemon Company to assess API availability and authorization. |
| F1.7   | Password reset                       | High     | Standard forgot-password flow: user requests a reset link by email, receives a tokenized URL, and sets a new password. Mirrors the existing verification token pattern (`verificationToken` / `tokenExpiresAt`). Only one active reset token per user at a time. |
| F1.8   | Account deletion & data export (GDPR) | Medium  | User can request account deletion from their profile. Soft-delete with **anonymization**: personal data (email, screenName, playerId) replaced with anonymous placeholders, borrow/event history preserved for data integrity. Confirmation via **email link** (expires 24 h). Users can also **export all personal data as JSON** (profile, borrows, events, decks). See [User model — GDPR](models/user.md). |

## F2 — Deck Library

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F2.1   | Register a deck                      | High     | A user registers a physical deck they own, assigning it a name, archetype, and format (Expanded). |
| F2.2   | Import deck list (copy-paste)        | High     | User pastes a deck list in standard PTCG text format. The system parses it (`ptcgo-parser`), validates each card against TCGdex, checks Expanded legality (Black & White onward + banned list), and creates a new `DeckVersion` with the parsed cards. The raw text is preserved on the version for reference. `Deck.currentVersion` is updated to the newly created version. |
| F2.3   | Deck detail view                     | Medium   | Display deck info: owner, current version's archetype and card list (categorized: Pokemon / Trainer by subtype / Energy, sorted by quantity then name), availability status, languages, borrow history, and version history with the ability to view past versions. Mouse over a card name shows the card image (from TCGdex). |
| F2.4   | Deck catalog (browse & search)       | Medium   | List all registered decks. Filters: **archetype**, **event** (show only decks that were played at a specific event via F3.7), owner, availability, format. Default sort: **best tournament result** (lowest placement ascending — a 1st-place finish ranks above 2nd), then by **`Deck.updatedAt` descending** (most recently updated first). Decks without any tournament result appear after those with results, sorted by `updatedAt` only. The catalog is publicly accessible (anonymous visitors can browse) and serves as the full **deck library**. |
| F2.5   | Deck availability status             | High     | Each deck has a real-time status: available, lent, reserved, retired. |
| F2.6   | Deck archetype management            | Low      | Admin-managed list of archetypes (e.g. "Lugia VSTAR", "Mew VMAX") for consistent categorization. **Note:** needs further specification — archetype entity structure, CRUD screens, and whether deck archetypes are strictly from the managed list or free-text with suggestions. |
| F2.7   | Retire / reactivate a deck           | Low      | Owner can mark a deck as retired (no longer available) or reactivate it. |
| F2.8   | Update deck list (new version)       | High     | Owner pastes an updated deck list → creates a new `DeckVersion`. `Deck.currentVersion` moves to the new version. Previous versions are preserved for history. Archetype, languages, and estimated value can be updated per version. |
| F2.9   | Deck version history                 | Medium   | View all past versions of a deck: version number, archetype, creation date, and card list. Compare versions to see what changed (cards added/removed/quantity changed). |

## F3 — Event Management

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F3.1   | Create an event                      | High     | An organizer (or admin) declares an upcoming event with name, event ID, date, location, format, registration link, tournament structure (`TournamentStructure` enum), entry fee (cents + ISO 4217 currency), min/max attendees, round durations, decklist requirement, and optional league link. The tournament ID field has a **"Sync" CTA** to prefill the form from the official Pokemon event page (F3.18). |
| F3.2   | Event listing                        | Medium   | Browse upcoming and past events with date, location, and participant count. The **homepage event list** defaults to showing only events where the current user has an engagement state of at least `interested` (see F3.13). A **"Find Tournament"** call-to-action is displayed next to the list, linking to the event discovery page (F3.15). Unauthenticated visitors see the public event listing directly. |
| F3.3   | Event detail view                    | Medium   | Show event info (tournament structure, entry fee, league info, registration link, round durations, attendee limits, decklist requirement), list of borrow requests, and deck assignments for that event. |
| F3.4   | Register participation to an event   | Medium   | A player declares they intend to attend an event (prerequisite to requesting a borrow). Participation has two modes: **playing** (the player intends to compete and may borrow a deck) or **spectating** (the player attends without playing — they can still lend their own decks to others but cannot borrow). Spectating is useful for mixed-format events where the player may not qualify for a later stage (e.g. Standard day two or top cut) or simply wants to support the community by making decks available. Changing mode (spectating ↔ playing) is allowed as long as the event hasn't ended. |
| F3.5   | Assign event staff team              | High     | An organizer assigns staff members to an event. Staff role is **per event** (not a global role). Staff can then act as intermediaries for deck lending at that event only. |
| F3.6   | Tournament ID verification (to investigate) | Low | Investigate whether the Pokemon tournament system exposes an API to verify that the organizer is the actual TO of the referenced tournament ID. Related: F3.18 already fetches the event page and can extract the organizer name — this could serve as a lightweight verification hint. |
| F3.7   | Register played deck for event       | Medium   | A player (deck owner or borrower) records which deck version they played at an event, creating an `EventDeckEntry`. This is separate from borrowing — it tracks tournament deck registration for history and traceability. After the event, the organizer or staff can record the player's **final placement** (integer ranking, e.g. 1st, 2nd, top 4, top 8) and **match record** (wins-losses-ties). These results feed into the tournament results page (F3.17) and the deck library sort order (F2.4). |
| F3.8   | League/Store management              | Medium   | Create and manage leagues/stores with name, website, address, and contact details. Events can be linked to a league for recurring venue tracking. |
| F3.9   | Edit an event                        | High     | An organizer can update an event's details (name, date, location, tournament structure, entry fee, attendee limits, round durations, registration link, league link, decklist requirement) as long as the event has not ended. The **"Sync" CTA** (F3.18) is also available on the edit form to re-fetch data from the Pokemon event page. Participants are notified of material changes (date, location, cancellation — see F8.2). |
| F3.10  | Cancel an event                      | Medium   | An organizer can cancel an event. Cancellation cascades: all `pending` and `approved` borrows for this event are automatically cancelled, and participants/owners are notified (F8.2). Cancelled events remain visible (for history) but are clearly marked. A cancelled event cannot be un-cancelled or edited further. |
| F3.11  | Event visibility                     | Medium   | Each event has a visibility setting controlling discoverability. Three modes: **public** — visible to all visitors (including unauthenticated users), listed in the event finder (F3.15) and agendas; **series** — not independently discoverable, visible only from the parent series page (F3.12), useful for events that only make sense in their series context; **invitation-only** — visible only to users who have been explicitly invited by the event organizer or staff (the `invited` engagement state from F3.13 doubles as access grant). Default: public. The event listing (F3.2), event finder (F3.15), and detail view (F3.3) all respect this setting. Organizers, event staff, and admins always see all events regardless of visibility. |
| F3.12  | Event series                         | Low      | Group related events into a named **series** (e.g. "June Expanded Cup — Île-de-France"). A series has a name, optional description, date range, format, and organizer. Events can be linked to a series. A series has its own **public or invitation-only page** displaying the chronological list of its events with dates, locations (leagues), and results. Events with `series` visibility (F3.11) are listed on the series page but do not appear in the public event finder — the series page is their only entry point. A public series page is accessible to all; an invitation-only series page is restricted to users who are `interested` or `invited` in at least one event of the series. |
| F3.13  | Player engagement states             | Medium   | A player-event relationship goes through engagement states that determine agenda visibility and access. States: **interested** — the player self-declares interest; the event appears in their personal agenda and iCal feed (F3.14), but they are not yet committed to attending. **invited** — set by event organizer or staff only; grants visibility for invitation-only events (F3.11) and adds the event to the player's agenda. **registered (playing)** — the player has confirmed participation and intends to compete (existing F3.4 behavior). **registered (spectating)** — the player has confirmed attendance as a spectator (see F3.4). Progression: a player can move from `interested`/`invited` → `registered (playing/spectating)` and back to `interested` if they un-register. The `invited` state can only be added by event staff or the organizer but can be combined with any registration state. The homepage event list (F3.2) defaults to showing events where the player has any engagement state. |
| F3.14  | iCal agenda feed                     | Low      | Each user has a personal, token-authenticated iCal/ICS feed URL (e.g. `/ical/{userToken}.ics`). The feed contains all events where the user has any engagement state (`interested`, `invited`, `registered`). Each event is rendered as a `VEVENT` with name, date/time, location, and a link to the event detail page. The feed updates dynamically. The token is regenerable from the user profile (invalidates the previous URL). Useful for syncing with Google Calendar, Apple Calendar, Outlook, etc. |
| F3.15  | Event discovery                      | Medium   | A dedicated **"Find Tournament"** page for browsing and searching public events (visibility = `public`). Filters: date range, format, location/region, tournament structure, series. Results show event name, date, location, league, participant count, and a quick "I'm interested" action (sets the `interested` state from F3.13). Accessible from the homepage CTA next to the user's event list (F3.2). Also serves as the landing page for unauthenticated visitors exploring available events. |
| F3.16  | Public iCal feed                     | Low      | An anonymous, unauthenticated iCal/ICS feed at a fixed URL (e.g. `/ical/public.ics`). Contains all publicly visible events: those with `public` visibility (F3.11) **and** events with `series` visibility whose parent series is public. Past events are **truncated to 1 month** (events whose `endDate` — or `date` for single-day events — is older than 1 month are excluded). Future events have no cutoff. The response sets `Cache-Control: public, max-age=3600` (1-hour HTTP cache). Each event is rendered as a `VEVENT` with name, date/time, location, league name, tournament structure, and a link to the event detail page. Cancelled events are excluded. No authentication or token required — suitable for embedding in community calendars and public websites. |
| F3.17  | Tournament results                   | Medium   | Public page showing the results of a completed event. Displays the final standings: placement, player name, match record (W-L-T), deck archetype, and a link to the played deck version (F3.7). **Privacy:** in public/anonymous view, player names are displayed as **"FirstName L."** (first name + first letter of last name) using the `firstName`/`lastName` fields from the User model. Authenticated users see the player's full screen name instead. Results are ordered by placement ascending (1st, 2nd, …). Each event detail page (F3.3) links to its results once results have been entered. Only visible for events with `public` visibility or events in a public series. |
| F3.18  | Sync from Pokemon event page         | Medium   | A **"Sync"** button next to the Tournament ID field on the event create (F3.1) and edit (F3.9) forms. When the user enters a tournament ID (e.g. `26-03-000114`) and clicks Sync, the system fetches the official Pokemon event page (`https://www.pokemon.com/{locale}/play-pokemon/pokemon-events/{tournamentId}/`) via a lightweight **backend proxy endpoint** (needed to bypass CORS). The proxy parses the page's **JSON-LD** `schema.org/Event` markup and key HTML elements, then returns structured JSON. Available fields: event name, start date, venue name, full address (street, city, region, postal code, country), format (e.g. "TCG: Standard" → mapped to app format values), event type (e.g. "League Challenge", "League Cup", "Regional" → mapped to `TournamentStructure` enum), league name, and organizer name. The **frontend receives the JSON and prefills the form** — this is purely a helper; the user reviews, can modify any field, and explicitly saves. Fields already filled by the user are **not overwritten** unless the user confirms. The sync also populates the league selector (F3.8): if a league matching the venue name/address is found, it is auto-selected; otherwise the user is prompted to create one. Visual feedback: loading spinner on the Sync button, success toast listing prefilled fields, or error message if the page is unreachable or the tournament ID is invalid. |

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
| F4.11  | Borrow conflict detection               | High     | Detect **temporal conflicts** when a deck is requested for overlapping events. **Hard block:** if the deck is `approved` or `lent` for an overlapping event, new approvals are blocked. **Soft warning:** `pending` requests for overlapping events show a warning but the owner can still approve. Overlap rule: `event_A.date < event_B.endDate AND event_B.date < event_A.endDate`. See [Borrow model — Conflict Detection](models/borrow.md). |

## F5 — Zebra Label Printing (via PrintNode)

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F5.1   | Generate ZPL label for a deck        | High     | Generate a Zebra Programming Language (ZPL) label containing: deck ID, deck name, owner name, and a barcode/QR code encoding the deck ID. |
| F5.2   | Push label to printer via PrintNode  | High     | Send the generated ZPL payload to a Zebra printer through the PrintNode cloud API. The Zebra printer runs a local PrintNode client. |
| F5.3   | Scan label to identify deck          | High     | Scan a deck box label (barcode/QR) using a USB HID barcode reader in the browser to pull up the deck detail or trigger a lend/return action. See [Scanner Technicality](technicalities/scanner.md). |
| F5.4   | Reprint label                        | Low      | Reprint a label for a deck (e.g. after box replacement). |
| F5.5   | PrintNode printer management         | Medium   | Configure PrintNode API key and select target printer from available PrintNode printers. |
| F5.6   | Camera QR scan (mobile fallback)     | Medium   | Tap scan button to open device camera and scan deck label QR code. Uses `html5-qrcode`. Same lookup/action as HID scanner (F5.3). See [Camera Scanner Technicality](technicalities/camera_scanner.md). |
| F5.7   | PDF label card (home printing)       | Medium   | Generate a downloadable PDF with a TCG card-sized label (63.5 × 88.9 mm) containing deck ID, name, owner, and QR code. Printed on any home printer, cut out, and slipped into a card sleeve. Uses Dompdf + `endroid/qr-code`. Same QR encoding as ZPL label — scannable by F5.3 and F5.6. See [PDF Label Technicality](technicalities/pdf_label.md). |

> **Future:** A dedicated technicality document for PrintNode integration (ZPL generation, API client, printer selection) is planned — `docs/technicalities/printnode.md`.

## F6 — Card Data & Validation

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F6.1   | Parse PTCG text format               | High     | Parse pasted deck lists using `ptcgo-parser` (npm) into structured card objects (name, set code, card number, quantity). |
| F6.2   | Card validation via TCGdex           | High     | Validate each parsed card against TCGdex (`@tcgdex/sdk`): confirm card exists, resolve card type (pokemon/trainer/energy) and trainer subtype (supporter/item/tool/stadium). |
| F6.3   | Expanded format validation           | High     | Custom validator: all cards must be from Black & White (BLW) series onward, not on the banned list, 60 cards total, max 4 copies of any card (except basic energy). |
| F6.4   | Display card images                  | Medium   | Show card images on hover in the deck detail view (fetched from TCGdex, cached client-side). |
| F6.5   | Banned card list management          | Medium   | Admin-managed list of banned cards for Expanded format. Each entry: card name, set code, card number, ban date, optional announcement URL. Existing deck versions are **NOT retroactively invalidated** — a **warning badge** is shown on affected decks. New imports (F2.2, F2.8) are validated against the current banned list. Admin CRUD accessible from F7 area. |
| F6.6   | Visual deck list (card mosaic)       | Low      | Alternative view for the deck detail page: display the deck as a mosaic grid of card images instead of the tabular list. Cards are grouped in the same order as the table view (Pokemon, Trainer, Energy) with section headers. Each card tile shows the card image (from TCGdex) with a quantity badge overlay (top-left corner). Toggled via a view-mode switch on the deck detail page. Falls back to the table view for cards without an image. |

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
| Event details changed (F3.9)     | All engaged users (interested + registered)        | Email + in-app |
| Event cancelled (F3.10)          | All engaged users + deck owners with borrows       | Email + in-app |
| Event reminder (1 day before)    | Registered participants with active borrows        | Email          |
| Invited to event (F3.13)         | Invited user                                       | Email + in-app |
| Event approaching (3 days before)| Interested users (not yet registered)              | In-app         |

## F9 — Localization & Internationalization

| ID     | Feature                              | Priority | Description |
|--------|--------------------------------------|----------|-------------|
| F9.1   | User language preference             | Medium   | Each user has a `preferredLocale` (ISO 639-1, default `en`). Applied server-side via a Symfony locale listener and client-side via a React context. All translatable strings use the active locale. See [User model](models/user.md). |
| F9.2   | User timezone                        | Medium   | Each user has a `timezone` (IANA string, default `UTC`). All UI datetimes are converted to the user's timezone for display. See [User model](models/user.md). |
| F9.3   | Application translation              | Medium   | Symfony Translation component (YAML catalogues) for backend strings and `react-i18next` (JSON catalogues) for frontend strings. Initial languages: `en`, `fr`. Dot-notation keys (e.g. `app.deck.status.available`). All user-facing strings wrapped in translation calls (`trans()` / `t()`). |
| F9.4   | UTC datetime storage                 | High     | All database datetimes stored in **UTC**. Event dates are displayed in the event's `timezone` field (see [Event model](models/event.md)). When the user's timezone differs from the event's timezone, a user-relative hint is shown — e.g. "10:00 CET (16:00 your time)". Borrow and notification timestamps displayed in the user's timezone (F9.2). |

## F10 — Global UX Concerns

| ID      | Feature                              | Priority | Description |
|---------|--------------------------------------|----------|-------------|
| F10.1   | Mobile UX review                     | Medium   | The current desktop-first UI needs a thorough mobile responsiveness pass. Key areas: (1) **Card image overlay** (F6.4): hover-based preview does not work on touch devices — consider tap-to-preview, a bottom sheet, or a modal with swipe-to-dismiss; (2) **Deck detail tables**: two-column layout may be too narrow on small screens — consider stacking columns vertically on mobile; (3) **Dashboard and event listing**: ensure cards, buttons, and forms are touch-friendly with adequate tap targets (min 44×44 px). A mobile-first CSS review should be planned before the first production release. |
| F10.2   | Anonymous homepage                   | Medium   | The homepage for unauthenticated (anonymous) visitors. Three content sections with CTAs: **(1) Upcoming events** — shows the next few public events (visibility = `public` or in a public series) with date, location, and tournament structure. CTA: **"Browse the full agenda"** → links to the public event calendar / event discovery page (F3.15), and a secondary link to subscribe to the public iCal feed (F3.16). **(2) Top decks** — displays the **10 most recently played decks** from public events, ordered by **best tournament result** (placement ascending) then by **`Deck.updatedAt` descending**. Each entry shows deck name, archetype, the event it was played at, the player's placement, and the player name as "FirstName L." (see F3.17 privacy rule). CTA: **"Search the library"** → links to the full deck catalog (F2.4). **(3) Recent tournament results** — latest completed public events with a summary (winner's archetype, number of participants). CTA: **"See all results"** → links to the tournament results listing. Each event links to its full results page (F3.17). |

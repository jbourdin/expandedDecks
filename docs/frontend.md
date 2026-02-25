# Frontend Architecture

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](docs.md) | [Features](features.md)

---

## UI Library — Mantine 7

**Choice:** [Mantine 7](https://mantine.dev)

### Rationale

| Criterion                | Mantine advantage                                                                 |
|--------------------------|-----------------------------------------------------------------------------------|
| Application shell        | Built-in `AppShell` with responsive navbar, header, and content layout            |
| Notifications            | `@mantine/notifications` provides a ready-made notification system (F8.4)         |
| Data tables              | Rich table primitives for deck catalog (F2.4) and event listing (F3.2)            |
| Form handling            | `@mantine/form` with validation, error handling, and controlled inputs            |
| Modals & overlays        | Confirmation dialogs, borrow approval flows, deck detail popups                   |
| TypeScript-first         | Full type safety out of the box — no `@types/*` packages needed                   |
| Webpack Encore           | Works with the existing Symfony UX / Webpack Encore build pipeline                |

### Packages

| Package                    | Purpose                              |
|----------------------------|--------------------------------------|
| `@mantine/core`            | Core components and `AppShell`       |
| `@mantine/hooks`           | Utility hooks (media queries, etc.)  |
| `@mantine/form`            | Form state and validation            |
| `@mantine/notifications`   | Toast / notification system          |
| `@mantine/dates`           | Date pickers and calendars           |
| `@tabler/icons-react`      | Icon set (peer dependency)           |

### CSS

Import `@mantine/core/styles.css` in the application entrypoint. PostCSS is not required for basic usage.

---

## Application Shell — `AppShell`

The application uses Mantine's `AppShell` component with a collapsible sidebar navbar and a top header bar.

### Header

| Position | Content                                                                |
|----------|------------------------------------------------------------------------|
| Left     | Application name / logo                                                |
| Right    | Scanner shortcut · Notification bell with unread badge (F8.4) · User avatar / dropdown menu |

### Navbar — Sections by Role

The sidebar navbar displays links based on the authenticated user's roles and data.

#### All authenticated users

| Label          | Feature ref | Description                          |
|----------------|-------------|--------------------------------------|
| My Decks       | F2          | User's owned decks                   |
| Deck Catalog   | F2.4        | Browse and search all decks          |
| Events         | F3          | Upcoming and past events             |
| My Borrows     | F4          | Active and past borrows              |

#### Deck owners (user owns at least one deck)

| Label          | Feature ref | Description                          |
|----------------|-------------|--------------------------------------|
| Borrow Inbox   | F4.10       | Pending requests with badge count    |

#### Organizers

| Label          | Feature ref | Description                          |
|----------------|-------------|--------------------------------------|
| Create Event   | F3.1        | Shortcut to event creation           |
| My Events      | F3          | Events organized by the user         |

#### Admins

| Label          | Feature ref | Description                          |
|----------------|-------------|--------------------------------------|
| Dashboard      | F7.1        | Admin overview and statistics        |
| Users          | F7.2        | User management                      |
| Archetypes     | F2.6        | Deck archetype CRUD                  |
| Printers       | F5.5        | PrintNode printer configuration      |

### Responsive Behavior

- On mobile viewports the navbar collapses to icon-only mode.
- Active link is highlighted based on the current route.

---

## Homepage — Role-Aware Dashboard

The homepage (`/`) is a dynamic dashboard. Widgets are shown or hidden based on the user's role and data context.

### Widget Layout (top to bottom)

#### 1. Action Needed (conditional)

Shown only when pending items exist. Surfaces urgent actions:

| Item                                    | Audience         | Action                          |
|-----------------------------------------|------------------|---------------------------------|
| Pending borrow requests to review       | Deck owner       | Links to Borrow Inbox (F4.10)  |
| Overdue decks                           | Owner / borrower | Links to deck detail            |
| Unanswered borrow requests              | Borrower         | Informational — no action       |

#### 2. Upcoming Events (conditional)

Shown only when the user is registered to at least one future event. Displays the next 3 events:

- Event name, date, location
- Number of decks lent / borrowed for that event
- Each card links to the event detail (F3.3)

#### 3. My Decks (conditional)

Shown only when the user owns decks. Horizontal card grid (max 6 cards, "View all" link).

Each card displays:
- Deck name
- Archetype
- Status badge: available / lent / reserved / retired (F2.5)
- Links to deck detail (F2.3)

#### 4. Recent Notifications (always shown)

Last 5 notifications with timestamps. Each links to the relevant entity (borrow, event, deck). "View all" links to the notification center (F8.4).

#### 5. My Upcoming Events — organizer extra (conditional)

Appended when the user has the organizer role. Shows events the user organizes with:
- Participant count
- Pending borrow request count

#### 6. Quick Stats — admin extra (conditional)

Appended when the user has the admin role. Mirrors the admin dashboard (F7.1):
- Total decks
- Active borrows
- Upcoming events
- Overdue returns

---

## Scanner Availability

The application supports two complementary methods for scanning deck labels:

### USB HID Scanner (passive)

A global keyboard listener detects USB HID barcode scanner input from any page (F5.3). When a scan is detected, the application performs a deck lookup and navigates to the deck detail or triggers a lend/return action depending on context.

No dedicated scan button is required — the scanner fires keystrokes that the detection algorithm picks up automatically. See [Scanner Detection](technicalities/scanner.md) for implementation details.

### Camera QR Scanner (on-demand)

On smartphones and tablets where no USB HID scanner is available, a **scan button** in the `AppShell` header opens a camera-based QR scanner modal (F5.6). The device's rear camera decodes QR codes and Code128 barcodes from deck labels using the `html5-qrcode` library.

The modal is fullscreen on mobile viewports for easier aiming. The camera auto-stops after 60 seconds of inactivity to save battery. See [Camera QR Scanner](technicalities/camera_scanner.md) for implementation details.

### Unified Behavior

Both methods feed into the same `onScan(deckId)` callback. The `useDeckScanner` hook combines HID detection (always active) with camera scanning (on-demand), ensuring consistent downstream behavior regardless of the input method.

---

## Label Printing

The application supports two methods for producing physical deck labels, each targeting a different context.

### ZPL Label (Venue Printing)

At event venues equipped with a Zebra thermal printer, labels are generated as ZPL code and sent to the printer via the PrintNode cloud API (F5.1, F5.2). This is the primary labelling method — fast, adhesive labels printed on-site. See [PrintNode printer management](features.md) (F5.5) for configuration.

### PDF Label Card (Home Printing)

For pre-event preparation at home where no Zebra printer is available, deck owners can generate a **downloadable PDF** containing a TCG card-sized label (63.5 × 88.9 mm). The label is printed on any home inkjet or laser printer, cut out along crop marks, and slipped into a card sleeve at the front of the deck box (F5.7).

The QR code on the PDF label uses the same deck identifier encoding as the ZPL label, so it is fully compatible with both the USB HID scanner (F5.3) and the camera QR scanner (F5.6).

See [PDF Label Card](technicalities/pdf_label.md) for full technical details.

### Deck Detail Action — Print Label

| Attribute   | Value                                                                 |
|-------------|-----------------------------------------------------------------------|
| Button      | "Print label" with `IconPrinter`                                      |
| Visibility  | Deck owner only                                                       |
| Placement   | Deck detail action bar (alongside edit, retire, etc.)                 |
| Behavior    | Opens the PDF (`/deck/{id}/label.pdf`) in a new tab for browser print dialog |

---

## Localization & Internationalization

> **@see** docs/features.md F9 — Localization & Internationalization

### Locale Switcher

A `Select` or `SegmentedControl` component in the user profile/settings page allows the user to choose their preferred UI language. The selection is persisted to `User.preferredLocale` (F9.1).

For **unauthenticated pages** (login, registration), the locale falls back to the browser's `Accept-Language` header.

### Translation Infrastructure

| Layer    | Library                  | Catalogue format | Locale source |
|----------|--------------------------|------------------|---------------|
| Backend  | Symfony Translation      | YAML (`.yaml`)   | `User.preferredLocale` via locale listener, or `Accept-Language` fallback |
| Frontend | `react-i18next`          | JSON (`.json`)   | `data-locale` attribute on the root HTML element, set by Twig |

- Translation keys use **dot notation** (e.g. `app.deck.status.available`) matching the project convention
- Catalogue files live in `translations/` (Symfony) and `assets/translations/` (React)
- Initial languages: **en** (default), **fr**

### Datetime Display

| Context                   | Timezone used            | Format helper                    | Example |
|---------------------------|--------------------------|----------------------------------|---------|
| Event dates               | Event's `timezone`       | `Intl.DateTimeFormat` / `date-fns-tz` | "Sat 15 Mar 2026, 10:00 CET" |
| Event dates (user hint)   | User's `timezone` (F9.2) | Same, appended                   | "(16:00 your time)" |
| Borrow timestamps         | User's `timezone`        | `Intl.DateTimeFormat`            | "Requested 2 Mar 2026, 14:30" |
| Notification timestamps   | User's `timezone`        | Relative or absolute             | "3 hours ago" / "2 Mar, 09:00" |

- Mantine date components (`@mantine/dates`) are configured with the active locale for calendar labels and day names
- The `data-timezone` attribute on the root HTML element carries the user's timezone to React

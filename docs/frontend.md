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

A global keyboard listener detects USB HID barcode scanner input from any page (F5.3). When a scan is detected, the application performs a deck lookup and navigates to the deck detail or triggers a lend/return action depending on context.

No dedicated scan button is required — the scanner fires keystrokes that the detection algorithm picks up automatically. See [Scanner Detection](technicalities/scanner.md) for implementation details.

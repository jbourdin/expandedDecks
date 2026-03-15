# Expanded Decks is live — beta release

We're excited to announce that Expanded Decks is now available online in its first public beta. After months of development, the application is deployed and ready for early adopters to try out.

This is a beta release, which means things are still actively being developed, tested, and improved. We're putting it in your hands now because the core features are solid enough to be useful — but expect rough edges, ongoing changes, and the occasional bug.

## What's available today

The beta ships with a full set of features covering the three main pillars of the application: deck management, event coordination, and the borrow workflow.

### Deck library

You can register your decks, import deck lists by pasting the standard PTCG text format, and browse the community catalog. Card data is automatically enriched with images from TCGdex. Each deck is tied to an archetype — browse the archetype catalog to discover decks by playstyle, or filter the deck list by archetype directly.

Deck lists are versioned: when you update a deck, the previous version is preserved and you can compare them side by side. Decks can be retired when they're no longer in active rotation, and reactivated later.

### Events

Create events, set dates and locations, manage staff roles, and track which players are engaged. The event system integrates tightly with the borrow workflow — players can request decks specifically for an event, and if an event is cancelled, related borrows are handled automatically.

Event results can be published for the community to see.

### Borrow workflow

The full lending and borrowing lifecycle is in place: request a deck, get notified when the owner responds, confirm the hand-off, and track the return. Competing requests for the same deck are resolved automatically. The dashboard shows you at a glance what you need to act on — pending requests, borrows to return, events coming up.

### Notifications and email

In-app notifications keep you informed about borrow requests, approvals, and event updates. Transactional emails are sent for critical actions like password resets and account verification.

### CMS pages

Organizers and editors can publish content pages with a simple CMS — useful for rules, guidelines, or community announcements. Pages are organized into menu categories and support both English and French.

### Internationalization

The entire application is available in English and French. User preferences are respected for both the UI language and email communications.

## What's coming next

The beta covers the essential workflows, but there's more on the roadmap. Here's a glimpse of what we're working toward:

- **PDF labels and camera scanning** — generate printable labels with QR codes for your deck boxes, and scan them with your phone camera to quickly identify decks at events.
- **Zebra label printing** — for communities that want professional thermal labels, with USB barcode scanner support for high-throughput check-in.
- **Bookmarks and overdue tracking** — bookmark your favorite decks, events, and archetypes. Get reminders when a borrowed deck is overdue.
- **iCal feeds** — sync events to your calendar app.
- **Visual deck lists** — a card mosaic view as an alternative to the text-based list.
- **Play! Pokemon QR integration** — scan player QR codes for quick identification.
- **Multi-factor authentication** — TOTP-based MFA for accounts that want extra security.

## Work in progress

This is a living project. Features are being added, existing ones are being refined, and we're actively testing in real conditions. If you encounter a bug, have a suggestion, or want to contribute, the project is open-source on GitHub:

[github.com/jbourdin/expandedDecks](https://github.com/jbourdin/expandedDecks)

Your feedback during this beta phase is invaluable — it shapes what gets prioritized and how the tool evolves. Thanks for being part of this.

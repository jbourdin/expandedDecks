# Expanded Decks

## A shared deck library for the Pokemon TCG community

Expanded Decks is a web application built for Pokemon TCG players and organizers who want to keep the Expanded format accessible and thriving. It serves as a shared library of physical decks — a place where community members can register their decks, browse what's available, and coordinate loans for tournaments and casual play.

The philosophy behind the project is straightforward: building a competitive Expanded collection is expensive and time-consuming. Many players want to try the format, revisit classic archetypes, or simply play something different for a day — but they don't have the cards. Meanwhile, experienced collectors often have decks sitting in a box between events. Expanded Decks bridges that gap by giving communities a transparent, organized way to share their collections.

The project is fully open-source and available on GitHub: [github.com/jbourdin/expandedDecks](https://github.com/jbourdin/expandedDecks)

## For players

The deck catalog is the heart of the application. Every registered deck has a full, versioned deck list imported from the standard PTCG text format — the same format you'd paste into PTCGO or Live. Card data is automatically enriched with images and metadata from TCGdex, so you can visually browse what each deck contains before deciding to borrow it.

When an event is coming up, you can browse available decks by archetype, check what's not already spoken for, and submit a borrow request. The deck owner receives a notification and can approve or decline. Once both sides confirm the hand-off, the system tracks the loan until the deck is returned. If multiple people request the same deck for the same event, conflicting requests are handled automatically — no awkward back-and-forth needed.

Deck owners can register as many decks as they like, update their lists when they make changes, and keep a full version history. If you swap out a tech card or rebuild a deck for a new metagame, the previous versions are still visible for reference.

## For organizers

Running an Expanded event means making sure players have decks to play with. Expanded Decks gives organizers a clear view of which decks are registered in the community library, which ones are available for a given event, and which ones are already committed to other players.

You can create events, invite staff, and manage engagements. The borrow system integrates with the event calendar, so when an event is cancelled, outstanding borrows are automatically handled. Staff members can be assigned roles and permissions to help manage the logistics on the day.

The application also supports label printing for physical deck boxes — either via home-printable PDF labels or professional Zebra thermal printers — so each deck can be quickly identified and scanned at check-in.

## Built for the community

Expanded Decks is available in English and French, with full translation support for adding more languages. It runs as a modern web application accessible from any device — phone, tablet, or desktop. There's no app to install; just open the site and start browsing.

The project welcomes contributions from developers, translators, and anyone with ideas for making Expanded more accessible. Check out the repository, open an issue, or just come say hello.

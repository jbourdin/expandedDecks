# French Translation Guide

> **Audience:** Developer, Translator, AI Agent · **Scope:** Reference — French copy conventions and glossary

← Back to [Main Documentation](../docs.md) | [CLAUDE.md](../../CLAUDE.md)

## Overview

This document records the deliberate choices behind the French translations in
`translations/messages.fr.xlf`. The goal is a tone that feels natural to French
Pokémon TCG players — the people who meet at league nights and tournaments —
rather than institutional product copy. When adding or editing French strings,
follow these rules; when a rule feels wrong for a specific string, raise it
here rather than silently diverging.

## Voice and tone

### Tutoiement everywhere

The entire interface, including transactional emails, uses **tu**. The app is a
community tool between players; *vous* reads as corporate distance in this
context. This includes serious flows (GDPR account deletion, security warnings)
— the tone stays informal but the content stays precise.

```text
✅ Abonne-toi à cette URL dans ton agenda.
✅ Ton compte a été créé. Vérifie ta boîte mail pour confirmer ton adresse.
❌ Veuillez vérifier votre boîte de réception.
```

### No « Veuillez »

Never use *Veuillez + infinitif*. Use the direct second-person-singular
imperative instead. « Merci de… » is acceptable when softening a request that
asks for real-world effort (e.g. returning a physical deck), but plain
imperative is the default.

```text
✅ Réessaie. / Saisis d'abord un identifiant de tournoi.
✅ Merci de rendre tes decks empruntés au propriétaire ou à la table du staff.
❌ Veuillez réessayer.
```

### Confirmation prompts: direct question

Confirmation dialogs drop the « Êtes-vous sûr de vouloir… » frame entirely.
State the action as a question, then the consequence.

```text
✅ Supprimer ce deck ? Il sera masqué du catalogue.
✅ Annuler cet événement ? Cette action est irréversible.
❌ Êtes-vous sûr de vouloir supprimer ce deck ?
```

## Inclusive writing

Convention: **point médian** (`·`) on every word that refers to people —
singular and plural, badges, headers, flashes, generic groups. The Pokémon
community is diverse and the copy should reflect it; masculine generics and
parenthesised feminines (which frame the feminine as optional) are both out.

```text
✅ Tu es maintenant inscrit·e comme joueur·euse.
✅ Organisateur·rice · Spectateur·rice · Invité·e · Intéressé·e
✅ Seul·es les dresseur·euses invité·es peuvent s'inscrire en tant que joueur·euses.
✅ %name% a été ajouté·e à l'équipe de staff.
❌ les joueurs inscrits (masculine generic)
❌ inscrit(e), joueur(se) (parentheses)
```

Form rules:

- **Single final suffix on plurals**: « invité·es », « joueur·euses »,
  « utilisateur·rices » — not the doubled form « invité·e·s ».
- **Determiners and adjectives follow**: « Seul·es », « tou·tes », « un·e ».
- **Prefer an epicene rephrase when a pronoun follows** — pronoun chains
  (« il/elle devra ») read worse than a rewrite:
  « Choisis la personne qui reprendra l'organisation. Elle devra accepter… »,
  « Aucun compte trouvé pour ce nom. », « Anonymiser ce compte ? ».

Exceptions (deliberate):

- **Official card terms** stay as printed: « Dresseur » (card type),
  « Supporter », « HIGH TECH ».
- **Official tournament-document reproductions**: the decklist PDF mirrors the
  official sheet (« Nom du joueur », « ID joueur » fields).
- **Technical identifier names**: « ID joueur » (the Player ID field name),
  « Nom d'utilisateur Discord » (Discord's own field name).

## Glossary — game and app vocabulary

French TCG vernacular keeps many English borrowings, settled as masculine
nouns. Do **not** translate these; players use the English terms.

| English | French | Notes |
|---------|--------|-------|
| deck | **le deck** | Never « paquet » / « jeu de cartes ». |
| decklist | **la decklist** | Feminine. « liste » alone is fine in running prose once context is set. |
| staple | **le staple** / **staple cards** | Page titles keep « Staple cards ». |
| set | **le set** | « code de set » for the PTCG set code (`LOR-093`). « extension » is the synonym used in longer prose for the product itself (« Extension la plus récente »). Both are legitimate; codes are always « set ». |
| printing (of a card) | **la version** | « les versions les moins rares ». Not « édition » (evokes 1st edition), not « impression ». |
| top cut | **le top cut** | |
| round | **la ronde** | Established French tournament term (not « manche », not « round »). |
| Expanded (format) | **Expanded** | UI copy says « format Expanded » — that's what players say. The official translation « Étendu » is kept **only** in the SEO/JSON-LD strings (`app.seo.*`) to catch official-wording searches. See open questions. |
| Standard (format) | **Standard** | |
| archetype | **l'archétype** | Proper French word, used by players. |
| tag | **le tag** / **tagué** | French spelling of the verb: « tagué », not « taggé ». |
| mulligan, proxy, sleeve… | borrowed as-is | If they ever appear, keep them in English. |
| Player ID | **ID joueur** | One term everywhere (forms, placeholders, PDF labels). Not « ID Pokemon » / « Pokemon ID ». |

### Official card-type terms (TCG French localisation)

These follow the official French card wording — never improvise:

| English | French |
|---------|--------|
| Trainer | **Dresseur** |
| Item | **Objet** |
| Tool | **Outil** |
| Stadium | **Stade** |
| Supporter | **Supporter** |
| Technical Machine | **Machine Technique** |
| Energy | **Énergie** |
| ACE SPEC | **HIGH TECH** — yes, the official French localisation replaced one English term with another; it's what the cards print. Never « ACE SPEC » or « Carte AS » in French copy. |

Card **names** are proper nouns served from the database (`nameEn`/`nameFr`)
and are never translated by hand (see project memory / Cardmarket export doc).

### Borrow-lifecycle vocabulary

The physical lending workflow has a fixed French vocabulary:

| Concept | French | Notes |
|---------|--------|-------|
| borrow (noun/verb) | **l'emprunt / emprunter** | |
| lend | **le prêt / prêter** | « Prêt direct » = walk-up lend. |
| hand off | **remettre / la remise** | Owner→borrower or owner→staff hand-over. |
| return | **rendre / le retour** | Past participle is **rendu**, never « retourné » (anglicism — *retourner* means going back somewhere). The noun « retour » is correct. |
| overdue | **en retard** | Avoid « restitution » (legalese). |
| custody | **la garde** | « Accepter la garde des decks ». In player-facing copy, prefer the concrete place: « à la table du staff ». Never keep « custody » in French text. |

### App-specific nouns

| Term | French | Notes |
|------|--------|-------|
| label (physical, printed) | **l'étiquette** | Reserved for the Zebra/PDF deck-box labels. UI grouping tags are « tags ». |
| screen name | **le pseudo** | |
| email | **l'e-mail** | With hyphen, everywhere. « boîte mail » is fine for the inbox. |
| in-app (notification channel) | **Dans l'app** | |
| event | **l'événement** | Accent on the first é. Never the borrowed « event » in user-facing copy. |
| library | **la bibliothèque** | The shared deck pool. |
| mosaic | **la mosaïque** | Server-generated card-grid image. |
| minified list | **la liste simplifiée** | One term — not « minifiée ». |

## Spelling and typography

- **Pokémon** always takes the accent in French text, including in proper
  nouns where the accented form is official: « The Pokémon Company »,
  « Pokémon Organized Play », « PokéAPI ». Unaccented `Pokemon` may only appear
  inside technical identifiers (class names, slugs, URLs).
- **Ellipsis**: the single character `…`, never three dots `...`.
- **Examples**: abbreviate as « ex. » (not « ex : », not « par ex. »).
- **No Title Case**: French capitalises only the first word of a label
  (« Catalogue de decks », « Nouvel événement », « Mes decks »).
- **Apostrophes**: straight `'` (or `&apos;` in XLIFF), consistently with the
  existing file. Do not introduce typographic `’` piecemeal.
- **Punctuation spacing**: a plain space before `: ; ! ?` and inside « … »,
  matching standard French typography. We deliberately use a *breaking* space
  (see open questions).
- **Ranges**: en dash `–` (« 1–10 »).

## Pluralisation

- User-facing counts use Symfony interval/plural syntax:
  `{0}%count% decks|{1}%count% deck|]1,Inf[%count% decks`.
  **No space after the interval marker** — `{1} %count%…` renders a leading
  space (this bug existed in both locales and was fixed).
- Admin/technical flashes may use the compact `(s)` convention
  (« %count% version(s) de deck ») — these are operator-facing and the
  precision-to-noise tradeoff is fine there.
- Durations in emails use `(s)`: « %hours% heure(s) ».

## Register by surface

| Surface | Register |
|---------|----------|
| Public pages, dashboards, flashes | Player-casual: tutoiement, jargon allowed, contractions natural. |
| Transactional emails | Same tutoiement; keep one greeting style (« Bonjour %name%, »). |
| GDPR / security copy | Tutoiement, but complete and unambiguous sentences — no cuteness. |
| Error pages (4xx/5xx) | Pokémon-flavoured humour is the point (« Un bug sauvage est apparu ! ») — keep accents correct. |
| Admin technical dashboard | Developer jargon tolerated (backfill, job, cache, slug, CardPrinting) — these screens are for operators. |

## Open questions (for future discussion)

1. **Non-breaking spaces before `: ; ! ?`** — proper French typography wants
   U+00A0/U+202F. We currently use plain spaces everywhere: invisible
   characters are a maintenance hazard in hand-edited XLIFF, and some render
   targets (ZPL label fonts, PDF generation) handle them inconsistently. If
   orphan punctuation at line-wraps becomes visible in practice, revisit —
   possibly via a Twig output filter rather than in the source strings.
2. **« Expanded » vs « Étendu »** — UI says Expanded (player vernacular); the
   two `app.seo.*` JSON-LD strings keep « Étendu » (official wording, distinct
   search audience). If that split proves confusing, collapse to one.
3. **Email greeting** — « Bonjour %name%, » is neutral-friendly. « Salut » was
   considered and rejected as too casual for transactional mail, but it's a
   one-line change if the community voice evolves.

# Documentation

> **Audience:** Developer, AI Agent · **Scope:** Reference

← Back to [README](../README.md)

---

## Project

- [Philosophy](philosophy.md) — Mission, values, and relationship to official services
- [Production Installation](installation.md) — Docker image, environment variables, workers, health checks

## Features

- [Feature List](features.md) — Full feature catalogue with priorities and status
- [Implementation Roadmap](roadmap.md) — Remaining features organized by logical phase
- [Changelog](changelog.md) — Release history with implemented features per version

## Frontend

- [Frontend Architecture](frontend.md) — UI library (Mantine), application shell, homepage layout

## Data Models

- [User Model](models/user.md) — Player accounts, roles, authentication
- [Deck Model](models/deck.md) — Deck ownership, card lists, labels
- [Event Model](models/event.md) — Tournaments, leagues, event lifecycle
- [Borrow Model](models/borrow.md) — Deck lending and return workflow
- [Notification Model](models/notification.md) — In-app notification system
- [CMS Model](models/cms.md) — Content pages, menu categories, translations

## API

- [API Access](api.md) — REST API specification, OAuth2, MCP server

## Standards

- [Coding Standards](standards/coding.md) — PSR-12, Symfony ruleset, PHPStan
- [Naming Conventions](standards/naming.md) — Classes, routes, templates, translations
- [Version Control](standards/version_control.md) — Gitflow, commits, PRs
- [Documentation Standards](standards/documentation.md) — File structure, headers, linking
- [File Headers](standards/file_headers.md) — Copyright & license blocks
- [Release Process](standards/release_process.md) — Release workflow, tagging, and GitHub releases

## Technical Deep-Dives

- [Barcode Scanner Detection](technicalities/scanner.md) — USB HID scanner integration
- [Camera QR Scanner](technicalities/camera_scanner.md) — Mobile camera fallback for QR code scanning
- [PDF Label Card](technicalities/pdf_label.md) — Home-printable TCG card-sized deck label (Dompdf)
- [Mobile UX Audit](technicalities/mobile_audit.md) — Mobile responsiveness issues and fix plan (F10.1)
- [Localization](technicalities/localization.md) — Locale detection, timezone handling, translation infrastructure
- [Deck Mosaic](technicalities/mosaic.md) — Server-generated card mosaic image (GD, Flysystem, S3)
- [Card Enrichment](technicalities/enrichment.md) — TCGdex enrichment pipeline, card identity model, minified export
- [Cardmarket Export](technicalities/cardmarket_export.md) — Cardmarket wishlist text format, name overrides, ability/attack handling
- [Basic Energy Images](technicalities/basic_energy_images.md) — Image sources for basic energy cards (MEE, SVE, NRG, all eras)
- [TCGdex Known Issues](technicalities/tcgdex_known_issues.md) — Data quality issues and workarounds
- [Error Pages](technicalities/error_pages.md) — Custom error pages, Pokemon sprites, CDN integration, Sentry

## Planning

- [Initial Plan](initial_plan.md) — Original project skeleton plan
- [Archetype Features Plan](plans/archetype_features.md) — Archetype ecosystem design and implementation plan
- [Doctrine Entities Plan](plans/doctrine_entities.md) — Entity modeling decisions and schema design

## Other

- [Credits & References](credits.md) — Libraries, APIs, acknowledgements

# API Access

> **Audience:** Developer, AI Agent · **Scope:** Features, Architecture

<- Back to [Main Documentation](docs.md) | [Feature List](features.md)

This document specifies the public REST API for Expanded Decks. The API enables third-party integrations, mobile clients, and AI-powered tools (via MCP) to interact with events and CMS content programmatically.

---

## Overview

The API is organized into three tiers by priority:

| Tier | Scope | Description |
|------|-------|-------------|
| **Core** | Events | Full CRUD on events, engagement management (register, spectate, express interest), attendee listing |
| **Secondary** | CMS | Create and edit pages and menu categories through the API |
| **Bonus** | MCP | Model Context Protocol server exposing event and CMS tools for AI agents |

All tiers depend on an **OAuth2 prerequisite** — an authorization server with token management and a connected-apps UI.

---

## F16 — API Access

### F16.1 — OAuth2 Authorization Server (prerequisite)

| Aspect | Detail |
|--------|--------|
| **Priority** | High |
| **Depends on** | F1.1, F1.4 |

Implement an OAuth2 authorization server using the **Authorization Code** grant with PKCE. This is the prerequisite for all API access.

**Scope model:**

| Scope | Grants access to |
|-------|-----------------|
| `event:read` | List and view events, attendees |
| `event:write` | Create, update, cancel, finish events |
| `event:engage` | Register, express interest, spectate |
| `cms:read` | List and view pages, menu categories |
| `cms:write` | Create, update, publish/unpublish pages and categories |

**Authorization flow:**

1. Third-party app redirects user to `/oauth/authorize` with `client_id`, `redirect_uri`, `scope`, `state`, and PKCE `code_challenge`.
2. User authenticates (if not already logged in) and reviews requested scopes on a **consent screen**.
3. On approval, the server redirects back with an authorization `code`.
4. The app exchanges the code for an `access_token` (short-lived, 1 hour) and a `refresh_token` (long-lived, 30 days) via `POST /oauth/token`.
5. Refresh tokens can be rotated via the same endpoint with `grant_type=refresh_token`.

**Token storage:** Access tokens are JWT (stateless validation). Refresh tokens are stored in the database with `expiresAt`, `revokedAt`, and the associated `client_id` and `user_id`.

**Entities:**

- `OAuthClient` — registered third-party application (`clientId`, `clientSecret` hash, `redirectUris[]`, `allowedScopes[]`, `name`, `description`, `logoUrl`)
- `OAuthAccessToken` — JWT claims reference (for revocation list, optional)
- `OAuthRefreshToken` — persisted refresh token (`token` hash, `user`, `client`, `scopes[]`, `expiresAt`, `revokedAt`)
- `OAuthAuthorizationCode` — short-lived code (`code` hash, `user`, `client`, `scopes[]`, `redirectUri`, `codeChallenge`, `codeChallengeMethod`, `expiresAt`)

**Admin management:** Admins can create and manage OAuth clients via the admin panel (client name, redirect URIs, allowed scopes). Client secrets are shown once on creation and stored hashed.

---

### F16.2 — Connected Apps Management

| Aspect | Detail |
|--------|--------|
| **Priority** | High |
| **Depends on** | F16.1 |

A user-facing screen under the profile section where users can view and revoke third-party app access.

**UI:**

- List of apps the user has authorized, showing: app name, logo, granted scopes, date authorized, last used date.
- **Revoke** button per app — immediately invalidates all refresh tokens and active access tokens for that app+user pair.
- Confirmation modal before revocation: "This will disconnect {app name}. The app will no longer be able to access your account."

**Route:** `GET /profile/connected-apps` (authenticated, `ROLE_PLAYER` minimum).

---

### F16.3 — Event CRUD API

| Aspect | Detail |
|--------|--------|
| **Priority** | High |
| **Depends on** | F16.1, F3.1 |
| **Scopes** | `event:read`, `event:write` |

RESTful endpoints for event management. All responses use JSON. Dates are ISO 8601 with timezone offset.

**Endpoints:**

| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| `GET` | `/api/v1/events` | `event:read` | List events (paginated, filterable) |
| `GET` | `/api/v1/events/{event}` | `event:read` | Get event details |
| `POST` | `/api/v1/events` | `event:write` | Create an event |
| `PUT` | `/api/v1/events/{event}` | `event:write` | Update an event |
| `DELETE` | `/api/v1/events/{event}` | `event:write` | Cancel an event (soft-delete) |

**List filters** (`GET /api/v1/events`):

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | `upcoming`, `past`, `cancelled` |
| `visibility` | string | `public`, `draft`, `private` |
| `from` | ISO date | Events starting on or after this date |
| `to` | ISO date | Events starting on or before this date |
| `page` | int | Page number (default: 1) |
| `limit` | int | Items per page (default: 20, max: 100) |

**Authorization rules:**

- `event:read` — public events are visible to all authenticated users; draft/private events require the user to be the organizer or staff.
- `event:write` — only users with `ROLE_ORGANIZER` or `ROLE_ADMIN` can create events. Only the event organizer (or admin) can update/cancel.

**Create/Update payload:**

```json
{
  "name": "League Challenge March",
  "description": "Monthly league challenge at the local store.",
  "startDate": "2026-04-15T10:00:00+02:00",
  "endDate": "2026-04-15T18:00:00+02:00",
  "timezone": "Europe/Paris",
  "location": "Game Store Paris",
  "format": "expanded",
  "tournamentStructure": "swiss",
  "visibility": "public",
  "maxPlayers": 32
}
```

**Response format** (single event):

```json
{
  "id": 42,
  "name": "League Challenge March",
  "organizer": { "id": 1, "screenName": "JohnDoe" },
  "startDate": "2026-04-15T10:00:00+02:00",
  "endDate": "2026-04-15T18:00:00+02:00",
  "timezone": "Europe/Paris",
  "location": "Game Store Paris",
  "format": "expanded",
  "tournamentStructure": "swiss",
  "visibility": "public",
  "maxPlayers": 32,
  "engagementCounts": {
    "playing": 18,
    "spectating": 3,
    "interested": 7
  },
  "isCancelled": false,
  "isFinished": false,
  "createdAt": "2026-03-20T14:30:00+00:00",
  "updatedAt": "2026-03-22T09:15:00+00:00"
}
```

---

### F16.4 — Event Engagement API

| Aspect | Detail |
|--------|--------|
| **Priority** | High |
| **Depends on** | F16.1, F3.13 |
| **Scopes** | `event:engage`, `event:read` |

Endpoints for users to express interest, register as player or spectator, and withdraw from events.

**Endpoints:**

| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| `POST` | `/api/v1/events/{event}/engage` | `event:engage` | Set engagement state for self or another user |
| `DELETE` | `/api/v1/events/{event}/engage` | `event:engage` | Withdraw engagement for self or another user |
| `GET` | `/api/v1/events/{event}/engagement` | `event:read` | Get engagement for self or another user |

**Engage payload:**

```json
{
  "state": "registered_playing"
}
```

To engage **on behalf of another user** (requires `ROLE_ORGANIZER` or event staff), identify the target user with either field:

```json
{
  "state": "registered_playing",
  "userId": 7
}
```

```json
{
  "state": "registered_playing",
  "playerId": "12345678"
}
```

When neither `userId` nor `playerId` is provided, the action applies to the authenticated user (self-engagement).

**User resolution:** The `userId` and `playerId` fields are mutually exclusive. `userId` is the internal user ID; `playerId` is the Play! Pokemon player ID (see F1.1). If both are provided, the API returns `422 Unprocessable Entity`. If the identifier does not match any user, the API returns `404 Not Found`.

Valid states: `interested`, `registered_playing`, `registered_spectating`.

**State transition rules** (same as F3.13):

- Any authenticated user can express `interested`.
- `registered_playing` requires an open registration slot (`maxPlayers` not reached).
- `registered_spectating` is always available.
- An `invited` user can transition to any registration state.
- Withdrawal (`DELETE`) removes the engagement entirely, except for `invited` users who retain their invitation flag.

**Withdrawal for another user:** `DELETE /api/v1/events/{event}/engage?userId=7` or `DELETE /api/v1/events/{event}/engage?playerId=12345678`. Requires `ROLE_ORGANIZER` or event staff.

**Get engagement for another user:** `GET /api/v1/events/{event}/engagement?userId=7` or `GET /api/v1/events/{event}/engagement?playerId=12345678`.

**Response:** The updated engagement object:

```json
{
  "eventId": 42,
  "user": { "id": 7, "screenName": "AshK", "playerId": "12345678" },
  "state": "registered_playing",
  "invitedAt": null,
  "engagedAt": "2026-03-25T10:00:00+00:00"
}
```

---

### F16.5 — Event Attendees API

| Aspect | Detail |
|--------|--------|
| **Priority** | High |
| **Depends on** | F16.1, F3.13 |
| **Scopes** | `event:read` |

List attendees grouped by engagement type.

**Endpoints:**

| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| `GET` | `/api/v1/events/{event}/attendees` | `event:read` | List all attendees |

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `state` | string | Filter by state: `interested`, `registered_playing`, `registered_spectating`, `invited` |
| `userId` | int | Filter by internal user ID — returns the single attendee matching this ID (if engaged) |
| `playerId` | string | Filter by Play! Pokemon player ID — returns the single attendee matching this ID (if engaged) |
| `page` | int | Page number (default: 1) |
| `limit` | int | Items per page (default: 50, max: 200) |

`userId` and `playerId` are mutually exclusive. If both are provided, the API returns `422 Unprocessable Entity`. These filters can be combined with `state` to check if a specific user has a particular engagement state.

**Response:**

```json
{
  "eventId": 42,
  "attendees": [
    {
      "user": { "id": 7, "screenName": "AshK", "playerId": "12345678" },
      "state": "registered_playing",
      "invitedAt": null,
      "engagedAt": "2026-03-25T10:00:00+00:00"
    }
  ],
  "pagination": { "page": 1, "limit": 50, "total": 21 }
}
```

**Authorization:** Public events expose attendees to any authenticated user. Draft/private events require the user to be organizer, staff, or an attendee themselves.

---

### F16.6 — CMS Pages API

| Aspect | Detail |
|--------|--------|
| **Priority** | Medium |
| **Depends on** | F16.1, F11.1 |
| **Scopes** | `cms:read`, `cms:write` |

CRUD on CMS pages with translation support.

**Endpoints:**

| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| `GET` | `/api/v1/pages` | `cms:read` | List pages (paginated) |
| `GET` | `/api/v1/pages/{id}` | `cms:read` | Get page with translations |
| `POST` | `/api/v1/pages` | `cms:write` | Create a page |
| `PUT` | `/api/v1/pages/{id}` | `cms:write` | Update a page |
| `DELETE` | `/api/v1/pages/{id}` | `cms:write` | Delete a page |

**Authorization:** `cms:write` requires the user to have `ROLE_CMS_EDITOR` or `ROLE_ADMIN`. `cms:read` returns only published pages unless the user has editor/admin role.

**Create/Update payload:**

```json
{
  "slug": "march-tournament-recap",
  "isPublished": true,
  "noIndex": false,
  "categoryId": 3,
  "translations": {
    "en": {
      "title": "March Tournament Recap",
      "slug": "march-tournament-recap",
      "content": "## Great event!\n\nWe had 32 players...",
      "metaTitle": "March Tournament Recap - Expanded Decks",
      "metaDescription": "Recap of the March expanded format tournament."
    },
    "fr": {
      "title": "Bilan du tournoi de mars",
      "slug": "bilan-tournoi-mars",
      "content": "## Super tournoi !\n\nNous avons eu 32 joueurs...",
      "metaTitle": "Bilan du tournoi de mars - Expanded Decks",
      "metaDescription": "Bilan du tournoi de mars au format expanded."
    }
  }
}
```

---

### F16.7 — CMS Menu Categories API

| Aspect | Detail |
|--------|--------|
| **Priority** | Medium |
| **Depends on** | F16.1, F11.2 |
| **Scopes** | `cms:read`, `cms:write` |

CRUD on menu categories with translation support.

**Endpoints:**

| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| `GET` | `/api/v1/categories` | `cms:read` | List categories with translations |
| `POST` | `/api/v1/categories` | `cms:write` | Create a category |
| `PUT` | `/api/v1/categories/{id}` | `cms:write` | Update a category |
| `DELETE` | `/api/v1/categories/{id}` | `cms:write` | Delete a category (fails if pages attached) |

**Authorization:** Same as F16.6 — `cms:write` requires `ROLE_CMS_EDITOR` or `ROLE_ADMIN`.

---

### F16.8 — MCP Server for AI Integration

| Aspect | Detail |
|--------|--------|
| **Priority** | Low |
| **Depends on** | F16.1, F16.3, F16.4, F16.6 |

A [Model Context Protocol](https://modelcontextprotocol.io/) server that exposes Expanded Decks functionality as tools usable by AI agents (Claude, etc.).

**Transport:** Streamable HTTP (`/mcp`) — the server handles MCP JSON-RPC messages over HTTP, compatible with Claude Desktop and other MCP clients.

**Authentication:** OAuth2 bearer token (same as API). The MCP client configures credentials via the standard MCP OAuth flow, which maps to F16.1's authorization code grant.

**Exposed tools:**

| Tool | Maps to | Description |
|------|---------|-------------|
| `list_events` | F16.3 | List upcoming events with filters |
| `get_event` | F16.3 | Get full event details and engagement counts |
| `create_event` | F16.3 | Create a new event |
| `update_event` | F16.3 | Update event details |
| `engage_event` | F16.4 | Register or express interest in an event (self or by userId/playerId) |
| `list_attendees` | F16.5 | List event attendees by state, userId, or playerId |
| `list_pages` | F16.6 | List CMS pages |
| `get_page` | F16.6 | Get page content with translations |
| `create_page` | F16.6 | Create a CMS page (write articles) |
| `update_page` | F16.6 | Update page content |
| `list_categories` | F16.7 | List menu categories |

**Use cases:**

- An organizer asks their AI assistant: "Create a league challenge event for next Saturday at the usual store, 32 players max, Swiss format."
- A CMS editor asks: "Write an article summarizing last weekend's tournament results and publish it in the News category."
- A player asks: "What events are coming up this month? Register me for the one on the 15th."

**Implementation:** A Symfony controller handles the `/mcp` endpoint, parses MCP JSON-RPC requests, dispatches to the appropriate API service layer (shared with F16.3-F16.7), and returns MCP-formatted responses. The tool definitions (name, description, input schema) are served via the MCP `tools/list` method.

---

## API Conventions

### Versioning

All API endpoints are prefixed with `/api/v1/`. Breaking changes require a new version (`/api/v2/`).

### Authentication & Authorization

All API requests must include a valid OAuth2 bearer token in the `Authorization` header:

```
Authorization: Bearer <access_token>
```

Requests without a valid token receive `401 Unauthorized`. Requests with insufficient scopes receive `403 Forbidden`.

**Effective permissions = OAuth scopes ∩ user roles.** A granted scope does not elevate the user's privileges — it only defines the ceiling of what the API client is allowed to do. The actual permission for each action is the **intersection** of:

1. **OAuth scopes** granted to the token (e.g. `event:write`)
2. **User's roles and context** (e.g. `ROLE_ORGANIZER`, event ownership, staff assignment)

Examples:

| Token scope | User role | Action | Result |
|-------------|-----------|--------|--------|
| `event:write` | `ROLE_ORGANIZER` | Create event | **Allowed** |
| `event:write` | `ROLE_PLAYER` | Create event | **403** — user lacks `ROLE_ORGANIZER` |
| `event:read` | `ROLE_ORGANIZER` | Create event | **403** — token lacks `event:write` scope |
| `cms:write` | `ROLE_PLAYER` | Create page | **403** — user lacks `ROLE_CMS_EDITOR` |
| `event:write` | `ROLE_ORGANIZER` | Update another organizer's event | **403** — not the event owner |

This model ensures that a compromised or over-scoped token cannot perform actions the user themselves could not perform through the web UI. The same authorization checks (role guards, ownership checks, per-event staff verification) that protect web controllers are applied to API controllers.

The MCP server (F16.8) follows the same model — MCP tools are subject to both the token's scopes and the user's roles.

### Error format

Errors follow the [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) Problem Details format:

```json
{
  "type": "https://expandeddecks.com/errors/validation",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The 'name' field is required.",
  "violations": [
    { "field": "name", "message": "This value should not be blank." }
  ]
}
```

### Pagination

Paginated responses include a `pagination` object:

```json
{
  "data": [...],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 87,
    "pages": 5
  }
}
```

### User identification

Endpoints that accept a user identifier support two fields:

| Field | Type | Description |
|-------|------|-------------|
| `userId` | int | Internal Expanded Decks user ID |
| `playerId` | string | Play! Pokemon player ID (see F1.1) |

These fields are **mutually exclusive** — providing both returns `422 Unprocessable Entity`. When neither is provided, the action applies to the authenticated user. If the identifier does not match any user, the API returns `404 Not Found`.

This applies to: engagement endpoints (F16.4), attendee filters (F16.5), and corresponding MCP tools.

### Event identification

All event endpoints accept either identifier in the `{event}` path parameter:

| Format | Example | Resolves by |
|--------|---------|-------------|
| Integer | `/api/v1/events/42` | Internal database ID (`Event.id`) |
| String | `/api/v1/events/0000123456` | Pokemon tournament ID (`Event.eventId`) |

Resolution order: if the value is numeric, it is matched against the internal `id` first; if no match, it falls back to `eventId`. Non-numeric values are matched against `eventId` only. Returns `404 Not Found` if neither matches.

This applies to: all `/api/v1/events/{event}` endpoints (F16.3, F16.4, F16.5) and corresponding MCP tools.

### Rate limiting

API requests are rate-limited per access token: **60 requests per minute** for standard endpoints, **10 requests per minute** for write operations. Rate limit headers are included in all responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1711360800
```

Exceeding the limit returns `429 Too Many Requests`.

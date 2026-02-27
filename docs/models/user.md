# User Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\User`

### Fields

| Field              | Type              | Nullable | Description |
|--------------------|-------------------|----------|-------------|
| `id`               | `int` (auto)      | No       | Primary key |
| `email`            | `string(180)`     | No       | Unique. Used for login. |
| `screenName`       | `string(50)`      | No       | Unique. Public display name visible to other users (shown to authenticated users). |
| `firstName`        | `string(50)`      | No       | User's first name. Used for the anonymous public display format "FirstName L." in tournament results (F3.17). |
| `lastName`         | `string(50)`      | No       | User's last name. Only the first letter is shown publicly ("FirstName L.") in tournament results. Full last name visible only to the user themselves and admins. |
| `playerId`         | `string(30)`      | Yes      | Pokemon TCG player ID (e.g. tournament ID). Optional at registration, can be added later. |
| `password`         | `string`          | No       | Hashed password (Symfony PasswordHasher). |
| `roles`            | `json`            | No       | Array of role strings. Default: `["ROLE_PLAYER"]`. |
| `isVerified`       | `bool`            | No       | `false` until email verification is completed. Default: `false`. |
| `verificationToken`| `string(64)`      | Yes      | Random token sent by email for account activation. Cleared after verification. |
| `tokenExpiresAt`   | `DateTimeImmutable`| Yes     | Expiration timestamp for the verification token. |
| `createdAt`        | `DateTimeImmutable`| No      | Account creation timestamp. |
| `lastLoginAt`      | `DateTimeImmutable`| Yes     | Last successful login timestamp. |
| `resetToken`       | `string(64)`      | Yes      | Random token sent by email for password reset. Cleared after successful reset. Only one active reset token at a time. |
| `resetTokenExpiresAt` | `DateTimeImmutable` | Yes  | Expiration timestamp for the reset token. |
| `preferredLocale` | `string(5)`     | No       | ISO 639-1 UI language. Default: `"en"`. See F9.1. |
| `timezone`        | `string(50)`    | No       | IANA timezone string. Default: `"UTC"`. See F9.2. |
| `deletedAt`       | `DateTimeImmutable` | Yes  | Soft-delete timestamp. Null = active. See F1.8. |
| `isAnonymized`    | `bool`          | No       | Personal data anonymized after deletion. Default: `false`. See F1.8. |
| `iCalToken`       | `string(64)`    | Yes      | Random token for the personal iCal feed URL. Generated on first access, regenerable from profile (invalidates previous URL). See F3.14. |

### Roles

| Role                   | Constant              | Description |
|------------------------|-----------------------|-------------|
| `ROLE_PLAYER`          | Default for all users | Register decks, request borrows, attend events |
| `ROLE_ARCHETYPE_EDITOR`| Granted by admin      | Create, edit, and publish archetype descriptions (F2.6, F2.10) |
| `ROLE_CMS_EDITOR`      | Granted by admin      | Create, edit, and publish content pages and menu categories (F11.1, F11.2) |
| `ROLE_ORGANIZER`       | Granted by admin      | Create events, assign staff teams |
| `ROLE_ADMIN`           | Granted manually      | Full access: user management, audit log, all operations |

> **Note:** Staff is **not** a global role. It is a **per-event assignment** modeled via the `EventStaff` join entity (see [Event model](event.md)). A user can be staff at one event and a regular player at another.

Symfony role hierarchy:
```
ROLE_ADMIN > ROLE_ORGANIZER > ROLE_CMS_EDITOR > ROLE_ARCHETYPE_EDITOR > ROLE_PLAYER
```

### Constraints

- `email`: unique, valid email format
- `screenName`: unique, 3–50 characters, alphanumeric + underscores
- `firstName`: required, 1–50 characters
- `lastName`: required, 1–50 characters
- `playerId`: optional, unique when provided
- `password`: minimum 8 characters (validated at form level, stored hashed)
- `verificationToken`: generated as 64-character random hex string
- `resetToken`: generated as 64-character random hex string, same pattern as `verificationToken`
- `resetTokenExpiresAt`: must be in the future at generation time
- `preferredLocale`: required, must be a supported locale (`en`, `fr`). Default: `"en"`
- `timezone`: required, must be a valid IANA timezone identifier. Default: `"UTC"`
- `deletedAt`: once set, cannot be cleared (irreversible)
- `isAnonymized`: only `false → true` transition allowed (irreversible). Anonymized users cannot log in.
- `iCalToken`: generated as 64-character random hex string. Unique. Regenerable (old token immediately invalidated).

### Authentication Flow

1. User submits registration form (email, first name, last name, screen name, password, optional player ID)
2. Account is created with `isVerified = false`
3. A verification email is sent with a tokenized activation link
4. User clicks the link → token is validated against `verificationToken` and `tokenExpiresAt`
5. On success: `isVerified = true`, token fields are cleared
6. Login is only allowed when `isVerified = true`

### Password Reset Flow

1. User submits the forgot-password form with their email
2. If the email matches an active (`isVerified = true`) account, a reset token is generated
3. `resetToken` is set to a 64-character random hex string, `resetTokenExpiresAt` is set to a configurable delay from now
4. A password reset email is sent with a tokenized reset link
5. User clicks the link → token is validated against `resetToken` and `resetTokenExpiresAt`
6. On success: user sets a new password, both `resetToken` and `resetTokenExpiresAt` are cleared
7. If the token is expired or invalid, the user is prompted to request a new one

> **@see** docs/features.md F1.7 — Password reset

### GDPR — Data Export & Account Deletion

> **@see** docs/features.md F1.8 — Account deletion & data export (GDPR)

#### Data Export

Users can download all their personal data as a **JSON file** from their profile page. The export includes:

- Profile information (email, screenName, playerId, roles, preferredLocale, timezone, createdAt)
- Owned decks (name, archetype, versions, card lists)
- Borrow history (requests made, as borrower)
- Event participations
- Notifications

#### Account Deletion Flow

1. User requests deletion from their profile
2. A confirmation email is sent with a tokenized link (expires **24 hours**)
3. User clicks the confirmation link
4. **Anonymization** is applied (not hard delete — preserves data integrity):
   - `email` → `deleted_<id>@anon.local`
   - `screenName` → `[deleted_<id>]`
   - `playerId` → `null`
   - `password` → invalidated (random hash)
   - `verificationToken`, `resetToken` → cleared
   - `deletedAt` → set to current timestamp
   - `isAnonymized` → `true`
5. All relations (borrows, event participations, deck ownership) are **preserved** for historical integrity
6. In the UI, anonymized users display as `[deleted user]`
7. Anonymized accounts cannot log in (authentication check on `isAnonymized`)

### Future Considerations

- **F1.5 — MFA/TOTP**: Will add `totpSecret` field and a `isTotpEnabled` flag. Planned for later.
- **F1.6 — Pokemon SSO**: If feasible, would add an `externalId` field for the Pokemon Company account link. Requires investigation.
- **F3.14 — iCal agenda feed**: `iCalToken` field supports the personal iCal feed URL. Token-authenticated (no login required for calendar clients).
- **F9 — Localization**: `preferredLocale` and `timezone` fields are now part of the core model.

### Relations

| Relation           | Type         | Target entity | Description |
|--------------------|--------------|---------------|-------------|
| `ownedDecks`       | OneToMany    | `Deck`        | Decks owned by this user |
| `borrowRequests`   | OneToMany    | `Borrow`      | Borrow requests made by this user |
| `eventEngagements` | OneToMany    | `EventEngagement` | Player engagement states for events (interested, invited, registered). See [Event model](event.md) and F3.13. |
| `staffAssignments` | OneToMany    | `EventStaff`  | Events where this user is assigned as staff (see [Event model](event.md)) |
| `notifications`    | OneToMany    | `Notification`| Notifications addressed to this user (see [Notification model](notification.md)) |

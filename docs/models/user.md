# User Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\User`

### Fields

| Field              | Type              | Nullable | Description |
|--------------------|-------------------|----------|-------------|
| `id`               | `int` (auto)      | No       | Primary key |
| `email`            | `string(180)`     | No       | Unique. Used for login. |
| `screenName`       | `string(50)`      | No       | Unique. Public display name visible to other users. |
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

### Roles

| Role             | Constant              | Description |
|------------------|-----------------------|-------------|
| `ROLE_PLAYER`    | Default for all users | Register decks, request borrows, attend events |
| `ROLE_ORGANIZER` | Granted by admin      | Create events, assign staff teams |
| `ROLE_ADMIN`     | Granted manually      | Full access: user management, audit log, all operations |

> **Note:** Staff is **not** a global role. It is a **per-event assignment** modeled via the `EventStaff` join entity (see [Event model](event.md)). A user can be staff at one event and a regular player at another.

Symfony role hierarchy:
```
ROLE_ADMIN > ROLE_ORGANIZER > ROLE_PLAYER
```

### Constraints

- `email`: unique, valid email format
- `screenName`: unique, 3–50 characters, alphanumeric + underscores
- `playerId`: optional, unique when provided
- `password`: minimum 8 characters (validated at form level, stored hashed)
- `verificationToken`: generated as 64-character random hex string
- `resetToken`: generated as 64-character random hex string, same pattern as `verificationToken`
- `resetTokenExpiresAt`: must be in the future at generation time

### Authentication Flow

1. User submits registration form (email, screen name, password, optional player ID)
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

### Future Considerations

- **F1.5 — MFA/TOTP**: Will add `totpSecret` field and a `isTotpEnabled` flag. Planned for later.
- **F1.6 — Pokemon SSO**: If feasible, would add an `externalId` field for the Pokemon Company account link. Requires investigation.

### Relations

| Relation           | Type         | Target entity | Description |
|--------------------|--------------|---------------|-------------|
| `ownedDecks`       | OneToMany    | `Deck`        | Decks owned by this user |
| `borrowRequests`   | OneToMany    | `Borrow`      | Borrow requests made by this user |
| `eventParticipations` | ManyToMany | `Event`      | Events this user participates in (as player) |
| `staffAssignments` | OneToMany    | `EventStaff`  | Events where this user is assigned as staff (see [Event model](event.md)) |
| `notifications`    | OneToMany    | `Notification`| Notifications addressed to this user (see [Notification model](notification.md)) |

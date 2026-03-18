# Production Installation

> **Audience:** DevOps, Developer · **Scope:** Deployment, Reference

← Back to [Documentation](docs.md) | [README](../README.md)

---

## Docker Image

The application ships as a single Docker image based on [FrankenPHP](https://frankenphp.dev/) (`dunglas/frankenphp:php8.5`).

```bash
docker build --build-arg APP_VERSION=$(git describe --tags --always) -t expanded-decks .
```

The image includes all PHP extensions (pdo_mysql, intl, opcache, apcu, gd), Composer dependencies, and compiled frontend assets. It exposes port `8080` and runs FrankenPHP in worker mode.

---

## Environment Variables

All configuration is injected via environment variables. No `.env` files are read in production (`APP_ENV=prod` is baked into the image).

### Core

| Variable | Required | Example | Description |
|----------|----------|---------|-------------|
| `APP_SECRET` | **Yes** | `a3b8f...` (random hex) | Symfony secret for CSRF tokens, signed cookies, and session encryption. Generate with `openssl rand -hex 32`. |
| `APP_VERSION` | No | `v1.0.0-beta.5` | Application version displayed in UI footer and sent to Sentry. Set at build time via `--build-arg`. |
| `DEFAULT_URI` | **Yes** | `https://expanded-decks.com` | Canonical base URL for absolute URL generation (emails, iCal feeds, QR codes). |
| `SYMFONY_TRUSTED_PROXIES` | **Yes** | `REMOTE_ADDR` | Trusted proxy IPs. Set to `REMOTE_ADDR` when behind a load balancer/reverse proxy. |

### Database

| Variable | Required | Example | Description |
|----------|----------|---------|-------------|
| `DATABASE_URL` | **Yes** | `mysql://user:pass@host:3306/db?serverVersion=8.0&charset=utf8mb4` | Doctrine DBAL connection string. Also used for session storage (PDO adapter). |

### Email

| Variable | Required | Example | Description |
|----------|----------|---------|-------------|
| `MAILER_DSN` | **Yes** | `ses+smtp://KEY:SECRET@default?region=eu-west-1` | Symfony Mailer transport. Supports SMTP, AWS SES, Postmark, SendGrid, Mailgun. |
| `MAIL_SENDER` | **Yes** | `noreply@expanded-decks.com` | "From" address for all transactional emails. |
| `ADMIN_EMAIL` | **Yes** | `admin@expanded-decks.com` | Administrator email for system notifications. |

### Async Messaging (Symfony Messenger)

Each transport has its own DSN. Default: `doctrine://default?auto_setup=0` (Doctrine transport with DB-backed queues). Can be switched to SQS, Redis, AMQP, etc.

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `MESSENGER_TRANSPORT_TRANSACTIONAL_EMAIL_DSN` | No | `doctrine://default?auto_setup=0` | Transactional email queue. |
| `MESSENGER_TRANSPORT_DECK_ENRICHMENT_DSN` | No | `doctrine://default?auto_setup=0` | TCGdex card enrichment + mosaic generation queue. |
| `MESSENGER_TRANSPORT_NOTIFICATION_DSN` | No | `doctrine://default?auto_setup=0` | Push notification dispatch queue. |
| `MESSENGER_TRANSPORT_BORROW_LIFECYCLE_DSN` | No | `doctrine://default?auto_setup=0` | Borrow state transition queue. |
| `MESSENGER_TRANSPORT_FAILED_DSN` | No | `doctrine://default?queue_name=failed` | Dead-letter queue for failed messages (all transports). |
| `MESSENGER_WEBHOOK_SECRET` | No | (empty) | Shared secret for webhook-based message consumption. Leave empty to disable. |

### Error Tracking (Sentry)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `SENTRY_DSN` | No | (empty) | Sentry DSN. Leave empty to disable error tracking. |
| `SENTRY_TRACES_SAMPLE_RATE` | No | `0` | Performance tracing sample rate (`0.0`–`1.0`). |
| `SENTRY_LOGS_ACTION_LEVEL` | No | `error` | Minimum log level that triggers the Sentry handler (`error`, `warning`, `info`, `debug`). |

### Mosaic Image Storage (Flysystem)

Generated deck mosaic images are stored via Flysystem. Two adapters are available.

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `MOSAIC_STORAGE_ADAPTER` | No | `local` | Storage adapter: `local` (filesystem) or `s3` (Scaleway/S3-compatible). |
| `MOSAIC_STORAGE_LOCAL_DIR` | No | `var/storage/mosaic` | Local adapter: storage directory relative to project root. |
| `MOSAIC_PUBLIC_URL` | If `s3` | — | Public base URL for serving images (e.g. `https://bucket.s3.fr-par.scw.cloud`). |
| `SCALEWAY_S3_BUCKET` | If `s3` | — | S3 bucket name. |
| `SCALEWAY_S3_REGION` | If `s3` | — | S3 region (e.g. `fr-par`, `nl-ams`). |
| `SCALEWAY_S3_ENDPOINT` | If `s3` | — | S3 API endpoint (e.g. `https://s3.fr-par.scw.cloud`). |
| `SCALEWAY_S3_ACCESS_KEY` | If `s3` | — | S3 access key ID. |
| `SCALEWAY_S3_SECRET_KEY` | If `s3` | — | S3 secret access key. |

---

## Workers

The application uses Symfony Messenger for async processing. In production, consume queues via a cron job or a long-running worker process:

```bash
# Consume all transports
php bin/console messenger:consume transactional_email deck_enrichment notification borrow_lifecycle --time-limit=300

# Or consume specific transports individually
php bin/console messenger:consume deck_enrichment --time-limit=300
```

### Transports

| Transport | Queue | Messages |
|-----------|-------|----------|
| `transactional_email` | `transactionalEmail` | Outgoing emails (Symfony Mailer) |
| `deck_enrichment` | `deck_enrichment` | TCGdex card enrichment, mosaic image generation |
| `notification` | `notification` | Push notifications (Notifier) |
| `borrow_lifecycle` | `borrow_lifecycle` | Borrow state transitions, competing borrow declining |
| `failed` | `failed` | Dead-letter queue (retry 3×, multiplier ×2) |

---

## Database Setup

Run migrations on first deploy and after each update:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

---

## Health Check

The application exposes health check endpoints for container orchestration:

- **Liveness:** `GET /health` — returns 200 if the application process is running
- **Readiness:** `GET /health/ready` — returns 200 if the database connection is healthy

---

## Minimal Production Example

```bash
docker run -d \
  -p 8080:8080 \
  -e APP_SECRET="$(openssl rand -hex 32)" \
  -e DEFAULT_URI="https://expanded-decks.com" \
  -e SYMFONY_TRUSTED_PROXIES="REMOTE_ADDR" \
  -e DATABASE_URL="mysql://user:pass@db:3306/expanded_decks?serverVersion=8.0&charset=utf8mb4" \
  -e MAILER_DSN="ses+smtp://KEY:SECRET@default?region=eu-west-1" \
  -e MAIL_SENDER="noreply@expanded-decks.com" \
  -e ADMIN_EMAIL="admin@expanded-decks.com" \
  -e SENTRY_DSN="https://key@o0.ingest.sentry.io/0" \
  -e MOSAIC_STORAGE_ADAPTER="s3" \
  -e SCALEWAY_S3_BUCKET="expanded-decks-mosaic" \
  -e SCALEWAY_S3_REGION="fr-par" \
  -e SCALEWAY_S3_ENDPOINT="https://s3.fr-par.scw.cloud" \
  -e SCALEWAY_S3_ACCESS_KEY="SCWXXXXXXXXX" \
  -e SCALEWAY_S3_SECRET_KEY="secret" \
  -e MOSAIC_PUBLIC_URL="https://expanded-decks-mosaic.s3.fr-par.scw.cloud" \
  expanded-decks
```

#!/bin/sh
set -e

# Clear and warm Symfony cache on startup with actual runtime env vars.
# This ensures the compiled container matches the injected secrets.
php bin/console cache:clear --env=prod --no-debug 2>/dev/null || true
php bin/console cache:warmup --env=prod --no-debug

# Start MeiliSearch in the background so the reindex command can reach it.
# Supervisor will take over management once it starts (autorestart=true).
meilisearch --http-addr 127.0.0.1:7700 --env production \
    --master-key "${MEILI_MASTER_KEY}" \
    --db-path /var/lib/meilisearch/data &

# Wait for MeiliSearch to be ready (max ~5s)
for i in 1 2 3 4 5 6 7 8 9 10; do
    if curl -sf http://127.0.0.1:7700/health > /dev/null 2>&1; then
        break
    fi
    sleep 0.5
done

# Seed reserved CMS pages backing the banned/staple listing intro blocks.
# Idempotent: skips channels that already have the page.
php bin/console app:listings:seed-intros --env=prod --no-debug 2>/dev/null || true

# Rebuild search indexes from MySQL (ephemeral disk — index lost on restart)
php bin/console app:search:reindex --env=prod --no-debug 2>/dev/null || true

# Stop the temporary MeiliSearch — Supervisor will start and manage its own
kill %1 2>/dev/null || true
sleep 0.5

# Launch Supervisor (manages FrankenPHP + Messenger workers + MeiliSearch)
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf

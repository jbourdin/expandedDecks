#!/bin/sh
set -e

# Clear and warm Symfony cache on startup with actual runtime env vars.
# This ensures the compiled container matches the injected secrets.
php bin/console cache:clear --env=prod --no-debug 2>/dev/null || true
php bin/console cache:warmup --env=prod --no-debug

# Execute the original FrankenPHP entrypoint
exec "$@"

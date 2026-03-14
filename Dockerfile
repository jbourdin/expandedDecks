# ---------------------------------------------------------------------------
# Multi-stage Dockerfile for the Expanded Decks Symfony application.
#
# Stages:
#   1. composer  — install PHP dependencies (no dev)
#   2. assets    — build frontend with Webpack Encore
#   3. runtime   — final production image
#
# Build:  docker build -t expanded-decks .
# Run:    docker run -p 8080:8080 --env-file .env.local expanded-decks
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Stage 1 — PHP dependencies
# ---------------------------------------------------------------------------
FROM php:8.5-cli AS composer

RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------------------------------------------------------------------------
# Stage 2 — Frontend assets
# ---------------------------------------------------------------------------
FROM node:22-slim AS assets

WORKDIR /app

COPY package.json package-lock.json webpack.config.js tsconfig.json ./
RUN npm ci --ignore-scripts

COPY assets/ assets/
COPY templates/ templates/

RUN npx encore production

# ---------------------------------------------------------------------------
# Stage 3 — Runtime image
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:php8.5 AS runtime

# Install required PHP extensions
RUN install-php-extensions \
    pdo_mysql \
    intl \
    opcache \
    apcu

# OPcache production settings
RUN echo '\
opcache.enable=1\n\
opcache.memory_consumption=256\n\
opcache.max_accelerated_files=20000\n\
opcache.validate_timestamps=0\n\
opcache.preload_user=www-data\n\
' > "$PHP_INI_DIR/conf.d/opcache-prod.ini"

# Use production php.ini
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app

# Copy application code and built artifacts
COPY --from=composer /app/vendor vendor/
COPY --from=assets /app/public/build public/build/
COPY . .

# Install public assets (favicons, bundles, etc.)
RUN php bin/console assets:install public --env=prod --no-debug

# Remove dev files not needed in production
RUN rm -rf tests/ .env.test .env.dev docker-compose.yml node_modules/ assets/ \
    webpack.config.js package.json package-lock.json .php-cs-fixer.dist.php \
    phpstan.neon phpunit.xml.dist vitest.config.ts .eslintrc.js .stylelintrc.json

ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV SERVER_NAME=":8080"

# Ensure FrankenPHP data directories are writable
RUN mkdir -p /data/caddy /config/caddy && chown -R www-data:www-data /data /config

# Compile .env files for production (avoids parsing .env at runtime)
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN composer dump-env prod && rm /usr/bin/composer

# Warm up Symfony cache with a dummy DATABASE_URL so the container can
# compile without a real database connection. The actual DATABASE_URL is
# provided at runtime via environment variables and overrides this.
RUN DATABASE_URL="mysql://dummy:dummy@localhost/dummy?serverVersion=8.0" \
    php bin/console cache:warmup --env=prod --no-debug

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:8080/health || exit 1

EXPOSE 8080

USER www-data

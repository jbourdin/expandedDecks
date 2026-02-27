.DEFAULT_GOAL := help

## —— Project ——————————————————————————————————————————————————————————

.PHONY: help
help: ## Show this help
	@grep -E '(^[a-zA-Z0-9_.%-]+:.*?##.*$$)|(^## )' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m  %-20s\033[0m %s\n", $$1, $$2}' \
		| sed -e 's/\[32m  ##/[33m/'

.PHONY: install
install: ## Install project dependencies
	symfony composer install
	npm install

.PHONY: start
start: ## Start dev server and Docker services
	docker compose up -d
	symfony proxy:start
	symfony server:start -d

.PHONY: stop
stop: ## Stop dev server and Docker services
	symfony server:stop
	docker compose down

.PHONY: mailpit
mailpit: ## Open Mailpit web UI
	open http://localhost:8035

## —— Messenger ————————————————————————————————————————————————————————

.PHONY: worker.enrichment
worker.enrichment: ## Run the deck enrichment Messenger worker
	symfony console messenger:consume deck_enrichment -vv --no-debug

.PHONY: worker.all
worker.all: ## Run all Messenger workers (async + deck enrichment)
	symfony console messenger:consume async deck_enrichment -vv --no-debug

## —— Database —————————————————————————————————————————————————————————

.PHONY: migrations
migrations: ## Execute Doctrine migrations
	symfony console doctrine:migrations:migrate --no-interaction

.PHONY: fixtures
fixtures: ## Load fixture data and dispatch enrichment
	symfony console doctrine:database:drop --force --if-exists
	symfony console doctrine:database:create
	symfony console doctrine:migrations:migrate --no-interaction
	symfony console doctrine:fixtures:load --no-interaction --append
	symfony console app:enrich:retry

## —— Assets ——————————————————————————————————————————————————————————

.PHONY: assets
assets: ## Build frontend assets (production)
	npx encore production

.PHONY: assets.watch
assets.watch: ## Build frontend assets and watch for changes
	npx encore dev --watch

## —— Quality —————————————————————————————————————————————————————————

.PHONY: test
test: ## Run test suite
	symfony php bin/phpunit

.PHONY: phpstan
phpstan: ## Run PHPStan static analysis
	symfony php vendor/bin/phpstan analyse --memory-limit=512M

.PHONY: cs-fix
cs-fix: ## Fix code style with PHP-CS-Fixer
	symfony php vendor/bin/php-cs-fixer fix

.PHONY: cs-check
cs-check: ## Check code style (dry-run)
	symfony php vendor/bin/php-cs-fixer fix --dry-run --diff

.PHONY: eslint
eslint: ## Run ESLint on frontend assets
	npx eslint assets/

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

.PHONY: ngrok.start
ngrok.start: ## Start ngrok tunnel (daemon) and print the public URL
	@ngrok http https://127.0.0.1:8742 --log=stdout > /dev/null & echo $$! > var/ngrok.pid
	@sleep 2
	@curl -s http://127.0.0.1:4040/api/tunnels | php -r 'echo json_decode(file_get_contents("php://stdin"))->tunnels[0]->public_url . "\n";'

.PHONY: ngrok.stop
ngrok.stop: ## Stop ngrok tunnel and agent for this app
	@if [ -f var/ngrok.pid ]; then \
		kill $$(cat var/ngrok.pid) 2>/dev/null && echo "ngrok stopped" || echo "ngrok was not running"; \
		rm -f var/ngrok.pid; \
	else \
		echo "No ngrok PID file found"; \
	fi

.PHONY: mailpit
mailpit: ## Open Mailpit web UI
	open http://localhost:8035

## —— Messenger ————————————————————————————————————————————————————————

.PHONY: worker.email
worker.email: ## Run the transactional email Messenger worker
	symfony console messenger:consume transactional_email -vv --no-debug

.PHONY: worker.enrichment
worker.enrichment: ## Run the deck enrichment Messenger worker
	symfony console messenger:consume deck_enrichment -vv --no-debug

.PHONY: worker.notification
worker.notification: ## Run the notification Messenger worker
	symfony console messenger:consume notification -vv --no-debug

.PHONY: worker.borrow
worker.borrow: ## Run the borrow lifecycle Messenger worker
	symfony console messenger:consume borrow_lifecycle -vv --no-debug

.PHONY: worker.all
worker.all: ## Run all Messenger workers
	symfony console messenger:consume transactional_email deck_enrichment notification borrow_lifecycle -vv --no-debug

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
	symfony console app:banned-cards:sync
	symfony console app:enrich:retry

## —— Assets ——————————————————————————————————————————————————————————

.PHONY: assets
assets: ## Build frontend assets (production)
	npx encore production

.PHONY: assets.watch
assets.watch: ## Build frontend assets and watch for changes
	npx encore dev --watch

## —— Quality —————————————————————————————————————————————————————————

.PHONY: lint-all
lint-all: lint-yaml lint-i18n cs-fix eslint-fix stylelint-fix lint-container phpstan ## Run all linters and fixers

.PHONY: test
test: ## Run test suite
	symfony php bin/phpunit

.PHONY: test.unit
test.unit: ## Run PHP unit tests only
	symfony php bin/phpunit --testsuite unit

.PHONY: test.functional
test.functional: ## Run PHP functional tests only
	symfony php bin/phpunit --testsuite functional

.PHONY: coverage
coverage: ## Run PHP tests with coverage (requires pcov)
	symfony php -d pcov.enabled=1 bin/phpunit --coverage-clover var/coverage/clover.xml --coverage-text

.PHONY: test.front
test.front: ## Run frontend (Vitest) tests
	npx vitest run

.PHONY: phpstan test.phpstan
phpstan test.phpstan: ## Run PHPStan static analysis
	symfony php vendor/bin/phpstan analyse --memory-limit=512M

.PHONY: cs-fix test.phpcs.fix
cs-fix test.phpcs.fix: ## Fix code style with PHP-CS-Fixer
	symfony php vendor/bin/php-cs-fixer fix

.PHONY: cs-check test.phpcs
cs-check test.phpcs: ## Check code style (dry-run)
	symfony php vendor/bin/php-cs-fixer fix --dry-run --diff

.PHONY: lint-i18n
lint-i18n: ## Validate translation files (syntax + content)
	symfony console lint:xliff translations/
	symfony console lint:translations

.PHONY: lint-container
lint-container: ## Validate the Symfony dependency injection container
	symfony console lint:container

.PHONY: lint-yaml
lint-yaml: ## Validate YAML configuration files
	symfony console lint:yaml config/

.PHONY: eslint
eslint: ## Run ESLint on frontend assets
	npx eslint assets/

.PHONY: eslint-fix
eslint-fix: ## Fix ESLint issues on frontend assets
	npx eslint assets/ --fix

.PHONY: stylelint
stylelint: ## Lint SCSS and CSS files
	npx stylelint "assets/**/*.scss"

.PHONY: stylelint-fix
stylelint-fix: ## Fix SCSS and CSS style issues
	npx stylelint "assets/**/*.scss" --fix

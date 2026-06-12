.DEFAULT_GOAL := help

install:
	docker compose up -d --build

sh:
	docker compose exec php sh

cache:
	docker compose exec php php bin/console cache:clear

migrate:
	docker compose exec php php bin/console doctrine:migrations:migrate

migrate-local:
	php bin/console doctrine:migrations:migrate

fixtures:
	docker compose exec php rm -rf var/cache/* || true
	docker compose exec php php bin/console doctrine:fixtures:load

fixtures-local:
	php bin/console doctrine:fixtures:load

test-init:
	docker compose exec -e APP_ENV=test -e DATABASE_URL="postgresql://app:app@database:5432/app_test?serverVersion=16&charset=utf8" php php bin/console doctrine:database:create --if-not-exists
	docker compose exec -e APP_ENV=test -e DATABASE_URL="postgresql://app:app@database:5432/app_test?serverVersion=16&charset=utf8" php php bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec -e APP_ENV=test -e DATABASE_URL="postgresql://app:app@database:5432/app_test?serverVersion=16&charset=utf8" php php bin/console doctrine:fixtures:load --no-interaction

test-init-local:
	php bin/console doctrine:database:create --env=test --if-not-exists
	php bin/console doctrine:migrations:migrate --env=test --no-interaction
	php bin/console doctrine:fixtures:load --env=test --no-interaction

test:
	docker compose exec -e APP_ENV=test -e APP_DEBUG=1 -e DATABASE_URL="postgresql://app:app@database:5432/app_test?serverVersion=16&charset=utf8" php php bin/phpunit

test-local:
	php bin/phpunit

phpstan:
	docker compose exec php vendor/bin/phpstan analyse --memory-limit=512M

phpstan-local:
	vendor/bin/phpstan analyse

lint:
	docker compose exec php vendor/bin/php-cs-fixer check --diff

lint-local:
	vendor/bin/php-cs-fixer check --diff

lint-fix:
	docker compose exec php vendor/bin/php-cs-fixer fix

lint-fix-local:
	vendor/bin/php-cs-fixer fix

mutation:
	docker compose exec php vendor/bin/infection --threads=max --show-mutations --no-progress

mutation-local:
	vendor/bin/infection --threads=max --show-mutations --no-progress

ci-local: lint-local phpstan-local test-local

logs:
	docker compose logs -f --tail=100 php

worker-logs:
	docker compose logs -f --tail=100 worker

up start:
	docker compose up -d --build

assets:
	docker compose exec php php bin/console asset-map:compile

down stop:
	docker compose down

restart: down up

help:
	@echo "Makefile commands:"
	@echo "  install       - Build and start Docker services"
	@echo "  sh            - Open a shell inside the PHP container"
	@echo "  up            - Start the Docker containers"
	@echo "  assets        - Compile Symfony AssetMapper files"
	@echo "  down          - Stop and remove the Docker containers"
	@echo "  restart       - Restart the Docker containers"
	@echo "  cache         - Clear the Symfony cache"
	@echo "  migrate       - Run Doctrine migrations in Docker"
	@echo "  migrate-local - Run Doctrine migrations locally"
	@echo "  fixtures      - Load Doctrine fixtures in Docker"
	@echo "  fixtures-local- Load Doctrine fixtures locally"
	@echo "  test          - Run PHPUnit in Docker"
	@echo "  test-local    - Run PHPUnit locally"
	@echo "  phpstan       - Run PHPStan in Docker"
	@echo "  phpstan-local - Run PHPStan locally"
	@echo "  lint          - Check code style (PHP CS Fixer) in Docker"
	@echo "  lint-local    - Check code style locally"
	@echo "  lint-fix      - Auto-fix code style in Docker"
	@echo "  lint-fix-local- Auto-fix code style locally"
	@echo "  mutation      - Run mutation tests (Infection) in Docker"
	@echo "  mutation-local- Run mutation tests locally"
	@echo "  ci-local      - Run lint + phpstan + tests locally (simule la CI)"
	@echo "  logs          - Follow the logs of the PHP container"
	@echo "  worker-logs   - Follow the logs of the Messenger worker"
	@echo "  help          - Show this help message"

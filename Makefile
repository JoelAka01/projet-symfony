.DEFAULT_GOAL := help

install:
	docker compose up -d --build

sh:
	docker compose exec php sh

cache:
	docker compose exec php php bin/console cache:clear

migrate:
	@docker compose ps php --quiet 2>nul | findstr . >nul || (echo Error: Docker services not running. Run 'make up' first. && exit /b 1)
	docker compose exec php php bin/console doctrine:migrations:migrate

migrate-local:
	php bin/console doctrine:migrations:migrate

fixtures:
	docker compose exec php rm -rf var/cache/* || true
	docker compose exec php php bin/console doctrine:fixtures:load

fixtures-local:
	php bin/console doctrine:fixtures:load

test:
	@docker compose ps php --quiet 2>nul | findstr . >nul || (echo Error: Docker services not running. Run 'make up' first. && exit /b 1)
	docker compose exec php php bin/phpunit

test-local:
	php bin/phpunit

phpstan:
	@docker compose ps php --quiet 2>nul | findstr . >nul || (echo Error: Docker services not running. Run 'make up' first. && exit /b 1)
	docker compose exec php vendor/bin/phpstan analyse

phpstan-local:
	vendor\bin\phpstan analyse

logs:
	docker compose logs -f --tail=100 php

up start:
	docker compose up -d

down stop:
	docker compose down

restart: down up

help:
	@echo "Makefile commands:"
	@echo "  install       - Build and start Docker services"
	@echo "  sh            - Open a shell inside the PHP container"
	@echo "  up            - Start the Docker containers"
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
	@echo "  logs          - Follow the logs of the PHP container"
	@echo "  help          - Show this help message"

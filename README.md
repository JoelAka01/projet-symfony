# SEO GEO AI

Project for a SEO, GEO, and AI visibility SaaS platform.

## Requirements

- PHP 8.2 or newer
- Composer 2
- Docker Desktop, optional but recommended
- Symfony CLI, optional for local development outside Docker

## Install With Docker

```bash
make install
# or manually:
docker compose up -d --build
```

Local URLs:

- Application: http://localhost:8080
- Mailpit: http://localhost:8025

Run Symfony commands in Docker (ensure containers are running with `make up` first):

```bash
make migrate
make fixtures
make test
make phpstan
make lint          # Check code style (PHP CS Fixer)
make lint-fix      # Auto-fix code style
make mutation      # Run mutation tests (Infection)
make worker-logs
make assets
# or manually:
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console doctrine:fixtures:load
docker compose exec php php bin/phpunit
docker compose exec php vendor/bin/phpstan analyse
docker compose exec php vendor/bin/php-cs-fixer check --diff
docker compose exec php vendor/bin/infection --threads=max --show-mutations
docker compose exec php php bin/console asset-map:compile
docker compose logs -f worker
```

## Install Without Docker

Start PostgreSQL and Mailpit yourself, or only start those services with Docker:

```bash
docker compose up -d database mailpit
```

Then install and run the app locally:

```bash
composer install
cp .env.example .env
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
symfony server:start
```

- Application: http://localhost:8000

On Windows PowerShell, use this instead of `cp`:

```powershell
Copy-Item .env.example .env
```

Default local database URL:

```text
postgresql://app:app@127.0.0.1:5432/app?serverVersion=16&charset=utf8
```

Docker overrides it inside the PHP container:

```text
postgresql://app:app@database:5432/app?serverVersion=16&charset=utf8
```

## Useful Commands

```bash
php bin/console about
php bin/console debug:router
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
php bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer check --diff
vendor/bin/infection --threads=max --show-mutations
```

Makefile shortcuts (Docker):

```bash
make install        # Build and start Docker services
make up             # Start Docker containers
make migrate        # Run migrations (Docker)
make fixtures       # Load fixtures (Docker)
make test           # Run tests (Docker)
make phpstan        # Run static analysis (Docker)
make lint           # Check code style — PHP CS Fixer (Docker)
make lint-fix       # Auto-fix code style (Docker)
make mutation       # Run mutation tests — Infection (Docker)
make worker-logs    # Follow Messenger worker logs
```

Makefile shortcuts (Local without Docker):

```bash
make migrate-local  # Run migrations locally
make fixtures-local # Load fixtures locally
make test-local     # Run tests locally
make phpstan-local  # Run static analysis locally
make lint-local     # Check code style locally
make lint-fix-local # Auto-fix code style locally
make mutation-local # Run mutation tests locally
make ci-local       # Run lint + phpstan + tests (simulates CI)
```

## Demo Credentials

```text
Admin:   admin@example.com   / password
Manager: manager@example.com / password
User:    user@example.com    / password
```

Made with care by Dilan EESHVARAN, Kassi Joel Emmanuel AKA and Mahamadou GORY KANTE - 4IW1 2025/2026

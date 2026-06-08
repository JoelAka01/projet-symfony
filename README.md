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
# or manually:
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console doctrine:fixtures:load
docker compose exec php php bin/phpunit
docker compose exec php vendor/bin/phpstan analyse
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
```

Makefile shortcuts (Docker):

```bash
make install        # Build and start Docker services
make up             # Start Docker containers
make migrate        # Run migrations (Docker)
make fixtures       # Load fixtures (Docker)
make test           # Run tests (Docker)
make phpstan        # Run static analysis (Docker)
```

Makefile shortcuts (Local without Docker):

```bash
make migrate-local  # Run migrations locally
make fixtures-local # Load fixtures locally
make test-local     # Run tests locally
make phpstan-local  # Run static analysis locally
```

## Demo Credentials

```text
Admin:   admin@example.com   / password
User:    user@example.com    / password
```

Made with care by Dilan EESHVARAN, Kassi Joel Emmanuel AKA and Mahamadou GORY KANTE - 4IW1 2025/2026

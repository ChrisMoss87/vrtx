# VRTX CRM Backend

A multi-tenant CRM backend built with Laravel and Domain-Driven Design principles.

## Requirements

- PHP 8.2+
- PostgreSQL 15+
- Composer
- Node.js 18+ (for frontend assets)

## Quick Start

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run central database migrations
php artisan migrate

# Seed tenants
php artisan db:seed --class=TenantSeeder

# Run tenant migrations
php artisan tenants:migrate

# Start the development server
php artisan serve
```

## Architecture

This project follows **Domain-Driven Design (DDD)** with **Clean Architecture**:

```
app/
├── Domain/           # Core business logic (entities, value objects, interfaces)
├── Application/      # Use cases and application services
├── Infrastructure/   # External concerns (database, APIs, caching)
├── Http/            # Controllers and middleware
├── Console/         # Artisan commands
└── Providers/       # Service providers
```

## Multi-Tenancy

The application uses database-per-tenant isolation. Each tenant has:
- Separate PostgreSQL database
- Isolated data and configuration
- Custom domain/subdomain routing

See [docs/tenancy.md](docs/tenancy.md) for detailed documentation.

### Tenant Commands

```bash
# List all tenants
php artisan tenants:list

# Create a new tenant
php artisan tenants:create {id} --domain={domain} --name={name}

# Run migrations for all tenants
php artisan tenants:migrate

# Run any command for tenants
php artisan tenants:run "db:seed --class=SampleDataSeeder"
```

## Development

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/MyTest.php
```

### Code Style

```bash
# Format code with Pint
./vendor/bin/pint
```

### Database Migrations

```bash
# Central migrations
php artisan migrate

# Tenant migrations
php artisan tenants:migrate

# Fresh tenant migrations
php artisan tenants:migrate --fresh
```

## Documentation

- [Multi-Tenancy](docs/tenancy.md) - Tenant isolation and management

## Environment Variables

Key environment variables:

| Variable | Description |
|----------|-------------|
| `DB_CONNECTION` | Default database connection (`pgsql`) |
| `DB_DATABASE` | Central database name |
| `CENTRAL_DB_DATABASE` | Central database for tenants table |

## License

Proprietary - All rights reserved.

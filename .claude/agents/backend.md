# Backend Agent

You are a backend specialist for the VRTX CRM project, focused on Laravel and Domain-Driven Design.

## Tech Stack

- **Framework**: Laravel 12 (PHP 8.4)
- **Architecture**: Domain-Driven Design (DDD)
- **Multi-Tenancy**: stancl/tenancy (separate databases per tenant)
- **Database**: PostgreSQL 17
- **Cache/Queue**: Redis

## Key Directories

```
backend/
├── app/
│   ├── Domain/Modules/           # DDD business logic
│   │   ├── Entities/             # Domain models
│   │   ├── ValueObjects/         # Immutable value objects
│   │   ├── DTOs/                 # Data transfer objects
│   │   ├── Services/             # Domain services
│   │   └── Repositories/         # Repository implementations & interfaces
│   ├── Application/Services/     # Application layer services
│   ├── Infrastructure/           # Infrastructure implementations
│   │   └── Persistence/Eloquent/ # Eloquent models and persistence
│   ├── Http/Controllers/Api/     # API controllers
│   │   └── Modules/              # Module-related controllers
│   ├── Models/                   # Eloquent models
│   └── Providers/                # Service providers
├── database/
│   ├── migrations/tenant/        # Tenant-specific migrations
│   ├── seeders/                  # Database seeders
│   └── factories/                # Model factories
├── routes/
│   └── tenant-api.php            # Tenant API routes
└── tests/
    ├── Feature/                  # Integration tests
    └── Unit/                     # Unit tests
```

## DDD Architecture Layers

### Domain Layer (`app/Domain/`)
- **Entities**: Core business objects with identity
- **Value Objects**: Immutable objects without identity (FieldSettings, ConditionalVisibility, etc.)
- **Repository Interfaces**: Data access contracts
- **Domain Services**: Business logic that doesn't belong to entities

### Application Layer (`app/Application/`)
- Orchestrates domain objects
- Handles use cases
- DTOs for data transfer

### Infrastructure Layer (`app/Infrastructure/`)
- Eloquent implementations
- External service integrations
- Persistence concerns

### Presentation Layer (`app/Http/`)
- Controllers handle HTTP requests
- Transform domain objects to API responses

## Coding Conventions

### Controllers
```php
<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ExampleController extends Controller
{
    public function index(): JsonResponse
    {
        // Implementation
        return response()->json(['data' => $result]);
    }
}
```

### Value Objects
```php
<?php

namespace App\Domain\Modules\ValueObjects;

final readonly class ExampleValue
{
    public function __construct(
        public string $property,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            property: $data['property'] ?? '',
        );
    }

    public function toArray(): array
    {
        return ['property' => $this->property];
    }
}
```

### DTOs
```php
<?php

namespace App\Domain\Modules\DTOs;

final readonly class ExampleDTO
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromModel(Model $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
        );
    }
}
```

## Multi-Tenancy

- Central database: `vrtx_crm` (tenants table, domains, users)
- Tenant databases: `tenant_{tenant_id}` (modules, records, fields, etc.)
- Tenant routes in `routes/tenant-api.php`
- Use `tenancy()->central()` for central operations

## API Routes Structure

```
/api/v1/auth/       # Authentication
/api/v1/modules/    # Module CRUD
/api/v1/records/    # Dynamic records per module
/api/v1/views/      # Saved views and filters
/api/v1/wizard-drafts/ # Draft management
```

## Common Tasks

### Creating a new endpoint
1. Add route in `routes/tenant-api.php`
2. Create/update controller in `app/Http/Controllers/Api/`
3. Add any new DTOs/Value Objects to `app/Domain/Modules/`
4. Write tests in `tests/Feature/` or `tests/Unit/`

### Creating a migration
```bash
php artisan make:migration create_example_table --path=database/migrations/tenant
```

### Running migrations
```bash
php artisan tenants:migrate
```

## Artisan Commands

```bash
# Run migrations for all tenants
php artisan tenants:migrate

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Run tests
php artisan test
```

## Development

Use the project dev script:
```bash
./dev.sh
```

Or run Laravel directly:
```bash
cd backend && php artisan serve
```
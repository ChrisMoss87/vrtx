# Multi-Tenancy System

This document describes the custom DDD-based multi-tenancy implementation that provides tenant isolation through separate databases.

## Architecture Overview

The tenancy system follows Domain-Driven Design principles with a clean separation between domain logic and infrastructure concerns.

```
app/Domain/Tenancy/           # Domain layer - pure business logic
├── Entities/
│   ├── Tenant.php            # Tenant aggregate root
│   └── Domain.php            # Domain entity
├── ValueObjects/
│   └── TenantId.php          # Strongly typed ID
└── Repositories/
    └── TenantRepositoryInterface.php

app/Infrastructure/Tenancy/   # Infrastructure layer
├── TenancyManager.php        # Tenancy lifecycle coordinator
├── TenantManager.php         # Tenant CRUD operations
├── TenantContext.php         # Current tenant state (singleton)
├── TenancyServiceProvider.php
├── helpers.php               # tenant() and tenancy() functions
├── Bootstrappers/            # Make Laravel features tenant-aware
├── Middleware/               # HTTP tenant resolution
└── Jobs/                     # Async tenant operations
```

## How It Works

### 1. Tenant Resolution

When a request arrives, middleware identifies the tenant from the domain:

```php
// Full domain matching (e.g., techco.vrtx.local)
InitializeTenancyByDomain::class

// Subdomain matching (e.g., techco from techco.vrtx.local)
InitializeTenancyBySubdomain::class

// Tries domain first, falls back to subdomain
InitializeTenancyByDomainOrSubdomain::class
```

### 2. Bootstrappers

Once a tenant is identified, bootstrappers configure Laravel for that tenant:

| Bootstrapper | Purpose |
|-------------|---------|
| `DatabaseBootstrapper` | Switches DB connection to tenant database |
| `CacheBootstrapper` | Prefixes cache keys with tenant ID |
| `FilesystemBootstrapper` | Scopes storage paths to tenant |
| `QueueBootstrapper` | Maintains tenant context in queued jobs |

### 3. Database Isolation

Each tenant has a separate PostgreSQL database:
- Central database: `vrtx_crm` (stores tenants, domains, plugins)
- Tenant databases: `tenant{id}` (e.g., `tenanttechco`, `tenantacme`)

## Configuration

Edit `config/tenancy.php`:

```php
return [
    // Domains that should NOT trigger tenancy (central app)
    'central_domains' => [
        '127.0.0.1',
        'localhost',
    ],

    // Bootstrappers to run when tenancy initializes
    'bootstrappers' => [
        DatabaseBootstrapper::class,
        CacheBootstrapper::class,
        FilesystemBootstrapper::class,
        QueueBootstrapper::class,
    ],

    'database' => [
        'central_connection' => 'central',
        'template_connection' => 'pgsql',
        'prefix' => 'tenant',  // Database naming: tenant + id
        'suffix' => '',
    ],
];
```

## Usage

### Accessing Current Tenant

```php
// Get current tenant entity
$tenant = tenant();

// Get tenant ID
$tenantId = tenant('id');

// Get value from tenant data
$plan = tenant('plan');

// Check if tenancy is initialized
if (tenancy()->isInitialized()) {
    // ...
}
```

### Running Code in Tenant Context

```php
use App\Infrastructure\Tenancy\TenancyManager;

$manager = app(TenancyManager::class);

// Initialize for a specific tenant
$manager->initializeById('techco');

// Run callback in tenant context
$manager->runById('techco', function ($tenant) {
    // All queries go to tenanttechco database
    $users = User::all();
});

// End tenancy (revert to central)
$manager->end();
```

### Creating Tenants Programmatically

```php
use App\Infrastructure\Tenancy\TenantManager;

$tenantManager = app(TenantManager::class);

// Create tenant with database and migrations
$tenant = $tenantManager->create(
    id: 'newcorp',
    data: ['name' => 'New Corp', 'plan' => 'professional'],
    domain: 'newcorp.vrtx.local',
    createDatabase: true,
    migrate: true,
);

// Delete tenant and database
$tenantManager->delete('newcorp', deleteDatabase: true);
```

## Artisan Commands

### List Tenants

```bash
php artisan tenants:list
```

### Create Tenant

```bash
php artisan tenants:create newcorp \
    --domain=newcorp.vrtx.local \
    --name="New Corp" \
    --plan=starter
```

### Delete Tenant

```bash
php artisan tenants:delete newcorp --force
```

### Run Migrations

```bash
# All tenants
php artisan tenants:migrate

# Fresh migration (drops all tables)
php artisan tenants:migrate --fresh

# Specific tenants
php artisan tenants:migrate --tenants=techco,acme

# With seeding
php artisan tenants:migrate --seed
```

### Run Any Command for Tenants

```bash
# Run for all tenants
php artisan tenants:run "db:seed --class=SampleDataSeeder"

# Run for specific tenants
php artisan tenants:run "cache:clear" --tenants=techco
```

## Routes

Tenant routes are defined in:
- `routes/tenant.php` - Web routes
- `routes/tenant-api.php` - API routes

Both files apply tenancy middleware:

```php
Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api/v1')->group(function () {
    // Tenant-scoped routes
});
```

## Queue Jobs

Queue jobs automatically maintain tenant context. The `QueueBootstrapper` adds tenant ID to job payloads and restores context when processing.

```php
// Job dispatched from tenant context
ProcessReport::dispatch($reportId);

// Inside the job, tenant context is automatically restored
public function handle()
{
    // tenant('id') returns the original tenant
    $report = Report::find($this->reportId);
}
```

## Testing

Use `TenantTestCase` for tests that need tenant tables:

```php
use Tests\TenantTestCase;

class MyTest extends TenantTestCase
{
    public function test_something(): void
    {
        // Both central and tenant migrations are run
        // Tenancy middleware is disabled
        // Uses single test database
    }
}
```

## Database Connections

Configure in `config/database.php`:

```php
'connections' => [
    // Central database (tenants, domains, plugins)
    'central' => [
        'driver' => 'pgsql',
        'database' => env('DB_DATABASE', 'vrtx_crm'),
        // ...
    ],

    // Default connection (switched to tenant DB at runtime)
    'pgsql' => [
        'driver' => 'pgsql',
        'database' => env('DB_DATABASE', 'vrtx_crm'),
        // ...
    ],
],
```

## Adding New Bootstrappers

Create a class implementing `TenancyBootstrapperInterface`:

```php
namespace App\Infrastructure\Tenancy\Bootstrappers;

use App\Domain\Tenancy\Entities\Tenant;

class MyCustomBootstrapper implements TenancyBootstrapperInterface
{
    public function bootstrap(Tenant $tenant): void
    {
        // Configure something for the tenant
    }

    public function revert(): void
    {
        // Restore to central context
    }
}
```

Register in `config/tenancy.php`:

```php
'bootstrappers' => [
    // ...existing bootstrappers...
    MyCustomBootstrapper::class,
],
```

## Migration from Stancl/Tenancy

This implementation replaces the `stancl/tenancy` package with a pure DDD approach:

| Stancl | DDD Implementation |
|--------|-------------------|
| `Tenant` model | `App\Domain\Tenancy\Entities\Tenant` |
| `tenancy()->initialize()` | `TenancyManager::initialize()` |
| `tenant()` helper | Same - returns entity or data |
| `Stancl\Tenancy\Events\*` | Laravel events: `tenancy.initializing`, `tenancy.initialized`, etc. |
| `tenants:migrate` command | Same command, custom implementation |

Key differences:
- No Eloquent models - uses DB facade directly
- Pure domain entities with value objects
- Repository pattern for data access
- Explicit dependency injection

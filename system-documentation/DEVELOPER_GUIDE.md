# VRTX CRM Developer Guide

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Technology Stack](#technology-stack)
3. [Project Structure](#project-structure)
4. [Frontend Development](#frontend-development)
5. [Backend Development](#backend-development)
6. [API Reference](#api-reference)
7. [Testing](#testing)
8. [Deployment](#deployment)

---

## Architecture Overview

VRTX CRM is a multi-tenant SaaS application built with:
- **Frontend**: SvelteKit 2 with Svelte 5
- **Backend**: Laravel 11 with PHP 8.3
- **Database**: PostgreSQL with tenant-per-database architecture
- **Caching**: Redis for sessions and cache

### Multi-Tenancy
Each tenant has:
- A separate database (e.g., `tenant_acme`, `tenant_techco`)
- A subdomain (e.g., `acme.vrtx.local`)
- Isolated data and configuration

---

## Technology Stack

### Frontend
- **SvelteKit 2.x** - Application framework
- **Svelte 5** - UI framework with runes (`$state`, `$derived`, `$effect`)
- **Tailwind CSS 4** - Styling
- **shadcn/ui** - Component library (bits-ui based)
- **TipTap** - Rich text editor
- **Playwright** - E2E testing
- **Vitest** - Unit testing

### Backend
- **Laravel 11** - PHP framework
- **Stancl/Tenancy** - Multi-tenancy package
- **Spatie/Permission** - RBAC
- **PostgreSQL 16** - Database

---

## Project Structure

```
vrtx/
├── frontend/                    # SvelteKit application
│   ├── src/
│   │   ├── lib/
│   │   │   ├── api/            # API client modules
│   │   │   ├── components/     # Reusable components
│   │   │   │   ├── ui/         # shadcn/ui components
│   │   │   │   ├── form/       # Form field components
│   │   │   │   ├── datatable/  # DataTable components
│   │   │   │   ├── kanban/     # Kanban/pipeline components
│   │   │   │   ├── reporting/  # Report/chart components
│   │   │   │   └── workflow-builder/
│   │   │   ├── stores/         # Svelte stores
│   │   │   └── utils/          # Utilities
│   │   └── routes/
│   │       ├── (app)/          # Authenticated routes
│   │       └── (auth)/         # Public auth routes
│   ├── e2e/                    # Playwright tests
│   └── static/                 # Static assets
│
├── backend/                     # Laravel application
│   ├── app/
│   │   ├── Application/        # Application services
│   │   ├── Http/Controllers/   # API controllers
│   │   ├── Models/             # Eloquent models
│   │   └── Services/           # Business logic services
│   ├── database/
│   │   └── migrations/
│   │       ├── tenant/         # Tenant migrations
│   │       └── landlord/       # Central migrations
│   └── routes/
│       └── api_v1.php          # API routes
│
└── system-documentation/        # Documentation
```

---

## Frontend Development

### Component Conventions

**Svelte 5 Runes Pattern:**
```svelte
<script lang="ts">
  import { Button } from '$lib/components/ui/button';

  interface Props {
    title: string;
    count?: number;
  }

  let { title, count = 0 }: Props = $props();

  // Reactive state
  let items = $state<string[]>([]);

  // Derived values
  const total = $derived(items.length + count);

  // Effects
  $effect(() => {
    console.log('Total changed:', total);
  });
</script>
```

### API Client Usage

```typescript
// Import the API module
import { modulesApi, type Module } from '$lib/api/modules';
import { recordsApi } from '$lib/api/records';

// Fetch data
const modules = await modulesApi.getAll();
const records = await recordsApi.list('contacts', { page: 1, per_page: 25 });

// Create record
const newRecord = await recordsApi.create('contacts', {
  first_name: 'John',
  last_name: 'Doe'
});
```

### Form Components

Use the standardized form field components:

```svelte
<script lang="ts">
  import TextField from '$lib/components/form/TextField.svelte';
  import SelectField from '$lib/components/form/SelectField.svelte';

  let name = $state('');
  let status = $state('active');
</script>

<TextField label="Name" name="name" bind:value={name} required />
<SelectField
  label="Status"
  name="status"
  bind:value={status}
  options={[
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' }
  ]}
/>
```

### DataTable Integration

```svelte
<script lang="ts">
  import DataTable from '$lib/components/datatable/DataTable.svelte';

  const config = {
    module: { api_name: 'contacts' },
    columns: [
      { accessorKey: 'first_name', header: 'First Name' },
      { accessorKey: 'last_name', header: 'Last Name' },
      { accessorKey: 'email', header: 'Email' }
    ]
  };
</script>

<DataTable {config} />
```

---

## Backend Development

### Creating a Controller

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\RecordService;
use App\Http\Controllers\Controller;

class ContactsController extends Controller
{
    public function __construct(
        private RecordService $recordService
    ) {}

    public function index(Request $request)
    {
        $records = $this->recordService->list(
            'contacts',
            $request->all()
        );

        return response()->json($records);
    }
}
```

### Tenant-Aware Models

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Module extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'api_name',
        'is_active'
    ];
}
```

### Service Layer Pattern

```php
<?php

namespace App\Application\Services;

class ModuleService
{
    public function create(array $data): Module
    {
        // Validation
        $validated = $this->validate($data);

        // Business logic
        $module = Module::create($validated);

        // Events
        event(new ModuleCreated($module));

        return $module;
    }
}
```

---

## API Reference

### Authentication

```
POST /api/v1/auth/login
POST /api/v1/auth/register
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

### Modules

```
GET    /api/v1/modules           # List all modules
GET    /api/v1/modules/active    # List active modules
GET    /api/v1/modules/{id}      # Get module by ID
POST   /api/v1/modules           # Create module
PUT    /api/v1/modules/{id}      # Update module
DELETE /api/v1/modules/{id}      # Delete module
```

### Records

```
GET    /api/v1/records/{module}           # List records
GET    /api/v1/records/{module}/{id}      # Get record
POST   /api/v1/records/{module}           # Create record
PUT    /api/v1/records/{module}/{id}      # Update record
DELETE /api/v1/records/{module}/{id}      # Delete record
```

### Pipelines

```
GET    /api/v1/pipelines                  # List pipelines
GET    /api/v1/pipelines/{id}             # Get pipeline
POST   /api/v1/pipelines                  # Create pipeline
PUT    /api/v1/pipelines/{id}             # Update pipeline
DELETE /api/v1/pipelines/{id}             # Delete pipeline
POST   /api/v1/pipelines/{id}/move-card   # Move card to stage
```

### Reports

```
GET    /api/v1/reports                    # List reports
GET    /api/v1/reports/{id}               # Get report
POST   /api/v1/reports                    # Create report
PUT    /api/v1/reports/{id}               # Update report
DELETE /api/v1/reports/{id}               # Delete report
POST   /api/v1/reports/{id}/execute       # Execute report
GET    /api/v1/reports/{id}/export        # Export report
```

### Workflows

```
GET    /api/v1/workflows                  # List workflows
GET    /api/v1/workflows/{id}             # Get workflow
POST   /api/v1/workflows                  # Create workflow
PUT    /api/v1/workflows/{id}             # Update workflow
DELETE /api/v1/workflows/{id}             # Delete workflow
POST   /api/v1/workflows/{id}/toggle      # Toggle active status
GET    /api/v1/workflows/{id}/executions  # Get executions
```

---

## Testing

### E2E Tests (Playwright)

```bash
# Run all E2E tests
pnpm test:e2e

# Run specific test file
pnpm exec playwright test auth.test.ts

# Run with UI
pnpm test:e2e:ui

# Run headed (visible browser)
pnpm test:e2e:headed
```

### Writing E2E Tests

```typescript
import { test, expect, login } from './fixtures';

test.describe('Module Management', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('should display modules list', async ({ page }) => {
    await page.goto('/modules');
    await expect(page.locator('h1')).toContainText('Modules');
  });
});
```

### Unit Tests (Vitest)

```bash
pnpm test:unit
```

---

## Deployment

### Environment Variables

```env
# Frontend (.env)
PUBLIC_API_URL=https://api.vrtx.com

# Backend (.env)
APP_URL=https://vrtx.com
DB_HOST=localhost
DB_DATABASE=vrtx_central
REDIS_HOST=localhost
```

### Build Commands

```bash
# Frontend
cd frontend
pnpm build

# Backend
cd backend
composer install --no-dev
php artisan config:cache
php artisan route:cache
```

### Docker (Development)

```bash
# Start all services
./dev.sh

# Or manually
docker-compose up -d
```

---

## Code Style

### TypeScript
- Use strict mode
- Prefer `interface` over `type`
- Use explicit return types

### Svelte
- Use Svelte 5 runes (`$state`, `$derived`, `$effect`)
- Keep components focused and small
- Use TypeScript for all scripts

### PHP
- Follow PSR-12
- Use strict types
- Use dependency injection

---

## Contributing

1. Create a feature branch
2. Make changes
3. Run tests (`pnpm test`)
4. Run type check (`pnpm check`)
5. Submit pull request

---

*VRTX CRM Developer Guide - Version 1.0*

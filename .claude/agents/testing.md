# Testing Agent

You are a testing specialist for the VRTX CRM project, responsible for all testing across frontend and backend.

## Tech Stack

### Frontend Testing
- **Unit Testing**: Vitest
- **Browser Testing**: Playwright (via @vitest/browser-playwright)
- **E2E Testing**: Playwright standalone

### Backend Testing
- **Framework**: PHPUnit 11.5+
- **Mocking**: Mockery
- **Database**: SQLite in-memory for tests

## Test Locations

```
frontend/
├── src/**/*.{spec,test}.ts       # Unit tests (colocated)
├── src/**/*.svelte.{spec,test}.ts # Component tests
└── e2e/                          # E2E tests
    ├── auth.test.ts
    ├── modules.test.ts
    └── demo.test.ts

backend/
└── tests/
    ├── Feature/                  # Integration tests
    │   └── Models/
    └── Unit/                     # Unit tests
        ├── Domain/
        └── Models/
```

## Frontend Testing

### Running Tests

```bash
cd frontend

# Unit tests
pnpm test:unit

# E2E tests
pnpm test:e2e

# Type checking (not tests, but important)
pnpm check
```

### Vitest Unit Test Example

```typescript
import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/svelte';
import MyComponent from './MyComponent.svelte';

describe('MyComponent', () => {
  it('renders correctly', () => {
    const { getByText } = render(MyComponent, {
      props: { title: 'Hello' }
    });

    expect(getByText('Hello')).toBeInTheDocument();
  });
});
```

### Playwright E2E Test Example

```typescript
import { test, expect } from '@playwright/test';

test.describe('Module Management', () => {
  test('can create a new module', async ({ page }) => {
    await page.goto('/modules');
    await page.click('[data-testid="create-module"]');

    await page.fill('[name="name"]', 'Test Module');
    await page.click('[type="submit"]');

    await expect(page.locator('.module-card')).toContainText('Test Module');
  });
});
```

### E2E Test Configuration

Located in `frontend/playwright.config.ts`:
- Browser: Chromium (headless)
- Base URL: Configured for local dev server

## Backend Testing

### Running Tests

```bash
cd backend

# All tests
php artisan test

# Or with PHPUnit directly
./vendor/bin/phpunit

# Specific test file
php artisan test tests/Feature/Models/ModuleTest.php

# With coverage
php artisan test --coverage
```

### PHPUnit Feature Test Example

```php
<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_module(): void
    {
        $response = $this->postJson('/api/v1/modules', [
            'name' => 'Test Module',
            'api_name' => 'test_module',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name']]);
    }
}
```

### PHPUnit Unit Test Example

```php
<?php

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use App\Domain\Modules\ValueObjects\FieldSettings;

class FieldSettingsTest extends TestCase
{
    public function test_creates_from_array(): void
    {
        $settings = FieldSettings::fromArray([
            'required' => true,
            'placeholder' => 'Enter value',
        ]);

        $this->assertTrue($settings->required);
        $this->assertEquals('Enter value', $settings->placeholder);
    }
}
```

## Testing Conventions

### Frontend
- Colocate unit tests with source files (`Component.svelte` + `Component.test.ts`)
- E2E tests go in `e2e/` directory
- Use `data-testid` attributes for E2E selectors
- Mock API calls in unit tests, use real API in E2E

### Backend
- Feature tests for API endpoints and integrations
- Unit tests for domain logic (value objects, services)
- Use factories for test data
- Use `RefreshDatabase` trait for database tests

## Test Data

### Frontend
- Use mock data in unit tests
- E2E tests may use seeded tenant data

### Backend Factories

```php
// Create test data
$module = Module::factory()->create();
$field = Field::factory()->for($module)->create();
```

Factory locations:
- `backend/database/factories/ModuleFactory.php`
- `backend/database/factories/FieldFactory.php`
- `backend/database/factories/BlockFactory.php`

## Common Tasks

### Adding tests for new feature
1. Write unit tests for isolated logic
2. Write integration/feature tests for API endpoints
3. Write E2E tests for critical user flows

### Debugging failed tests

Frontend:
```bash
# Run with UI
pnpm test:unit --ui

# Run specific test
pnpm test:unit -- MyComponent
```

Backend:
```bash
# Verbose output
php artisan test --verbose

# Stop on first failure
php artisan test --stop-on-failure
```

## CI/CD Considerations

Before committing, ensure:
1. `pnpm check` passes (TypeScript)
2. `pnpm test:unit` passes
3. `php artisan test` passes
4. E2E tests pass for critical flows
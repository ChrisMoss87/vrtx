# VRTX CRM Comprehensive Testing Plan

## Overview
Complete testing strategy covering backend API endpoints, frontend UI interactions, factories, seeders, and all interactive features.

---

## Current Test Coverage Assessment

### Backend Tests (Existing)
| Category | Files | Coverage |
|----------|-------|----------|
| Unit Tests | 7 | Models, DTOs, Value Objects |
| Feature Tests | 16 | Core API endpoints |
| **Gap Areas** | - | Workflows, Blueprints, Email, Dashboards, Reports |

### Frontend Tests (Existing)
| Category | Files | Coverage |
|----------|-------|----------|
| E2E Tests | 10 | Auth, Records, Pipelines, basic flows |
| Unit Tests | 1 | Demo only |
| **Gap Areas** | - | DataTable features, Form validation, Workflow builder |

---

## Phase 1: Backend Factories & Seeders

### 1.1 New Factories Required

**File:** `backend/database/factories/`

| Factory | Model | Priority |
|---------|-------|----------|
| `WorkflowFactory.php` | Workflow | High |
| `WorkflowStepFactory.php` | WorkflowStep | High |
| `WorkflowExecutionFactory.php` | WorkflowExecution | Medium |
| `BlueprintFactory.php` | Blueprint | High |
| `BlueprintStateFactory.php` | BlueprintState | High |
| `BlueprintTransitionFactory.php` | BlueprintTransition | High |
| `ActivityFactory.php` | Activity | High |
| `AuditLogFactory.php` | AuditLog | Medium |
| `ReportFactory.php` | Report | High |
| `DashboardFactory.php` | Dashboard | High |
| `DashboardWidgetFactory.php` | DashboardWidget | Medium |
| `EmailAccountFactory.php` | EmailAccount | Medium |
| `EmailMessageFactory.php` | EmailMessage | Medium |
| `EmailTemplateFactory.php` | EmailTemplate | Medium |
| `ImportFactory.php` | Import | Medium |
| `ExportFactory.php` | Export | Low |
| `ApiKeyFactory.php` | ApiKey | Medium |
| `WebhookFactory.php` | Webhook | Medium |

### 1.2 Enhanced Seeders Required

**File:** `backend/database/seeders/`

| Seeder | Purpose | Priority |
|--------|---------|----------|
| `WorkflowSeeder.php` | Sample workflows with steps | High |
| `BlueprintSeeder.php` | Sample blueprints with states/transitions | High |
| `ActivitySeeder.php` | Sample activities for timeline | Medium |
| `ReportSeeder.php` | Sample reports (table, chart, pivot) | High |
| `DashboardSeeder.php` | Sample dashboards with widgets | High |
| `EmailSeeder.php` | Sample email templates | Medium |
| `IntegrationSeeder.php` | Sample API keys, webhooks | Low |
| `TestDataSeeder.php` | Large dataset for performance testing | High |

### 1.3 Factory Implementation Examples

```php
// WorkflowFactory.php
class WorkflowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'module_id' => Module::factory(),
            'trigger_type' => fake()->randomElement(['record_created', 'record_updated', 'field_changed']),
            'trigger_config' => [],
            'is_active' => true,
            'trigger_timing' => 'all',
        ];
    }

    public function withSteps(int $count = 3): static
    {
        return $this->has(WorkflowStep::factory()->count($count), 'steps');
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

// BlueprintFactory.php
class BlueprintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'module_id' => Module::factory(),
            'field_id' => Field::factory(),
            'is_active' => true,
        ];
    }

    public function withStates(int $count = 4): static
    {
        return $this->has(BlueprintState::factory()->count($count), 'states');
    }
}
```

---

## Phase 2: Backend API Tests

### 2.1 Test File Structure

```
tests/
├── Feature/
│   ├── Api/
│   │   ├── Auth/
│   │   │   └── AuthApiTest.php
│   │   ├── Modules/
│   │   │   ├── ModuleApiTest.php (exists)
│   │   │   ├── RecordApiTest.php (exists)
│   │   │   └── ViewsApiTest.php (exists)
│   │   ├── Pipelines/
│   │   │   ├── PipelineApiTest.php (exists)
│   │   │   ├── StageApiTest.php (exists)
│   │   │   └── KanbanApiTest.php (NEW)
│   │   ├── Workflows/
│   │   │   ├── WorkflowApiTest.php (NEW)
│   │   │   ├── WorkflowExecutionTest.php (NEW)
│   │   │   └── WorkflowTriggerTest.php (NEW)
│   │   ├── Blueprints/
│   │   │   ├── BlueprintApiTest.php (NEW)
│   │   │   ├── BlueprintStateApiTest.php (NEW)
│   │   │   ├── BlueprintTransitionApiTest.php (NEW)
│   │   │   └── BlueprintExecutionApiTest.php (NEW)
│   │   ├── Activities/
│   │   │   └── ActivityApiTest.php (NEW)
│   │   ├── AuditLog/
│   │   │   └── AuditLogApiTest.php (NEW)
│   │   ├── Reports/
│   │   │   └── ReportApiTest.php (NEW)
│   │   ├── Dashboards/
│   │   │   └── DashboardApiTest.php (NEW)
│   │   ├── Email/
│   │   │   ├── EmailAccountApiTest.php (NEW)
│   │   │   ├── EmailMessageApiTest.php (NEW)
│   │   │   └── EmailTemplateApiTest.php (NEW)
│   │   ├── DataManagement/
│   │   │   ├── ImportApiTest.php (NEW)
│   │   │   └── ExportApiTest.php (NEW)
│   │   ├── Integration/
│   │   │   ├── ApiKeyApiTest.php (NEW)
│   │   │   ├── WebhookApiTest.php (NEW)
│   │   │   └── IncomingWebhookApiTest.php (NEW)
│   │   ├── Search/
│   │   │   └── SearchApiTest.php (NEW)
│   │   └── Rbac/
│   │       └── RbacApiTest.php (NEW)
│   └── Services/
│       ├── WorkflowEngineTest.php (NEW)
│       ├── ConditionEvaluatorTest.php (NEW)
│       ├── BlueprintEngineTest.php (NEW)
│       ├── RecordServiceTest.php (NEW)
│       └── ReportServiceTest.php (NEW)
└── Unit/
    ├── Models/ (exists)
    ├── Services/
    │   ├── Workflow/
    │   │   └── Actions/ (NEW - test each action handler)
    │   └── Blueprint/ (NEW)
    └── Domain/ (exists)
```

### 2.2 API Endpoint Test Coverage

#### Authentication Endpoints
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/auth/register` | POST | Valid registration, duplicate email, weak password, missing fields |
| `/api/v1/auth/login` | POST | Valid login, invalid credentials, inactive user, rate limiting |
| `/api/v1/auth/logout` | POST | Valid logout, expired token |
| `/api/v1/auth/me` | GET | Authenticated user, unauthenticated |

#### Module Endpoints (27 test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/modules` | GET | List all, filter active, pagination, empty |
| `/api/v1/modules/{id}` | GET | Valid module, not found, by api_name |
| `/api/v1/modules` | POST | Valid creation, duplicate api_name, missing required |
| `/api/v1/modules/{id}` | PUT | Valid update, validation errors, not found |
| `/api/v1/modules/{id}` | DELETE | Valid delete, has records, not found |
| `/api/v1/modules/{id}/toggle-status` | POST | Activate, deactivate |
| `/api/v1/modules/reorder` | POST | Valid reorder, invalid ids |

#### Record Endpoints (35+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/records/{module}` | GET | List, pagination, filtering, sorting, search |
| `/api/v1/records/{module}/{id}` | GET | Valid record, not found, hidden fields |
| `/api/v1/records/{module}` | POST | Valid creation, validation, formula calc, unique fields |
| `/api/v1/records/{module}/{id}` | PUT | Valid update, workflow trigger, field changed |
| `/api/v1/records/{module}/{id}` | PATCH | Partial update, invalid fields |
| `/api/v1/records/{module}/{id}` | DELETE | Valid delete, cascade, not found |
| `/api/v1/records/{module}/bulk` | DELETE | Bulk delete, permissions |
| `/api/v1/records/{module}/bulk-update` | PATCH | Mass update, validation |
| `/api/v1/records/{module}/lookup` | GET | Lookup search, filtering |

#### Pipeline Endpoints (20+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/pipelines` | GET | List all pipelines |
| `/api/v1/pipelines/{id}` | GET | Single pipeline with stages |
| `/api/v1/pipelines` | POST | Create pipeline |
| `/api/v1/pipelines/{id}` | PUT | Update pipeline |
| `/api/v1/pipelines/{id}` | DELETE | Delete pipeline |
| `/api/v1/pipelines/module/{module}` | GET | Pipelines for module |
| `/api/v1/pipelines/{id}/kanban` | GET | Kanban data (records per stage) |
| `/api/v1/pipelines/{id}/move-record` | POST | Move record between stages |
| `/api/v1/pipelines/{id}/record-history/{recordId}` | GET | Stage history for record |
| `/api/v1/pipelines/{id}/reorder-stages` | POST | Reorder stages |

#### Workflow Endpoints (25+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/workflows` | GET | List, filter by module, filter by trigger |
| `/api/v1/workflows/{id}` | GET | Single workflow with steps |
| `/api/v1/workflows` | POST | Create workflow |
| `/api/v1/workflows/{id}` | PUT | Update workflow |
| `/api/v1/workflows/{id}` | DELETE | Delete workflow |
| `/api/v1/workflows/{id}/toggle-active` | POST | Activate/deactivate |
| `/api/v1/workflows/{id}/clone` | POST | Clone workflow |
| `/api/v1/workflows/{id}/trigger` | POST | Manual trigger |
| `/api/v1/workflows/trigger-types` | GET | Available triggers |
| `/api/v1/workflows/action-types` | GET | Available actions |
| `/api/v1/workflows/{id}/executions` | GET | Execution history |
| `/api/v1/workflows/{id}/executions/{execId}` | GET | Single execution details |
| `/api/v1/workflows/{id}/reorder-steps` | POST | Reorder steps |

#### Blueprint Endpoints (40+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/blueprints` | CRUD | Standard CRUD operations |
| `/api/v1/blueprints/{id}/states` | CRUD | State management |
| `/api/v1/blueprints/{id}/transitions` | CRUD | Transition management |
| `/api/v1/blueprints/{id}/toggle-active` | POST | Activate/deactivate |
| `/api/v1/blueprint-execution/record/{id}` | GET | Get record state |
| `/api/v1/blueprint-execution/start-transition` | POST | Start transition |
| `/api/v1/blueprint-execution/complete` | POST | Complete transition |
| `/api/v1/blueprint-execution/cancel` | POST | Cancel transition |
| `/api/v1/blueprint-execution/pending-approvals` | GET | Pending approvals |
| `/api/v1/blueprint-execution/approve/{id}` | POST | Approve transition |
| `/api/v1/blueprint-execution/reject/{id}` | POST | Reject transition |

#### Activity Endpoints (15+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/activities` | GET | List, filter by type, filter by subject |
| `/api/v1/activities/{id}` | GET | Single activity |
| `/api/v1/activities` | POST | Create activity |
| `/api/v1/activities/{id}` | PUT | Update activity |
| `/api/v1/activities/{id}` | DELETE | Delete activity |
| `/api/v1/activities/{id}/complete` | POST | Mark complete |
| `/api/v1/activities/{id}/toggle-pin` | POST | Pin/unpin |
| `/api/v1/activities/types` | GET | Activity types |
| `/api/v1/activities/timeline/{type}/{id}` | GET | Timeline for record |
| `/api/v1/activities/upcoming` | GET | Upcoming activities |
| `/api/v1/activities/overdue` | GET | Overdue activities |

#### Report Endpoints (20+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/reports` | CRUD | Standard CRUD |
| `/api/v1/reports/{id}/execute` | POST | Execute report |
| `/api/v1/reports/{id}/export` | POST | Export to CSV/Excel |
| `/api/v1/reports/{id}/toggle-favorite` | POST | Favorite toggle |
| `/api/v1/reports/{id}/duplicate` | POST | Clone report |
| `/api/v1/reports/types` | GET | Report types |
| `/api/v1/reports/fields/{moduleId}` | GET | Available fields |
| `/api/v1/reports/preview` | POST | Preview report |
| `/api/v1/reports/kpi` | POST | KPI calculation |

#### Dashboard Endpoints (15+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/dashboards` | CRUD | Standard CRUD |
| `/api/v1/dashboards/{id}/duplicate` | POST | Clone dashboard |
| `/api/v1/dashboards/{id}/set-default` | POST | Set as default |
| `/api/v1/dashboards/{id}/layout` | PUT | Update layout |
| `/api/v1/dashboards/{id}/widgets` | GET/POST | Widget management |
| `/api/v1/dashboards/{id}/widgets/{widgetId}` | PUT/DELETE | Widget CRUD |
| `/api/v1/dashboards/widget-types` | GET | Available widgets |
| `/api/v1/dashboards/{id}/all-widget-data` | GET | All widget data |

#### Email Endpoints (25+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/email-accounts` | CRUD | Account management |
| `/api/v1/email-accounts/{id}/test` | POST | Test connection |
| `/api/v1/email-accounts/{id}/sync` | POST | Sync emails |
| `/api/v1/email-messages` | CRUD | Message management |
| `/api/v1/email-messages/{id}/send` | POST | Send email |
| `/api/v1/email-messages/{id}/reply` | POST | Reply to email |
| `/api/v1/email-messages/{id}/forward` | POST | Forward email |
| `/api/v1/email-messages/mark-read` | POST | Bulk mark read |
| `/api/v1/email-templates` | CRUD | Template management |

#### Import/Export Endpoints (15+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/modules/{module}/imports` | GET/POST | Import management |
| `/api/v1/modules/{module}/imports/upload` | POST | Upload file |
| `/api/v1/modules/{module}/imports/template` | GET | Download template |
| `/api/v1/modules/{module}/imports/{id}/configure` | POST | Map fields |
| `/api/v1/modules/{module}/imports/{id}/validate` | POST | Validate data |
| `/api/v1/modules/{module}/imports/{id}/execute` | POST | Execute import |
| `/api/v1/modules/{module}/exports` | CRUD | Export management |

#### RBAC Endpoints (20+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/rbac/roles` | CRUD | Role management |
| `/api/v1/rbac/permissions` | GET | All permissions |
| `/api/v1/rbac/module-permissions/{moduleId}` | GET/PUT | Module permissions |
| `/api/v1/rbac/users/{userId}/roles` | GET/POST/DELETE | User role assignment |
| `/api/v1/rbac/me/permissions` | GET | Current user permissions |

#### Integration Endpoints (20+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/api-keys` | CRUD | API key management |
| `/api/v1/api-keys/{id}/regenerate` | POST | Regenerate key |
| `/api/v1/webhooks` | CRUD | Webhook management |
| `/api/v1/webhooks/{id}/test` | POST | Test webhook |
| `/api/v1/webhooks/{id}/deliveries` | GET | Delivery history |
| `/api/v1/incoming-webhooks` | CRUD | Incoming webhook management |

#### Search Endpoints (10+ test cases)
| Endpoint | Method | Test Cases |
|----------|--------|------------|
| `/api/v1/search` | GET | Global search |
| `/api/v1/search/quick` | GET | Quick search |
| `/api/v1/search/suggestions` | GET | Search suggestions |
| `/api/v1/search/history` | GET/DELETE | Search history |
| `/api/v1/search/saved` | CRUD | Saved searches |

---

## Phase 3: Frontend E2E Tests

### 3.1 Test File Structure

```
frontend/e2e/
├── auth/
│   ├── login.test.ts
│   ├── register.test.ts
│   └── logout.test.ts
├── datatable/
│   ├── sorting.test.ts
│   ├── filtering.test.ts
│   ├── pagination.test.ts
│   ├── column-management.test.ts
│   ├── bulk-actions.test.ts
│   ├── inline-editing.test.ts
│   ├── views.test.ts
│   └── search.test.ts
├── forms/
│   ├── field-validation.test.ts
│   ├── conditional-visibility.test.ts
│   ├── formula-fields.test.ts
│   ├── lookup-fields.test.ts
│   ├── file-upload.test.ts
│   └── all-field-types.test.ts
├── pipelines/
│   ├── kanban-view.test.ts
│   ├── drag-drop.test.ts
│   ├── stage-management.test.ts
│   └── pipeline-metrics.test.ts
├── workflows/
│   ├── workflow-builder.test.ts
│   ├── trigger-config.test.ts
│   ├── action-config.test.ts
│   ├── condition-builder.test.ts
│   └── execution-history.test.ts
├── blueprints/
│   ├── blueprint-designer.test.ts
│   ├── state-management.test.ts
│   ├── transitions.test.ts
│   └── approvals.test.ts
├── dashboards/
│   ├── dashboard-crud.test.ts
│   ├── widget-management.test.ts
│   └── widget-config.test.ts
├── reports/
│   ├── report-builder.test.ts
│   ├── report-execution.test.ts
│   └── report-export.test.ts
├── email/
│   ├── email-compose.test.ts
│   ├── email-templates.test.ts
│   └── email-accounts.test.ts
├── import-export/
│   ├── import-wizard.test.ts
│   └── export-flow.test.ts
├── wizard/
│   ├── multi-step.test.ts
│   ├── draft-management.test.ts
│   └── conditional-steps.test.ts
└── settings/
    ├── roles.test.ts
    └── integrations.test.ts
```

### 3.2 DataTable Test Scenarios

#### Sorting Tests (`datatable/sorting.test.ts`)
```typescript
describe('DataTable Sorting', () => {
  test('should sort by text column ascending', async ({ page }) => {
    // Click column header
    // Verify sort indicator
    // Verify first row has alphabetically first value
  });

  test('should sort by text column descending', async ({ page }) => {
    // Click column header twice
    // Verify descending sort indicator
    // Verify first row has alphabetically last value
  });

  test('should sort by number column', async ({ page }) => {
    // Click number column
    // Verify numerical ordering
  });

  test('should sort by date column', async ({ page }) => {
    // Click date column
    // Verify chronological ordering
  });

  test('should persist sort across pagination', async ({ page }) => {
    // Apply sort
    // Navigate to page 2
    // Verify sort is maintained
  });

  test('should clear sort on third click', async ({ page }) => {
    // Click 3 times
    // Verify no sort applied
  });
});
```

#### Filtering Tests (`datatable/filtering.test.ts`)
```typescript
describe('DataTable Filtering', () => {
  describe('Text Filters', () => {
    test('should filter by contains', async ({ page }) => {});
    test('should filter by starts_with', async ({ page }) => {});
    test('should filter by ends_with', async ({ page }) => {});
    test('should filter by equals', async ({ page }) => {});
    test('should filter by not_equals', async ({ page }) => {});
    test('should filter by is_empty', async ({ page }) => {});
    test('should filter by is_not_empty', async ({ page }) => {});
  });

  describe('Number Filters', () => {
    test('should filter by equals', async ({ page }) => {});
    test('should filter by greater_than', async ({ page }) => {});
    test('should filter by less_than', async ({ page }) => {});
    test('should filter by between', async ({ page }) => {});
  });

  describe('Date Filters', () => {
    test('should filter by exact date', async ({ page }) => {});
    test('should filter by date range', async ({ page }) => {});
    test('should filter by before date', async ({ page }) => {});
    test('should filter by after date', async ({ page }) => {});
    test('should filter by relative date (today, this week)', async ({ page }) => {});
  });

  describe('Select Filters', () => {
    test('should filter by single option', async ({ page }) => {});
    test('should filter by multiple options', async ({ page }) => {});
  });

  describe('Combined Filters', () => {
    test('should apply multiple filters (AND)', async ({ page }) => {});
    test('should apply filters with OR logic', async ({ page }) => {});
    test('should save filter as preset', async ({ page }) => {});
    test('should load saved filter preset', async ({ page }) => {});
  });

  describe('Filter UI', () => {
    test('should show filter chips for active filters', async ({ page }) => {});
    test('should remove filter by clicking chip X', async ({ page }) => {});
    test('should clear all filters', async ({ page }) => {});
  });
});
```

#### Column Management Tests (`datatable/column-management.test.ts`)
```typescript
describe('DataTable Column Management', () => {
  test('should toggle column visibility', async ({ page }) => {});
  test('should resize column by dragging', async ({ page }) => {});
  test('should persist column widths', async ({ page }) => {});
  test('should reorder columns via drag-drop', async ({ page }) => {});
  test('should reset to default columns', async ({ page }) => {});
});
```

#### Inline Editing Tests (`datatable/inline-editing.test.ts`)
```typescript
describe('DataTable Inline Editing', () => {
  test('should enable edit mode on cell click', async ({ page }) => {});
  test('should save changes on blur', async ({ page }) => {});
  test('should cancel edit on Escape', async ({ page }) => {});
  test('should validate field before saving', async ({ page }) => {});
  test('should show error for invalid input', async ({ page }) => {});
  test('should update via API on save', async ({ page }) => {});
});
```

### 3.3 Form Test Scenarios

#### Field Validation Tests (`forms/field-validation.test.ts`)
```typescript
describe('Form Field Validation', () => {
  test('should require required fields', async ({ page }) => {});
  test('should validate email format', async ({ page }) => {});
  test('should validate URL format', async ({ page }) => {});
  test('should validate phone format', async ({ page }) => {});
  test('should validate number range', async ({ page }) => {});
  test('should validate unique fields', async ({ page }) => {});
  test('should show inline validation errors', async ({ page }) => {});
  test('should show summary of all errors', async ({ page }) => {});
});
```

#### Conditional Visibility Tests (`forms/conditional-visibility.test.ts`)
```typescript
describe('Conditional Field Visibility', () => {
  test('should show field when condition met', async ({ page }) => {});
  test('should hide field when condition not met', async ({ page }) => {});
  test('should handle AND conditions', async ({ page }) => {});
  test('should handle OR conditions', async ({ page }) => {});
  test('should handle nested conditions', async ({ page }) => {});
  test('should clear hidden field values', async ({ page }) => {});
});
```

#### Formula Fields Tests (`forms/formula-fields.test.ts`)
```typescript
describe('Formula Fields', () => {
  test('should calculate on dependent field change', async ({ page }) => {});
  test('should handle math operations', async ({ page }) => {});
  test('should handle text concatenation', async ({ page }) => {});
  test('should handle date calculations', async ({ page }) => {});
  test('should be read-only', async ({ page }) => {});
});
```

### 3.4 Kanban/Pipeline Tests

#### Drag-Drop Tests (`pipelines/drag-drop.test.ts`)
```typescript
describe('Kanban Drag and Drop', () => {
  test('should drag card to different stage', async ({ page }) => {});
  test('should update record via API on drop', async ({ page }) => {});
  test('should revert on API error', async ({ page }) => {});
  test('should show visual feedback during drag', async ({ page }) => {});
  test('should prevent drop on invalid stages', async ({ page }) => {});
  test('should update stage counts after move', async ({ page }) => {});
});
```

---

## Phase 4: Performance & Load Testing

### 4.1 Backend Load Tests

**Tool:** Laravel Pest with parallel execution or k6

| Test | Target | Threshold |
|------|--------|-----------|
| Records list (100 records) | `GET /records/{module}` | < 200ms |
| Records list (1000 records) | `GET /records/{module}` | < 500ms |
| Records list (10000 records) | `GET /records/{module}` | < 1s |
| Kanban data (100 records) | `GET /pipelines/{id}/kanban` | < 300ms |
| Kanban data (1000 records) | `GET /pipelines/{id}/kanban` | < 800ms |
| Global search | `GET /search?q=test` | < 200ms |
| Report execution | `POST /reports/{id}/execute` | < 2s |
| Bulk update (100 records) | `PATCH /records/{module}/bulk-update` | < 3s |

### 4.2 Frontend Performance Tests

| Metric | Target |
|--------|--------|
| Initial page load | < 2s |
| DataTable render (100 rows) | < 100ms |
| DataTable render (1000 rows) | < 500ms |
| Filter application | < 200ms |
| Sort application | < 150ms |
| Form render (20 fields) | < 100ms |
| Kanban board render | < 300ms |

---

## Phase 5: Test Data Requirements

### 5.1 TestDataSeeder Specifications

```php
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create modules
        $modules = [
            'contacts' => 15, // 15 fields
            'companies' => 12,
            'deals' => 18,
            'activities' => 10,
        ];

        foreach ($modules as $name => $fieldCount) {
            $module = Module::factory()
                ->has(Block::factory()->count(3))
                ->has(Field::factory()->count($fieldCount))
                ->create(['api_name' => $name]);

            // Create 1000 records per module
            ModuleRecord::factory()
                ->count(1000)
                ->for($module)
                ->create();
        }

        // Create pipelines with stages
        Pipeline::factory()
            ->has(Stage::factory()->count(6))
            ->count(3)
            ->create();

        // Create workflows
        Workflow::factory()
            ->withSteps(5)
            ->count(10)
            ->create();

        // Create reports
        Report::factory()->count(20)->create();

        // Create dashboards
        Dashboard::factory()
            ->has(DashboardWidget::factory()->count(6))
            ->count(5)
            ->create();
    }
}
```

---

## Implementation Priority

### Week 1: Foundation
1. Create all missing factories
2. Create TestDataSeeder
3. Set up test database configuration

### Week 2: Backend API Tests
1. Auth API tests
2. Module/Record API tests
3. Pipeline API tests

### Week 3: Backend Feature Tests
1. Workflow API tests
2. Blueprint API tests
3. Activity/Audit API tests

### Week 4: Backend Service Tests
1. WorkflowEngine tests
2. ConditionEvaluator tests
3. RecordService tests

### Week 5: Frontend DataTable Tests
1. Sorting tests
2. Filtering tests
3. Column management tests

### Week 6: Frontend Form Tests
1. Field validation tests
2. Conditional visibility tests
3. Formula field tests

### Week 7: Frontend Feature Tests
1. Kanban/Pipeline tests
2. Workflow builder tests
3. Dashboard tests

### Week 8: Performance Tests
1. Backend load tests
2. Frontend performance tests
3. Optimization validation

---

## Test Commands

```bash
# Backend
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=WorkflowApiTest
php artisan test --parallel

# Frontend
pnpm test:e2e
pnpm test:e2e -- --grep "DataTable"
pnpm test:e2e -- auth/
```

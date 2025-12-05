# VRTX CRM - Complete Documentation

## Overview

VRTX CRM is a multi-tenant SaaS Customer Relationship Management system built with modern technologies. It provides comprehensive sales, contact, and workflow automation capabilities with complete data isolation between tenants.

---

## Table of Contents

1. [Technology Stack](#technology-stack)
2. [Architecture](#architecture)
3. [Multi-Tenancy](#multi-tenancy)
4. [Module System](#module-system)
5. [Field Types](#field-types)
6. [Form Builder](#form-builder)
7. [DataTable & Views](#datatable--views)
8. [Sales Pipelines](#sales-pipelines)
9. [Workflow Automation](#workflow-automation)
10. [Reporting & Analytics](#reporting--analytics)
11. [Dashboards](#dashboards)
12. [Import & Export](#import--export)
13. [Role-Based Access Control](#role-based-access-control)
14. [Email Integration](#email-integration)
15. [Activity Timeline](#activity-timeline)
16. [API Reference](#api-reference)
17. [Development Setup](#development-setup)
18. [Security](#security)

---

## Technology Stack

### Backend
| Technology | Version | Purpose |
|------------|---------|---------|
| Laravel | 11+ | PHP Framework |
| PHP | 8.3+ | Runtime |
| PostgreSQL | 16+ | Database (JSONB support) |
| Redis | Latest | Cache & Sessions |
| Stancl/Tenancy | v4 | Multi-tenant architecture |
| Spatie/Permission | Latest | RBAC implementation |
| Laravel Sanctum | Latest | API authentication |

### Frontend
| Technology | Version | Purpose |
|------------|---------|---------|
| SvelteKit | 2.x | Application framework |
| Svelte | 5.x | UI framework (runes) |
| TypeScript | 5.x | Type safety |
| Tailwind CSS | 4.x | Styling |
| shadcn-svelte | Latest | Component library (bits-ui) |
| TipTap | Latest | Rich text editor |
| Chart.js | Latest | Data visualization |
| dnd-kit | Latest | Drag and drop |

### Infrastructure
| Technology | Purpose |
|------------|---------|
| Docker | Container services |
| Nginx | Reverse proxy & subdomain routing |
| Mailhog | Email testing (development) |

---

## Architecture

### Domain-Driven Design Layers

```
┌─────────────────────────────────────────────────────┐
│                Presentation Layer                    │
│     Controllers, API Routes, JSON Resources         │
└─────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────┐
│                Application Layer                     │
│       Services, DTOs, Events, Commands              │
└─────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────┐
│                  Domain Layer                        │
│    Entities, Value Objects, Repository Interfaces   │
└─────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────┐
│               Infrastructure Layer                   │
│   Eloquent Models, Repository Implementations, DB   │
└─────────────────────────────────────────────────────┘
```

### Backend Directory Structure

```
backend/
├── app/
│   ├── Application/           # Application services
│   │   └── Services/          # RecordService, ModuleService, etc.
│   ├── Domain/
│   │   └── Modules/
│   │       ├── DTOs/          # Data Transfer Objects
│   │       ├── Entities/      # Domain entities
│   │       ├── Repositories/  # Repository interfaces & implementations
│   │       ├── Services/      # Domain services
│   │       └── ValueObjects/  # ConditionalVisibility, ValidationRule, etc.
│   ├── Http/Controllers/Api/  # API Controllers
│   ├── Models/                # Eloquent models
│   └── Services/
│       └── Workflow/          # Workflow engine & actions
├── database/
│   └── migrations/
│       ├── tenant/            # Per-tenant migrations
│       └── landlord/          # Central database migrations
└── routes/
    └── api_v1.php             # API routes
```

### Frontend Directory Structure

```
frontend/
├── src/
│   ├── lib/
│   │   ├── api/               # API client modules
│   │   │   ├── client.ts      # Base HTTP client
│   │   │   ├── modules.ts     # Modules API
│   │   │   ├── records.ts     # Records API
│   │   │   ├── pipelines.ts   # Pipelines API
│   │   │   ├── workflows.ts   # Workflows API
│   │   │   ├── reports.ts     # Reports API
│   │   │   └── dashboards.ts  # Dashboards API
│   │   ├── components/
│   │   │   ├── ui/            # shadcn-svelte base components
│   │   │   ├── form/          # Form field components (21 types)
│   │   │   ├── datatable/     # DataTable system
│   │   │   ├── form-builder/  # Visual form builder
│   │   │   ├── kanban/        # Pipeline/Kanban views
│   │   │   ├── reporting/     # Charts and report widgets
│   │   │   ├── workflow-builder/  # Workflow automation builder
│   │   │   ├── import-wizard/ # Data import wizard
│   │   │   └── export-builder/ # Data export configuration
│   │   ├── stores/            # Svelte stores (auth, tenant)
│   │   └── utils/             # Utility functions
│   └── routes/
│       ├── (app)/             # Authenticated routes
│       │   ├── dashboard/
│       │   ├── records/[moduleApiName]/
│       │   ├── modules/
│       │   ├── pipelines/
│       │   ├── workflows/
│       │   ├── reports/
│       │   ├── dashboards/
│       │   └── settings/
│       └── (auth)/            # Public auth routes
│           ├── login/
│           └── register/
└── e2e/                       # Playwright E2E tests
```

---

## Multi-Tenancy

### Architecture
VRTX uses **database-per-tenant** isolation:
- Each tenant has a separate PostgreSQL database
- Tenants identified by subdomain (e.g., `acme.vrtx.local`)
- Central database stores tenant metadata
- Complete data isolation between tenants

### Tenant Configuration

**Central Database** (`vrtx_crm`):
- `tenants` table - Tenant metadata
- `domains` table - Domain/subdomain mappings

**Tenant Databases** (e.g., `tenant_acme`):
- All CRM data: modules, records, users, workflows, etc.
- Completely isolated from other tenants

### Tenant URLs

| Tenant | URL | Database |
|--------|-----|----------|
| Acme Corp | http://acme.vrtx.local | tenant_acme |
| TechCo | http://techco.vrtx.local | tenant_techco |
| Startup Inc | http://startup.vrtx.local | tenant_startup |

### Tenant Management Commands

```bash
# Create tenant
php artisan tinker
$tenant = \App\Models\Tenant::create(['id' => 'acme']);
$tenant->domains()->create(['domain' => 'acme.vrtx.local']);

# Run tenant migrations
php artisan tenants:migrate

# Seed tenant data
php artisan tenants:seed

# List all tenants
\App\Models\Tenant::with('domains')->get();
```

---

## Module System

### Overview
Modules are configurable CRM entities (Contacts, Companies, Deals, etc.) with:
- Custom fields organized in blocks
- Configurable layouts and field widths
- Validation rules and default values
- Conditional visibility logic
- Lookup relationships between modules

### Core Module Types
- **Contacts** - Individual people
- **Companies** - Organizations/Accounts
- **Deals** - Sales opportunities
- **Tasks** - Action items
- **Activities** - Calls, meetings, notes
- **Custom Modules** - User-defined entities

### Module Structure

```typescript
interface Module {
  id: number;
  name: string;                    // "Sales Opportunities"
  singular_name: string;           // "Opportunity"
  api_name: string;                // "sales_opportunities"
  icon: string;                    // Lucide icon name
  description: string;
  is_active: boolean;
  display_order: number;
  settings: ModuleSettings;
  blocks: Block[];
}

interface Block {
  id: number;
  name: string;                    // "Basic Information"
  type: 'section' | 'tab';
  display_order: number;
  settings: {
    collapsible: boolean;
    default_collapsed: boolean;
    columns: 1 | 2 | 3;
    conditional_visibility?: ConditionalVisibility;
  };
  fields: Field[];
}

interface ModuleSettings {
  has_import: boolean;
  has_export: boolean;
  has_mass_actions: boolean;
  has_comments: boolean;
  has_attachments: boolean;
  has_activity_log: boolean;
  has_custom_views: boolean;
  record_name_field: string;
  enable_kanban_view?: boolean;
  kanban_field?: string;
}
```

---

## Field Types

VRTX supports **21 field types** with comprehensive configuration options:

### Text Fields
| Type | Description | Key Settings |
|------|-------------|--------------|
| `text` | Single-line text | min/max length, pattern, placeholder |
| `textarea` | Multi-line text | rows, max_length |
| `email` | Email address | pattern validation |
| `phone` | Phone number | format, country code |
| `url` | Web URL | protocols allowed |
| `rich_text` | HTML content | TipTap toolbar config |

### Numeric Fields
| Type | Description | Key Settings |
|------|-------------|--------------|
| `number` | Integer | min/max value, step |
| `decimal` | Float | precision, min/max |
| `currency` | Money | currency_code, precision |
| `percent` | Percentage | min/max, show_slider |

### Choice Fields
| Type | Description | Key Settings |
|------|-------------|--------------|
| `select` | Single dropdown | options, allow_custom |
| `multiselect` | Multiple selection | options, max_selections |
| `radio` | Radio buttons | options, layout |
| `checkbox` | Boolean checkbox | default_checked |
| `toggle` | Boolean switch | on_label, off_label |

### Date/Time Fields
| Type | Description | Key Settings |
|------|-------------|--------------|
| `date` | Date only | min/max date, format |
| `datetime` | Date + time | timezone support |
| `time` | Time only | format, step |

### Relationship Fields
| Type | Description | Key Settings |
|------|-------------|--------------|
| `lookup` | Related record | related_module, display_field, depends_on |

### Calculated Fields
| Type | Description | Key Settings |
|------|-------------|--------------|
| `formula` | Calculated value | formula, dependencies, return_type |

### Media Fields
| Type | Description | Key Settings |
|------|-------------|--------------|
| `file` | File upload | allowed_types, max_size |
| `image` | Image upload | dimensions, formats |

### Field Configuration

```typescript
interface Field {
  id: number;
  label: string;                   // "Opportunity Name"
  api_name: string;                // "opportunity_name"
  type: FieldType;
  description?: string;
  help_text?: string;
  is_required: boolean;
  is_unique: boolean;
  is_searchable: boolean;
  is_filterable: boolean;
  is_sortable: boolean;
  default_value?: any;
  display_order: number;
  width: 25 | 33 | 50 | 100;       // Percentage width
  validation_rules: ValidationRules;
  settings: FieldSettings;
  options?: FieldOption[];          // For select/multiselect/radio
}
```

### Conditional Visibility

Fields can be shown/hidden based on conditions:

```typescript
interface ConditionalVisibility {
  enabled: boolean;
  operator: 'and' | 'or';
  conditions: Condition[];
}

interface Condition {
  field: string;
  operator: ConditionOperator;
  value: any;
}

// Supported operators (17 total):
type ConditionOperator =
  | 'equals' | 'not_equals'
  | 'contains' | 'not_contains'
  | 'starts_with' | 'ends_with'
  | 'greater_than' | 'less_than'
  | 'greater_than_or_equal' | 'less_than_or_equal'
  | 'between' | 'in' | 'not_in'
  | 'is_empty' | 'is_not_empty'
  | 'is_checked' | 'is_not_checked';
```

### Formula Fields

Calculated fields support 30+ functions:

**Mathematical**: `SUM`, `SUBTRACT`, `MULTIPLY`, `DIVIDE`, `ROUND`, `CEILING`, `FLOOR`, `ABS`, `MIN`, `MAX`, `AVERAGE`

**Date/Time**: `TODAY`, `NOW`, `DAYS_BETWEEN`, `MONTHS_BETWEEN`, `YEARS_BETWEEN`, `ADD_DAYS`, `ADD_MONTHS`, `ADD_YEARS`, `FORMAT_DATE`

**Text**: `CONCAT`, `UPPER`, `LOWER`, `TRIM`, `LEFT`, `RIGHT`, `SUBSTRING`, `REPLACE`

**Logical**: `IF`, `AND`, `OR`, `NOT`, `IS_BLANK`, `IS_NUMBER`

**Lookup**: `LOOKUP` (retrieve value from option metadata)

Example:
```json
{
  "formula": "IF(stage = 'closed_won', final_amount, final_amount * (probability / 100))",
  "formula_type": "calculation",
  "return_type": "currency",
  "dependencies": ["stage", "final_amount", "probability"]
}
```

### Lookup Relationships

```typescript
interface LookupSettings {
  related_module_id: number;
  related_module_name: string;
  display_field: string;              // Field to show in dropdown
  search_fields: string[];            // Fields to search
  allow_create: boolean;              // Quick create modal
  cascade_delete: boolean;
  relationship_type: 'one_to_one' | 'many_to_one' | 'many_to_many';
  depends_on?: string;                // Parent field for cascading
  dependency_filter?: {
    field: string;
    operator: string;
    target_field: string;
  };
}
```

---

## Form Builder

### Visual Interface
- Drag-and-drop field placement from palette
- Block organization (sections, tabs)
- Column layout configuration (1, 2, or 3 columns)
- Field width adjustment (25%, 33%, 50%, 100%)
- Real-time preview

### Components

| Component | Purpose |
|-----------|---------|
| `FieldPalette.svelte` | Draggable field type selection |
| `FormCanvas.svelte` | Drop zone with layout preview |
| `FieldConfigPanel.svelte` | Field settings (basic, validation, display, advanced) |
| `BlockConfigPanel.svelte` | Block type and layout settings |
| `ConditionalVisibilityBuilder.svelte` | Visual rule builder |
| `FormulaEditor.svelte` | Formula editing with autocomplete |
| `LookupConfigurator.svelte` | Relationship configuration |

### Form Renderer

Dynamic form rendering based on module JSON:
- Parses module structure
- Renders blocks and fields
- Handles conditional visibility
- Validates on submit
- Calculates formula fields
- Manages lookup dependencies

---

## DataTable & Views

### Features
- Server-side pagination, sorting, filtering
- Column visibility toggle
- Saved custom views per user
- Bulk actions (edit, delete, export)
- Row selection with checkboxes
- Quick inline editing
- Advanced filter groups (AND/OR logic)

### Components

| Component | Purpose |
|-----------|---------|
| `DataTable.svelte` | Main table orchestrator |
| `DataTableHeader.svelte` | Column headers with sort |
| `DataTableRow.svelte` | Record rows |
| `DataTableFiltersDrawer.svelte` | Advanced filtering |
| `DataTableViewSwitcher.svelte` | View management |
| `DataTableActions.svelte` | Bulk action toolbar |
| `FilterGroup.svelte` | Nested filter conditions |

### Views Configuration

```typescript
interface DataTableView {
  id: string;
  name: string;
  is_default: boolean;
  columns: string[];            // Visible column API names
  sort?: { field: string; direction: 'asc' | 'desc' };
  filters?: FilterGroup;
}

interface FilterGroup {
  logic: 'and' | 'or';
  filters: Filter[];
  groups?: FilterGroup[];       // Nested groups
}

interface Filter {
  field: string;
  operator: string;
  value: any;
}
```

---

## Sales Pipelines

### Features
- Multiple pipelines per module
- Custom stages with colors and probabilities
- Kanban board view with drag-and-drop
- Stage automation triggers
- Win/loss tracking
- Pipeline analytics

### Pipeline Structure

```typescript
interface Pipeline {
  id: number;
  name: string;
  module_id: number;
  is_default: boolean;
  stages: PipelineStage[];
}

interface PipelineStage {
  id: number;
  name: string;
  color: string;                 // Hex color
  probability: number;           // 0-100%
  display_order: number;
  is_closed_won: boolean;
  is_closed_lost: boolean;
}
```

### Kanban Components

| Component | Purpose |
|-----------|---------|
| `KanbanBoard.svelte` | Main board layout |
| `KanbanColumn.svelte` | Stage column with cards |
| `KanbanCard.svelte` | Individual record card |
| `StageList.svelte` | Pipeline stage configuration |

### Pipeline Builder

Visual pipeline configuration:
- Add/remove/reorder stages
- Stage color picker
- Probability assignment
- Closed won/lost designation
- Default pipeline setting

---

## Workflow Automation

### Trigger Types
| Trigger | Description |
|---------|-------------|
| `record_created` | When a new record is created |
| `record_updated` | When an existing record is modified |
| `record_deleted` | When a record is deleted |
| `field_changed` | When a specific field value changes |
| `time_based` | Scheduled execution (cron) |
| `webhook` | External HTTP trigger |
| `manual` | User-initiated trigger |

### Action Types (14 total)
| Action | Description |
|--------|-------------|
| `send_email` | Send email notification |
| `send_notification` | In-app notification |
| `create_record` | Create a new record |
| `update_record` | Update existing record |
| `delete_record` | Delete a record |
| `update_field` | Update specific field value |
| `webhook` | Call external URL |
| `assign_user` | Assign record to user |
| `add_tag` | Add tag to record |
| `remove_tag` | Remove tag from record |
| `create_task` | Create a task |
| `move_stage` | Change pipeline stage |
| `delay` | Wait before next action |
| `condition` | Conditional branching |

### Workflow Structure

```typescript
interface Workflow {
  id: number;
  name: string;
  description?: string;
  module_id: number;
  trigger_type: TriggerType;
  trigger_config: TriggerConfig;
  conditions?: ConditionGroup;
  is_active: boolean;
  steps: WorkflowStep[];
}

interface WorkflowStep {
  id: number;
  workflow_id: number;
  action_type: ActionType;
  action_config: ActionConfig;
  order: number;
  on_success_step_id?: number;
  on_failure_step_id?: number;
}
```

### Condition Evaluator

21+ operators for workflow conditions:
- Comparison: `equals`, `not_equals`, `greater_than`, `less_than`, etc.
- String: `contains`, `starts_with`, `ends_with`
- List: `in`, `not_in`
- Null: `is_empty`, `is_not_empty`
- Date: `date_before`, `date_after`, `is_today`

### Execution Tracking

```typescript
interface WorkflowExecution {
  id: number;
  workflow_id: number;
  record_id: number;
  status: 'pending' | 'running' | 'completed' | 'failed';
  started_at: string;
  completed_at?: string;
  error_message?: string;
  step_logs: WorkflowStepLog[];
}
```

---

## Reporting & Analytics

### Report Types
| Type | Description |
|------|-------------|
| `table` | Tabular data display |
| `summary` | Aggregated metrics |
| `matrix` | Cross-tabulation |
| `chart` | Visual charts |

### Chart Types
- Line chart (trends over time)
- Bar chart (comparisons)
- Pie chart (distribution)
- Donut chart
- Funnel chart (conversion)
- Area chart

### Report Structure

```typescript
interface Report {
  id: number;
  name: string;
  module_id: number;
  type: ReportType;
  config: {
    columns: ReportColumn[];
    filters?: FilterGroup;
    groupBy?: string[];
    aggregations?: Aggregation[];
    sort?: SortConfig[];
    limit?: number;
  };
  chart_config?: ChartConfig;
}

interface ReportColumn {
  field: string;
  label: string;
  width?: number;
  aggregation?: 'sum' | 'avg' | 'count' | 'min' | 'max';
}

interface ChartConfig {
  type: ChartType;
  x_axis: string;
  y_axis: string;
  series?: string;
  colors?: string[];
}
```

### Report Components

| Component | Purpose |
|-----------|---------|
| `ReportBuilder.svelte` | Report configuration UI |
| `ReportViewer.svelte` | Report display |
| `ChartWidget.svelte` | Chart rendering |
| `TableReport.svelte` | Tabular report |
| `SummaryReport.svelte` | Metric cards |

---

## Dashboards

### Features
- Drag-and-drop widget layout
- Multiple dashboards per user
- Widget resize and positioning
- Real-time data refresh
- Dashboard sharing

### Widget Types
| Widget | Description |
|--------|-------------|
| `kpi` | Single metric display |
| `chart` | Chart visualization |
| `table` | Data table |
| `list` | Record list |
| `activity` | Activity feed |
| `pipeline` | Pipeline summary |

### Dashboard Structure

```typescript
interface Dashboard {
  id: number;
  name: string;
  is_default: boolean;
  layout: DashboardWidget[];
}

interface DashboardWidget {
  id: string;
  type: WidgetType;
  title: string;
  config: WidgetConfig;
  position: { x: number; y: number };
  size: { width: number; height: number };
}

interface KPIConfig {
  module_id: number;
  aggregation: 'count' | 'sum' | 'avg';
  field?: string;
  filters?: FilterGroup;
  comparison?: 'previous_period' | 'previous_year';
}
```

---

## Import & Export

### Import Wizard

Multi-step import process:
1. **Upload** - CSV/Excel file selection
2. **Mapping** - Map columns to module fields
3. **Preview** - Review data before import
4. **Import** - Execute with progress tracking
5. **Summary** - Results with error details

Features:
- Duplicate detection (skip, update, create)
- Field value transformation
- Validation before import
- Batch processing
- Error logging

### Export Builder

Configurable data export:
- Field selection
- Filter application
- Format selection (CSV, Excel, JSON)
- Scheduled exports

```typescript
interface ImportConfig {
  module_id: number;
  file_path: string;
  mapping: FieldMapping[];
  duplicate_handling: 'skip' | 'update' | 'create';
  batch_size: number;
}

interface ExportConfig {
  module_id: number;
  fields: string[];
  filters?: FilterGroup;
  format: 'csv' | 'xlsx' | 'json';
  include_headers: boolean;
}
```

---

## Role-Based Access Control

### Permission Types
| Level | Description |
|-------|-------------|
| **Module** | Create, Read, Update, Delete per module |
| **Field** | Read, Write permissions per field |
| **Record** | Owner, Team, All record access |
| **Action** | Import, Export, Bulk operations |

### Role Structure

```typescript
interface Role {
  id: number;
  name: string;
  description?: string;
  is_admin: boolean;
  module_permissions: ModulePermission[];
}

interface ModulePermission {
  module_id: number;
  can_create: boolean;
  can_read: boolean;
  can_update: boolean;
  can_delete: boolean;
  can_import: boolean;
  can_export: boolean;
  record_access_level: 'owner' | 'team' | 'all';
  field_permissions?: FieldPermission[];
}

interface FieldPermission {
  field_id: number;
  can_read: boolean;
  can_write: boolean;
}
```

### Default Roles
- **Administrator** - Full system access
- **Manager** - Team-level access
- **User** - Owner-level access
- **Read Only** - View-only access

---

## Email Integration

### Features
- Email composition with rich text
- Template system with merge fields
- Contact/record association
- Email tracking (opens, clicks)
- Bulk email sending
- SMTP configuration

### Email Templates

```typescript
interface EmailTemplate {
  id: number;
  name: string;
  subject: string;
  body_html: string;
  body_text?: string;
  merge_fields: string[];       // {{record.field_name}}
}
```

### Email Composer Components

| Component | Purpose |
|-----------|---------|
| `EmailComposer.svelte` | Email writing interface |
| `TemplateSelector.svelte` | Template selection |
| `MergeFieldInserter.svelte` | Variable insertion |
| `RecipientSelector.svelte` | To/CC/BCC selection |

---

## Activity Timeline

### Activity Types
- Emails sent/received
- Calls logged
- Meetings scheduled
- Notes added
- Tasks created/completed
- Field changes
- Stage transitions
- File uploads

### Activity Structure

```typescript
interface Activity {
  id: number;
  record_id: number;
  module_id: number;
  type: ActivityType;
  title: string;
  description?: string;
  metadata: Record<string, any>;
  created_by: number;
  created_at: string;
}
```

### Components

| Component | Purpose |
|-----------|---------|
| `ActivityTimeline.svelte` | Activity feed display |
| `ActivityItem.svelte` | Individual activity |
| `ActivityFilters.svelte` | Filter by type/date |
| `QuickNote.svelte` | Add note/call/meeting |

---

## API Reference

### Authentication

```
POST /api/v1/auth/login          # Login
POST /api/v1/auth/register       # Register
POST /api/v1/auth/logout         # Logout
GET  /api/v1/auth/me             # Current user
```

### Modules

```
GET    /api/v1/modules           # List all modules
GET    /api/v1/modules/active    # List active modules
GET    /api/v1/modules/{id}      # Get module details
POST   /api/v1/modules           # Create module
PUT    /api/v1/modules/{id}      # Update module
DELETE /api/v1/modules/{id}      # Delete module
POST   /api/v1/modules/{id}/toggle  # Activate/deactivate
```

### Records

```
GET    /api/v1/records/{module}           # List records (paginated)
GET    /api/v1/records/{module}/{id}      # Get single record
POST   /api/v1/records/{module}           # Create record
PUT    /api/v1/records/{module}/{id}      # Update record
DELETE /api/v1/records/{module}/{id}      # Delete record
POST   /api/v1/records/{module}/bulk      # Bulk operations
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

### Workflows

```
GET    /api/v1/workflows                  # List workflows
GET    /api/v1/workflows/{id}             # Get workflow
POST   /api/v1/workflows                  # Create workflow
PUT    /api/v1/workflows/{id}             # Update workflow
DELETE /api/v1/workflows/{id}             # Delete workflow
POST   /api/v1/workflows/{id}/toggle      # Toggle active
GET    /api/v1/workflows/{id}/executions  # Execution history
```

### Reports

```
GET    /api/v1/reports                    # List reports
GET    /api/v1/reports/{id}               # Get report
POST   /api/v1/reports                    # Create report
PUT    /api/v1/reports/{id}               # Update report
DELETE /api/v1/reports/{id}               # Delete report
POST   /api/v1/reports/{id}/execute       # Run report
GET    /api/v1/reports/{id}/export        # Export results
```

### Dashboards

```
GET    /api/v1/dashboards                 # List dashboards
GET    /api/v1/dashboards/{id}            # Get dashboard
POST   /api/v1/dashboards                 # Create dashboard
PUT    /api/v1/dashboards/{id}            # Update dashboard
DELETE /api/v1/dashboards/{id}            # Delete dashboard
```

### Import/Export

```
POST   /api/v1/imports                    # Start import
GET    /api/v1/imports/{id}               # Get import status
POST   /api/v1/exports                    # Start export
GET    /api/v1/exports/{id}               # Get export status
GET    /api/v1/exports/{id}/download      # Download file
```

---

## Development Setup

### Prerequisites
- PHP 8.3+
- Node.js 20+
- PostgreSQL 16+
- Redis
- Docker & Docker Compose

### Quick Start

```bash
# 1. Clone repository
git clone <repository-url>
cd vrtx

# 2. Start infrastructure
docker-compose up -d

# 3. Backend setup
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
./dev.sh

# 4. Frontend setup
cd ../frontend
pnpm install
pnpm dev

# 5. Access application
# Open http://acme.vrtx.local
# Login: john@acme.com / password123
```

### Environment Configuration

**Backend (.env)**
```env
APP_URL=http://vrtx.local
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=vrtx_crm
DB_USERNAME=vrtx_user
DB_PASSWORD=vrtx_password
REDIS_HOST=127.0.0.1
```

**Frontend (.env)**
```env
PUBLIC_API_URL=http://acme.vrtx.local/api/v1
```

### /etc/hosts Configuration

```
127.0.0.1 vrtx.local
127.0.0.1 acme.vrtx.local
127.0.0.1 techco.vrtx.local
127.0.0.1 startup.vrtx.local
```

### Test Accounts

| Tenant | Email | Password | Role |
|--------|-------|----------|------|
| Acme | john@acme.com | password123 | Admin |
| Acme | testuser@acme.com | password123 | User |
| TechCo | bob@techco.com | password123 | Admin |
| Startup | alice@startup.com | password123 | Admin |

### Development Commands

```bash
# Backend
./dev.sh                          # Start dev server
php artisan migrate               # Run migrations
php artisan tenants:migrate       # Run tenant migrations
php artisan test                  # Run tests

# Frontend
pnpm dev                          # Start dev server
pnpm build                        # Production build
pnpm check                        # TypeScript check
pnpm test:e2e                     # E2E tests
```

---

## Security

### Authentication
- JWT token authentication via Laravel Sanctum
- Tokens stored securely
- Token expiration configured
- Refresh token mechanism

### Password Security
- Bcrypt hashing
- Password validation rules enforced
- No plain text storage

### Data Protection
- Multi-tenant database isolation
- Parameterized queries (SQL injection prevention)
- XSS prevention (Svelte auto-escaping, DOMPurify)
- CORS configuration

### API Security
- Input validation on all endpoints
- HTTP security headers
- Rate limiting recommended
- Request logging for audit

### OWASP Top 10 Coverage
| Vulnerability | Status |
|---------------|--------|
| Broken Access Control | ✅ Mitigated (RBAC) |
| Cryptographic Failures | ✅ Mitigated (Bcrypt, HTTPS) |
| Injection | ✅ Mitigated (Parameterized queries) |
| Insecure Design | ✅ Addressed (Multi-tenant isolation) |
| Security Misconfiguration | ⚠️ Environment-dependent |
| Vulnerable Components | ⚠️ Regular updates needed |
| Authentication Failures | ✅ Mitigated (JWT + validation) |
| Software Integrity | ⚠️ CI/CD hardening needed |
| Security Logging | ⚠️ Needs audit logging |
| SSRF | ✅ Mitigated (Webhook validation) |

### Recommendations
- [ ] Implement API rate limiting
- [ ] Add two-factor authentication (2FA)
- [ ] Enable audit logging
- [ ] Regular dependency updates
- [ ] HTTPS with proper SSL certificates

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Cmd/Ctrl + K` | Global search |
| `Cmd/Ctrl + N` | New record |
| `Cmd/Ctrl + S` | Save |
| `Escape` | Close modal/cancel |
| `Enter` | Submit form |

---

## Summary Statistics

| Category | Count |
|----------|-------|
| Field Types | 21 |
| Condition Operators | 17+ |
| Formula Functions | 30+ |
| Workflow Triggers | 7 |
| Workflow Actions | 14 |
| Chart Types | 6 |
| Widget Types | 6 |

---

*VRTX CRM Documentation - Version 1.0*

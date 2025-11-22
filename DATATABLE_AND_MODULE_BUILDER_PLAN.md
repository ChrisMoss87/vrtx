# VRTX CRM - DataTable & Dynamic Module Builder Implementation Plan

## Overview

Building a **full-featured DataTable component** and **Dynamic Module Builder** system for a modern multi-tenant CRM. The system will allow tenants to:
1. Create custom modules (e.g., Contacts, Companies, Deals, Custom Entities)
2. Define fields dynamically with various field types
3. View, filter, sort, and paginate data in powerful datatables
4. Create, edit, and delete records through generated forms

---

## Resources Available (from `/useful` directory)

### Frontend Components
- **ModuleTable.svelte** - Basic table with sorting
- **DynamicForm.svelte** - Form generator from module definition
- **FieldDisplay.svelte** - Display field values
- **FieldEditorDrawer.svelte** - Edit field definitions
- **ModulePreview.svelte** - Preview module structure
- **FieldTemplatePicker.svelte** - Pick from predefined field templates
- **ValidationPanel.svelte** - Show validation errors
- Complete shadcn-svelte UI components (table, form, dialog, drawer, etc.)
- Field components: TextField, SelectField, DateField, CurrencyField, etc.

### Backend (Laravel)
- **Module Entity** - Domain-driven design structure
- **Field Entity** - Field definitions with types
- **Block Entity** - Grouping fields into blocks
- **ModuleRecord Entity** - Dynamic record storage
- **Repositories & Services** - Module management logic
- **Policies** - Permission system for modules

### Key Features Identified
- Field types: text, textarea, select, multiselect, date, datetime, currency, percent, checkbox, switch, radio, lookup, file, image
- Blocks for organizing fields
- Validation rules
- Module settings
- Relationships between modules

---

## Phase 1: Foundation & Setup (Week 1)

### 1.1 Database Schema Design

**Central Database (per tenant):**

```sql
-- Modules table (defines custom entities)
CREATE TABLE modules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    singular_name VARCHAR(255) NOT NULL,
    api_name VARCHAR(255) UNIQUE NOT NULL, -- e.g., 'contacts', 'companies'
    icon VARCHAR(100),
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    settings JSONB DEFAULT '{}',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

-- Blocks table (groups of fields)
CREATE TABLE blocks (
    id SERIAL PRIMARY KEY,
    module_id INT REFERENCES modules(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT 'standard', -- standard, repeating, grid
    display_order INT DEFAULT 0,
    settings JSONB DEFAULT '{}',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Fields table (field definitions)
CREATE TABLE fields (
    id SERIAL PRIMARY KEY,
    module_id INT REFERENCES modules(id) ON DELETE CASCADE,
    block_id INT REFERENCES blocks(id) ON DELETE CASCADE,
    label VARCHAR(255) NOT NULL,
    api_name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL, -- text, select, date, currency, etc.
    is_required BOOLEAN DEFAULT false,
    is_unique BOOLEAN DEFAULT false,
    is_searchable BOOLEAN DEFAULT true,
    is_filterable BOOLEAN DEFAULT true,
    is_sortable BOOLEAN DEFAULT true,
    display_order INT DEFAULT 0,
    settings JSONB DEFAULT '{}', -- field-specific settings
    validation_rules JSONB DEFAULT '{}',
    default_value TEXT,
    help_text TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(module_id, api_name)
);

-- Field options (for select, radio, multiselect fields)
CREATE TABLE field_options (
    id SERIAL PRIMARY KEY,
    field_id INT REFERENCES fields(id) ON DELETE CASCADE,
    label VARCHAR(255) NOT NULL,
    value VARCHAR(255) NOT NULL,
    color VARCHAR(50),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT true
);

-- Module relationships (one-to-many, many-to-many)
CREATE TABLE module_relationships (
    id SERIAL PRIMARY KEY,
    from_module_id INT REFERENCES modules(id) ON DELETE CASCADE,
    to_module_id INT REFERENCES modules(id) ON DELETE CASCADE,
    relationship_name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL, -- has_many, belongs_to, many_to_many
    settings JSONB DEFAULT '{}',
    created_at TIMESTAMP
);

-- Module records (dynamic data storage using JSONB)
CREATE TABLE module_records (
    id SERIAL PRIMARY KEY,
    module_id INT REFERENCES modules(id) ON DELETE CASCADE,
    data JSONB NOT NULL DEFAULT '{}',
    created_by INT REFERENCES users(id),
    updated_by INT REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX idx_module_records_module_id ON module_records(module_id);
CREATE INDEX idx_module_records_data ON module_records USING GIN (data);
CREATE INDEX idx_fields_module_id ON fields(module_id);
CREATE INDEX idx_blocks_module_id ON blocks(module_id);
```

### 1.2 Laravel Backend Setup

**File Structure:**
```
backend/app/
├── Domain/
│   └── Modules/
│       ├── Entities/
│       │   ├── Module.php
│       │   ├── Field.php
│       │   ├── Block.php
│       │   ├── FieldOption.php
│       │   ├── ModuleRecord.php
│       │   └── Relationship.php
│       ├── ValueObjects/
│       │   ├── FieldType.php
│       │   ├── BlockType.php
│       │   ├── RelationshipType.php
│       │   ├── FieldSettings.php
│       │   ├── ValidationRules.php
│       │   └── ModuleSettings.php
│       ├── Repositories/
│       │   ├── ModuleRepositoryInterface.php
│       │   ├── ModuleRecordRepositoryInterface.php
│       │   └── Implementations/
│       │       ├── EloquentModuleRepository.php
│       │       └── EloquentModuleRecordRepository.php
│       ├── Services/
│       │   ├── ModuleService.php
│       │   ├── ModuleRecordService.php
│       │   ├── FieldService.php
│       │   └── ValidationService.php
│       └── DTOs/
│           ├── CreateModuleDTO.php
│           ├── UpdateModuleDTO.php
│           ├── CreateFieldDTO.php
│           └── ModuleRecordDTO.php
├── Http/
│   └── Controllers/
│       └── Api/
│           ├── ModuleController.php
│           ├── ModuleRecordController.php
│           ├── FieldController.php
│           └── ModuleBuilderController.php
├── Models/ (Eloquent)
│   ├── Module.php
│   ├── Field.php
│   ├── Block.php
│   ├── FieldOption.php
│   └── ModuleRecord.php
└── Policies/
    ├── ModulePolicy.php
    └── ModuleRecordPolicy.php
```

**Key Tasks:**
- [x] Review existing code from `/useful`
- [ ] Create migrations for all tables
- [ ] Implement Eloquent models
- [ ] Build repository pattern
- [ ] Create service layer
- [ ] Add validation logic

---

## Phase 2: Backend API Development (Week 2)

### 2.1 Module Management API

**Endpoints:**

```php
// Module CRUD
GET    /api/v1/modules                  // List all modules
POST   /api/v1/modules                  // Create module
GET    /api/v1/modules/{apiName}        // Get module details
PUT    /api/v1/modules/{apiName}        // Update module
DELETE /api/v1/modules/{apiName}        // Delete module
PATCH  /api/v1/modules/{apiName}/toggle // Activate/deactivate

// Field Management
POST   /api/v1/modules/{apiName}/fields           // Add field
PUT    /api/v1/modules/{apiName}/fields/{fieldId} // Update field
DELETE /api/v1/modules/{apiName}/fields/{fieldId} // Delete field
POST   /api/v1/modules/{apiName}/fields/reorder   // Reorder fields

// Block Management
POST   /api/v1/modules/{apiName}/blocks           // Add block
PUT    /api/v1/modules/{apiName}/blocks/{blockId} // Update block
DELETE /api/v1/modules/{apiName}/blocks/{blockId} // Delete block
```

### 2.2 Module Records API (Dynamic CRUD)

**Endpoints:**

```php
// Records CRUD
GET    /api/v1/modules/{apiName}/records              // List records (with filters, sort, search)
POST   /api/v1/modules/{apiName}/records              // Create record
GET    /api/v1/modules/{apiName}/records/{id}         // Get record
PUT    /api/v1/modules/{apiName}/records/{id}         // Update record
DELETE /api/v1/modules/{apiName}/records/{id}         // Delete record
POST   /api/v1/modules/{apiName}/records/bulk-delete  // Bulk delete
POST   /api/v1/modules/{apiName}/records/bulk-update  // Bulk update

// Export
GET    /api/v1/modules/{apiName}/records/export       // Export to CSV/Excel
```

**Query Parameters for GET /records:**
```
?page=1
&per_page=25
&sort[0][field]=name&sort[0][direction]=asc
&filters[0][field]=status&filters[0][operator]=eq&filters[0][value]=active
&search=john
&include=relationships
```

### 2.3 Module Record Service Implementation

**Key Features:**
- Dynamic validation based on field definitions
- JSONB querying for filtering/sorting
- Full-text search across searchable fields
- Relationship loading
- Audit trail (created_by, updated_by)

**Simplified Filter System:**
```php
// Instead of complex filter builders, use simple operators:
'eq'        => '='           // equals
'neq'       => '!='          // not equals
'gt'        => '>'           // greater than
'gte'       => '>='          // greater than or equal
'lt'        => '<'           // less than
'lte'       => '<='          // less than or equal
'contains'  => 'ILIKE %X%'   // contains (case-insensitive)
'starts'    => 'ILIKE X%'    // starts with
'ends'      => 'ILIKE %X'    // ends with
'in'        => 'IN'          // in array
'not_in'    => 'NOT IN'      // not in array
'null'      => 'IS NULL'     // is null
'not_null'  => 'IS NOT NULL' // is not null
'between'   => 'BETWEEN'     // between two values
```

---

## Phase 3: Enhanced DataTable Component (Week 3)

### 3.1 DataTable Features

**Core Features:**
- Pagination (10, 25, 50, 100, 250, 500 per page)
- Sorting (single column, multi-column)
- Filtering (simplified UI)
- Search (global search across all searchable fields)
- Column visibility toggle
- Column resizing
- Row selection (single, multiple)
- Bulk actions
- Export to CSV/Excel
- Responsive (mobile-friendly)
- Loading states & skeleton loaders
- Empty states
- Error handling

### 3.2 Simplified Filter UI

**Instead of complex filter builders:**

```svelte
<FilterBar>
  <!-- Quick filters (chips) -->
  <QuickFilter field="status" value="active" label="Active Only" />
  <QuickFilter field="created_at" operator="gte" value="last_30_days" label="Last 30 Days" />

  <!-- Add filter dropdown -->
  <AddFilterDropdown>
    <FilterOption field="status" type="select" />
    <FilterOption field="created_at" type="date_range" />
    <FilterOption field="amount" type="number_range" />
  </AddFilterDropdown>

  <!-- Global search -->
  <SearchInput placeholder="Search..." />
</FilterBar>
```

**Filter Types:**
- Text filters: equals, contains, starts with, ends with
- Number filters: equals, greater than, less than, between
- Date filters: today, yesterday, last 7 days, last 30 days, custom range
- Select filters: equals, in list
- Boolean filters: true/false toggle

### 3.3 DataTable Component Structure

```
frontend/src/lib/components/datatable/
├── DataTable.svelte           # Main component
├── DataTableHeader.svelte     # Column headers with sorting
├── DataTableRow.svelte        # Data rows
├── DataTableCell.svelte       # Individual cells with formatters
├── DataTablePagination.svelte # Pagination controls
├── DataTableFilters.svelte    # Simplified filter UI
├── DataTableSearch.svelte     # Global search
├── DataTableToolbar.svelte    # Actions, export, etc.
├── DataTableColumnMenu.svelte # Column visibility/settings
└── DataTableBulkActions.svelte # Bulk operations
```

**Component Props:**
```typescript
interface DataTableProps {
  module: Module;              // Module definition
  records: PaginatedRecords;   // Data
  loading?: boolean;
  onRefresh?: () => void;
  onSort?: (field: string, direction: 'asc' | 'desc') => void;
  onFilter?: (filters: Filter[]) => void;
  onSearch?: (query: string) => void;
  onPageChange?: (page: number) => void;
  onRowClick?: (record: ModuleRecord) => void;
  onBulkAction?: (action: string, ids: number[]) => void;
  selectable?: boolean;
  exportable?: boolean;
}
```

---

## Phase 4: Module Builder UI (Week 4)

### 4.1 Module Builder Pages

**Route Structure:**
```
/modules                     # List all modules
/modules/new                 # Create new module
/modules/{apiName}           # View module records (DataTable)
/modules/{apiName}/settings  # Edit module definition
/modules/{apiName}/new       # Create new record
/modules/{apiName}/{id}      # View/edit record
```

### 4.2 Module Builder Interface

**Components:**
```
frontend/src/lib/components/module-builder/
├── ModuleBuilderLayout.svelte     # Main builder layout
├── ModuleSettings.svelte          # Name, icon, description
├── BlockEditor.svelte             # Add/edit blocks
├── FieldEditor.svelte             # Add/edit fields
├── FieldTypeSelector.svelte       # Select field type
├── FieldSettingsPanel.svelte      # Field-specific settings
├── FieldTemplates.svelte          # Predefined field templates
├── RelationshipEditor.svelte      # Define relationships
├── ModulePreview.svelte           # Live preview
└── ValidationPanel.svelte         # Show validation rules
```

**Field Templates (Quick Add):**
- Name (text, required)
- Email (email, validated)
- Phone (tel, formatted)
- Address (text)
- Website (url)
- Company (lookup to companies module)
- Status (select: active, inactive)
- Priority (select: low, medium, high)
- Amount (currency, USD)
- Percentage (percent, 0-100)
- Date Created (datetime, auto)
- Notes (textarea)

### 4.3 Module Builder Workflow

**Step-by-Step:**
1. **Create Module** → Name, singular name, icon, description
2. **Add Blocks** → Group related fields
3. **Add Fields** → From templates or custom
4. **Configure Fields** → Set type, validation, settings
5. **Define Relationships** → Link to other modules
6. **Preview** → See how it looks
7. **Activate** → Make module live

**Features:**
- Drag-and-drop field reordering
- Live preview of form
- Field template library
- Validation rule builder
- Import/export module definitions (JSON)
- Duplicate module/fields
- Undo/redo support

---

## Phase 5: Dynamic Forms & Record Management (Week 5)

### 5.1 Dynamic Form Generator

**Form Component:**
```svelte
<DynamicForm
  module={moduleDefinition}
  record={existingRecord}
  onSubmit={handleSubmit}
  onCancel={handleCancel}
/>
```

**Features:**
- Auto-generate form from module definition
- Conditional field visibility
- Real-time validation
- File uploads
- Image preview
- Relationship pickers
- Auto-save drafts
- Form sections from blocks
- Responsive layout

### 5.2 Record Detail View

**Layout:**
```
┌─────────────────────────────────────┐
│ Header: Name, Status, Actions       │
├─────────────────────────────────────┤
│ Tabs: Details | Related | Activity  │
├─────────────────────────────────────┤
│ ┌─────────────┐ ┌─────────────────┐ │
│ │             │ │                 │ │
│ │   Details   │ │   Quick Info    │ │
│ │   (Blocks)  │ │   (Summary)     │ │
│ │             │ │                 │ │
│ └─────────────┘ └─────────────────┘ │
└─────────────────────────────────────┘
```

**Components:**
```
frontend/src/lib/components/records/
├── RecordDetail.svelte         # Main detail view
├── RecordHeader.svelte         # Header with actions
├── RecordTabs.svelte           # Tab navigation
├── RecordDetailsTab.svelte     # Details tab content
├── RecordRelatedTab.svelte     # Related records
├── RecordActivityTab.svelte    # Activity timeline
└── RecordQuickInfo.svelte      # Sidebar summary
```

---

## Phase 6: Advanced Features (Week 6)

### 6.1 Bulk Operations

**Features:**
- Bulk select (all on page, all matching filter)
- Bulk delete
- Bulk update (change status, assign owner, etc.)
- Bulk export
- Progress indicators

### 6.2 Views & Saved Filters

**Features:**
- Save current filter/sort as a view
- Share views with team
- Default view per user
- Predefined views (All, My Records, Recent, etc.)

### 6.3 Import/Export

**Import:**
- CSV import with mapping
- Validation before import
- Preview import data
- Error handling

**Export:**
- Export current view
- Export all records
- Format: CSV, Excel
- Include related records option

### 6.4 Activity Timeline

**Track:**
- Record created
- Field changes (with old/new values)
- Status changes
- Related records added/removed
- Comments/notes added

---

## Implementation Priorities

### High Priority (Must Have)
1. ✅ Module CRUD API
2. ✅ Module Record CRUD API
3. ✅ DataTable with pagination, sorting, search
4. ✅ Simplified filters
5. ✅ Module Builder UI
6. ✅ Dynamic form generator
7. ✅ Record detail view

### Medium Priority (Should Have)
8. ⏳ Bulk operations
9. ⏳ Column visibility/resize
10. ⏳ Export to CSV
11. ⏳ Field templates
12. ⏳ Relationship support
13. ⏳ Validation panel

### Low Priority (Nice to Have)
14. 📋 Saved views
15. 📋 Import CSV
16. 📋 Activity timeline
17. 📋 Module templates (import/export)
18. 📋 Advanced filtering (AND/OR groups)
19. 📋 Kanban view
20. 📋 Calendar view

---

## Technical Specifications

### Frontend Stack
- **Framework:** SvelteKit 2
- **UI Library:** shadcn-svelte (Tailwind CSS)
- **Icons:** lucide-svelte
- **State:** Svelte 5 runes ($state, $derived, $effect)
- **Forms:** Native Svelte with validation
- **Tables:** Custom DataTable component
- **Date:** date-fns
- **API:** Fetch API with apiClient

### Backend Stack
- **Framework:** Laravel 12
- **Database:** PostgreSQL with JSONB
- **Authentication:** Laravel Sanctum
- **Multi-tenancy:** stancl/tenancy
- **Architecture:** Domain-Driven Design
- **Testing:** PHPUnit, Pest

### Performance Considerations
- Pagination (never load all records)
- JSONB indexes for fast filtering
- Lazy loading relationships
- Virtual scrolling for large datasets
- Debounced search
- Optimistic UI updates
- Caching module definitions

---

## Testing Strategy

### Backend Tests
- Unit tests for entities
- Integration tests for repositories
- Feature tests for API endpoints
- Validation tests
- Permission tests

### Frontend Tests
- Component tests (Vitest)
- E2E tests (Playwright)
- Filter/sort/pagination tests
- Form validation tests
- Accessibility tests

---

## Migration from `/useful` Directory

### Step 1: Copy & Adapt Components
1. Copy shadcn components → `frontend/src/lib/components/ui/`
2. Copy module components → `frontend/src/lib/components/modules/`
3. Copy form components → `frontend/src/lib/components/form/`
4. Adapt to current project structure

### Step 2: Backend Migration
1. Review Domain entities
2. Simplify if needed (avoid over-engineering)
3. Adapt to our multi-tenancy setup
4. Create migrations from entity definitions

### Step 3: Integration
1. Wire up API endpoints
2. Test with Postman/cURL
3. Connect frontend components
4. Test full flow

---

## Next Steps

**Immediate Actions:**
1. Review this plan with stakeholder
2. Create database migrations
3. Set up backend models and repositories
4. Build Module CRUD API
5. Create DataTable component
6. Implement simplified filters
7. Build module builder UI
8. Test end-to-end flow

**Success Criteria:**
- Can create a "Contacts" module with fields
- Can add/edit/delete contact records
- Can filter, sort, search contacts in DataTable
- Can export contacts to CSV
- All features work in multi-tenant environment
- Mobile-responsive UI
- Fast performance (< 500ms API response)

---

## Timeline Summary

| Week | Focus | Deliverables |
|------|-------|-------------|
| 1 | Foundation | DB schema, migrations, models |
| 2 | Backend API | Module & Record CRUD endpoints |
| 3 | DataTable | Full-featured table with filters |
| 4 | Module Builder | UI to create/edit modules |
| 5 | Forms & Detail | Dynamic forms, record views |
| 6 | Polish | Bulk ops, export, testing |

**Total:** 6 weeks for MVP with core features
**Extended:** +2-4 weeks for advanced features

---

## Questions to Resolve

1. **Field Types:** Start with basic types or implement all 15+ types immediately?
2. **Relationships:** Implement in Phase 1 or defer to Phase 2?
3. **Permissions:** Field-level permissions or module-level only?
4. **Audit Trail:** Full audit or just created_at/updated_at?
5. **File Storage:** Local or S3? Max file size?
6. **Multi-currency:** Support or USD only?
7. **Localization:** i18n needed?
8. **API Versioning:** /v1/ only or plan for /v2/?

---

*This plan is subject to adjustment based on feedback and priorities.*

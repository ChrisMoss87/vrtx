# Phase 1: Dynamic Module System Foundation - Detailed Workflows

## Overview
**Duration:** Weeks 1-2 (80-100 hours)
**Goal:** Complete dynamic module/field/block system with full CRUD operations

---

## Workflow 1.1: Backend Value Objects (6-8 hours)

### Tasks
1. **Create ConditionalVisibility Value Object** (2h)
   - File: `backend/app/Domain/Modules/ValueObjects/ConditionalVisibility.php`
   - Properties: `enabled`, `operator` (and/or), `conditions[]`
   - Methods: `fromArray()`, `jsonSerialize()`, `evaluate()`
   - Unit tests

2. **Create ValidationRule Value Object** (1.5h)
   - File: `backend/app/Domain/Modules/ValueObjects/ValidationRule.php`
   - Properties: `rules[]`, `messages[]`, `custom_validation`
   - Methods: `fromArray()`, `toValidationArray()`, `merge()`
   - Unit tests

3. **Create LookupConfiguration Value Object** (2h)
   - File: `backend/app/Domain/Modules/ValueObjects/LookupConfiguration.php`
   - Properties: `related_module_id`, `display_field`, `search_fields[]`, etc.
   - Methods: `fromArray()`, `jsonSerialize()`, `hasDependency()`
   - Unit tests

4. **Create DependencyFilter Value Object** (1.5h)
   - File: `backend/app/Domain/Modules/ValueObjects/DependencyFilter.php`
   - Properties: `field`, `operator`, `target_field`, `value`
   - Methods: `fromArray()`, `buildQuery()`, `evaluate()`
   - Unit tests

### Acceptance Criteria
- [ ] All value objects are immutable (readonly)
- [ ] All have `fromArray()` and `jsonSerialize()` methods
- [ ] 100% test coverage
- [ ] Type hints on all properties and methods

---

## Workflow 1.2: Database Migrations (3-4 hours)

### Tasks
1. **Create Advanced Fields Migration** (2h)
   - File: `backend/database/migrations/YYYY_MM_DD_add_advanced_features_to_fields.php`
   - Add columns:
     ```sql
     ALTER TABLE fields ADD COLUMN conditional_visibility JSON;
     ALTER TABLE fields ADD COLUMN formula_definition JSON;
     ALTER TABLE fields ADD COLUMN lookup_settings JSON;
     ALTER TABLE fields ADD COLUMN dependencies JSON;
     ALTER TABLE fields ADD COLUMN calculate_on_change JSON;
     ```
   - Add indexes on JSON fields for performance
   - Migration up/down methods

2. **Create Module Settings Migration** (1h)
   - File: `backend/database/migrations/YYYY_MM_DD_add_module_settings.php`
   - Expand `settings` column structure
   - Add default values
   - Create indexes

3. **Test Migrations** (1h)
   - Run migrations up/down
   - Test with sample data
   - Verify indexes created
   - Document migration order

### Acceptance Criteria
- [ ] Migrations run successfully
- [ ] Rollback works correctly
- [ ] Indexes improve query performance
- [ ] No data loss on existing records

---

## Workflow 1.3: Module Service Layer (8-10 hours)

### Tasks
1. **Create ModuleService** (3h)
   - File: `backend/app/Domain/Modules/Services/ModuleService.php`
   - Methods:
     - `createModule(array $data): Module`
     - `updateModule(Module $module, array $data): Module`
     - `deleteModule(Module $module): bool`
     - `duplicateModule(Module $module): Module`
     - `exportModuleSchema(Module $module): array`
     - `importModuleSchema(array $schema): Module`
   - Business logic validation
   - Event dispatching (ModuleCreated, ModuleUpdated, etc.)
   - Unit tests

2. **Create FieldValidationService** (2h)
   - File: `backend/app/Domain/Modules/Services/FieldValidationService.php`
   - Methods:
     - `validateFieldConfiguration(array $config): ValidationResult`
     - `generateValidationRules(Field $field): array`
     - `validateFieldValue(Field $field, mixed $value): bool`
     - `getValidationErrorMessages(Field $field): array`
   - Support all 21 field types
   - Custom validation logic
   - Unit tests

3. **Create FormulaEvaluator** (3h)
   - File: `backend/app/Domain/Modules/Services/FormulaEvaluator.php`
   - Methods:
     - `evaluate(string $formula, array $context): mixed`
     - `parseFormula(string $formula): AST`
     - `validateFormula(string $formula): ValidationResult`
     - `getDependencies(string $formula): array`
   - Implement parser for formula language
   - Support all formula functions (SUM, IF, LOOKUP, etc.)
   - Security: prevent code injection
   - Unit tests with edge cases

4. **Create ModuleSchemaService** (2h)
   - File: `backend/app/Domain/Modules/Services/ModuleSchemaService.php`
   - Methods:
     - `generateSchema(Module $module): array`
     - `validateSchema(array $schema): ValidationResult`
     - `getFieldDependencyGraph(Module $module): array`
     - `detectCircularDependencies(Module $module): array`
   - Generate complete JSON schema
   - Dependency graph for formulas
   - Unit tests

### Acceptance Criteria
- [ ] All services follow single responsibility principle
- [ ] All methods have return type hints
- [ ] Services use dependency injection
- [ ] 90%+ test coverage
- [ ] Events dispatched for all state changes

---

## Workflow 1.4: API Controllers (8-10 hours)

### Tasks
1. **Create ModuleController** (2.5h)
   - File: `backend/app/Http/Controllers/Api/ModuleController.php`
   - Endpoints:
     - `GET /api/modules` - List all modules
     - `POST /api/modules` - Create module
     - `GET /api/modules/{id}` - Get module details
     - `PUT /api/modules/{id}` - Update module
     - `DELETE /api/modules/{id}` - Delete module
     - `POST /api/modules/{id}/duplicate` - Duplicate module
     - `GET /api/modules/{id}/schema` - Get full schema
   - Form requests for validation
   - API resources for transformation
   - Integration tests

2. **Create FieldController** (2.5h)
   - File: `backend/app/Http/Controllers/Api/FieldController.php`
   - Endpoints:
     - `GET /api/modules/{moduleId}/fields` - List fields
     - `POST /api/modules/{moduleId}/fields` - Create field
     - `GET /api/fields/{id}` - Get field
     - `PUT /api/fields/{id}` - Update field
     - `DELETE /api/fields/{id}` - Delete field
     - `POST /api/fields/{id}/reorder` - Reorder fields
   - Validate field configurations
   - Handle field type changes
   - Integration tests

3. **Create BlockController** (1.5h)
   - File: `backend/app/Http/Controllers/Api/BlockController.php`
   - Endpoints:
     - `GET /api/modules/{moduleId}/blocks` - List blocks
     - `POST /api/modules/{moduleId}/blocks` - Create block
     - `PUT /api/blocks/{id}` - Update block
     - `DELETE /api/blocks/{id}` - Delete block
     - `POST /api/blocks/{id}/reorder` - Reorder blocks
   - Handle block types (section, tab)
   - Integration tests

4. **Create FieldOptionController** (1.5h)
   - File: `backend/app/Http/Controllers/Api/FieldOptionController.php`
   - Endpoints:
     - `GET /api/fields/{fieldId}/options` - List options
     - `POST /api/fields/{fieldId}/options` - Create option
     - `PUT /api/field-options/{id}` - Update option
     - `DELETE /api/field-options/{id}` - Delete option
     - `POST /api/field-options/reorder` - Reorder options
   - Validate option structure
   - Integration tests

5. **Create ModuleRecordController** (2h)
   - File: `backend/app/Http/Controllers/Api/ModuleRecordController.php`
   - Endpoints:
     - `GET /api/modules/{apiName}/records` - List records
     - `POST /api/modules/{apiName}/records` - Create record
     - `GET /api/modules/{apiName}/records/{id}` - Get record
     - `PUT /api/modules/{apiName}/records/{id}` - Update record
     - `DELETE /api/modules/{apiName}/records/{id}` - Delete record
   - Dynamic validation based on module schema
   - Formula field calculation
   - Conditional visibility handling
   - Integration tests

### Acceptance Criteria
- [ ] All endpoints documented in API docs
- [ ] Request validation with Form Requests
- [ ] API Resources for consistent responses
- [ ] Rate limiting configured
- [ ] Integration tests for all CRUD operations
- [ ] Authorization via policies

---

## Workflow 1.5: API Resources & Form Requests (4-5 hours)

### Tasks
1. **Create API Resources** (2h)
   - `ModuleResource.php`
   - `FieldResource.php`
   - `BlockResource.php`
   - `FieldOptionResource.php`
   - `ModuleRecordResource.php`
   - Consistent JSON structure
   - Include relationships conditionally
   - Resource collections

2. **Create Form Requests** (2h)
   - `StoreModuleRequest.php`
   - `UpdateModuleRequest.php`
   - `StoreFieldRequest.php`
   - `UpdateFieldRequest.php`
   - `StoreBlockRequest.php`
   - `StoreModuleRecordRequest.php` (dynamic validation)
   - Custom validation rules
   - Authorization logic

3. **Test Resources** (1h)
   - Unit tests for transformations
   - Test conditional loading
   - Test collections

### Acceptance Criteria
- [ ] Consistent response format across all endpoints
- [ ] Validation errors are user-friendly
- [ ] Resources include only necessary data
- [ ] Authorization checks in requests

---

## Workflow 1.6: Frontend Module API Client (4-5 hours)

### Tasks
1. **Create API Client Functions** (2.5h)
   - File: `frontend/src/lib/api/modules.ts`
   - Functions:
     ```typescript
     export const moduleApi = {
       list: () => Promise<Module[]>
       get: (id: number) => Promise<Module>
       create: (data: CreateModuleDto) => Promise<Module>
       update: (id: number, data: UpdateModuleDto) => Promise<Module>
       delete: (id: number) => Promise<void>
       duplicate: (id: number) => Promise<Module>
       getSchema: (id: number) => Promise<ModuleSchema>
     }
     ```
   - Error handling
   - Type definitions
   - Request/response interceptors

2. **Create Field API Client** (1.5h)
   - File: `frontend/src/lib/api/fields.ts`
   - CRUD operations for fields
   - Reordering API
   - Type definitions

3. **Create Record API Client** (1h)
   - File: `frontend/src/lib/api/records.ts`
   - Dynamic record operations
   - Search and filtering
   - Pagination support

### Acceptance Criteria
- [ ] Full TypeScript types
- [ ] Error handling with user-friendly messages
- [ ] Request/response logging in dev mode
- [ ] Retry logic for failed requests
- [ ] Loading states

---

## Workflow 1.7: Frontend Stores (5-6 hours)

### Tasks
1. **Create Module Store** (2.5h)
   - File: `frontend/src/lib/stores/moduleStore.svelte.ts`
   - State:
     ```typescript
     const modules = $state<Module[]>([])
     const currentModule = $state<Module | null>(null)
     const loading = $state(false)
     const error = $state<string | null>(null)
     ```
   - Actions:
     - `loadModules()`
     - `loadModule(id)`
     - `createModule(data)`
     - `updateModule(id, data)`
     - `deleteModule(id)`
   - Optimistic updates
   - Cache management

2. **Create Schema Cache Store** (1.5h)
   - File: `frontend/src/lib/stores/schemaCache.svelte.ts`
   - Cache module schemas in memory
   - Invalidation strategy
   - Preload schemas on app load
   - LocalStorage persistence

3. **Create Field Store** (1.5h)
   - File: `frontend/src/lib/stores/fieldStore.svelte.ts`
   - Manage field state
   - Field reordering
   - Field type utilities

4. **Write Store Tests** (1h)
   - Unit tests for all stores
   - Test state mutations
   - Test async actions
   - Test error handling

### Acceptance Criteria
- [ ] Reactive state with Svelte 5 runes
- [ ] Optimistic updates for better UX
- [ ] Error handling and rollback
- [ ] Unit tests for all store actions
- [ ] LocalStorage caching where appropriate

---

## Workflow 1.8: Frontend Module Management UI (10-12 hours)

### Tasks
1. **Create Module List Page** (3h)
   - File: `frontend/src/routes/(app)/admin/modules/+page.svelte`
   - Features:
     - Data table with all modules
     - Search by name
     - Filter by active/inactive
     - Sort by various fields
     - Bulk actions (activate/deactivate)
     - Create module button
   - Components:
     - `ModuleCard.svelte` (grid view option)
     - `ModuleTable.svelte` (table view)
     - `ModuleFilters.svelte`

2. **Create Module Form Page** (4h)
   - File: `frontend/src/routes/(app)/admin/modules/[id]/edit/+page.svelte`
   - Form sections:
     - Basic info (name, icon, description)
     - Display settings
     - Module features toggles
     - Advanced settings
   - Real-time validation
   - Auto-save draft
   - Discard changes confirmation

3. **Create Field Library Component** (3h)
   - File: `frontend/src/lib/components/modules/FieldLibrary.svelte`
   - Display all 21 field types with icons and descriptions
   - Search/filter fields
   - Categorize by type (text, number, date, relationship, etc.)
   - Preview component for each type

4. **Create Module Settings Panel** (2h)
   - File: `frontend/src/lib/components/modules/ModuleSettings.svelte`
   - Toggle features:
     - Import/Export
     - Mass actions
     - Comments
     - Attachments
     - Activity log
     - Custom views
   - Record name field selector
   - Additional settings (Kanban, timeline, auto-number)

### Acceptance Criteria
- [ ] Can view all modules in list/grid format
- [ ] Can create new module with basic info
- [ ] Can edit existing module
- [ ] Can delete module (with confirmation)
- [ ] All form inputs validated
- [ ] Loading states during API calls
- [ ] Success/error toasts

---

## Workflow 1.9: Type Definitions (3-4 hours)

### Tasks
1. **Create Backend DTOs** (1.5h)
   - Generate TypeScript types from API responses
   - Use Laravel Data package or manual definitions
   - Types for: Module, Field, Block, FieldOption, ModuleRecord

2. **Create Frontend Types** (1.5h)
   - File: `frontend/src/lib/types/modules.ts`
   - Interfaces for all entities
   - Enums for field types, operators, etc.
   - Form DTOs (CreateModuleDto, UpdateModuleDto, etc.)

3. **Validate Types** (1h)
   - Ensure frontend/backend types match
   - Use arktype for runtime validation
   - Generate validation schemas

### Acceptance Criteria
- [ ] Complete type coverage
- [ ] No TypeScript errors
- [ ] Runtime validation with arktype
- [ ] Types exported from index files

---

## Workflow 1.10: Integration Testing (4-5 hours)

### Tasks
1. **Write API Integration Tests** (2h)
   - Test all CRUD operations
   - Test validation errors
   - Test authorization
   - Test relationships (cascade deletes, etc.)

2. **Write E2E Tests** (2h)
   - Test module creation flow
   - Test field creation flow
   - Test module editing
   - Test module deletion

3. **Test Coverage Report** (1h)
   - Run coverage tools
   - Identify gaps
   - Write additional tests
   - Aim for 80%+ coverage

### Acceptance Criteria
- [ ] All API endpoints tested
- [ ] All UI flows tested
- [ ] 80%+ code coverage
- [ ] CI/CD pipeline runs tests
- [ ] Tests pass consistently

---

## Workflow 1.11: Documentation (2-3 hours)

### Tasks
1. **API Documentation** (1h)
   - Document all endpoints
   - Request/response examples
   - Error codes
   - Use Swagger/OpenAPI

2. **User Documentation** (1h)
   - How to create a module
   - How to configure fields
   - Best practices
   - Screenshots/videos

3. **Developer Documentation** (1h)
   - Architecture overview
   - Code structure
   - How to add new field types
   - Contribution guidelines

### Acceptance Criteria
- [ ] API docs generated automatically
- [ ] User guide with examples
- [ ] Developer docs in README
- [ ] Code comments for complex logic

---

## Phase 1 Deliverables Checklist

### Backend
- [ ] All value objects created and tested
- [ ] Database migrations completed
- [ ] Service layer implemented
- [ ] API controllers with full CRUD
- [ ] API resources and form requests
- [ ] Integration tests passing

### Frontend
- [ ] API client functions
- [ ] State management stores
- [ ] Module list page
- [ ] Module create/edit page
- [ ] Field library component
- [ ] TypeScript types

### Testing & Docs
- [ ] Unit tests (80%+ coverage)
- [ ] Integration tests
- [ ] E2E tests
- [ ] API documentation
- [ ] User documentation

### Demo-Ready Features
- [ ] Can create a custom module via UI
- [ ] Can add basic field types to module
- [ ] Can view module in list
- [ ] Can edit module settings
- [ ] Can delete module

---

## Risk Mitigation

### Technical Risks
1. **Formula parser complexity** → Use existing library (mathjs, expr-eval)
2. **JSON query performance** → Add GIN indexes on JSON columns
3. **Frontend state complexity** → Use established patterns (TanStack Query)

### Timeline Risks
1. **Underestimated complexity** → Build MVP first, iterate
2. **Scope creep** → Stick to defined features for Phase 1
3. **Testing takes longer** → Write tests alongside development

---

## Success Metrics

- [ ] Can create module with 5+ field types in under 5 minutes
- [ ] Module list loads in under 500ms
- [ ] Form validation provides instant feedback
- [ ] Zero critical bugs in testing
- [ ] All acceptance criteria met

**Phase 1 Complete when:**
✅ All workflows completed
✅ All tests passing
✅ Documentation complete
✅ Demo-ready module creation system

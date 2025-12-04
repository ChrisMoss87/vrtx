# Phases 3-15: Detailed Workflows

## Table of Contents
- [Phase 3: Dynamic Form Renderer](#phase-3-dynamic-form-renderer)
- [Phase 4: Multi-Page Forms & Wizards](#phase-4-multi-page-forms--wizards)
- [Phase 5: Rich Text Editor](#phase-5-rich-text-editor)
- [Phase 6: Sales Pipelines & Kanban](#phase-6-sales-pipelines--kanban)
- [Phase 7: Workflow Automation](#phase-7-workflow-automation)
- [Phase 8: Email Integration](#phase-8-email-integration)
- [Phase 9: Activity Timeline](#phase-9-activity-timeline)
- [Phase 10: Reporting & Analytics](#phase-10-reporting--analytics)
- [Phase 11: RBAC](#phase-11-role-based-access-control)
- [Phase 12: Import/Export](#phase-12-importexport--data-management)
- [Phase 13: Mobile Optimization](#phase-13-mobile-optimization)
- [Phase 14: Search & Navigation](#phase-14-search--global-navigation)
- [Phase 15: Testing & Polish](#phase-15-testing-polish--documentation)

---

# Phase 3: Dynamic Form Renderer

## Overview
**Duration:** Week 5 (40-50 hours)
**Goal:** Render any module as a functional form based on JSON configuration

---

## Workflow 3.1: Core Form Renderer (12-15 hours)

### Tasks
1. **Create DynamicForm Component** (4h)
   - File: `frontend/src/lib/components/dynamic-form/DynamicForm.svelte`
   - Props: `moduleSchema`, `initialData`, `mode` (create/edit/view)
   - Features:
     - Parse module schema
     - Initialize form state
     - Handle form submission
     - Validation on submit
     - Loading states
     - Error handling
   - Integration with sveltekit-superforms

2. **Create BlockRenderer Component** (3h)
   - File: `frontend/src/lib/components/dynamic-form/BlockRenderer.svelte`
   - Render section blocks (with columns)
   - Render tab blocks (with tab navigation)
   - Handle collapsible sections
   - Responsive column layout
   - Conditional block visibility

3. **Create FieldRenderer Component** (4h)
   - File: `frontend/src/lib/components/dynamic-form/FieldRenderer.svelte`
   - Switch on field type
   - Render appropriate field component
   - Pass field settings to component
   - Handle field-level validation
   - Show validation errors
   - Field label and help text
   - Required indicator

4. **Test Form Rendering** (1h)
   - Test with sample modules
   - Test all field types render
   - Test validation
   - Test submission

### Acceptance Criteria
- [ ] Any module schema can be rendered
- [ ] All field types display correctly
- [ ] Form validation works
- [ ] Can submit form data
- [ ] Error states display properly

---

## Workflow 3.2: Conditional Visibility Logic (6-8 hours)

### Tasks
1. **Create Visibility Evaluator** (3h)
   - File: `frontend/src/lib/form-logic/conditionalVisibility.ts`
   - Functions:
     - `evaluateCondition(condition, formData): boolean`
     - `evaluateConditions(rules, formData): boolean`
     - `getVisibleFields(schema, formData): string[]`
   - Support all operators
   - Handle nested conditions
   - Performance optimization (memoization)

2. **Integrate with Form Renderer** (2h)
   - Track form state changes
   - Re-evaluate visibility on field change
   - Show/hide fields dynamically
   - Smooth transitions
   - Clear hidden field values (optional)

3. **Create Visibility Hook** (1h)
   - File: `frontend/src/lib/hooks/useFieldVisibility.svelte.ts`
   - Reactive visibility state
   - Subscribe to form changes
   - Batch visibility updates

4. **Test Conditional Logic** (1h)
   - Unit tests for all operators
   - Test AND/OR logic
   - Test performance with many fields
   - Test edge cases

### Acceptance Criteria
- [ ] Fields show/hide based on conditions
- [ ] All operators work correctly
- [ ] No performance issues
- [ ] Smooth animations

---

## Workflow 3.3: Formula Calculator (8-10 hours)

### Tasks
1. **Create Formula Parser** (3h)
   - File: `frontend/src/lib/form-logic/formulaParser.ts`
   - Parse formula string to AST
   - Tokenize formula
   - Handle operator precedence
   - Support all formula functions
   - Error handling

2. **Create Formula Evaluator** (3h)
   - File: `frontend/src/lib/form-logic/formulaCalculator.ts`
   - Execute formula AST
   - Implement all functions:
     - Math functions (SUM, MULTIPLY, etc.)
     - Date functions (TODAY, DAYS_BETWEEN, etc.)
     - Text functions (CONCAT, UPPER, etc.)
     - Logical functions (IF, AND, OR, etc.)
     - LOOKUP function
   - Type coercion
   - Error handling

3. **Create Dependency Tracker** (2h)
   - File: `frontend/src/lib/form-logic/dependencyManager.ts`
   - Build dependency graph
   - Detect circular dependencies
   - Calculate recalculation order
   - Batch recalculations

4. **Integrate with Form** (1h)
   - Trigger calculations on field change
   - Update calculated field values
   - Loading states during calculation
   - Error display

5. **Test Formula System** (1h)
   - Test all formula functions
   - Test dependency tracking
   - Test circular dependency detection
   - Test performance

### Acceptance Criteria
- [ ] All formula functions work
- [ ] Calculations trigger on dependency change
- [ ] Circular dependencies detected
- [ ] No calculation errors

---

## Workflow 3.4: Field Dependency Logic (4-5 hours)

### Tasks
1. **Create Dependency Filter** (2h)
   - File: `frontend/src/lib/form-logic/dependencyFilter.ts`
   - Filter lookup options based on parent field
   - Build filter queries
   - Support multiple dependencies
   - Cache filtered results

2. **Integrate with Lookup Fields** (2h)
   - Update LookupField component
   - Fetch filtered data on parent change
   - Clear value when filter changes
   - Loading state during fetch

3. **Test Dependencies** (1h)
   - Test cascading dropdowns
   - Test with multiple dependencies
   - Test performance

### Acceptance Criteria
- [ ] Cascading dropdowns work
- [ ] Options filter correctly
- [ ] Performance acceptable
- [ ] No race conditions

---

## Workflow 3.5: Dynamic Validation (5-6 hours)

### Tasks
1. **Create Validation Engine** (3h)
   - File: `frontend/src/lib/form-logic/validationEngine.ts`
   - Generate validation schema from field config
   - Support all field types
   - Custom validation rules
   - Cross-field validation
   - Async validation (e.g., uniqueness checks)

2. **Integrate with superforms** (2h)
   - Convert module schema to validation schema
   - Real-time validation
   - Display validation errors
   - Custom error messages

3. **Test Validation** (1h)
   - Test all field types
   - Test custom rules
   - Test async validation

### Acceptance Criteria
- [ ] All field types validated
- [ ] Custom rules work
- [ ] Error messages clear
- [ ] Real-time feedback

---

## Workflow 3.6: Form State Management (4-5 hours)

### Tasks
1. **Create Form Hook** (2h)
   - File: `frontend/src/lib/hooks/useForm.svelte.ts`
   - Form state management
   - Field value tracking
   - Dirty state tracking
   - Submit handling
   - Reset form

2. **Create Auto-save** (1h)
   - Debounced save to API
   - Draft saving to localStorage
   - Resume from draft
   - Clear draft on submit

3. **Create Form Context** (1h)
   - Share form state across components
   - Field registration
   - Form-level actions

4. **Test State Management** (1h)
   - Test form operations
   - Test auto-save
   - Test draft resume

### Acceptance Criteria
- [ ] Form state managed correctly
- [ ] Auto-save works
- [ ] Draft resume works
- [ ] No state bugs

---

## Workflow 3.7: Integration & Testing (4-5 hours)

### Tasks
1. **Component Tests** (2h)
   - Test DynamicForm
   - Test BlockRenderer
   - Test FieldRenderer
   - Test validation

2. **E2E Tests** (2h)
   - Test form fill and submit
   - Test conditional visibility
   - Test formula calculations
   - Test validation errors

3. **Documentation** (1h)
   - Usage examples
   - API documentation
   - Common patterns

### Acceptance Criteria
- [ ] 80%+ test coverage
- [ ] All E2E flows work
- [ ] Documentation complete

---

## Phase 3 Deliverables

- [ ] DynamicForm component renders any module
- [ ] All 21 field types work
- [ ] Conditional visibility functional
- [ ] Formula calculations work
- [ ] Field dependencies work
- [ ] Validation engine complete
- [ ] Form submission works
- [ ] Tests passing
- [ ] Documentation complete

**Demo:** Create a record using dynamically rendered form

---

# Phase 4: Multi-Page Forms & Wizards

## Overview
**Duration:** Week 6 (40-50 hours)
**Goal:** Support complex multi-step forms with progress tracking

---

## Workflow 4.1: Wizard Infrastructure (10-12 hours)

### Tasks
1. **Create Wizard Container** (3h)
   - File: `frontend/src/lib/components/wizard/Wizard.svelte`
   - Multi-step navigation
   - Progress indicator
   - Step validation
   - Step transition animations
   - Keyboard navigation (tab, enter, arrows)

2. **Create Step Component** (2h)
   - File: `frontend/src/lib/components/wizard/WizardStep.svelte`
   - Render step content
   - Step validation
   - Step enable/disable
   - Step completion status

3. **Create Progress Indicator** (2h)
   - File: `frontend/src/lib/components/wizard/WizardProgress.svelte`
   - Visual progress bar
   - Step indicators (numbered circles)
   - Step labels
   - Clickable navigation
   - Responsive design

4. **Create Navigation Buttons** (1h)
   - File: `frontend/src/lib/components/wizard/WizardNavigation.svelte`
   - Previous button
   - Next button
   - Submit button (last step)
   - Cancel button
   - Button states (disabled, loading)

5. **Create Wizard Hook** (2h)
   - File: `frontend/src/lib/hooks/useWizard.svelte.ts`
   - Current step tracking
   - Navigation functions
   - Validation state per step
   - Completion tracking
   - Draft saving

6. **Test Wizard** (1h)
   - Test navigation
   - Test validation per step
   - Test progress tracking

### Acceptance Criteria
- [ ] Can navigate between steps
- [ ] Progress indicator accurate
- [ ] Step validation works
- [ ] Can save draft
- [ ] Responsive design

---

## Workflow 4.2: Wizard Builder UI (8-10 hours)

### Tasks
1. **Create Wizard Builder** (4h)
   - File: `frontend/src/lib/components/form-builder/WizardBuilder.svelte`
   - Add/remove steps
   - Reorder steps
   - Configure step settings
   - Assign fields to steps
   - Preview wizard

2. **Create Step Configuration** (3h)
   - File: `frontend/src/lib/components/form-builder/StepConfig.svelte`
   - Step name
   - Step description
   - Step type (form, review, confirmation)
   - Required fields
   - Conditional step logic (skip step if...)
   - Custom completion logic

3. **Test Wizard Builder** (1h)
   - Test step CRUD
   - Test field assignment
   - Test conditional logic

### Acceptance Criteria
- [ ] Can build multi-step wizard
- [ ] Can configure steps
- [ ] Can assign fields to steps
- [ ] Preview works

---

## Workflow 4.3: Step Types (6-8 hours)

### Tasks
1. **Form Step** (2h)
   - Regular form fields
   - Step-level validation
   - Auto-advance on completion

2. **Review Step** (2h)
   - Summary of all entered data
   - Edit buttons for each step
   - Confirmation checkboxes

3. **Confirmation Step** (1h)
   - Success message
   - Next actions
   - Download/print options

4. **Custom Step Templates** (1h)
   - Payment step template
   - File upload step template
   - Terms acceptance step template

5. **Test Step Types** (1h)
   - Test each step type
   - Test transitions

### Acceptance Criteria
- [ ] All step types functional
- [ ] Review step shows all data
- [ ] Confirmation step displays correctly

---

## Workflow 4.4: Conditional Step Logic (4-5 hours)

### Tasks
1. **Create Step Visibility Rules** (2h)
   - Skip steps based on conditions
   - Branch to different steps
   - Dynamic step ordering

2. **Integrate with Wizard** (2h)
   - Evaluate step visibility
   - Update progress indicator
   - Handle step skipping

3. **Test Conditional Steps** (1h)
   - Test skip logic
   - Test branching

### Acceptance Criteria
- [ ] Steps can be conditionally skipped
- [ ] Branching works
- [ ] Progress indicator updates

---

## Workflow 4.5: Draft Management (4-5 hours)

### Tasks
1. **Create Draft System** (2h)
   - Auto-save to localStorage
   - Save to server (API endpoint)
   - Resume from draft
   - List saved drafts

2. **Create Draft UI** (1h)
   - Draft indicator
   - Resume draft prompt
   - Delete draft option

3. **Test Draft System** (1h)
   - Test auto-save
   - Test resume
   - Test expiration

### Acceptance Criteria
- [ ] Drafts save automatically
- [ ] Can resume from draft
- [ ] Old drafts cleaned up

---

## Workflow 4.6: Integration & Testing (4-5 hours)

### Tasks
1. **Component Tests** (2h)
   - Test Wizard component
   - Test all step types
   - Test navigation
   - Test validation

2. **E2E Tests** (2h)
   - Test complete wizard flow
   - Test conditional steps
   - Test draft save/resume
   - Test validation

3. **Documentation** (1h)
   - Wizard builder guide
   - Step configuration guide
   - Examples

### Acceptance Criteria
- [ ] 80%+ test coverage
- [ ] All flows work
- [ ] Documentation complete

---

## Phase 4 Deliverables

- [ ] Wizard component functional
- [ ] Can build multi-step forms
- [ ] All step types work
- [ ] Conditional step logic works
- [ ] Draft management works
- [ ] Progress tracking accurate
- [ ] Tests passing
- [ ] Documentation complete

**Demo:** Build and complete a multi-step wizard form

---

# Phase 5: Rich Text Editor

## Overview
**Duration:** Week 7 (35-40 hours)
**Goal:** TipTap-based rich text with mentions, embeds, collaborative editing

---

## Workflow 5.1: TipTap Setup (6-8 hours)

### Tasks
1. **Install TipTap** (1h)
   ```bash
   pnpm add @tiptap/core @tiptap/pm @tiptap/starter-kit
   pnpm add @tiptap/extension-link @tiptap/extension-image
   pnpm add @tiptap/extension-table @tiptap/extension-mention
   pnpm add @tiptap/extension-placeholder @tiptap/extension-character-count
   ```

2. **Create Editor Component** (3h)
   - File: `frontend/src/lib/components/editor/RichTextEditor.svelte`
   - Initialize TipTap editor
   - Configure extensions
   - Content binding
   - Read-only mode
   - Character limit

3. **Create Editor Toolbar** (2h)
   - File: `frontend/src/lib/components/editor/EditorToolbar.svelte`
   - Bold, italic, underline, strikethrough
   - Headings (H1-H6)
   - Lists (bullet, ordered)
   - Link button
   - Image button
   - Code block
   - Blockquote
   - Clear formatting

4. **Test Basic Editor** (1h)
   - Test all formatting options
   - Test content binding
   - Test read-only mode

### Acceptance Criteria
- [ ] Editor renders correctly
- [ ] All basic formatting works
- [ ] Content saves properly
- [ ] Toolbar functional

---

## Workflow 5.2: Advanced Editor Features (8-10 hours)

### Tasks
1. **Image Upload Extension** (3h)
   - Drag-and-drop images
   - Paste images from clipboard
   - Upload to server
   - Image resizing handles
   - Alt text editing
   - Caption support

2. **Link Extension** (2h)
   - Link insertion modal
   - Edit existing links
   - Link preview on hover
   - Open in new tab option
   - Remove link

3. **Table Extension** (2h)
   - Insert table
   - Add/remove rows/columns
   - Merge cells
   - Table headers
   - Table styling

4. **Code Block Extension** (1h)
   - Syntax highlighting
   - Language selector
   - Copy code button

5. **Test Advanced Features** (1h)
   - Test image upload
   - Test tables
   - Test code blocks

### Acceptance Criteria
- [ ] Images can be uploaded
- [ ] Links work correctly
- [ ] Tables functional
- [ ] Code blocks highlighted

---

## Workflow 5.3: Mentions Extension (6-8 hours)

### Tasks
1. **Create Mention Extension** (3h)
   - File: `frontend/src/lib/components/editor/extensions/Mention.ts`
   - Trigger mention with @
   - Autocomplete dropdown
   - Search users
   - Insert mention
   - Mention styling

2. **Create Mention Dropdown** (2h)
   - File: `frontend/src/lib/components/editor/MentionDropdown.svelte`
   - User list with avatars
   - Keyboard navigation
   - Click to insert
   - Loading state

3. **Backend Mention API** (1h)
   - Search users endpoint
   - Filter by name/email
   - Return user data

4. **Test Mentions** (1h)
   - Test @ trigger
   - Test autocomplete
   - Test insertion

### Acceptance Criteria
- [ ] @ triggers mention dropdown
- [ ] Users searchable
- [ ] Mentions insertable
- [ ] Mentions styled correctly

---

## Workflow 5.4: Slash Commands (4-5 hours)

### Tasks
1. **Create Slash Command Extension** (2h)
   - File: `frontend/src/lib/components/editor/extensions/SlashCommands.ts`
   - Trigger with /
   - Command dropdown
   - Insert blocks (heading, list, etc.)
   - Quick actions

2. **Create Command Menu** (2h)
   - File: `frontend/src/lib/components/editor/CommandMenu.svelte`
   - Command list with icons
   - Search commands
   - Keyboard navigation
   - Execute command

3. **Test Slash Commands** (1h)
   - Test / trigger
   - Test all commands

### Acceptance Criteria
- [ ] / triggers command menu
- [ ] All commands work
- [ ] Keyboard navigation works

---

## Workflow 5.5: File Handling (4-5 hours)

### Tasks
1. **File Upload API** (2h)
   - Create upload endpoint
   - Image optimization (resize, webp conversion)
   - File storage (S3 or local)
   - Return file URL

2. **Frontend File Handling** (2h)
   - Drag-drop file upload
   - Progress indicator
   - Error handling
   - File preview

3. **Test File Upload** (1h)
   - Test various file types
   - Test large files
   - Test errors

### Acceptance Criteria
- [ ] Files upload successfully
- [ ] Images optimized
- [ ] Progress shown
- [ ] Errors handled

---

## Workflow 5.6: Integration & Testing (4-5 hours)

### Tasks
1. **Integrate with Forms** (2h)
   - Add to field types
   - Save/load content
   - Validation

2. **Component Tests** (1h)
   - Test editor operations
   - Test extensions

3. **E2E Tests** (1h)
   - Test formatting
   - Test image upload
   - Test mentions

4. **Documentation** (1h)
   - Editor usage guide
   - Extension guide

### Acceptance Criteria
- [ ] Editor in dynamic forms
- [ ] Tests passing
- [ ] Documentation complete

---

## Phase 5 Deliverables

- [ ] Rich text editor functional
- [ ] All formatting options work
- [ ] Image upload works
- [ ] Mentions work
- [ ] Slash commands work
- [ ] Tables supported
- [ ] Code blocks highlighted
- [ ] Tests passing
- [ ] Documentation complete

**Demo:** Create a note with rich formatting, images, and mentions

---

# Phase 6: Sales Pipelines & Kanban

## Overview
**Duration:** Weeks 8-9 (80-100 hours)
**Goal:** Visual deal pipelines with drag-and-drop stages

---

## Workflow 6.1: Pipeline Data Model (8-10 hours)

### Backend Tasks
1. **Create Pipeline Model** (2h)
   - File: `backend/app/Models/Pipeline.php`
   - Fields: name, module_id, is_active, settings
   - Relationships: hasMany stages, hasMany deals

2. **Create Stage Model** (2h)
   - File: `backend/app/Models/Stage.php`
   - Fields: name, pipeline_id, probability, color, order
   - Relationships: belongsTo pipeline, hasMany deals

3. **Create Deal Model** (2h)
   - File: `backend/app/Models/Deal.php`
   - Extends ModuleRecord
   - Additional fields: stage_id, value, probability, close_date
   - Stage history tracking

4. **Database Migrations** (2h)
   - pipelines table
   - stages table
   - Add stage_id to module_records
   - stage_history table

### Acceptance Criteria
- [ ] Models created
- [ ] Migrations run successfully
- [ ] Relationships work

---

## Workflow 6.2: Pipeline API (6-8 hours)

### Tasks
1. **Create Pipeline Controller** (2h)
   - CRUD operations
   - Get pipeline with stages
   - Reorder stages

2. **Create Stage Controller** (2h)
   - CRUD operations
   - Move deals to stage
   - Stage statistics

3. **Create Deal Controller** (2h)
   - Inherits from ModuleRecordController
   - Additional methods for stage changes
   - Deal statistics

4. **Test APIs** (1h)
   - Integration tests
   - Test stage transitions

### Acceptance Criteria
- [ ] All CRUD operations work
- [ ] Stage transitions tracked
- [ ] Tests passing

---

## Workflow 6.3: Pipeline Builder UI (10-12 hours)

### Tasks
1. **Create Pipeline List** (3h)
   - File: `frontend/src/routes/(app)/admin/pipelines/+page.svelte`
   - List all pipelines
   - Create pipeline button
   - Edit/delete pipeline

2. **Create Pipeline Form** (4h)
   - File: `frontend/src/routes/(app)/admin/pipelines/[id]/edit/+page.svelte`
   - Pipeline name, module
   - Stage management:
     - Add stage
     - Edit stage (name, color, probability)
     - Reorder stages (drag-drop)
     - Delete stage
   - Pipeline settings

3. **Create Stage Card** (2h)
   - File: `frontend/src/lib/components/pipeline/StageCard.svelte`
   - Stage name with color
   - Probability indicator
   - Stage statistics
   - Drag handle

4. **Test Pipeline Builder** (1h)
   - Test CRUD operations
   - Test stage reordering

### Acceptance Criteria
- [ ] Can create pipelines
- [ ] Can manage stages
- [ ] Can reorder stages
- [ ] UI intuitive

---

## Workflow 6.4: Kanban Board (15-18 hours)

### Tasks
1. **Create Kanban Container** (4h)
   - File: `frontend/src/lib/components/pipeline/PipelineKanban.svelte`
   - Horizontal scroll for stages
   - Stage columns
   - Drag-drop between stages
   - Loading states
   - Empty states

2. **Create Stage Column** (4h)
   - File: `frontend/src/lib/components/pipeline/StageColumn.svelte`
   - Stage header with count
   - Deal cards list
   - Add deal button
   - Stage total value
   - Drop zone
   - Virtual scrolling (for many deals)

3. **Create Deal Card** (4h)
   - File: `frontend/src/lib/components/pipeline/DealCard.svelte`
   - Deal name
   - Deal value
   - Contact/account info
   - Days in stage
   - Priority indicator
   - Assignee avatar
   - Quick actions menu
   - Drag handle

4. **Implement Drag Logic** (3h)
   - Drag deal between stages
   - API call on drop
   - Optimistic update
   - Rollback on error
   - Stage transition event

5. **Test Kanban** (1h)
   - Test drag-drop
   - Test with many deals
   - Test error handling

### Acceptance Criteria
- [ ] Kanban displays all stages
- [ ] Can drag deals between stages
- [ ] Deal cards show key info
- [ ] Performance good with 100+ deals
- [ ] Smooth animations

---

## Workflow 6.5: Deal Management (10-12 hours)

### Tasks
1. **Create Deal Detail Page** (5h)
   - File: `frontend/src/routes/(app)/deals/[id]/+page.svelte`
   - Deal header with key info
   - Editable fields
   - Stage selector
   - Activity timeline
   - Related records (contacts, quotes)
   - Notes section
   - Files section

2. **Create Deal Form** (3h)
   - Create/edit deal modal
   - Required fields validation
   - Stage selection
   - Value and probability
   - Close date picker

3. **Create Quick Actions** (2h)
   - Move to stage (dropdown)
   - Mark as won
   - Mark as lost
   - Clone deal
   - Delete deal

4. **Test Deal Management** (1h)
   - Test CRUD operations
   - Test stage transitions
   - Test validation

### Acceptance Criteria
- [ ] Can create/edit deals
- [ ] Can view deal details
- [ ] Can change stages
- [ ] Can mark won/lost

---

## Workflow 6.6: Pipeline Analytics (8-10 hours)

### Tasks
1. **Create Pipeline Dashboard** (4h)
   - File: `frontend/src/routes/(app)/pipelines/[id]/analytics/+page.svelte`
   - Total pipeline value
   - Weighted pipeline value
   - Deals by stage (chart)
   - Conversion rates
   - Average deal size
   - Sales velocity
   - Win/loss analysis

2. **Create Backend Analytics** (3h)
   - Pipeline metrics calculation
   - Stage statistics
   - Conversion funnel
   - Time in stage analytics
   - Win/loss reasons aggregation

3. **Create Charts** (2h)
   - Funnel chart (conversion)
   - Bar chart (deals by stage)
   - Line chart (pipeline trend)
   - Pie chart (win/loss)

4. **Test Analytics** (1h)
   - Verify calculations
   - Test with sample data

### Acceptance Criteria
- [ ] All metrics accurate
- [ ] Charts render correctly
- [ ] Real-time updates
- [ ] Export capabilities

---

## Workflow 6.7: Integration & Testing (6-8 hours)

### Tasks
1. **Component Tests** (3h)
   - Test Kanban components
   - Test deal components
   - Test stage transitions

2. **E2E Tests** (3h)
   - Test pipeline creation
   - Test drag-drop deals
   - Test deal lifecycle
   - Test analytics

3. **Documentation** (1h)
   - Pipeline setup guide
   - Best practices
   - Analytics guide

### Acceptance Criteria
- [ ] 80%+ test coverage
- [ ] All flows work
- [ ] Documentation complete

---

## Phase 6 Deliverables

- [ ] Pipeline data model complete
- [ ] Pipeline CRUD APIs
- [ ] Pipeline builder UI
- [ ] Kanban board functional
- [ ] Drag-drop working
- [ ] Deal management complete
- [ ] Pipeline analytics
- [ ] Tests passing
- [ ] Documentation complete

**Demo:** Create pipeline, add stages, drag deals through pipeline

---

# Phases 7-15: Executive Summary

Due to length, I'll provide condensed workflow summaries for phases 7-15:

---

## Phase 7: Workflow Automation (Weeks 10-12)

### Key Workflows
1. **Workflow Data Model** (8h) - Workflows, triggers, actions, conditions tables
2. **Workflow Builder UI** (12h) - Visual node editor, trigger/action configurators
3. **Workflow Engine** (15h) - Trigger evaluation, action execution, queue jobs
4. **Available Triggers** (8h) - Record events, time-based, webhooks, email
5. **Available Actions** (12h) - Email, create/update records, webhooks, assignments
6. **Condition Builder** (6h) - Visual condition editor, evaluation logic
7. **Testing & Monitoring** (8h) - Workflow logs, execution history, debugging

**Deliverable:** Full Zapier-like workflow automation

---

## Phase 8: Email Integration (Week 13)

### Key Workflows
1. **IMAP/SMTP Setup** (6h) - Email account connection, sync configuration
2. **Email Sync Engine** (8h) - Fetch emails, parse, thread, match to records
3. **Email Composer** (6h) - Rich editor, templates, attachments, tracking
4. **Email Templates** (4h) - Template builder, variables, conditional blocks
5. **Email Tracking** (4h) - Open/click tracking, analytics
6. **Thread View** (5h) - Conversation threading, reply/forward
7. **Testing** (4h) - Integration tests, E2E flows

**Deliverable:** Full email client within CRM

---

## Phase 9: Activity Timeline (Week 14)

### Key Workflows
1. **Activity Logging System** (6h) - Log all activities, polymorphic relations
2. **Timeline Component** (5h) - Unified activity feed, filtering, search
3. **Activity Types** (8h) - Notes, calls, meetings, emails, tasks, changes
4. **Activity Composer** (5h) - Add notes/calls/meetings inline
5. **Activity Analytics** (4h) - Activity reports, user activity tracking
6. **Testing** (4h) - Component and E2E tests

**Deliverable:** Complete activity history for every record

---

## Phase 10: Reporting & Analytics (Weeks 15-16)

### Key Workflows
1. **Query Builder Backend** (10h) - Build complex queries, joins, aggregations
2. **Report Builder UI** (12h) - Visual query builder, field selector, grouping
3. **Chart Library Integration** (8h) - Line, bar, pie, funnel, scatter charts
4. **Dashboard Builder** (10h) - Drag-drop widgets, layout, filters
5. **Scheduled Reports** (6h) - Email delivery, export options
6. **Caching & Performance** (5h) - Query caching, optimization
7. **Testing** (6h) - Report accuracy tests, performance tests

**Deliverable:** Custom dashboards and reporting system

---

## Phase 11: RBAC (Week 17)

### Key Workflows
1. **Permission System Setup** (6h) - Spatie/permission integration
2. **Role Management UI** (5h) - Create/edit roles, permission matrix
3. **Module-Level Permissions** (4h) - View/create/edit/delete per module
4. **Field-Level Permissions** (4h) - Hide sensitive fields by role
5. **Record-Level Permissions** (5h) - Ownership rules, team access
6. **Policy Implementation** (6h) - Authorization policies for all operations
7. **Testing** (4h) - Permission tests, security tests

**Deliverable:** Enterprise-grade access control

---

## Phase 12: Import/Export (Week 18)

### Key Workflows
1. **Import Wizard UI** (8h) - File upload, mapping, preview, execute
2. **Import Engine** (8h) - Parse CSV/Excel, validate, bulk insert
3. **Export Builder** (5h) - Field selection, filtering, format options
4. **Bulk Operations** (6h) - Bulk edit, delete, assign, tag
5. **Scheduled Import/Export** (4h) - Cron jobs, automated data sync
6. **Testing** (4h) - Import validation, large file tests

**Deliverable:** Complete data import/export system

---

## Phase 13: Mobile Optimization (Week 19)

### Key Workflows
1. **Responsive Layout** (8h) - Mobile-first design, breakpoints
2. **Mobile Navigation** (5h) - Bottom tabs, hamburger menu, swipe gestures
3. **Touch Optimization** (5h) - Touch targets, swipe actions, pull-refresh
4. **PWA Implementation** (6h) - Service worker, manifest, offline mode
5. **Mobile Forms** (5h) - Optimized form layouts, mobile field types
6. **Testing** (4h) - Mobile device testing, responsive tests

**Deliverable:** Fully responsive mobile experience

---

## Phase 14: Search & Navigation (Week 20)

### Key Workflows
1. **Global Search Backend** (6h) - PostgreSQL full-text search, indexing
2. **Search UI** (6h) - Cmd+K search, instant results, filtering
3. **Command Palette** (5h) - Quick actions, shortcuts, navigation
4. **Search Suggestions** (4h) - Autocomplete, recent searches
5. **Advanced Search** (5h) - Multi-field search, operators, saved searches
6. **Testing** (4h) - Search accuracy, performance tests

**Deliverable:** Lightning-fast global search

---

## Phase 15: Testing, Polish & Documentation (Weeks 21-22)

### Key Workflows
1. **Backend Unit Tests** (12h) - Test all services, value objects, models
2. **Backend Integration Tests** (10h) - Test all APIs, workflows
3. **Frontend Component Tests** (12h) - Test all components
4. **E2E Test Suite** (12h) - Test all user flows
5. **Performance Optimization** (10h) - Query optimization, bundle size, caching
6. **User Documentation** (10h) - User guides, videos, screenshots
7. **Developer Documentation** (8h) - API docs, architecture docs
8. **Security Audit** (6h) - Vulnerability scan, fix issues

**Deliverable:** Production-ready system with comprehensive tests and docs

---

## Summary Timeline

| Phase | Duration | Effort | Key Deliverable |
|-------|----------|--------|-----------------|
| 1 | Weeks 1-2 | 80-100h | Dynamic module system |
| 2 | Weeks 3-4 | 80-100h | Form builder |
| 3 | Week 5 | 40-50h | Form renderer |
| 4 | Week 6 | 40-50h | Multi-page forms |
| 5 | Week 7 | 35-40h | Rich text editor |
| 6 | Weeks 8-9 | 80-100h | Sales pipelines |
| 7 | Weeks 10-12 | 120-140h | Workflow automation |
| 8 | Week 13 | 40-50h | Email integration |
| 9 | Week 14 | 35-40h | Activity timeline |
| 10 | Weeks 15-16 | 70-80h | Reporting & analytics |
| 11 | Week 17 | 35-40h | RBAC |
| 12 | Week 18 | 35-40h | Import/export |
| 13 | Week 19 | 35-40h | Mobile optimization |
| 14 | Week 20 | 30-35h | Search & navigation |
| 15 | Weeks 21-22 | 70-80h | Testing & polish |

**Total Estimated Effort:** 900-1100 hours (22-28 weeks at 40h/week)

---

## All Documentation Created

1. `PHASE_1_WORKFLOWS.md` - Detailed workflows for Phase 1
2. `PHASE_2_WORKFLOWS.md` - Detailed workflows for Phase 2
3. `PHASES_3_TO_15_WORKFLOWS.md` - This document with condensed workflows
4. `CRM_FEATURES_COMPLETE.md` - Comprehensive 500+ feature list
5. `COMPREHENSIVE_DEVELOPMENT_PLAN.md` - Original high-level plan

**Ready to start implementation!**

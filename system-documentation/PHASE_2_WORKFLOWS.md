# Phase 2: Visual Form Builder - Detailed Workflows

## Overview
**Duration:** Weeks 3-4 (80-100 hours)
**Goal:** Drag-and-drop form builder with all 21 field types and advanced configuration

---

## Workflow 2.1: Setup Drag-and-Drop Infrastructure (4-5 hours)

### Tasks
1. **Install Dependencies** (0.5h)
   ```bash
   pnpm add @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities
   pnpm add -D @types/node
   ```

2. **Create DnD Context Provider** (1.5h)
   - File: `frontend/src/lib/components/form-builder/DndContext.svelte`
   - Setup DndContext with sensors
   - Configure collision detection
   - Handle drag events
   - Create overlay component

3. **Create Drag Utilities** (1h)
   - File: `frontend/src/lib/utils/dnd.ts`
   - Helper functions for drag operations
   - ID generation
   - Position calculations
   - Type guards

4. **Test DnD Setup** (1h)
   - Create simple drag test
   - Verify drag/drop works
   - Test keyboard navigation
   - Test touch support

### Acceptance Criteria
- [ ] DnD library installed and configured
- [ ] Drag context working
- [ ] Smooth drag animations
- [ ] Touch and keyboard support
- [ ] No console errors

---

## Workflow 2.2: Field Palette Component (6-8 hours)

### Tasks
1. **Create Field Type Registry** (2h)
   - File: `frontend/src/lib/form-builder/fieldTypes.ts`
   - Define all 21 field types:
     ```typescript
     export const FIELD_TYPES = {
       text: { label: 'Text', icon: 'Type', category: 'basic' },
       textarea: { label: 'Textarea', icon: 'AlignLeft', category: 'basic' },
       number: { label: 'Number', icon: 'Hash', category: 'numeric' },
       email: { label: 'Email', icon: 'Mail', category: 'basic' },
       phone: { label: 'Phone', icon: 'Phone', category: 'basic' },
       url: { label: 'URL', icon: 'Link', category: 'basic' },
       select: { label: 'Dropdown', icon: 'ChevronDown', category: 'choice' },
       multiselect: { label: 'Multi-Select', icon: 'List', category: 'choice' },
       radio: { label: 'Radio', icon: 'Circle', category: 'choice' },
       checkbox: { label: 'Checkbox', icon: 'CheckSquare', category: 'choice' },
       toggle: { label: 'Toggle', icon: 'ToggleLeft', category: 'choice' },
       date: { label: 'Date', icon: 'Calendar', category: 'datetime' },
       datetime: { label: 'Date & Time', icon: 'Clock', category: 'datetime' },
       time: { label: 'Time', icon: 'Clock', category: 'datetime' },
       currency: { label: 'Currency', icon: 'DollarSign', category: 'numeric' },
       percent: { label: 'Percent', icon: 'Percent', category: 'numeric' },
       lookup: { label: 'Lookup', icon: 'Search', category: 'relationship' },
       formula: { label: 'Formula', icon: 'Calculator', category: 'calculated' },
       file: { label: 'File', icon: 'File', category: 'media' },
       image: { label: 'Image', icon: 'Image', category: 'media' },
       rich_text: { label: 'Rich Text', icon: 'FileText', category: 'basic' }
     }
     ```
   - Categories: basic, numeric, choice, datetime, relationship, calculated, media
   - Icon mapping (lucide-svelte)

2. **Create Field Palette Component** (3h)
   - File: `frontend/src/lib/components/form-builder/FieldPalette.svelte`
   - Features:
     - Display all field types in grid
     - Group by category with collapsible sections
     - Search/filter field types
     - Drag source for each field type
     - Field type descriptions on hover
     - Popular fields section
   - Responsive design (sidebar on desktop, drawer on mobile)

3. **Create Field Type Card** (1h)
   - File: `frontend/src/lib/components/form-builder/FieldTypeCard.svelte`
   - Display icon, label, description
   - Draggable handle
   - Hover state
   - Click to add (alternative to drag)

4. **Style and Polish** (1h)
   - Smooth animations
   - Hover effects
   - Loading states
   - Empty states
   - Accessibility (ARIA labels)

### Acceptance Criteria
- [ ] All 21 field types displayed
- [ ] Can search field types
- [ ] Can filter by category
- [ ] Fields are draggable
- [ ] Click to add alternative
- [ ] Responsive on mobile
- [ ] Accessible

---

## Workflow 2.3: Form Canvas Component (10-12 hours)

### Tasks
1. **Create Form Canvas Container** (2h)
   - File: `frontend/src/lib/components/form-builder/FormCanvas.svelte`
   - Main canvas area
   - Drop zones for blocks and fields
   - Empty state (when no blocks)
   - Toolbar (add block, undo/redo, preview)
   - Zoom controls (optional)

2. **Create Block Container** (3h)
   - File: `frontend/src/lib/components/form-builder/BlockContainer.svelte`
   - Display block header with name and type
   - Collapsible sections
   - Tab interface for tab-type blocks
   - Column layout support (1, 2, 3 columns)
   - Drop zone for fields
   - Block settings menu (edit, duplicate, delete)
   - Drag handle for reordering blocks

3. **Create Field Container** (3h)
   - File: `frontend/src/lib/components/form-builder/FieldContainer.svelte`
   - Display field with label and type badge
   - Field preview (shows how it will look)
   - Width controls (25%, 33%, 50%, 100%)
   - Drag handle for reordering
   - Field menu (edit, duplicate, delete)
   - Required indicator
   - Validation rules indicator
   - Conditional visibility indicator

4. **Create Drop Zones** (2h)
   - File: `frontend/src/lib/components/form-builder/DropZone.svelte`
   - Visual drop zone between fields
   - Highlight on drag over
   - Different styles for valid/invalid drops
   - Handle field from palette vs field reorder

5. **Implement Drag Logic** (2h)
   - Handle field drag from palette → canvas
   - Handle field reorder within block
   - Handle field move between blocks
   - Handle block reorder
   - Generate unique field IDs
   - Update form state on drop

### Acceptance Criteria
- [ ] Can drag field from palette to canvas
- [ ] Can reorder fields within blocks
- [ ] Can move fields between blocks
- [ ] Can reorder blocks
- [ ] Visual feedback during drag
- [ ] Field width adjustable
- [ ] Block types render correctly
- [ ] Empty state when no blocks

---

## Workflow 2.4: Field Configuration Panel (12-14 hours)

### Tasks
1. **Create Config Panel Container** (2h)
   - File: `frontend/src/lib/components/form-builder/FieldConfigPanel.svelte`
   - Right sidebar (fixed or slide-over)
   - Tabs: Basic, Validation, Display, Advanced
   - Close button
   - Save/Cancel buttons
   - Field type indicator at top
   - Real-time preview

2. **Create Basic Settings Tab** (3h)
   - File: `frontend/src/lib/components/form-builder/config/BasicSettings.svelte`
   - Fields:
     - Label (text input)
     - API Name (auto-generated from label, editable)
     - Description (textarea)
     - Help Text (textarea)
     - Required toggle
     - Unique toggle
     - Default Value (type-specific input)
   - API name slugification
   - Validation feedback

3. **Create Validation Settings Tab** (3h)
   - File: `frontend/src/lib/components/form-builder/config/ValidationSettings.svelte`
   - Type-specific validation rules:
     - Text: min/max length, pattern
     - Number: min/max value, step
     - Email: pattern, allow multiple
     - Date: min/max date
     - etc.
   - Custom validation rules (advanced)
   - Validation messages
   - Test validation button

4. **Create Display Settings Tab** (2h)
   - File: `frontend/src/lib/components/form-builder/config/DisplaySettings.svelte`
   - Fields:
     - Width (25%, 33%, 50%, 100%)
     - Placeholder
     - Prefix/Suffix (for number/currency)
     - Display format
     - Searchable toggle
     - Filterable toggle
     - Sortable toggle
   - Preview updates in real-time

5. **Create Advanced Settings Tab** (2h)
   - File: `frontend/src/lib/components/form-builder/config/AdvancedSettings.svelte`
   - Type-specific settings:
     - Select: Manage options
     - Lookup: Configure relationship
     - Formula: Edit formula
     - File: Allowed types, max size
   - Conditional visibility builder (button to open modal)
   - Field dependencies
   - Custom settings JSON editor

### Acceptance Criteria
- [ ] All tabs functional
- [ ] Type-specific settings shown
- [ ] Real-time preview
- [ ] Validation works
- [ ] Changes saved correctly
- [ ] Can cancel changes
- [ ] Mobile-responsive

---

## Workflow 2.5: Option Manager (For Select/Radio/Multiselect) (4-5 hours)

### Tasks
1. **Create Option Manager Component** (3h)
   - File: `frontend/src/lib/components/form-builder/OptionManager.svelte`
   - Features:
     - List of options
     - Add option button
     - Inline editing
     - Drag to reorder
     - Delete option
     - Activate/deactivate option
     - Bulk import (CSV)
     - Color picker (for each option)
     - Metadata JSON editor (for advanced use)

2. **Create Option Item Component** (1h)
   - File: `frontend/src/lib/components/form-builder/OptionItem.svelte`
   - Editable label and value
   - Drag handle
   - Active toggle
   - Color indicator
   - Delete button

3. **Test Option Management** (1h)
   - Test CRUD operations
   - Test reordering
   - Test validation (duplicate values)
   - Test bulk import

### Acceptance Criteria
- [ ] Can add/edit/delete options
- [ ] Can reorder options
- [ ] Can set colors
- [ ] Can add metadata
- [ ] Validation prevents duplicates
- [ ] Bulk import works

---

## Workflow 2.6: Conditional Visibility Builder (8-10 hours)

### Tasks
1. **Create Condition Builder Modal** (3h)
   - File: `frontend/src/lib/components/form-builder/ConditionalVisibilityBuilder.svelte`
   - Full-screen or large modal
   - Visual rule builder interface
   - AND/OR toggle
   - Add condition button
   - Preview pane (shows when field will be visible)
   - Save/Cancel buttons

2. **Create Condition Row Component** (3h)
   - File: `frontend/src/lib/components/form-builder/ConditionRow.svelte`
   - Field selector (dropdown of all fields in module)
   - Operator selector (equals, not_equals, contains, etc.)
   - Value input (type depends on selected field)
   - Delete condition button
   - Validation (prevent invalid conditions)

3. **Create Condition Logic** (2h)
   - File: `frontend/src/lib/form-builder/conditionalLogic.ts`
   - Evaluate conditions
   - Support all operators:
     - equals, not_equals
     - contains, not_contains
     - starts_with, ends_with
     - greater_than, less_than
     - greater_than_or_equal, less_than_or_equal
     - between, in, not_in
     - is_empty, is_not_empty
     - is_checked, is_not_checked
   - Handle AND/OR logic
   - Circular dependency detection

4. **Test Conditional Logic** (1h)
   - Unit tests for all operators
   - Test AND/OR combinations
   - Test edge cases
   - Test with different field types

### Acceptance Criteria
- [ ] Visual rule builder works
- [ ] All operators supported
- [ ] AND/OR logic works
- [ ] Preview shows correct visibility
- [ ] No circular dependencies allowed
- [ ] Validation prevents errors

---

## Workflow 2.7: Formula Editor (8-10 hours)

### Tasks
1. **Install Monaco Editor** (1h)
   ```bash
   pnpm add monaco-editor
   pnpm add -D vite-plugin-monaco-editor
   ```
   - Configure Vite plugin
   - Setup worker loading
   - Configure themes

2. **Create Formula Editor Component** (4h)
   - File: `frontend/src/lib/components/form-builder/FormulaEditor.svelte`
   - Monaco editor integration
   - Syntax highlighting for formulas
   - Autocomplete for:
     - Field names (from current module)
     - Formula functions
     - Operators
   - Error highlighting
   - Function reference panel (sidebar)
   - Test formula button
   - Examples dropdown

3. **Create Formula Language Definition** (2h)
   - File: `frontend/src/lib/form-builder/formulaLanguage.ts`
   - Define syntax rules for Monaco
   - Token definitions
   - Keywords (IF, AND, OR, SUM, etc.)
   - Autocomplete provider
   - Hover provider (show function docs)

4. **Create Formula Function Reference** (1.5h)
   - File: `frontend/src/lib/components/form-builder/FormulaReference.svelte`
   - List all functions by category
   - Click to insert function
   - Function documentation
   - Examples for each function

5. **Create Formula Validator** (1.5h)
   - File: `frontend/src/lib/form-builder/formulaValidator.ts`
   - Parse formula syntax
   - Validate field references exist
   - Check function signatures
   - Detect circular dependencies
   - Return helpful error messages

### Acceptance Criteria
- [ ] Monaco editor integrated
- [ ] Syntax highlighting works
- [ ] Autocomplete works
- [ ] Error highlighting
- [ ] Can test formulas
- [ ] Function reference helpful
- [ ] Validation catches errors

---

## Workflow 2.8: Lookup Configurator (6-8 hours)

### Tasks
1. **Create Lookup Config Component** (3h)
   - File: `frontend/src/lib/components/form-builder/LookupConfigurator.svelte`
   - Fields:
     - Related module selector (dropdown)
     - Display field selector (dropdown of fields from related module)
     - Search fields multi-select
     - Allow create toggle
     - Cascade delete toggle
     - Relationship type (one-to-one, many-to-one, many-to-many)
     - Quick create fields selector
     - Show recent toggle
     - Recent limit (number input)

2. **Create Dependency Configuration** (2h)
   - File: `frontend/src/lib/components/form-builder/DependencyConfig.svelte`
   - Parent field selector (depends_on)
   - Filter configuration:
     - Field in related module
     - Operator
     - Target field (from current form)
   - Visual diagram showing relationship
   - Test dependency button

3. **Create Relationship Preview** (1h)
   - File: `frontend/src/lib/components/form-builder/RelationshipPreview.svelte`
   - Visual representation of relationship
   - Show data flow
   - Highlight dependencies

4. **Test Lookup Configuration** (1h)
   - Test all relationship types
   - Test dependency filtering
   - Test quick create fields
   - Validate configuration

### Acceptance Criteria
- [ ] Can configure lookup relationships
- [ ] Can set up dependencies
- [ ] Visual preview helpful
- [ ] All relationship types supported
- [ ] Validation prevents errors

---

## Workflow 2.9: Block Configuration (4-5 hours)

### Tasks
1. **Create Block Config Component** (2h)
   - File: `frontend/src/lib/components/form-builder/BlockConfig.svelte`
   - Fields:
     - Block name
     - Block type (section or tab)
     - Display order
     - Columns (1, 2, 3)
     - Collapsible toggle
     - Default collapsed toggle
   - Conditional visibility for blocks
   - Save/Cancel buttons

2. **Create Block Type Selector** (1h)
   - Visual selector (cards)
   - Section vs Tab explanation
   - Preview of each type

3. **Create Block Layout Preview** (1h)
   - Show how fields will be laid out
   - Column visualization
   - Responsive preview

4. **Test Block Configuration** (1h)
   - Test all block types
   - Test column layouts
   - Test conditional visibility

### Acceptance Criteria
- [ ] Can configure block settings
- [ ] Block types work correctly
- [ ] Layout preview accurate
- [ ] Conditional visibility works

---

## Workflow 2.10: Field Type Components (20-25 hours)

### Tasks for Each Field Type Component

**Text Field** (1h)
- File: `frontend/src/lib/components/fields/TextField.svelte`
- Input with validation
- Min/max length
- Pattern validation
- Placeholder

**Textarea Field** (1h)
- Resizable textarea
- Character counter
- Min/max length

**Number Field** (1h)
- Number input
- Min/max value
- Step increment
- Prefix/suffix

**Email Field** (1h)
- Email validation
- Multiple emails support

**Phone Field** (1.5h)
- Phone formatting
- Country code selector
- Validation

**URL Field** (1h)
- URL validation
- Protocol detection

**Select Field** (2h)
- Searchable dropdown
- Custom options
- Clear button
- Loading state

**Multiselect Field** (2h)
- Multi-select dropdown
- Tag display
- Max selections
- Search

**Radio Field** (1h)
- Radio group
- Horizontal/vertical layout
- Custom styling

**Checkbox Field** (1h)
- Single checkbox
- Label position
- Indeterminate state

**Toggle Field** (1h)
- Switch component
- On/off labels
- Custom colors

**Date Field** (2h)
- Date picker
- Min/max date
- Format options
- Calendar popup

**Datetime Field** (2h)
- Date + time picker
- Timezone support
- Format options

**Time Field** (1.5h)
- Time picker
- 12/24 hour format
- Step increment

**Currency Field** (2h)
- Number input with currency symbol
- Thousand separator
- Decimal places
- Currency selector

**Percent Field** (1.5h)
- Number input with % symbol
- Optional slider
- Min/max

**Lookup Field** (3h)
- Searchable dropdown
- Async data loading
- Create new button
- Recent items
- Dependency filtering

**Formula Field** (1h)
- Display only (calculated)
- Format based on return type
- Loading state during calculation

**File Field** (2h)
- File upload
- Drag-drop support
- File type validation
- Size validation
- Multiple files
- Preview

**Image Field** (2h)
- Image upload
- Preview thumbnail
- Crop/resize
- Format conversion

**Rich Text Field** (2h)
- Integration with TipTap
- Toolbar
- Basic formatting
- Image upload

### Acceptance Criteria
- [ ] All 21 field types implemented
- [ ] Each field validates correctly
- [ ] All fields accessible
- [ ] Consistent styling
- [ ] Error states
- [ ] Loading states

---

## Workflow 2.11: Form Builder State Management (6-8 hours)

### Tasks
1. **Create Form Builder Store** (3h)
   - File: `frontend/src/lib/stores/formBuilderStore.svelte.ts`
   - State:
     ```typescript
     const currentModule = $state<Module | null>(null)
     const blocks = $state<Block[]>([])
     const fields = $state<Field[]>([])
     const selectedBlock = $state<Block | null>(null)
     const selectedField = $state<Field | null>(null)
     const isDirty = $state(false)
     const history = $state<HistoryItem[]>([])
     const historyIndex = $state(0)
     ```
   - Actions:
     - addBlock, updateBlock, deleteBlock, reorderBlocks
     - addField, updateField, deleteField, reorderFields
     - selectBlock, selectField
     - undo, redo
     - save, discard

2. **Create Undo/Redo System** (2h)
   - File: `frontend/src/lib/form-builder/history.ts`
   - Track all changes
   - Undo/redo operations
   - Keyboard shortcuts (Cmd+Z, Cmd+Shift+Z)
   - History limit (50 operations)

3. **Create Auto-save** (1h)
   - Debounced save to API
   - Visual indicator (saving/saved)
   - Error handling

4. **Test State Management** (1h)
   - Test all actions
   - Test undo/redo
   - Test auto-save
   - Test state persistence

### Acceptance Criteria
- [ ] State management works correctly
- [ ] Undo/redo works
- [ ] Auto-save works
- [ ] No state inconsistencies
- [ ] Keyboard shortcuts work

---

## Workflow 2.12: Form Builder Integration Testing (6-8 hours)

### Tasks
1. **Write Component Tests** (3h)
   - Test FieldPalette
   - Test FormCanvas
   - Test FieldConfigPanel
   - Test all sub-components

2. **Write E2E Tests** (3h)
   - Test drag field from palette
   - Test configure field
   - Test reorder fields
   - Test delete field
   - Test save module
   - Test undo/redo

3. **Visual Regression Tests** (1h)
   - Screenshot tests for all field types
   - Test different layouts
   - Test responsive views

4. **Performance Testing** (1h)
   - Test with 50+ fields
   - Test drag performance
   - Optimize re-renders

### Acceptance Criteria
- [ ] 80%+ component test coverage
- [ ] All E2E flows tested
- [ ] No visual regressions
- [ ] Performance acceptable

---

## Workflow 2.13: Documentation (4-5 hours)

### Tasks
1. **User Documentation** (2h)
   - How to use form builder
   - Field type guide
   - Best practices
   - Video tutorials

2. **Developer Documentation** (2h)
   - Component architecture
   - State management
   - How to add new field types
   - API integration

3. **Screenshots and Videos** (1h)
   - Record demo videos
   - Take screenshots
   - Create GIFs for docs

### Acceptance Criteria
- [ ] User guide complete
- [ ] Developer docs complete
- [ ] Video tutorials created
- [ ] All features documented

---

## Phase 2 Deliverables Checklist

### Core Components
- [ ] Field palette with all 21 types
- [ ] Form canvas with drag-drop
- [ ] Field configuration panel
- [ ] Block configuration
- [ ] All 21 field type components

### Advanced Features
- [ ] Conditional visibility builder
- [ ] Formula editor with Monaco
- [ ] Lookup configurator
- [ ] Option manager

### State & Logic
- [ ] Form builder store
- [ ] Undo/redo system
- [ ] Auto-save
- [ ] Validation

### Testing & Docs
- [ ] Component tests
- [ ] E2E tests
- [ ] User documentation
- [ ] Developer documentation

### Demo-Ready Features
- [ ] Can drag field from palette to canvas
- [ ] Can configure all field properties
- [ ] Can set up conditional visibility
- [ ] Can create formulas
- [ ] Can configure lookups
- [ ] Can save complete module

---

## Success Metrics

- [ ] Can build complete form in under 10 minutes
- [ ] Drag-drop latency under 50ms
- [ ] All 21 field types working
- [ ] Zero critical bugs
- [ ] User can complete form builder tutorial without help

**Phase 2 Complete when:**
✅ All workflows completed
✅ All field types implemented
✅ Drag-drop working smoothly
✅ Advanced features functional
✅ Tests passing
✅ Documentation complete
✅ Demo-ready form builder

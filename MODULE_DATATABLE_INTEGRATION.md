# Module Builder & DataTable Integration

## Overview

This document describes the full integration between the module builder and datatable system, including views management, default filters, sorting, and column visibility settings.

## Architecture

### Database Schema

#### 1. **modules** table (enhanced)
```sql
- default_filters: jsonb (nullable)
- default_sorting: jsonb (nullable)
- default_column_visibility: jsonb (nullable)
- default_page_size: integer (default: 50)
```

#### 2. **module_views** table (new)
```sql
- id: bigint (primary key)
- module_id: bigint (foreign key to modules)
- user_id: bigint (nullable, foreign key to users)
- name: string
- description: text (nullable)
- filters: jsonb (default: [])
- sorting: jsonb (default: [])
- column_visibility: jsonb (default: {})
- column_order: jsonb (nullable)
- column_widths: jsonb (nullable)
- page_size: integer (default: 50)
- is_default: boolean (default: false)
- is_shared: boolean (default: false)
- display_order: integer (default: 0)
- timestamps
```

### Backend Components

#### Models

1. **Module** (`app/Models/Module.php`)
   - Added default view settings fields
   - New relationship: `views()` - hasMany ModuleView

2. **ModuleView** (`app/Models/ModuleView.php`)
   - Represents saved datatable views
   - Scopes: `default()`, `shared()`, `accessibleBy()`, `ordered()`
   - Belongs to Module and User

#### Controllers

**ViewsController** (`app/Http/Controllers/Api/Modules/ViewsController.php`)

Endpoints:
- `GET /api/v1/views/{moduleApiName}` - List all accessible views
- `GET /api/v1/views/{moduleApiName}/default` - Get default view or module defaults
- `GET /api/v1/views/{moduleApiName}/{viewId}` - Get specific view
- `POST /api/v1/views/{moduleApiName}` - Create new view
- `PUT /api/v1/views/{moduleApiName}/{viewId}` - Update view
- `DELETE /api/v1/views/{moduleApiName}/{viewId}` - Delete view

### Frontend Components

#### API Client

**views.ts** (`frontend/src/lib/api/views.ts`)

Functions:
- `getViews(moduleApiName)` - Fetch all views
- `getView(moduleApiName, viewId)` - Fetch specific view
- `getDefaultView(moduleApiName)` - Fetch default view or module defaults
- `createView(moduleApiName, data)` - Create new view
- `updateView(moduleApiName, viewId, data)` - Update view
- `deleteView(moduleApiName, viewId)` - Delete view

Types:
```typescript
interface ModuleView {
  id: number
  name: string
  description: string | null
  filters: FilterConfig[]
  sorting: SortConfig[]
  column_visibility: Record<string, boolean>
  column_order: string[] | null
  column_widths: Record<string, number> | null
  page_size: number
  is_default: boolean
  is_shared: boolean
  // ...
}

interface ModuleDefaults {
  filters: FilterConfig[]
  sorting: SortConfig[]
  column_visibility: Record<string, boolean>
  page_size: number
}
```

#### Components

1. **DataTableViews** (`DataTableViews.svelte`)
   - View dropdown selector
   - Save/Update view dialog
   - Delete view functionality
   - Features:
     - List personal and shared views
     - Load view on click
     - Save current table state as new view
     - Update existing view
     - Set view as default
     - Share view with team
     - Delete view

2. **DataTableToolbar** (enhanced)
   - Added DataTableViews component
   - New prop: `enableViews` (default: true)
   - Positioned at top-left before filters

3. **DataTable** (enhanced)
   - Imports `getDefaultView` from API
   - On mount:
     1. Attempts to load user's default view
     2. Falls back to module defaults if no view
     3. Falls back to table defaults if neither exists
     4. Fetches data with applied settings

## Usage

### 1. Setting Module Defaults (Backend)

```php
$module = Module::findByApiName('contacts');
$module->update([
    'default_filters' => [
        ['field' => 'status', 'operator' => 'equals', 'value' => 'active']
    ],
    'default_sorting' => [
        ['field' => 'created_at', 'direction' => 'desc']
    ],
    'default_column_visibility' => [
        'internal_notes' => false,
        'tags' => false
    ],
    'default_page_size' => 25
]);
```

### 2. Using DataTable with Views (Frontend)

```svelte
<DataTable
    moduleApiName="contacts"
    enableViews={true}
    enableFilters={true}
    enableSearch={true}
/>
```

The datatable will automatically:
- Load the user's default view if set
- Otherwise apply module defaults
- Otherwise use component defaults

### 3. Saving a View (User Action)

1. User configures datatable (filters, sorting, columns, etc.)
2. User clicks "Default View" dropdown → "Save Current View"
3. Dialog appears with options:
   - **Name**: Required, e.g., "My Active Contacts"
   - **Description**: Optional
   - **Set as my default view**: Makes this view load on mount
   - **Share with team**: Makes view visible to all users
4. View is saved and immediately available

### 4. View Loading Priority

```
1. User's personal default view (is_default = true, user_id = current_user)
2. Module defaults (default_filters, default_sorting, etc.)
3. Component defaults (hardcoded in DataTable)
```

## Features

### Views Features

✅ **Personal Views**
- Each user can create unlimited views
- Private by default (only visible to creator)
- Can set one view as personal default

✅ **Shared Views**
- Can be marked as shared to make visible to all users
- Any user can load shared views
- Only creator can edit/delete their views

✅ **Default Views**
- Each user can have ONE default view per module
- Setting a new default automatically unsets previous default
- Module-level defaults apply when no user default exists

✅ **View Management**
- Create new views from current table state
- Update existing views
- Delete views
- Reset to default (clears current view)

### What Views Store

A view captures the complete datatable configuration:
- **Filters**: All active column and global filters
- **Sorting**: Sort order (supports multi-column sorting)
- **Column Visibility**: Which columns are shown/hidden
- **Column Order**: Custom column arrangement
- **Column Widths**: Custom column sizes
- **Page Size**: Number of rows per page

## Integration Points

### Module Builder → DataTable

1. **Module Creation**: Set default filters, sorting, visibility, page size
2. **Module Update**: Modify defaults anytime
3. **Defaults Propagation**: New users see module defaults immediately

### DataTable → Views

1. **Auto-Load**: Datatable loads appropriate view on mount
2. **State Sync**: Current table state can be saved as view
3. **Live Updates**: Views reflect current configuration state

### Views → Users

1. **Personal Workspace**: Each user customizes their view
2. **Team Collaboration**: Share useful views with team
3. **Quick Switching**: Easy dropdown to switch between views

## API Examples

### Create a View

```typescript
await createView('contacts', {
  name: 'Active Leads',
  description: 'Contacts with status=lead and active in last 30 days',
  filters: [
    { field: 'status', operator: 'equals', value: 'lead' },
    { field: 'last_activity', operator: 'greater_than', value: '2025-11-02' }
  ],
  sorting: [
    { field: 'last_activity', direction: 'desc' }
  ],
  column_visibility: {
    'internal_notes': false,
    'created_at': false
  },
  page_size: 25,
  is_default: true,
  is_shared: false
});
```

### Load Default View

```typescript
const { view, module_defaults } = await getDefaultView('contacts');

if (view) {
  // User has a default view
  console.log(`Loading ${view.name}`);
  table.loadView(view);
} else if (module_defaults) {
  // Use module defaults
  table.applyDefaults(module_defaults);
} else {
  // Use component defaults
  table.resetToDefaults();
}
```

## Benefits

1. **User Productivity**: Users can save their preferred table configurations
2. **Team Consistency**: Shared views ensure team sees same important data
3. **Flexibility**: Easy to switch between different views for different tasks
4. **Persistence**: Table state is saved and restored across sessions
5. **Defaults**: Module creators can set sensible defaults for all users
6. **Customization**: Each user can customize without affecting others

## Migration Path

1. ✅ Run migrations: `php artisan tenants:migrate`
2. ✅ Views table created with indexes
3. ✅ Module defaults columns added
4. ✅ API endpoints registered
5. ✅ Frontend components integrated
6. ✅ DataTable loads defaults on mount

## Testing Checklist

- [ ] Create a module with default filters and sorting
- [ ] Verify datatable applies module defaults on first load
- [ ] Save current table state as a new view
- [ ] Set view as default and reload page
- [ ] Verify default view loads automatically
- [ ] Share a view and verify it's visible to other users
- [ ] Update an existing view with new filters
- [ ] Delete a view
- [ ] Switch between multiple views
- [ ] Reset to module defaults

## Future Enhancements

- [ ] View templates (pre-defined views for common use cases)
- [ ] View permissions (admin-only, role-based)
- [ ] View history/versioning
- [ ] View analytics (most used views)
- [ ] Bulk view management
- [ ] Export/import views between modules
- [ ] View search and filtering
- [ ] View favoriting

# Advanced DataTable Component Guide

## Overview

The DataTable component is a fully-featured, enterprise-grade data table built with SvelteKit, shadcn-svelte, and Tailwind CSS. It provides a rich set of features for displaying and manipulating tabular data.

## Features

### Core Features

- ✅ **Column Sorting** - Single and multi-column sorting with Shift+Click
- ✅ **Advanced Filtering** - Type-specific filters (text, number, date, select, etc.)
- ✅ **Global Search** - Search across all searchable columns
- ✅ **Pagination** - Configurable page sizes with navigation controls
- ✅ **Row Selection** - Single and bulk row selection with checkboxes
- ✅ **Column Visibility** - Show/hide columns dynamically
- ✅ **Inline Editing** - Double-click to edit cell values
- ✅ **Bulk Actions** - Perform actions on multiple selected rows
- ✅ **Export** - Export data to CSV/Excel formats
- ✅ **Saved Views** - Save and load custom table configurations
- ✅ **Filter Chips** - Visual representation of active filters
- ✅ **Responsive Design** - Works on desktop and mobile devices
- ✅ **Loading States** - Skeleton loaders and loading indicators
- ✅ **Error Handling** - Graceful error states
- ✅ **Empty States** - Clear messaging when no data is available

### Advanced Features

- **Custom Cell Renderers** - Use custom Svelte components for cells
- **Column Pinning** - Pin columns to left or right (planned)
- **Column Resizing** - Adjust column widths (planned)
- **Column Reordering** - Drag and drop columns (planned)
- **Grouping** - Group rows by column values (planned)

## Installation

The DataTable component is already set up in your project. All required dependencies are installed:

```bash
# Dependencies already included
- axios (for API calls)
- @internationalized/date (for date handling)
- svelte-sonner (for toast notifications)
- lucide-svelte (for icons)
- shadcn-svelte components
```

## Basic Usage

```svelte
<script lang="ts">
	import DataTable from '$lib/components/datatable/DataTable.svelte';
	import type { ColumnDef } from '$lib/components/datatable/types';

	const columns: ColumnDef[] = [
		{
			id: 'id',
			header: 'ID',
			accessorKey: 'id',
			type: 'number',
			sortable: true,
			filterable: true,
			visible: true,
			width: 80
		},
		{
			id: 'name',
			header: 'Name',
			accessorKey: 'name',
			type: 'text',
			sortable: true,
			filterable: true,
			searchable: true,
			visible: true,
			width: 200
		},
		{
			id: 'email',
			header: 'Email',
			accessorKey: 'email',
			type: 'email',
			sortable: true,
			filterable: true,
			searchable: true,
			visible: true,
			width: 220
		}
	];

	const data = [
		{ id: 1, name: 'John Doe', email: 'john@example.com' },
		{ id: 2, name: 'Jane Smith', email: 'jane@example.com' }
	];
</script>

<DataTable moduleApiName="users" {columns} initialData={data} />
```

## Column Definition

### Column Properties

| Property        | Type                         | Description                                        |
| --------------- | ---------------------------- | -------------------------------------------------- |
| `id`            | `string`                     | Unique identifier for the column                   |
| `header`        | `string`                     | Column header label                                |
| `accessorKey`   | `string`                     | Path to access data (supports nested: `user.name`) |
| `type`          | `ColumnType`                 | Data type for formatting and filtering             |
| `sortable`      | `boolean`                    | Enable sorting for this column                     |
| `filterable`    | `boolean`                    | Enable filtering for this column                   |
| `searchable`    | `boolean`                    | Include in global search                           |
| `visible`       | `boolean`                    | Default visibility                                 |
| `width`         | `number`                     | Column width in pixels                             |
| `minWidth`      | `number`                     | Minimum column width                               |
| `maxWidth`      | `number`                     | Maximum column width                               |
| `pinned`        | `'left' \| 'right' \| false` | Pin column position                                |
| `cell`          | `Component`                  | Custom cell renderer component                     |
| `format`        | `(value, row) => string`     | Custom formatting function                         |
| `cellClass`     | `(value, row) => string`     | Dynamic cell CSS classes                           |
| `filterOptions` | `FilterOption[]`             | Options for select/multiselect filters             |

### Column Types

```typescript
type ColumnType =
	| 'text' // Text input filter
	| 'number' // Number filter with operators
	| 'decimal' // Decimal number filter
	| 'currency' // Currency formatting
	| 'percent' // Percentage formatting
	| 'date' // Date filter with presets
	| 'datetime' // Date + time filter
	| 'time' // Time filter
	| 'boolean' // Yes/No display
	| 'select' // Single select filter
	| 'multiselect' // Multiple select filter
	| 'email' // Email with mailto link
	| 'phone' // Phone with tel link
	| 'url' // URL with external link
	| 'lookup' // Lookup to related records
	| 'tags' // Tag display
	| 'actions'; // Actions column
```

## Props

| Prop                | Type                                  | Default  | Description                            |
| ------------------- | ------------------------------------- | -------- | -------------------------------------- |
| `moduleApiName`     | `string`                              | required | API endpoint name for data fetching    |
| `columns`           | `ColumnDef[]`                         | `[]`     | Column definitions                     |
| `initialData`       | `any[]`                               | `[]`     | Initial data (skips API fetch)         |
| `module`            | `object`                              | -        | Module config to auto-generate columns |
| `defaultView`       | `number`                              | -        | Default saved view ID to load          |
| `enableSelection`   | `boolean`                             | `true`   | Enable row selection                   |
| `enableFilters`     | `boolean`                             | `true`   | Enable column filters                  |
| `enableSearch`      | `boolean`                             | `true`   | Enable global search                   |
| `enableSorting`     | `boolean`                             | `true`   | Enable sorting                         |
| `enablePagination`  | `boolean`                             | `true`   | Enable pagination                      |
| `enableViews`       | `boolean`                             | `true`   | Enable saved views                     |
| `enableExport`      | `boolean`                             | `true`   | Enable export                          |
| `enableBulkActions` | `boolean`                             | `true`   | Enable bulk actions                    |
| `enableInlineEdit`  | `boolean`                             | `true`   | Enable inline editing                  |
| `class`             | `string`                              | `''`     | Additional CSS classes                 |
| `onRowClick`        | `(row) => void`                       | -        | Row click handler                      |
| `onSelectionChange` | `(rows) => void`                      | -        | Selection change handler               |
| `onCellUpdate`      | `(id, field, value) => Promise<void>` | -        | Cell update handler                    |

## Filter Types

### Text Filter

- Contains
- Equals
- Not equals
- Starts with
- Ends with
- Is empty
- Is not empty

### Number Filter

- Equals
- Not equals
- Greater than
- Greater than or equal
- Less than
- Less than or equal
- Between
- Is empty
- Is not empty

### Date Filter

**Quick Presets:**

- Today
- Yesterday
- Last 7 days
- Last 30 days
- This month
- Last month

**Custom Range:**

- From/To date picker

### Select Filter

- Single selection
- Uses provided filterOptions

### Multi-Select Filter

- Multiple selections
- Uses provided filterOptions

## Inline Editing

Double-click any editable cell to enter edit mode. Supported types:

- `text`, `email`, `phone`, `url`
- `number`, `decimal`
- `date`, `datetime`

**Keyboard Shortcuts:**

- `Enter` - Save changes
- `Escape` - Cancel editing

## Sorting

**Single Column Sort:**

- Click column header to sort ascending
- Click again to sort descending
- Click third time to remove sort

**Multi-Column Sort:**

- Hold `Shift` + Click to add additional sort columns
- Sort priority is shown with numbers

## Bulk Actions

When rows are selected, bulk action buttons appear:

- **Add Tags** - Tag multiple records
- **Export** - Export selected rows
- **Delete** - Delete selected rows (with confirmation)

## Export

Export visible data to:

- **Excel (.xlsx)** - Formatted spreadsheet
- **CSV (.csv)** - Comma-separated values

Respects:

- Current filters
- Current sorting
- Column visibility
- Selected rows (if any selected)

## Saved Views

Save and restore table configurations:

- Column visibility
- Column order
- Column widths
- Active filters
- Sort configuration
- Page size

## API Integration

The DataTable expects the following API response format:

```typescript
{
  data: T[],
  meta: {
    current_page: number,
    from: number,
    last_page: number,
    per_page: number,
    to: number,
    total: number
  }
}
```

### Request Parameters

```typescript
{
  page: number,
  per_page: number,
  sort?: [{ field: string, direction: 'asc' | 'desc' }],
  filters?: [{ field: string, operator: FilterOperator, value: any }],
  search?: string,
  columns?: string[]
}
```

## Custom Cell Renderers

Create custom Svelte components for cells:

```svelte
<!-- CustomStatusCell.svelte -->
<script lang="ts">
	import { Badge } from '$lib/components/ui/badge';

	let { value, row, column, index } = $props();

	const variant = value === 'active' ? 'default' : 'secondary';
</script>

<Badge {variant}>{value}</Badge>
```

Use in column definition:

```typescript
import CustomStatusCell from './CustomStatusCell.svelte';

{
	id: 'status',
	header: 'Status',
	accessorKey: 'status',
	type: 'text',
	cell: CustomStatusCell
}
```

## Theming

The DataTable uses your shadcn theme automatically via CSS variables:

- `--background`
- `--foreground`
- `--muted`
- `--primary`
- `--border`
- etc.

All defined in `app.css` using Tailwind CSS v4 and OKLCH colors.

## Examples

### Basic Contact List

```svelte
<DataTable moduleApiName="contacts" enableViews={false} enableExport={true} />
```

### Read-Only Table

```svelte
<DataTable
	moduleApiName="logs"
	enableSelection={false}
	enableInlineEdit={false}
	enableBulkActions={false}
/>
```

### Custom Formatting

```typescript
{
	id: 'amount',
	header: 'Amount',
	accessorKey: 'amount',
	type: 'currency',
	format: (value, row) => {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: row.currency || 'USD'
		}).format(value);
	}
}
```

## Component Structure

```
datatable/
├── DataTable.svelte          # Main component
├── DataTableToolbar.svelte   # Search, filters, actions
├── DataTableHeader.svelte    # Table header with sorting
├── DataTableBody.svelte      # Table body with rows
├── DataTablePagination.svelte# Pagination controls
├── DataTableColumnToggle.svelte # Column visibility
├── DataTableFiltersDrawer.svelte # Filter panel
├── DataTableFilterChips.svelte # Active filter chips
├── DataTableViewSwitcher.svelte # Saved views
├── DataTableSaveViewDialog.svelte # Save view dialog
├── DataTableActions.svelte   # Row actions
├── EditableCell.svelte       # Inline editing
├── filters/
│   ├── TextFilter.svelte
│   ├── NumberFilter.svelte
│   ├── DateFilter.svelte
│   ├── DateRangeFilter.svelte
│   ├── SelectFilter.svelte
│   ├── MultiSelectFilter.svelte
│   └── LookupFilter.svelte
├── types.ts                  # TypeScript definitions
├── utils.ts                  # Helper functions
└── index.ts                  # Exports
```

## Performance Tips

1. **Pagination** - Use server-side pagination for large datasets
2. **Virtual Scrolling** - Consider for 1000+ rows (future feature)
3. **Column Visibility** - Hide unused columns by default
4. **Debounced Search** - Search is already debounced (300ms)
5. **Memoization** - Custom cell components should use derived values

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (responsive)

## Accessibility

- ARIA labels on interactive elements
- Keyboard navigation
- Screen reader support
- Focus management
- Semantic HTML

## Demo

Visit `/datatable-demo` to see all features in action.

## Troubleshooting

### Data not loading

- Check API endpoint is correct
- Verify response format matches expected structure
- Check browser console for errors

### Filters not working

- Ensure column has `filterable: true`
- Verify backend supports filter operators
- Check filter values are correct type

### Inline editing not working

- Column type must be editable type
- Implement `onCellUpdate` handler
- Check for TypeScript errors

## Future Enhancements

- [x] Column resizing - Enable via `enableColumnResize={true}` prop, drag column borders to resize
- [x] Column reordering (drag & drop) - Works in Columns dropdown (DataTableColumnToggle)
- [x] Row grouping - Enable via Table Settings dropdown → Group By, uses `DataTableGroupedBody.svelte`
- [x] Virtual scrolling - Enable via Table Settings dropdown → Virtual Scroll toggle
- [x] Advanced export options (PDF) - Works via Export dropdown in toolbar
- [ ] Cell validation
- [ ] Conditional formatting
- [x] Column freezing/pinning - Pin columns via Columns dropdown → hover row → click pin icons
- [x] Mobile-optimized view - Auto-enables on mobile via `enableResponsive={true}` (default), shows card view

Legend: [x] = Complete & Active, [ ] = Not implemented

## License

Part of the VRTX CRM project.

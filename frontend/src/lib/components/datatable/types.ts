/**
 * DataTable Types and Interfaces
 *
 * Type definitions for the advanced DataTable component system
 */

import type { Component } from 'svelte';
import type { RecordData, RecordFieldValue, ModuleRecord } from '$lib/types/modules';

// Re-export shared filter types for backward compatibility
export type {
	FilterOperator,
	FilterConfig,
	FilterOption,
	FilterGroup as FilterGroupData,
	FilterValue as DataTableFilterValue,
	DateRangeValue,
	NumberRangeValue,
	SortConfig
} from '$lib/types/filters';

// Import for internal use
import type {
	FilterOperator,
	FilterConfig,
	FilterOption,
	FilterValue as DataTableFilterValue,
	DateRangeValue,
	NumberRangeValue,
	SortConfig,
	FilterGroup
} from '$lib/types/filters';

/**
 * Base row data type for tables - represents a module record with id and nested data
 * The id is always required and is either a number or string.
 */
export type BaseRowData = {
	id: number | string;
	data?: RecordData;
} & Record<string, unknown>;

/**
 * Column Definition
 */
export interface ColumnDef<TData extends BaseRowData = BaseRowData> {
	/** Unique identifier for the column */
	id: string;

	/** Column header label */
	header: string;

	/** Field name to access in the data object */
	accessorKey: string;

	/** Column data type */
	type: ColumnType;

	/** Whether column is sortable */
	sortable?: boolean;

	/** Whether column is filterable */
	filterable?: boolean;

	/** Whether column is searchable in global search */
	searchable?: boolean;

	/** Default column visibility */
	visible?: boolean;

	/** Column width in pixels */
	width?: number;

	/** Minimum column width */
	minWidth?: number;

	/** Maximum column width */
	maxWidth?: number;

	/** Pin column to left or right */
	pinned?: 'left' | 'right' | false;

	/** Custom cell renderer component */
	cell?: Component;

	/** Custom header renderer component */
	headerComponent?: Component;

	/** Custom filter component */
	filterComponent?: Component;

	/** Options for select/multiselect columns (used for display) */
	options?: FilterOption[];

	/** Filter options for select/multiselect columns (used by Quick Filter Bar) */
	filterOptions?: FilterOption[];

	/** Format function for cell display */
	format?: (value: RecordFieldValue, row: TData) => string;

	/** Cell class name function */
	cellClass?: (value: RecordFieldValue, row: TData) => string;

	/** Column-specific metadata */
	meta?: ColumnMetadata;

	/**
	 * Mobile display priority (1-5, lower = higher priority)
	 * Priority 1: Always visible on mobile (name/title fields)
	 * Priority 2: High priority (status, amount)
	 * Priority 3: Medium priority (email, phone, date)
	 * Priority 4: Low priority (description, notes)
	 * Priority 5: Hidden on mobile (timestamps, IDs)
	 */
	mobilePriority?: 1 | 2 | 3 | 4 | 5;

	/** Explicitly control mobile visibility (overrides mobilePriority) */
	mobileVisible?: boolean;
}

/**
 * Column metadata for additional column configuration
 */
export interface ColumnMetadata {
	/** Related module for lookup columns */
	relatedModule?: string;
	/** Display field for lookup columns */
	displayField?: string;
	/** Currency code for currency columns */
	currencyCode?: string;
	/** Whether this is a system field */
	isSystem?: boolean;
	/** Custom render mode */
	renderMode?: 'default' | 'inline' | 'badge' | 'avatar';
	/** Field definition from module (includes options for select/multiselect) */
	field?: {
		options?: Array<{ label?: string; value: string | number | boolean }>;
		[key: string]: unknown;
	};
	/** Any additional metadata */
	[key: string]: unknown;
}

/**
 * Column Types
 */
export type ColumnType =
	| 'text'
	| 'textarea'
	| 'number'
	| 'decimal'
	| 'currency'
	| 'percent'
	| 'date'
	| 'datetime'
	| 'time'
	| 'boolean'
	| 'checkbox'
	| 'toggle'
	| 'radio'
	| 'select'
	| 'multiselect'
	| 'email'
	| 'phone'
	| 'url'
	| 'lookup'
	| 'user'
	| 'tags'
	| 'actions';

/**
 * Sort Direction
 */
export type SortDirection = 'asc' | 'desc' | false;

/**
 * Pagination State
 */
export interface PaginationState {
	page: number;
	perPage: number;
	total: number;
	from: number;
	to: number;
	lastPage: number;
}

/**
 * Column Visibility State
 */
export type ColumnVisibility = Record<string, boolean>;

/**
 * Column Order State
 */
export type ColumnOrder = string[];

/**
 * Row Selection State
 */
export type RowSelection = Record<string | number, boolean>;

/**
 * Table View Configuration
 */
export interface TableViewConfig {
	id?: number;
	name: string;
	description?: string;
	isDefault: boolean;
	isShared: boolean;
	columns: {
		visibility: ColumnVisibility;
		order: ColumnOrder;
		widths: Record<string, number>;
		pinned: Record<string, 'left' | 'right' | false>;
	};
	filters: FilterConfig[];
	sorting: SortConfig[];
	pagination: {
		perPage: number;
	};
	grouping?: {
		field: string;
		direction: 'asc' | 'desc';
	};
}

/**
 * Table State
 */
export interface TableState<TData extends BaseRowData = BaseRowData> {
	data: TData[];
	loading: boolean;
	error: string | null;
	pagination: PaginationState;
	sorting: SortConfig[];
	filters: FilterConfig[];
	globalFilter: string;
	columnVisibility: ColumnVisibility;
	columnOrder: ColumnOrder;
	columnWidths: Record<string, number>;
	columnPinning: Record<string, 'left' | 'right' | false>;
	rowSelection: RowSelection;
	currentView: TableViewConfig | ModuleView | null;
}

/**
 * Data Table Props
 */
export interface DataTableProps<TData extends BaseRowData = BaseRowData> {
	/** Module API name to fetch data from */
	moduleApiName: string;

	/** Column definitions */
	columns?: ColumnDef<TData>[];

	/** Initial data (if not fetching from API) */
	initialData?: TData[];

	/** Default view ID to load */
	defaultView?: number;

	/** Enable row selection */
	enableSelection?: boolean;

	/** Enable column filters */
	enableFilters?: boolean;

	/** Enable global search */
	enableSearch?: boolean;

	/** Enable sorting */
	enableSorting?: boolean;

	/** Enable pagination */
	enablePagination?: boolean;

	/** Enable saved views */
	enableViews?: boolean;

	/** Enable export */
	enableExport?: boolean;

	/** Enable bulk actions */
	enableBulkActions?: boolean;

	/** Enable column reordering */
	enableColumnReorder?: boolean;

	/** Enable column resizing */
	enableColumnResize?: boolean;

	/** Custom CSS class */
	class?: string;

	/** Row click handler */
	onRowClick?: (row: TData) => void;

	/** Selection change handler */
	onSelectionChange?: (rows: TData[]) => void;

	/** Bulk action handler */
	onBulkAction?: (action: string, rows: TData[]) => void;
}

/**
 * API Request Parameters
 */
export interface DataTableRequest {
	page: number;
	per_page: number;
	sort?: SortConfig[];
	filters?: FilterConfig[];
	search?: string;
	columns?: string[];
}

/**
 * API Response Format
 */
export interface DataTableResponse<TData extends BaseRowData = BaseRowData> {
	data: TData[];
	meta: {
		current_page: number;
		from: number;
		last_page: number;
		per_page: number;
		to: number;
		total: number;
	};
}

/**
 * Bulk Action Definition
 */
export interface BulkAction {
	id: string;
	label: string;
	icon?: Component;
	variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost';
	confirm?: {
		title: string;
		description: string;
	};
	handler: (rows: BaseRowData[]) => void | Promise<void>;
}

/**
 * Column Context
 */
export interface ColumnContext<TData extends BaseRowData = BaseRowData> {
	column: ColumnDef<TData>;
	row: TData;
	value: RecordFieldValue;
	index: number;
}

/**
 * Create View Request (for saving new views)
 */
export interface CreateViewRequest {
	name: string;
	description?: string;
	is_default?: boolean;
	is_shared?: boolean;
}

/**
 * Module View (returned from API)
 */
export interface ModuleView {
	id: number;
	module_id: number;
	user_id: number | null;
	name: string;
	description: string | null;
	filters: FilterConfig[];
	sorting: SortConfig[];
	column_visibility: Record<string, boolean>;
	column_order: string[] | null;
	column_widths: Record<string, number> | null;
	page_size: number;
	is_default: boolean;
	is_shared: boolean;
	display_order: number;
	created_at: string;
	updated_at: string;
}

/**
 * Table Context (for Svelte context API)
 */
export interface TableContext<TData extends BaseRowData = BaseRowData> {
	state: TableState<TData>;
	columns: ColumnDef<TData>[];
	updateSort: (field: string, shiftKey?: boolean) => void;
	updateFilter: (filter: FilterConfig) => void;
	removeFilter: (field: string) => void;
	clearFilters: () => void;
	setFilters: (filters: FilterConfig[]) => void;
	updateGlobalFilter: (value: string) => void;
	goToPage: (page: number) => void;
	setPageSize: (size: number) => void;
	toggleRowSelection: (rowId: string | number) => void;
	toggleAllRows: () => void;
	clearSelection: () => void;
	toggleColumnVisibility: (columnId: string) => void;
	resetColumnVisibility: () => void;
	setColumnOrder: (order: ColumnOrder) => void;
	resizeColumn: (columnId: string, width: number) => void;
	pinColumn: (columnId: string, position: 'left' | 'right' | false) => void;
	loadView: (view: TableViewConfig | ModuleView) => void;
	saveView: (view: CreateViewRequest) => Promise<ModuleView | null>;
	deleteView: (viewId: number) => Promise<boolean>;
	updateCurrentView: () => Promise<ModuleView | null>;
	refresh: () => Promise<void>;
}

/**
 * Export Format
 */
export type ExportFormat = 'csv' | 'excel' | 'pdf';

/**
 * Export Options
 */
export interface ExportOptions {
	format: ExportFormat;
	filename?: string;
	includeHeaders?: boolean;
	selectedOnly?: boolean;
	allPages?: boolean;
}

// FilterGroupData is now exported from '$lib/types/filters' as FilterGroup

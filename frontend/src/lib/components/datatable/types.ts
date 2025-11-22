/**
 * DataTable Types and Interfaces
 *
 * Type definitions for the advanced DataTable component system
 */

import type { Component } from 'svelte';

/**
 * Column Definition
 */
export interface ColumnDef<TData = any> {
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

	/** Filter options for select/multiselect columns */
	filterOptions?: FilterOption[];

	/** Format function for cell display */
	format?: (value: any, row: TData) => string;

	/** Cell class name function */
	cellClass?: (value: any, row: TData) => string;

	/** Column-specific metadata */
	meta?: Record<string, any>;
}

/**
 * Column Types
 */
export type ColumnType =
	| 'text'
	| 'number'
	| 'decimal'
	| 'currency'
	| 'percent'
	| 'date'
	| 'datetime'
	| 'time'
	| 'boolean'
	| 'select'
	| 'multiselect'
	| 'email'
	| 'phone'
	| 'url'
	| 'lookup'
	| 'tags'
	| 'actions';

/**
 * Sort Direction
 */
export type SortDirection = 'asc' | 'desc' | false;

/**
 * Sort Configuration
 */
export interface SortConfig {
	field: string;
	direction: 'asc' | 'desc';
}

/**
 * Filter Operators
 */
export type FilterOperator =
	| 'equals'
	| 'not_equals'
	| 'contains'
	| 'not_contains'
	| 'starts_with'
	| 'ends_with'
	| 'in'
	| 'not_in'
	| 'greater_than'
	| 'greater_than_or_equal'
	| 'less_than'
	| 'less_than_or_equal'
	| 'between'
	| 'is_null'
	| 'is_not_null'
	| 'is_empty'
	| 'is_not_empty';

/**
 * Filter Configuration
 */
export interface FilterConfig {
	field: string;
	operator: FilterOperator;
	value: any;
}

/**
 * Filter Option (for select filters)
 */
export interface FilterOption {
	label: string;
	value: any;
	count?: number;
}

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
export interface TableState<TData = any> {
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
	currentView: TableViewConfig | null;
}

/**
 * Data Table Props
 */
export interface DataTableProps<TData = any> {
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
export interface DataTableResponse<TData = any> {
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
	handler: (rows: any[]) => void | Promise<void>;
}

/**
 * Filter Option
 */
export interface FilterOption {
	label: string;
	value: any;
	count?: number;
}

/**
 * Date Range Filter Value
 */
export interface DateRangeValue {
	from: Date | null;
	to: Date | null;
}

/**
 * Number Range Filter Value
 */
export interface NumberRangeValue {
	from: number | null;
	to: number | null;
}

/**
 * Column Context
 */
export interface ColumnContext<TData = any> {
	column: ColumnDef<TData>;
	row: TData;
	value: any;
	index: number;
}

/**
 * Table Context (for Svelte context API)
 */
export interface TableContext<TData = any> {
	state: TableState<TData>;
	columns: ColumnDef<TData>[];
	updateSort: (field: string) => void;
	updateFilter: (filter: FilterConfig) => void;
	removeFilter: (field: string) => void;
	clearFilters: () => void;
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
	loadView: (view: TableViewConfig) => void;
	saveView: (view: Partial<TableViewConfig>) => Promise<void>;
	deleteView: (viewId: number) => Promise<void>;
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

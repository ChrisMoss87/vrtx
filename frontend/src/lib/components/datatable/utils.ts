/**
 * DataTable Utility Functions
 */

import type {
	ColumnDef,
	SortConfig,
	FilterConfig,
	TableState,
	DataTableRequest,
	DataTableResponse,
	PaginationState,
	BaseRowData
} from './types';
import type { RecordData } from '$lib/types/modules';

/**
 * Generate columns from module fields
 */
export function generateColumnsFromModule(module: any): ColumnDef[] {
	const columns: ColumnDef[] = [];

	// Add ID column
	columns.push({
		id: 'id',
		header: 'ID',
		accessorKey: 'id',
		type: 'number',
		sortable: true,
		filterable: true,
		searchable: false,
		visible: true,
		width: 80,
		minWidth: 60,
		maxWidth: 120
	});

	// Generate columns from module fields
	module.blocks?.forEach((block: any) => {
		block.fields?.forEach((field: any) => {
			const fieldOptions = field.options?.map((opt: any) => ({
				label: opt.label,
				value: opt.value
			}));
			columns.push({
				id: field.api_name,
				header: field.label,
				accessorKey: `data.${field.api_name}`,
				type: mapFieldTypeToColumnType(field.type),
				sortable: true,
				filterable: true,
				searchable: field.is_searchable,
				visible: true,
				width: getDefaultColumnWidth(field.type),
				options: fieldOptions,
				filterOptions: fieldOptions, // Used by Quick Filter Bar
				meta: {
					field,
					blockName: block.name
				}
			});
		});
	});

	// Add timestamps
	columns.push(
		{
			id: 'created_at',
			header: 'Created',
			accessorKey: 'created_at',
			type: 'datetime',
			sortable: true,
			filterable: true,
			searchable: false,
			visible: true,
			width: 180
		},
		{
			id: 'updated_at',
			header: 'Updated',
			accessorKey: 'updated_at',
			type: 'datetime',
			sortable: true,
			filterable: true,
			searchable: false,
			visible: false,
			width: 180
		}
	);

	// Add actions column
	columns.push({
		id: 'actions',
		header: 'Actions',
		accessorKey: 'id',
		type: 'actions',
		sortable: false,
		filterable: false,
		searchable: false,
		visible: true,
		width: 80,
		pinned: 'right'
	});

	return columns;
}

/**
 * Map field type to column type
 */
function mapFieldTypeToColumnType(fieldType: string): any {
	const typeMap: Record<string, string> = {
		text: 'text',
		textarea: 'text',
		email: 'email',
		phone: 'phone',
		url: 'url',
		number: 'number',
		decimal: 'decimal',
		currency: 'currency',
		percent: 'percent',
		date: 'date',
		datetime: 'datetime',
		time: 'time',
		select: 'select',
		multiselect: 'multiselect',
		radio: 'select',
		checkbox: 'boolean',
		toggle: 'boolean',
		lookup: 'lookup',
		rich_text: 'text'
	};

	return typeMap[fieldType] || 'text';
}

/**
 * Get default column width based on type
 */
function getDefaultColumnWidth(fieldType: string): number {
	const widthMap: Record<string, number> = {
		text: 200,
		textarea: 300,
		email: 220,
		phone: 150,
		url: 250,
		number: 120,
		decimal: 120,
		currency: 120,
		percent: 100,
		date: 130,
		datetime: 180,
		time: 100,
		select: 150,
		multiselect: 200,
		checkbox: 80,
		toggle: 80,
		lookup: 180,
		rich_text: 300
	};

	return widthMap[fieldType] || 200;
}

/**
 * Transform filters from frontend array format to backend object format
 *
 * Frontend format: [{ field: 'name', operator: 'contains', value: 'test' }]
 * Backend format: { 'name': { operator: 'contains', value: 'test' } }
 */
export function transformFiltersForApi(filters: FilterConfig[]): Record<string, any> {
	const filterObj: Record<string, any> = {};

	filters.forEach((filter) => {
		// Map frontend operators to backend operators if needed
		const operatorMap: Record<string, string> = {
			is_empty: 'is_null',
			is_not_empty: 'is_not_null'
		};

		const operator = operatorMap[filter.operator] || filter.operator;

		// Handle between filter format
		if (filter.operator === 'between' && filter.value !== null && typeof filter.value === 'object' && !Array.isArray(filter.value)) {
			const val = filter.value as { min?: number; max?: number; from?: string | number; to?: string | number };
			filterObj[filter.field] = {
				operator,
				min: val.min ?? val.from,
				max: val.max ?? val.to
			};
		} else if (filter.operator === 'in' || filter.operator === 'not_in') {
			// Handle 'in' operator with array values
			filterObj[filter.field] = {
				operator,
				value: Array.isArray(filter.value) ? filter.value : [filter.value]
			};
		} else if (Array.isArray(filter.value) && filter.value.length > 0) {
			// If value is an array but operator isn't 'in', use 'in' operator
			filterObj[filter.field] = {
				operator: 'in',
				value: filter.value
			};
		} else {
			filterObj[filter.field] = {
				operator,
				value: filter.value
			};
		}
	});

	return filterObj;
}

/**
 * Build API request from table state
 */
export function buildApiRequest(state: TableState): DataTableRequest {
	const request: DataTableRequest = {
		page: state.pagination.page,
		per_page: state.pagination.perPage
	};

	// Add sorting
	if (state.sorting.length > 0) {
		request.sort = state.sorting;
	}

	// Add filters - transform to backend format
	if (state.filters.length > 0) {
		request.filters = state.filters;
	}

	// Add global search
	if (state.globalFilter) {
		request.search = state.globalFilter;
	}

	// Add visible columns
	const visibleColumns = Object.entries(state.columnVisibility)
		.filter(([_, visible]) => visible)
		.map(([columnId]) => columnId);

	if (visibleColumns.length > 0) {
		request.columns = visibleColumns;
	}

	return request;
}

/**
 * Parse API response to update table state
 */
export function parseApiResponse<TData extends BaseRowData = BaseRowData>(
	response: DataTableResponse<TData>
): { data: TData[]; pagination: PaginationState } {
	return {
		data: response.data,
		pagination: {
			page: response.meta.current_page,
			perPage: response.meta.per_page,
			total: response.meta.total,
			from: response.meta.from || 0,
			to: response.meta.to || 0,
			lastPage: response.meta.last_page
		}
	};
}

/**
 * Toggle sort direction
 */
export function toggleSortDirection(current: SortConfig[], field: string): SortConfig[] {
	const existing = current.find((s) => s.field === field);

	if (!existing) {
		// No sort yet, add ascending
		return [...current, { field, direction: 'asc' }];
	} else if (existing.direction === 'asc') {
		// Change to descending
		return current.map((s) => (s.field === field ? { field, direction: 'desc' } : s));
	} else {
		// Remove sort
		return current.filter((s) => s.field !== field);
	}
}

/**
 * Toggle multi-column sort with Shift key
 */
export function toggleMultiSort(
	current: SortConfig[],
	field: string,
	shiftKey: boolean
): SortConfig[] {
	if (!shiftKey) {
		// Single column sort
		const existing = current.find((s) => s.field === field);

		if (!existing) {
			return [{ field, direction: 'asc' }];
		} else if (existing.direction === 'asc') {
			return [{ field, direction: 'desc' }];
		} else {
			return [];
		}
	} else {
		// Multi-column sort
		return toggleSortDirection(current, field);
	}
}

/**
 * Get value from nested object path
 */
export function getNestedValue(obj: any, path: string): any {
	return path.split('.').reduce((current, key) => current?.[key], obj);
}

/**
 * Format cell value based on column type
 */
export function formatCellValue(value: any, columnType: string): string {
	if (value === null || value === undefined) {
		return '';
	}

	switch (columnType) {
		case 'date':
			return formatDate(value);
		case 'datetime':
			return formatDateTime(value);
		case 'time':
			return formatTime(value);
		case 'currency':
			return formatCurrency(value);
		case 'percent':
			return formatPercent(value);
		case 'number':
		case 'decimal':
			return formatNumber(value);
		case 'boolean':
			return value ? 'Yes' : 'No';
		case 'multiselect':
			return Array.isArray(value) ? value.join(', ') : String(value);
		default:
			return String(value);
	}
}

/**
 * Format date
 */
function formatDate(value: any): string {
	try {
		const date = new Date(value);
		return date.toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric'
		});
	} catch {
		return String(value);
	}
}

/**
 * Format datetime
 */
function formatDateTime(value: any): string {
	try {
		const date = new Date(value);
		return date.toLocaleString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit'
		});
	} catch {
		return String(value);
	}
}

/**
 * Format time
 */
function formatTime(value: any): string {
	try {
		const date = new Date(value);
		return date.toLocaleTimeString('en-US', {
			hour: '2-digit',
			minute: '2-digit'
		});
	} catch {
		return String(value);
	}
}

/**
 * Format currency
 */
function formatCurrency(value: any): string {
	const num = Number(value);
	if (isNaN(num)) return String(value);

	return new Intl.NumberFormat('en-US', {
		style: 'currency',
		currency: 'USD'
	}).format(num);
}

/**
 * Format percent
 */
function formatPercent(value: any): string {
	const num = Number(value);
	if (isNaN(num)) return String(value);

	return `${num}%`;
}

/**
 * Format number
 */
function formatNumber(value: any): string {
	const num = Number(value);
	if (isNaN(num)) return String(value);

	return new Intl.NumberFormat('en-US').format(num);
}

/**
 * Debounce function
 */
export function debounce<T extends (...args: any[]) => any>(
	func: T,
	wait: number
): (...args: Parameters<T>) => void {
	let timeout: ReturnType<typeof setTimeout> | null = null;

	return function (this: any, ...args: Parameters<T>) {
		const later = () => {
			timeout = null;
			func.apply(this, args);
		};

		if (timeout) clearTimeout(timeout);
		timeout = setTimeout(later, wait);
	};
}

/**
 * Serialize table state to URL params
 */
export function serializeTableState(state: TableState): URLSearchParams {
	const params = new URLSearchParams();

	// Pagination
	params.set('page', String(state.pagination.page));
	params.set('per_page', String(state.pagination.perPage));

	// Sorting
	if (state.sorting.length > 0) {
		params.set('sort', state.sorting.map((s) => `${s.field}:${s.direction}`).join(','));
	}

	// Filters
	if (state.filters.length > 0) {
		params.set('filters', JSON.stringify(state.filters));
	}

	// Search
	if (state.globalFilter) {
		params.set('search', state.globalFilter);
	}

	return params;
}

/**
 * Deserialize URL params to table state
 */
export function deserializeTableState(params: URLSearchParams): Partial<TableState> {
	const state: Partial<TableState> = {};

	// Pagination
	const page = params.get('page');
	const perPage = params.get('per_page');

	if (page || perPage) {
		state.pagination = {
			page: page ? parseInt(page) : 1,
			perPage: perPage ? parseInt(perPage) : 50,
			total: 0,
			from: 0,
			to: 0,
			lastPage: 1
		};
	}

	// Sorting
	const sort = params.get('sort');
	if (sort) {
		state.sorting = sort.split(',').map((s) => {
			const [field, direction] = s.split(':');
			return { field, direction: direction as 'asc' | 'desc' };
		});
	}

	// Filters
	const filters = params.get('filters');
	if (filters) {
		try {
			state.filters = JSON.parse(filters);
		} catch {
			state.filters = [];
		}
	}

	// Search
	const search = params.get('search');
	if (search) {
		state.globalFilter = search;
	}

	return state;
}

/**
 * Calculate selected row count
 */
export function getSelectedRowCount(rowSelection: Record<string | number, boolean>): number {
	return Object.values(rowSelection).filter(Boolean).length;
}

/**
 * Check if all rows are selected
 */
export function areAllRowsSelected(
	rowSelection: Record<string | number, boolean>,
	totalRows: number
): boolean {
	return getSelectedRowCount(rowSelection) === totalRows && totalRows > 0;
}

/**
 * Check if some rows are selected
 */
export function areSomeRowsSelected(rowSelection: Record<string | number, boolean>): boolean {
	return getSelectedRowCount(rowSelection) > 0;
}

/**
 * Mobile Priority Constants
 */
export const MOBILE_PRIORITY = {
	ALWAYS_VISIBLE: 1,
	HIGH: 2,
	MEDIUM: 3,
	LOW: 4,
	HIDDEN: 5
} as const;

/**
 * Get default mobile priority based on column type and name
 * Priority 1: Always visible (name/title fields, record identifiers)
 * Priority 2: High priority (status, stage, amount, currency)
 * Priority 3: Medium priority (email, phone, date, select)
 * Priority 4: Low priority (description, notes, textarea)
 * Priority 5: Hidden on mobile (timestamps, IDs, actions)
 */
export function getDefaultMobilePriority(
	column: ColumnDef,
	recordNameField?: string
): 1 | 2 | 3 | 4 | 5 {
	// If explicitly set, return that
	if (column.mobilePriority !== undefined) {
		return column.mobilePriority;
	}

	const id = column.id.toLowerCase();
	const type = column.type;

	// Priority 1: Record name field, name, title
	if (recordNameField && column.id === recordNameField) {
		return MOBILE_PRIORITY.ALWAYS_VISIBLE;
	}
	if (id === 'name' || id === 'title' || id.includes('_name') || id.endsWith('name')) {
		return MOBILE_PRIORITY.ALWAYS_VISIBLE;
	}

	// Priority 5: System fields, timestamps, IDs, actions
	if (id === 'id' || id === 'actions') {
		return MOBILE_PRIORITY.HIDDEN;
	}
	if (id === 'created_at' || id === 'updated_at' || id === 'deleted_at') {
		return MOBILE_PRIORITY.HIDDEN;
	}
	if (id.endsWith('_id') && type === 'number') {
		return MOBILE_PRIORITY.HIDDEN;
	}

	// Priority 2: High importance fields
	if (type === 'currency' || type === 'percent') {
		return MOBILE_PRIORITY.HIGH;
	}
	if (id === 'status' || id === 'stage' || id === 'state' || id === 'priority') {
		return MOBILE_PRIORITY.HIGH;
	}
	if (id === 'amount' || id === 'total' || id === 'value' || id === 'price') {
		return MOBILE_PRIORITY.HIGH;
	}

	// Priority 3: Medium importance
	if (type === 'email' || type === 'phone' || type === 'url') {
		return MOBILE_PRIORITY.MEDIUM;
	}
	if (type === 'date' || type === 'datetime') {
		return MOBILE_PRIORITY.MEDIUM;
	}
	if (type === 'select' || type === 'boolean') {
		return MOBILE_PRIORITY.MEDIUM;
	}
	if (type === 'lookup' || type === 'user') {
		return MOBILE_PRIORITY.MEDIUM;
	}

	// Priority 4: Low importance
	if (type === 'textarea' || type === 'text') {
		// Long text fields are lower priority
		if (id === 'description' || id === 'notes' || id === 'comment' || id === 'comments') {
			return MOBILE_PRIORITY.LOW;
		}
	}
	if (type === 'multiselect' || type === 'tags') {
		return MOBILE_PRIORITY.LOW;
	}

	// Default to medium priority
	return MOBILE_PRIORITY.MEDIUM;
}

/**
 * Check if a column should be visible on mobile based on priority threshold
 */
export function isColumnVisibleOnMobile(
	column: ColumnDef,
	priorityThreshold: number = 3,
	recordNameField?: string
): boolean {
	// Explicit override takes precedence
	if (column.mobileVisible !== undefined) {
		return column.mobileVisible;
	}

	const priority = getDefaultMobilePriority(column, recordNameField);
	return priority <= priorityThreshold;
}

/**
 * Get columns sorted by mobile priority
 */
export function getColumnsByMobilePriority(
	columns: ColumnDef[],
	recordNameField?: string
): ColumnDef[] {
	return [...columns].sort((a, b) => {
		const aPriority = getDefaultMobilePriority(a, recordNameField);
		const bPriority = getDefaultMobilePriority(b, recordNameField);
		return aPriority - bPriority;
	});
}

/**
 * Get visible columns for mobile (priority 1-3 by default)
 */
export function getMobileVisibleColumns(
	columns: ColumnDef[],
	maxColumns: number = 4,
	recordNameField?: string
): ColumnDef[] {
	const sorted = getColumnsByMobilePriority(columns, recordNameField);

	// Filter out actions and id columns for card display
	const filtered = sorted.filter(
		(col) => col.type !== 'actions' && col.id !== 'id'
	);

	return filtered.slice(0, maxColumns);
}

/**
 * Get hidden columns for mobile (to show in expanded section)
 */
export function getMobileHiddenColumns(
	columns: ColumnDef[],
	maxVisibleColumns: number = 4,
	recordNameField?: string
): ColumnDef[] {
	const sorted = getColumnsByMobilePriority(columns, recordNameField);

	// Filter out actions and id columns
	const filtered = sorted.filter(
		(col) => col.type !== 'actions' && col.id !== 'id'
	);

	return filtered.slice(maxVisibleColumns);
}

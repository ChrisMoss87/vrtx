/**
 * Shared Filter Types
 *
 * Unified filter types used across DataTable, Reports, Dashboards, and Kanban views.
 * This ensures consistent filter behavior throughout the application.
 */

/**
 * Filter Operators - all supported filter operations
 */
export type FilterOperator =
	// Equality operators
	| 'equals'
	| 'not_equals'
	// String operators
	| 'contains'
	| 'not_contains'
	| 'starts_with'
	| 'ends_with'
	// Array/Set operators
	| 'in'
	| 'not_in'
	// Comparison operators
	| 'greater_than'
	| 'greater_than_or_equal'
	| 'less_than'
	| 'less_than_or_equal'
	| 'between'
	// Null/Empty operators
	| 'is_null'
	| 'is_not_null'
	| 'is_empty'
	| 'is_not_empty'
	// Date-specific operators (relative dates)
	| 'today'
	| 'yesterday'
	| 'last_7_days'
	| 'last_30_days'
	| 'this_week'
	| 'last_week'
	| 'this_month'
	| 'last_month'
	| 'this_quarter'
	| 'last_quarter'
	| 'this_year'
	| 'last_year'
	| 'before'
	| 'after';

/**
 * Date range value for 'between' operator on date fields
 */
export interface DateRangeValue {
	from: Date | string | null;
	to: Date | string | null;
}

/**
 * Number range value for 'between' operator on numeric fields
 */
export interface NumberRangeValue {
	from: number | null;
	to: number | null;
}

/**
 * Filter value type - supports primitive values, arrays, and range objects
 */
export type FilterValue =
	| string
	| number
	| boolean
	| null
	| string[]
	| number[]
	| DateRangeValue
	| NumberRangeValue
	| { from: string; to: string }
	| { from: number; to: number };

/**
 * Filter Configuration - the core filter definition used everywhere
 */
export interface FilterConfig {
	/** The field name/key to filter on */
	field: string;
	/** The filter operator to apply */
	operator: FilterOperator;
	/** The value(s) to filter by */
	value: FilterValue;
}

/**
 * Filter Option - for select/multiselect filter dropdowns
 */
export interface FilterOption {
	label: string;
	value: string | number | boolean;
	count?: number;
}

/**
 * Filter Group - for advanced AND/OR filter logic
 */
export interface FilterGroup {
	id: string;
	logic: 'AND' | 'OR';
	conditions: FilterConfig[];
	groups: FilterGroup[];
}

/**
 * Date Range Preset - predefined date ranges for quick selection
 */
export type DateRangePreset =
	| 'today'
	| 'yesterday'
	| 'last_7_days'
	| 'last_30_days'
	| 'last_90_days'
	| 'this_week'
	| 'last_week'
	| 'this_month'
	| 'last_month'
	| 'this_quarter'
	| 'last_quarter'
	| 'this_year'
	| 'last_year'
	| 'custom';

/**
 * Date Range Configuration - for reports and dashboards
 */
export interface DateRangeConfig {
	/** The field to apply the date range to (defaults to created_at) */
	field?: string;
	/** Preset type or 'custom' for manual range */
	type?: DateRangePreset;
	/** Start date for custom range (ISO string) */
	start?: string;
	/** End date for custom range (ISO string) */
	end?: string;
}

/**
 * Sort Configuration
 */
export interface SortConfig {
	field: string;
	direction: 'asc' | 'desc';
}

/**
 * Helper function to check if an operator is a date-relative operator
 */
export function isDateRelativeOperator(operator: FilterOperator): boolean {
	return [
		'today',
		'yesterday',
		'last_7_days',
		'last_30_days',
		'this_week',
		'last_week',
		'this_month',
		'last_month',
		'this_quarter',
		'last_quarter',
		'this_year',
		'last_year'
	].includes(operator);
}

/**
 * Helper function to check if an operator requires no value
 */
export function isNoValueOperator(operator: FilterOperator): boolean {
	return [
		'is_null',
		'is_not_null',
		'is_empty',
		'is_not_empty',
		'today',
		'yesterday',
		'last_7_days',
		'last_30_days',
		'this_week',
		'last_week',
		'this_month',
		'last_month',
		'this_quarter',
		'last_quarter',
		'this_year',
		'last_year'
	].includes(operator);
}

/**
 * Helper function to check if an operator requires a range value
 */
export function isRangeOperator(operator: FilterOperator): boolean {
	return operator === 'between';
}

/**
 * Helper function to check if an operator requires an array value
 */
export function isArrayOperator(operator: FilterOperator): boolean {
	return operator === 'in' || operator === 'not_in';
}

/**
 * Get operators available for a given field type
 */
export function getOperatorsForFieldType(fieldType: string): FilterOperator[] {
	switch (fieldType) {
		case 'text':
		case 'textarea':
		case 'email':
		case 'phone':
		case 'url':
			return [
				'equals',
				'not_equals',
				'contains',
				'not_contains',
				'starts_with',
				'ends_with',
				'is_empty',
				'is_not_empty',
				'is_null',
				'is_not_null'
			];

		case 'number':
		case 'decimal':
		case 'currency':
		case 'percent':
			return [
				'equals',
				'not_equals',
				'greater_than',
				'greater_than_or_equal',
				'less_than',
				'less_than_or_equal',
				'between',
				'is_null',
				'is_not_null'
			];

		case 'date':
		case 'datetime':
			return [
				'equals',
				'not_equals',
				'before',
				'after',
				'between',
				'today',
				'yesterday',
				'last_7_days',
				'last_30_days',
				'this_week',
				'last_week',
				'this_month',
				'last_month',
				'this_quarter',
				'last_quarter',
				'this_year',
				'last_year',
				'is_null',
				'is_not_null'
			];

		case 'boolean':
		case 'checkbox':
		case 'toggle':
			return ['equals', 'not_equals', 'is_null', 'is_not_null'];

		case 'select':
		case 'radio':
			return ['equals', 'not_equals', 'in', 'not_in', 'is_null', 'is_not_null'];

		case 'multiselect':
		case 'tags':
			return ['in', 'not_in', 'is_empty', 'is_not_empty'];

		case 'lookup':
		case 'user':
			return ['equals', 'not_equals', 'in', 'not_in', 'is_null', 'is_not_null'];

		default:
			return ['equals', 'not_equals', 'is_null', 'is_not_null'];
	}
}

/**
 * Get human-readable label for an operator
 */
export function getOperatorLabel(operator: FilterOperator): string {
	const labels: Record<FilterOperator, string> = {
		equals: 'equals',
		not_equals: 'does not equal',
		contains: 'contains',
		not_contains: 'does not contain',
		starts_with: 'starts with',
		ends_with: 'ends with',
		in: 'is any of',
		not_in: 'is none of',
		greater_than: 'is greater than',
		greater_than_or_equal: 'is greater than or equal to',
		less_than: 'is less than',
		less_than_or_equal: 'is less than or equal to',
		between: 'is between',
		is_null: 'is empty',
		is_not_null: 'is not empty',
		is_empty: 'is empty',
		is_not_empty: 'is not empty',
		today: 'is today',
		yesterday: 'is yesterday',
		last_7_days: 'is in last 7 days',
		last_30_days: 'is in last 30 days',
		this_week: 'is this week',
		last_week: 'is last week',
		this_month: 'is this month',
		last_month: 'is last month',
		this_quarter: 'is this quarter',
		last_quarter: 'is last quarter',
		this_year: 'is this year',
		last_year: 'is last year',
		before: 'is before',
		after: 'is after'
	};
	return labels[operator] || operator;
}

/**
 * Transform filters for API request format
 * Converts array format to the object format expected by the backend
 */
export function transformFiltersForApi(
	filters: FilterConfig[]
): Record<string, { operator: string; value: FilterValue }> {
	const result: Record<string, { operator: string; value: FilterValue }> = {};

	for (const filter of filters) {
		result[filter.field] = {
			operator: filter.operator,
			value: filter.value
		};
	}

	return result;
}

/**
 * Parse filters from API response format back to FilterConfig array
 */
export function parseFiltersFromApi(
	apiFilters: Record<string, { operator: string; value: FilterValue }>
): FilterConfig[] {
	return Object.entries(apiFilters).map(([field, config]) => ({
		field,
		operator: config.operator as FilterOperator,
		value: config.value
	}));
}

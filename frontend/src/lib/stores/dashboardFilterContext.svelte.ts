/**
 * Dashboard Filter Context Store
 * Handles cross-widget filtering and global filter state
 */

import type { FilterConfig, DateRangeConfig, FilterValue } from '$lib/types/filters';

// Global filter state for cross-widget filtering
let globalFilters = $state<FilterConfig[]>([]);
let sourceWidgetId = $state<number | null>(null);
let globalDateRange = $state<DateRangeConfig | null>(null);
let selectedOwnerId = $state<number | null>(null);

// Callbacks for filter changes
let filterChangeCallbacks = $state<Array<(filters: FilterConfig[]) => void>>([]);

/**
 * Apply a global filter from a widget
 */
export function applyGlobalFilter(field: string, value: FilterValue, widgetId: number) {
	const existingIndex = globalFilters.findIndex((f) => f.field === field);

	if (existingIndex >= 0) {
		// Update existing filter
		globalFilters = globalFilters.map((f, i) =>
			i === existingIndex ? { ...f, value } : f
		);
	} else {
		// Add new filter
		globalFilters = [...globalFilters, { field, operator: 'equals', value }];
	}

	sourceWidgetId = widgetId;
	notifyFilterChange();
}

/**
 * Apply multiple global filters at once
 */
export function applyGlobalFilters(filters: FilterConfig[], widgetId: number) {
	globalFilters = [...filters];
	sourceWidgetId = widgetId;
	notifyFilterChange();
}

/**
 * Remove a specific global filter
 */
export function removeGlobalFilter(field: string) {
	globalFilters = globalFilters.filter((f) => f.field !== field);

	if (globalFilters.length === 0) {
		sourceWidgetId = null;
	}

	notifyFilterChange();
}

/**
 * Clear all global filters
 */
export function clearGlobalFilters() {
	globalFilters = [];
	sourceWidgetId = null;
	notifyFilterChange();
}

/**
 * Set global date range
 */
export function setGlobalDateRange(dateRange: DateRangeConfig | null) {
	globalDateRange = dateRange;
	notifyFilterChange();
}

/**
 * Set selected owner filter
 */
export function setSelectedOwner(ownerId: number | null) {
	selectedOwnerId = ownerId;

	if (ownerId !== null) {
		applyGlobalFilter('owner_id', ownerId, -1);
	} else {
		removeGlobalFilter('owner_id');
	}
}

/**
 * Get current global filters
 */
export function getGlobalFilters(): FilterConfig[] {
	return globalFilters;
}

/**
 * Get source widget ID that applied filters
 */
export function getSourceWidgetId(): number | null {
	return sourceWidgetId;
}

/**
 * Check if a widget is the source of current filters
 */
export function isFilterSource(widgetId: number): boolean {
	return sourceWidgetId === widgetId;
}

/**
 * Get global date range
 */
export function getGlobalDateRange(): DateRangeConfig | null {
	return globalDateRange;
}

/**
 * Get selected owner ID
 */
export function getSelectedOwner(): number | null {
	return selectedOwnerId;
}

/**
 * Check if there are any active global filters
 */
export function hasActiveFilters(): boolean {
	return globalFilters.length > 0 || globalDateRange !== null;
}

/**
 * Subscribe to filter changes
 */
export function onFilterChange(callback: (filters: FilterConfig[]) => void): () => void {
	filterChangeCallbacks = [...filterChangeCallbacks, callback];

	// Return unsubscribe function
	return () => {
		filterChangeCallbacks = filterChangeCallbacks.filter((cb) => cb !== callback);
	};
}

/**
 * Notify all subscribers of filter changes
 */
function notifyFilterChange() {
	for (const callback of filterChangeCallbacks) {
		callback(globalFilters);
	}
}

/**
 * Merge global filters with widget-specific filters
 */
export function mergeWithGlobalFilters(widgetFilters: FilterConfig[]): FilterConfig[] {
	const merged = [...widgetFilters];

	for (const globalFilter of globalFilters) {
		const existingIndex = merged.findIndex((f) => f.field === globalFilter.field);
		if (existingIndex === -1) {
			merged.push(globalFilter);
		}
	}

	return merged;
}

/**
 * Build query params for API calls including global filters
 */
export function buildFilterQueryParams(
	widgetFilters?: FilterConfig[],
	dateRange?: DateRangeConfig
): Record<string, string> {
	const params: Record<string, string> = {};

	const effectiveFilters = widgetFilters
		? mergeWithGlobalFilters(widgetFilters)
		: globalFilters;

	if (effectiveFilters.length > 0) {
		params.filters = JSON.stringify(effectiveFilters);
	}

	const effectiveDateRange = dateRange || globalDateRange;
	if (effectiveDateRange) {
		params.date_range = JSON.stringify(effectiveDateRange);
	}

	return params;
}

/**
 * Reactive getters for use in components
 */
export function useGlobalFilters() {
	return {
		get filters() {
			return globalFilters;
		},
		get sourceWidgetId() {
			return sourceWidgetId;
		},
		get dateRange() {
			return globalDateRange;
		},
		get selectedOwnerId() {
			return selectedOwnerId;
		},
		get hasActiveFilters() {
			return globalFilters.length > 0 || globalDateRange !== null;
		}
	};
}

/**
 * Dashboard Navigation Store
 * Handles widget click-through navigation and drill-down state
 */

import { goto } from '$app/navigation';
import type { FilterConfig } from '$lib/types/filters';

// Breadcrumb item for drill-down navigation
export interface DrillDownBreadcrumb {
	label: string;
	level: number;
	filters: FilterConfig[];
}

// Navigation context for a widget click
export interface WidgetClickContext {
	widgetId: number;
	widgetType: string;
	moduleApiName?: string;
	moduleId?: number;
	recordId?: number;
	filters?: FilterConfig[];
	drillDownField?: string;
	drillDownValue?: string | number;
}

// State using Svelte 5 runes
let navigationHistory = $state<string[]>([]);
let drillDownState = $state<Map<number, DrillDownBreadcrumb[]>>(new Map());

/**
 * Navigate to records list with filters
 */
export function navigateToRecords(moduleApiName: string, filters?: FilterConfig[]) {
	const url = new URL(`/records/${moduleApiName}`, window.location.origin);

	if (filters && filters.length > 0) {
		url.searchParams.set('filters', JSON.stringify(filters));
	}

	// Track navigation history
	navigationHistory = [...navigationHistory, url.pathname + url.search];

	goto(url.pathname + url.search);
}

/**
 * Navigate to a specific record
 */
export function navigateToRecord(moduleApiName: string, recordId: number | string) {
	const url = `/records/${moduleApiName}/${recordId}`;
	navigationHistory = [...navigationHistory, url];
	goto(url);
}

/**
 * Build navigation URL from widget click context
 */
export function buildNavigationUrl(context: WidgetClickContext): string | null {
	if (!context.moduleApiName) {
		return null;
	}

	// If we have a specific record ID, go to record detail
	if (context.recordId) {
		return `/records/${context.moduleApiName}/${context.recordId}`;
	}

	// Otherwise, go to records list with filters
	const url = new URL(`/records/${context.moduleApiName}`, window.location.origin);

	if (context.filters && context.filters.length > 0) {
		url.searchParams.set('filters', JSON.stringify(context.filters));
	}

	return url.pathname + url.search;
}

/**
 * Handle widget click and navigate
 */
export function handleWidgetClick(context: WidgetClickContext) {
	const url = buildNavigationUrl(context);

	if (url) {
		navigationHistory = [...navigationHistory, url];
		goto(url);
	}
}

/**
 * Get drill-down breadcrumbs for a widget
 */
export function getDrillDownBreadcrumbs(widgetId: number): DrillDownBreadcrumb[] {
	return drillDownState.get(widgetId) || [];
}

/**
 * Add a drill-down level for a widget
 */
export function addDrillDownLevel(
	widgetId: number,
	label: string,
	filters: FilterConfig[]
): DrillDownBreadcrumb[] {
	const current = drillDownState.get(widgetId) || [];
	const newBreadcrumb: DrillDownBreadcrumb = {
		label,
		level: current.length,
		filters
	};

	const updated = [...current, newBreadcrumb];
	drillDownState.set(widgetId, updated);
	drillDownState = new Map(drillDownState);

	return updated;
}

/**
 * Navigate to a specific drill-down level
 */
export function goToDrillDownLevel(widgetId: number, level: number): FilterConfig[] {
	const current = drillDownState.get(widgetId) || [];

	if (level < 0 || level >= current.length) {
		return [];
	}

	// Truncate breadcrumbs to this level
	const truncated = current.slice(0, level + 1);
	drillDownState.set(widgetId, truncated);
	drillDownState = new Map(drillDownState);

	return truncated[level]?.filters || [];
}

/**
 * Reset drill-down state for a widget
 */
export function resetDrillDown(widgetId: number) {
	drillDownState.delete(widgetId);
	drillDownState = new Map(drillDownState);
}

/**
 * Reset all drill-down states
 */
export function resetAllDrillDowns() {
	drillDownState = new Map();
}

/**
 * Go back in navigation history
 */
export function goBack() {
	if (navigationHistory.length > 1) {
		navigationHistory = navigationHistory.slice(0, -1);
		const previousUrl = navigationHistory[navigationHistory.length - 1];
		if (previousUrl) {
			goto(previousUrl);
		}
	}
}

/**
 * Clear navigation history
 */
export function clearNavigationHistory() {
	navigationHistory = [];
}

/**
 * Get current navigation history
 */
export function getNavigationHistory(): string[] {
	return navigationHistory;
}

/**
 * Check if a widget type supports click-through navigation
 */
export function supportsClickThrough(widgetType: string): boolean {
	const clickableTypes = [
		'kpi',
		'goal_kpi',
		'chart',
		'table',
		'funnel',
		'leaderboard',
		'recent_records',
		'progress',
		'heatmap'
	];
	return clickableTypes.includes(widgetType);
}

/**
 * Build filters from KPI widget config
 */
export function buildFiltersFromKpiConfig(config: Record<string, unknown>): FilterConfig[] {
	const filters: FilterConfig[] = [];

	// Add any existing filters from config
	if (Array.isArray(config.filters)) {
		filters.push(...(config.filters as FilterConfig[]));
	}

	// Add date range filter if present
	if (config.date_range && typeof config.date_range === 'object') {
		const dateRange = config.date_range as Record<string, unknown>;
		if (dateRange.type || (dateRange.start && dateRange.end)) {
			// Date range will be handled by the records page
		}
	}

	return filters;
}

/**
 * Build filters from chart segment click
 */
export function buildFiltersFromChartClick(
	config: Record<string, unknown>,
	segmentLabel: string,
	groupByField?: string
): FilterConfig[] {
	const filters = buildFiltersFromKpiConfig(config);

	// Add filter for the clicked segment
	if (groupByField && segmentLabel) {
		filters.push({
			field: groupByField,
			operator: 'equals',
			value: segmentLabel
		});
	}

	return filters;
}

/**
 * Build filters from funnel stage click
 */
export function buildFiltersFromFunnelClick(
	config: Record<string, unknown>,
	stageValue: string,
	stageField: string
): FilterConfig[] {
	const filters = buildFiltersFromKpiConfig(config);

	filters.push({
		field: stageField,
		operator: 'equals',
		value: stageValue
	});

	return filters;
}

/**
 * Build filters from leaderboard row click
 */
export function buildFiltersFromLeaderboardClick(
	config: Record<string, unknown>,
	userId: number
): FilterConfig[] {
	const filters = buildFiltersFromKpiConfig(config);

	filters.push({
		field: 'owner_id',
		operator: 'equals',
		value: userId
	});

	return filters;
}

import { apiClient } from './client';

// Types
export type ReportType = 'table' | 'chart' | 'summary' | 'matrix' | 'pivot';
export type ChartType = 'bar' | 'line' | 'pie' | 'doughnut' | 'area' | 'funnel' | 'scatter' | 'gauge' | 'kpi';
export type AggregationType = 'count' | 'sum' | 'avg' | 'min' | 'max' | 'count_distinct';

export interface ReportFilter {
	field: string;
	operator: string;
	value: any;
}

export interface ReportGrouping {
	field: string;
	alias?: string;
	interval?: 'hour' | 'day' | 'week' | 'month' | 'quarter' | 'year';
}

export interface ReportAggregation {
	function: AggregationType;
	field: string;
	alias?: string;
}

export interface ReportSorting {
	field: string;
	direction: 'asc' | 'desc';
}

export interface ReportDateRange {
	field?: string;
	type?: 'today' | 'yesterday' | 'this_week' | 'last_week' | 'this_month' | 'last_month' | 'this_quarter' | 'last_quarter' | 'this_year' | 'last_year' | 'last_7_days' | 'last_30_days' | 'last_90_days' | 'custom';
	start?: string;
	end?: string;
}

export interface ReportConfig {
	limit?: number;
	row_field?: string;
	col_field?: string;
	[key: string]: any;
}

export interface Report {
	id: number;
	name: string;
	description: string | null;
	module_id: number | null;
	user_id: number;
	type: ReportType;
	chart_type: ChartType | null;
	is_public: boolean;
	is_favorite: boolean;
	config: ReportConfig;
	filters: ReportFilter[];
	grouping: ReportGrouping[];
	aggregations: ReportAggregation[];
	sorting: ReportSorting[];
	date_range: ReportDateRange;
	schedule: ReportSchedule | null;
	last_run_at: string | null;
	created_at: string;
	updated_at: string;
	module?: {
		id: number;
		name: string;
		api_name: string;
	};
	user?: {
		id: number;
		name: string;
	};
}

export interface ReportSchedule {
	enabled: boolean;
	frequency: 'daily' | 'weekly' | 'monthly';
	time: string;
	day_of_week?: number;
	day_of_month?: number;
	recipients: string[];
	format: 'csv' | 'pdf' | 'xlsx';
}

export interface ReportResult {
	type: string;
	data: any[];
	total: number;
	grouping?: ReportGrouping[];
	aggregations?: ReportAggregation[];
	rows?: string[];
	columns?: string[];
}

export interface KpiResult {
	value: number;
	previous_value: number | null;
	change: number | null;
	change_percent: number | null;
	change_type: 'increase' | 'decrease' | 'neutral';
}

export interface ModuleField {
	name: string;
	label: string;
	type: string;
	system: boolean;
	options?: { value: string; label: string }[];
}

export interface CreateReportRequest {
	name: string;
	description?: string;
	module_id?: number;
	type: ReportType;
	chart_type?: ChartType;
	is_public?: boolean;
	config?: ReportConfig;
	filters?: ReportFilter[];
	grouping?: ReportGrouping[];
	aggregations?: ReportAggregation[];
	sorting?: ReportSorting[];
	date_range?: ReportDateRange;
}

export interface UpdateReportRequest extends Partial<CreateReportRequest> {}

export interface ReportPreviewRequest {
	module_id?: number;
	type: ReportType;
	chart_type?: ChartType;
	config?: ReportConfig;
	filters?: ReportFilter[];
	grouping?: ReportGrouping[];
	aggregations?: ReportAggregation[];
	sorting?: ReportSorting[];
	date_range?: ReportDateRange;
}

export interface KpiRequest {
	module_id?: number;
	aggregation: AggregationType;
	field?: string;
	filters?: ReportFilter[];
	date_range?: ReportDateRange;
	compare_range?: ReportDateRange;
}

// API Functions
export const reportsApi = {
	/**
	 * Get report types, chart types, and aggregations
	 */
	async getTypes(): Promise<{
		report_types: Record<string, string>;
		chart_types: Record<string, string>;
		aggregations: Record<string, string>;
	}> {
		const response = await apiClient.get<{
			report_types: Record<string, string>;
			chart_types: Record<string, string>;
			aggregations: Record<string, string>;
		}>('/reports/types');
		return response;
	},

	/**
	 * Get available fields for a module
	 */
	async getFields(moduleId: number): Promise<ModuleField[]> {
		const response = await apiClient.get<{ data: ModuleField[] }>('/reports/fields', {
			params: { module_id: moduleId }
		});
		return response.data;
	},

	/**
	 * List reports
	 */
	async list(options?: {
		module_id?: number;
		type?: ReportType;
		favorites?: boolean;
		search?: string;
		page?: number;
		per_page?: number;
	}): Promise<{
		data: Report[];
		meta: { current_page: number; last_page: number; per_page: number; total: number };
	}> {
		const response = await apiClient.get<{
			data: Report[];
			meta: { current_page: number; last_page: number; per_page: number; total: number };
		}>('/reports', { params: options });
		return response;
	},

	/**
	 * Create a new report
	 */
	async create(data: CreateReportRequest): Promise<Report> {
		const response = await apiClient.post<{ data: Report }>('/reports', data);
		return response.data;
	},

	/**
	 * Get a single report
	 */
	async get(id: number): Promise<Report> {
		const response = await apiClient.get<{ data: Report }>(`/reports/${id}`);
		return response.data;
	},

	/**
	 * Update a report
	 */
	async update(id: number, data: UpdateReportRequest): Promise<Report> {
		const response = await apiClient.put<{ data: Report }>(`/reports/${id}`, data);
		return response.data;
	},

	/**
	 * Delete a report
	 */
	async delete(id: number): Promise<void> {
		await apiClient.delete(`/reports/${id}`);
	},

	/**
	 * Execute a report and get results
	 */
	async execute(id: number, refresh = false): Promise<{
		data: ReportResult;
		cached: boolean;
		last_run_at: string | null;
	}> {
		const response = await apiClient.get<{
			data: ReportResult;
			cached: boolean;
			last_run_at: string | null;
		}>(`/reports/${id}/execute`, { params: { refresh } });
		return response;
	},

	/**
	 * Preview a report without saving
	 */
	async preview(data: ReportPreviewRequest): Promise<ReportResult> {
		const response = await apiClient.post<{ data: ReportResult }>('/reports/preview', data);
		return response.data;
	},

	/**
	 * Export a report
	 */
	async export(id: number, format: 'csv' | 'json' = 'csv'): Promise<Blob> {
		const response = await apiClient.get(`/reports/${id}/export`, {
			params: { format },
			responseType: 'blob'
		});
		return response as unknown as Blob;
	},

	/**
	 * Toggle favorite status
	 */
	async toggleFavorite(id: number): Promise<{ is_favorite: boolean }> {
		const response = await apiClient.post<{ is_favorite: boolean }>(`/reports/${id}/toggle-favorite`);
		return response;
	},

	/**
	 * Duplicate a report
	 */
	async duplicate(id: number): Promise<Report> {
		const response = await apiClient.post<{ data: Report }>(`/reports/${id}/duplicate`);
		return response.data;
	},

	/**
	 * Calculate KPI value
	 */
	async calculateKpi(data: KpiRequest): Promise<KpiResult> {
		const response = await apiClient.post<{ data: KpiResult }>('/reports/kpi', data);
		return response.data;
	}
};

// Helper functions
export function getChartIcon(chartType: ChartType): string {
	const icons: Record<ChartType, string> = {
		bar: 'bar-chart-2',
		line: 'trending-up',
		pie: 'pie-chart',
		doughnut: 'circle',
		area: 'area-chart',
		funnel: 'filter',
		scatter: 'scatter-chart',
		gauge: 'gauge',
		kpi: 'hash'
	};
	return icons[chartType] || 'bar-chart-2';
}

export function getReportTypeIcon(type: ReportType): string {
	const icons: Record<ReportType, string> = {
		table: 'table',
		chart: 'bar-chart-2',
		summary: 'file-text',
		matrix: 'grid',
		pivot: 'layout-grid'
	};
	return icons[type] || 'file-text';
}

export function formatChangePercent(value: number | null): string {
	if (value === null) return '';
	const sign = value >= 0 ? '+' : '';
	return `${sign}${value.toFixed(1)}%`;
}

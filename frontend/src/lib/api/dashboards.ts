import { apiClient } from './client';
import type { Report, ReportResult } from './reports';
import type { FilterConfig, DateRangeConfig, FilterOperator, FilterValue } from '$lib/types/filters';

// Re-export filter types for convenience
export type { FilterConfig, DateRangeConfig, FilterOperator, FilterValue } from '$lib/types/filters';

// Types
export type WidgetType =
	| 'report'
	| 'kpi'
	| 'chart'
	| 'table'
	| 'activity'
	| 'pipeline'
	| 'tasks'
	| 'calendar'
	| 'text'
	| 'iframe'
	| 'goal_kpi'
	| 'leaderboard'
	| 'funnel'
	| 'progress'
	| 'recent_records'
	| 'heatmap'
	| 'quick_links'
	| 'embed'
	| 'forecast';

export interface GridPosition {
	x: number;
	y: number;
	w: number;
	h: number;
	minW?: number;
	minH?: number;
	maxW?: number;
	maxH?: number;
}

export interface WidgetConfig {
	// KPI widget
	module_id?: number;
	aggregation?: string;
	field?: string;
	filters?: FilterConfig[];
	date_range?: DateRangeConfig;
	compare_range?: DateRangeConfig;
	// Pipeline widget
	pipeline_id?: number;
	// Tasks/Activity widget
	user_id?: number;
	limit?: number;
	// Text widget
	content?: string;
	// IFrame widget
	url?: string;
	// Goal KPI widget
	target?: number;
	target_label?: string;
	// Leaderboard widget
	rank_field?: string;
	display_fields?: string[];
	// Funnel widget
	stage_field?: string;
	value_field?: string;
	// Progress widget
	current_value?: number;
	goal_value?: number;
	// Recent records widget
	fields_to_display?: string[];
	// Heatmap widget
	x_field?: string;
	y_field?: string;
	value_aggregation?: string;
	// Quick links widget
	links?: {
		id: string;
		label: string;
		url: string;
		icon?: string;
		description?: string;
		external?: boolean;
		color?: string;
	}[];
	columns?: 1 | 2 | 3;
	// Embed widget
	embed_type?: 'iframe' | 'video' | 'image';
	allow_fullscreen?: boolean;
	aspect_ratio?: '16:9' | '4:3' | '1:1' | 'auto';
	[key: string]: any;
}

export interface LayoutItem {
	i: number; // widget id
	x: number;
	y: number;
	w: number;
	h: number;
	minW?: number;
	minH?: number;
	maxW?: number;
	maxH?: number;
	static?: boolean;
}

export interface DashboardLayout {
	lg?: LayoutItem[];
	md?: LayoutItem[];
	sm?: LayoutItem[];
	xs?: LayoutItem[];
	[key: string]: LayoutItem[] | undefined;
}

export interface DashboardWidget {
	id: number;
	dashboard_id: number;
	report_id: number | null;
	title: string;
	type: WidgetType;
	config: WidgetConfig;
	grid_position: GridPosition;
	refresh_interval: number;
	created_at: string;
	updated_at: string;
	report?: Pick<Report, 'id' | 'name' | 'type' | 'chart_type'>;
}

/**
 * Dashboard-level filters configuration
 */
export interface DashboardFilters {
	/** Global filters applied to all widgets */
	global?: FilterConfig[];
	/** Global date range applied to all widgets */
	date_range?: DateRangeConfig;
	/** Module-specific filters (keyed by module API name) */
	modules?: Record<string, FilterConfig[]>;
}

export interface Dashboard {
	id: number;
	name: string;
	description: string | null;
	user_id: number;
	is_default: boolean;
	is_public: boolean;
	layout: DashboardLayout;
	settings: Record<string, any>;
	filters: DashboardFilters;
	refresh_interval: number;
	created_at: string;
	updated_at: string;
	user?: {
		id: number;
		name: string;
	};
	widgets?: DashboardWidget[];
	widgets_count?: number;
}

export interface CreateDashboardRequest {
	name: string;
	description?: string;
	is_public?: boolean;
	is_default?: boolean;
	layout?: DashboardLayout;
	settings?: Record<string, any>;
	filters?: DashboardFilters;
	refresh_interval?: number;
}

export interface UpdateDashboardRequest extends Partial<CreateDashboardRequest> {}

export interface CreateWidgetRequest {
	title: string;
	type: WidgetType;
	report_id?: number;
	config?: WidgetConfig;
	grid_position?: GridPosition;
}

export interface UpdateWidgetRequest extends Partial<CreateWidgetRequest> {
	refresh_interval?: number;
}

// API Functions
export const dashboardsApi = {
	/**
	 * Get widget types
	 */
	async getWidgetTypes(): Promise<Record<string, string>> {
		const response = await apiClient.get<{ data: Record<string, string> }>('/dashboards/widget-types');
		return response.data;
	},

	/**
	 * List dashboards
	 */
	async list(): Promise<Dashboard[]> {
		const response = await apiClient.get<{ data: Dashboard[] }>('/dashboards');
		return response.data;
	},

	/**
	 * Create a new dashboard
	 */
	async create(data: CreateDashboardRequest): Promise<Dashboard> {
		const response = await apiClient.post<{ data: Dashboard }>('/dashboards', data);
		return response.data;
	},

	/**
	 * Get a single dashboard with widgets
	 */
	async get(id: number): Promise<Dashboard> {
		const response = await apiClient.get<{ data: Dashboard }>(`/dashboards/${id}`);
		return response.data;
	},

	/**
	 * Update a dashboard
	 */
	async update(id: number, data: UpdateDashboardRequest): Promise<Dashboard> {
		const response = await apiClient.put<{ data: Dashboard }>(`/dashboards/${id}`, data);
		return response.data;
	},

	/**
	 * Delete a dashboard
	 */
	async delete(id: number): Promise<void> {
		await apiClient.delete(`/dashboards/${id}`);
	},

	/**
	 * Duplicate a dashboard
	 */
	async duplicate(id: number): Promise<Dashboard> {
		const response = await apiClient.post<{ data: Dashboard }>(`/dashboards/${id}/duplicate`);
		return response.data;
	},

	/**
	 * Set dashboard as default
	 */
	async setDefault(id: number): Promise<Dashboard> {
		const response = await apiClient.post<{ data: Dashboard }>(`/dashboards/${id}/set-default`);
		return response.data;
	},

	/**
	 * Update dashboard layout
	 */
	async updateLayout(id: number, layout: DashboardLayout): Promise<Dashboard> {
		const response = await apiClient.put<{ data: Dashboard }>(`/dashboards/${id}/layout`, { layout });
		return response.data;
	},

	/**
	 * Get all widget data for a dashboard
	 */
	async getAllWidgetData(id: number, filterParams?: Record<string, string>): Promise<Record<number, ReportResult>> {
		const response = await apiClient.get<{ data: Record<number, ReportResult> }>(`/dashboards/${id}/data`, {
			params: filterParams
		});
		return response.data;
	},

	/**
	 * Export dashboard to PDF or Excel
	 */
	async export(id: number, format: 'pdf' | 'xlsx' = 'pdf'): Promise<Blob> {
		const response = await apiClient.get(`/dashboards/${id}/export`, {
			params: { format },
			responseType: 'blob'
		});
		return response as unknown as Blob;
	},

	// Widget operations
	widgets: {
		/**
		 * Add a widget to a dashboard
		 */
		async add(dashboardId: number, data: CreateWidgetRequest): Promise<DashboardWidget> {
			const response = await apiClient.post<{ data: DashboardWidget }>(`/dashboards/${dashboardId}/widgets`, data);
			return response.data;
		},

		/**
		 * Update a widget
		 */
		async update(dashboardId: number, widgetId: number, data: UpdateWidgetRequest): Promise<DashboardWidget> {
			const response = await apiClient.put<{ data: DashboardWidget }>(`/dashboards/${dashboardId}/widgets/${widgetId}`, data);
			return response.data;
		},

		/**
		 * Remove a widget
		 */
		async remove(dashboardId: number, widgetId: number): Promise<void> {
			await apiClient.delete(`/dashboards/${dashboardId}/widgets/${widgetId}`);
		},

		/**
		 * Update widget positions (batch update for drag/drop/resize)
		 */
		async updatePositions(
			dashboardId: number,
			widgets: { id: number; x: number; y: number; w: number; h: number }[]
		): Promise<void> {
			await apiClient.post(`/dashboards/${dashboardId}/widgets/positions`, { widgets });
		},

		/**
		 * Get widget data
		 */
		async getData(dashboardId: number, widgetId: number): Promise<any> {
			const response = await apiClient.get<{ data: any }>(`/dashboards/${dashboardId}/widgets/${widgetId}/data`);
			return response.data;
		}
	}
};

// Helper functions
export function getWidgetIcon(type: WidgetType): string {
	const icons: Record<WidgetType, string> = {
		report: 'file-bar-chart',
		kpi: 'hash',
		chart: 'bar-chart-2',
		table: 'table',
		activity: 'activity',
		pipeline: 'git-branch',
		tasks: 'check-square',
		calendar: 'calendar',
		text: 'file-text',
		iframe: 'globe',
		funnel: 'filter',
		goal_kpi: 'target',
		leaderboard: 'trophy',
		progress: 'trending-up',
		recent_records: 'list',
		heatmap: 'grid-3x3',
		quick_links: 'link',
		embed: 'globe',
		forecast: 'pie-chart'
	};
	return icons[type] || 'square';
}

export function getDefaultGridPosition(type: WidgetType): GridPosition {
	const positions: Record<WidgetType, GridPosition> = {
		report: { x: 0, y: 0, w: 6, h: 6 },
		kpi: { x: 0, y: 0, w: 3, h: 2, minW: 2, minH: 2 },
		chart: { x: 0, y: 0, w: 6, h: 4, minW: 3, minH: 3 },
		table: { x: 0, y: 0, w: 12, h: 6, minW: 6, minH: 4 },
		activity: { x: 0, y: 0, w: 4, h: 6, minW: 3, minH: 4 },
		pipeline: { x: 0, y: 0, w: 12, h: 4, minW: 6, minH: 3 },
		tasks: { x: 0, y: 0, w: 4, h: 4, minW: 3, minH: 3 },
		calendar: { x: 0, y: 0, w: 4, h: 4, minW: 3, minH: 3 },
		text: { x: 0, y: 0, w: 4, h: 2, minW: 2, minH: 1 },
		iframe: { x: 0, y: 0, w: 6, h: 4, minW: 3, minH: 2 },
		// Phase 2 widget types
		goal_kpi: { x: 0, y: 0, w: 3, h: 2, minW: 2, minH: 2 },
		leaderboard: { x: 0, y: 0, w: 4, h: 6, minW: 3, minH: 4 },
		funnel: { x: 0, y: 0, w: 6, h: 4, minW: 3, minH: 3 },
		progress: { x: 0, y: 0, w: 4, h: 2, minW: 3, minH: 2 },
		recent_records: { x: 0, y: 0, w: 4, h: 6, minW: 3, minH: 4 },
		heatmap: { x: 0, y: 0, w: 6, h: 5, minW: 4, minH: 4 },
		quick_links: { x: 0, y: 0, w: 4, h: 4, minW: 3, minH: 3 },
		embed: { x: 0, y: 0, w: 6, h: 4, minW: 3, minH: 3 },
		forecast: { x: 0, y: 0, w: 4, h: 6, minW: 3, minH: 4 }
	};
	return positions[type] || { x: 0, y: 0, w: 4, h: 4 };
}

export function generateWidgetId(): string {
	return `widget-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

// Dashboard Templates

export interface DashboardTemplateWidget {
	id: number;
	title: string;
	type: WidgetType;
	config: WidgetConfig;
	grid_position: GridPosition;
	refresh_interval: number;
}

export interface DashboardTemplate {
	id: number;
	name: string;
	slug: string;
	description: string | null;
	category: string;
	thumbnail: string | null;
	is_active: boolean;
	sort_order: number;
	widgets_count: number;
	widgets?: DashboardTemplateWidget[];
	created_at: string | null;
	updated_at: string | null;
}

export interface DashboardTemplateCategory {
	value: string;
	label: string;
}

export interface CreateDashboardFromTemplateRequest {
	name: string;
	description?: string;
}

export interface CreateDashboardFromTemplateResponse {
	id: number;
	name: string;
	description: string | null;
	widgets_count: number;
}

export const dashboardTemplatesApi = {
	/**
	 * List all active dashboard templates
	 */
	async list(category?: string): Promise<DashboardTemplate[]> {
		const params = category ? `?category=${encodeURIComponent(category)}` : '';
		const response = await apiClient.get<{ data: DashboardTemplate[] }>(`/dashboard-templates${params}`);
		return response.data;
	},

	/**
	 * Get template categories
	 */
	async getCategories(): Promise<DashboardTemplateCategory[]> {
		const response = await apiClient.get<{ data: DashboardTemplateCategory[] }>('/dashboard-templates/categories');
		return response.data;
	},

	/**
	 * Get a single template with widgets
	 */
	async get(id: number): Promise<DashboardTemplate> {
		const response = await apiClient.get<{ data: DashboardTemplate }>(`/dashboard-templates/${id}`);
		return response.data;
	},

	/**
	 * Create a dashboard from a template
	 */
	async createDashboard(templateId: number, data: CreateDashboardFromTemplateRequest): Promise<CreateDashboardFromTemplateResponse> {
		const response = await apiClient.post<{ data: CreateDashboardFromTemplateResponse; message: string }>(
			`/dashboard-templates/${templateId}/create-dashboard`,
			data
		);
		return response.data;
	}
};

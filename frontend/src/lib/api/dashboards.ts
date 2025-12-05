import { apiClient } from './client';
import type { Report, ReportResult } from './reports';

// Types
export type WidgetType = 'report' | 'kpi' | 'chart' | 'table' | 'activity' | 'pipeline' | 'tasks' | 'calendar' | 'text' | 'iframe';

export interface WidgetSize {
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
	filters?: any[];
	date_range?: any;
	compare_range?: any;
	// Pipeline widget
	pipeline_id?: number;
	// Tasks/Activity widget
	user_id?: number;
	limit?: number;
	// Text widget
	content?: string;
	// IFrame widget
	url?: string;
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
	position: number;
	size: WidgetSize;
	refresh_interval: number;
	created_at: string;
	updated_at: string;
	report?: Pick<Report, 'id' | 'name' | 'type' | 'chart_type'>;
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
	filters: Record<string, any>;
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
	filters?: Record<string, any>;
	refresh_interval?: number;
}

export interface UpdateDashboardRequest extends Partial<CreateDashboardRequest> {}

export interface CreateWidgetRequest {
	title: string;
	type: WidgetType;
	report_id?: number;
	config?: WidgetConfig;
	size?: WidgetSize;
	position?: number;
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
	async getAllWidgetData(id: number): Promise<Record<number, ReportResult>> {
		const response = await apiClient.get<{ data: Record<number, ReportResult> }>(`/dashboards/${id}/data`);
		return response.data;
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
		 * Reorder widgets
		 */
		async reorder(dashboardId: number, widgets: { id: number; position: number }[]): Promise<void> {
			await apiClient.post(`/dashboards/${dashboardId}/widgets/reorder`, { widgets });
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
		iframe: 'globe'
	};
	return icons[type] || 'square';
}

export function getDefaultWidgetSize(type: WidgetType): WidgetSize {
	const sizes: Record<WidgetType, WidgetSize> = {
		report: { w: 6, h: 6 },
		kpi: { w: 3, h: 2, minW: 2, minH: 2 },
		chart: { w: 6, h: 4, minW: 3, minH: 3 },
		table: { w: 12, h: 6 },
		activity: { w: 4, h: 6 },
		pipeline: { w: 12, h: 4 },
		tasks: { w: 4, h: 4 },
		calendar: { w: 4, h: 4 },
		text: { w: 4, h: 2, minW: 2, minH: 1 },
		iframe: { w: 6, h: 4, minW: 3, minH: 2 }
	};
	return sizes[type] || { w: 4, h: 4 };
}

export function generateWidgetId(): string {
	return `widget-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

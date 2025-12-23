import { apiClient } from './client';

export type ConditionType = 'above' | 'below' | 'percent_change' | 'equals';
export type Severity = 'info' | 'warning' | 'critical';
export type NotificationChannel = 'in_app' | 'email';
export type AlertHistoryStatus = 'triggered' | 'acknowledged' | 'dismissed';

export interface DashboardWidgetAlert {
	id: number;
	widget_id: number;
	user_id: number;
	name: string;
	condition_type: ConditionType;
	threshold_value: number;
	comparison_period: string | null;
	severity: Severity;
	notification_channels: NotificationChannel[];
	cooldown_minutes: number;
	is_active: boolean;
	last_triggered_at: string | null;
	trigger_count: number;
	created_at: string;
	updated_at: string;
	widget?: {
		id: number;
		title: string;
		type: string;
	};
}

export interface DashboardWidgetAlertHistory {
	id: number;
	alert_id: number;
	triggered_value: number;
	threshold_value: number;
	context: Record<string, unknown> | null;
	status: AlertHistoryStatus;
	acknowledged_by: number | null;
	acknowledged_at: string | null;
	created_at: string;
	updated_at: string;
	alert?: DashboardWidgetAlert;
}

export interface CreateAlertRequest {
	name: string;
	condition_type: ConditionType;
	threshold_value: number;
	comparison_period?: string;
	severity?: Severity;
	notification_channels?: NotificationChannel[];
	cooldown_minutes?: number;
	is_active?: boolean;
}

export interface UpdateAlertRequest extends Partial<CreateAlertRequest> {}

export const dashboardAlertsApi = {
	/**
	 * List alerts for a widget
	 */
	async listForWidget(dashboardId: number, widgetId: number): Promise<DashboardWidgetAlert[]> {
		const response = await apiClient.get<{ data: DashboardWidgetAlert[] }>(
			`/dashboards/${dashboardId}/widgets/${widgetId}/alerts`
		);
		return response.data;
	},

	/**
	 * Create a new alert
	 */
	async create(
		dashboardId: number,
		widgetId: number,
		data: CreateAlertRequest
	): Promise<DashboardWidgetAlert> {
		const response = await apiClient.post<{ data: DashboardWidgetAlert }>(
			`/dashboards/${dashboardId}/widgets/${widgetId}/alerts`,
			data
		);
		return response.data;
	},

	/**
	 * Update an alert
	 */
	async update(
		dashboardId: number,
		alertId: number,
		data: UpdateAlertRequest
	): Promise<DashboardWidgetAlert> {
		const response = await apiClient.put<{ data: DashboardWidgetAlert }>(
			`/dashboards/${dashboardId}/alerts/${alertId}`,
			data
		);
		return response.data;
	},

	/**
	 * Delete an alert
	 */
	async delete(dashboardId: number, alertId: number): Promise<void> {
		await apiClient.delete(`/dashboards/${dashboardId}/alerts/${alertId}`);
	},

	/**
	 * Toggle alert active status
	 */
	async toggle(dashboardId: number, alertId: number): Promise<DashboardWidgetAlert> {
		const response = await apiClient.post<{ data: DashboardWidgetAlert }>(
			`/dashboards/${dashboardId}/alerts/${alertId}/toggle`
		);
		return response.data;
	},

	/**
	 * Get alert history for a dashboard
	 */
	async getHistory(dashboardId: number): Promise<DashboardWidgetAlertHistory[]> {
		const response = await apiClient.get<{ data: DashboardWidgetAlertHistory[] }>(
			`/dashboards/${dashboardId}/alerts/history`
		);
		return response.data;
	},

	/**
	 * Acknowledge an alert history entry
	 */
	async acknowledge(dashboardId: number, historyId: number): Promise<DashboardWidgetAlertHistory> {
		const response = await apiClient.post<{ data: DashboardWidgetAlertHistory }>(
			`/dashboards/${dashboardId}/alerts/history/${historyId}/acknowledge`
		);
		return response.data;
	},

	/**
	 * Dismiss an alert history entry
	 */
	async dismiss(dashboardId: number, historyId: number): Promise<DashboardWidgetAlertHistory> {
		const response = await apiClient.post<{ data: DashboardWidgetAlertHistory }>(
			`/dashboards/${dashboardId}/alerts/history/${historyId}/dismiss`
		);
		return response.data;
	},

	/**
	 * Get unacknowledged alert count
	 */
	async getUnacknowledgedCount(): Promise<number> {
		const response = await apiClient.get<{ data: { count: number } }>(
			'/dashboards/alerts/unacknowledged-count'
		);
		return response.data.count;
	}
};

// Helper functions
export function getConditionTypeLabel(type: ConditionType): string {
	const labels: Record<ConditionType, string> = {
		above: 'Above threshold',
		below: 'Below threshold',
		percent_change: 'Percent change',
		equals: 'Equals value'
	};
	return labels[type];
}

export function getSeverityLabel(severity: Severity): string {
	const labels: Record<Severity, string> = {
		info: 'Info',
		warning: 'Warning',
		critical: 'Critical'
	};
	return labels[severity];
}

export function getSeverityColor(severity: Severity): string {
	const colors: Record<Severity, string> = {
		info: 'text-blue-600',
		warning: 'text-yellow-600',
		critical: 'text-red-600'
	};
	return colors[severity];
}

export function getSeverityBgColor(severity: Severity): string {
	const colors: Record<Severity, string> = {
		info: 'bg-blue-100',
		warning: 'bg-yellow-100',
		critical: 'bg-red-100'
	};
	return colors[severity];
}

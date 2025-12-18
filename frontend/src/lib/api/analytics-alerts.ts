import { apiClient } from './client';

export type AlertType = 'threshold' | 'anomaly' | 'trend' | 'comparison';
export type CheckFrequency = 'hourly' | 'daily' | 'weekly';
export type NotificationChannel = 'email' | 'in_app' | 'slack' | 'webhook';
export type AlertHistoryStatus = 'triggered' | 'resolved' | 'acknowledged' | 'muted';

export interface ThresholdCondition {
	operator: 'greater_than' | 'less_than' | 'greater_or_equal' | 'less_or_equal' | 'equals' | 'not_equals';
	value: number;
	comparison_period?: string;
}

export interface AnomalyCondition {
	sensitivity: 'low' | 'medium' | 'high';
	baseline_periods: number;
	min_deviation_percent: number;
}

export interface TrendCondition {
	direction: 'increasing' | 'decreasing';
	periods: number;
	min_change_percent: number;
}

export interface ComparisonCondition {
	compare_to: 'previous_day' | 'previous_week' | 'previous_month' | 'previous_quarter' | 'previous_year';
	change_type: 'percent' | 'absolute';
	threshold: number;
	direction?: 'any' | 'increase' | 'decrease';
}

export type ConditionConfig = ThresholdCondition | AnomalyCondition | TrendCondition | ComparisonCondition;

export interface NotificationConfig {
	channels: NotificationChannel[];
	recipients: number[];
	email_addresses?: string[];
	frequency: 'immediate' | 'daily_digest' | 'weekly_digest';
	quiet_hours?: {
		start: string;
		end: string;
	};
}

export interface AnalyticsAlert {
	id: number;
	name: string;
	description: string | null;
	user_id: number;
	alert_type: AlertType;
	module_id: number | null;
	report_id: number | null;
	metric_field: string | null;
	aggregation: string;
	filters: Array<{ field: string; operator: string; value: unknown }> | null;
	condition_config: ConditionConfig;
	notification_config: NotificationConfig;
	check_frequency: CheckFrequency;
	check_time: string | null;
	is_active: boolean;
	last_checked_at: string | null;
	last_triggered_at: string | null;
	trigger_count: number;
	consecutive_triggers: number;
	cooldown_minutes: number;
	cooldown_until: string | null;
	created_at: string;
	updated_at: string;
	module?: {
		id: number;
		api_name: string;
		label: string;
	};
	report?: {
		id: number;
		name: string;
	};
}

export interface AlertHistory {
	id: number;
	alert_id: number;
	status: AlertHistoryStatus;
	metric_value: number | null;
	threshold_value: number | null;
	baseline_value: number | null;
	deviation_percent: number | null;
	context: Record<string, unknown> | null;
	message: string | null;
	acknowledged_by: number | null;
	acknowledged_at: string | null;
	acknowledgment_note: string | null;
	notifications_sent: Array<{
		user_id?: number;
		emails?: string[];
		channels: string[];
		sent_at: string;
	}> | null;
	created_at: string;
	updated_at: string;
	alert?: AnalyticsAlert;
	acknowledgedBy?: {
		id: number;
		name: string;
	};
}

export interface AlertSubscription {
	id: number;
	alert_id: number;
	user_id: number;
	channels: NotificationChannel[] | null;
	is_muted: boolean;
	muted_until: string | null;
	created_at: string;
	updated_at: string;
}

export interface AlertStats {
	total_alerts: number;
	active_alerts: number;
	triggered_today: number;
	unacknowledged: number;
}

export interface AlertOptions {
	types: Record<string, string>;
	frequencies: Record<string, string>;
	operators: Record<string, string>;
	aggregations: Record<string, string>;
	comparison_periods: Record<string, string>;
	sensitivities: Record<string, string>;
}

export interface CreateAlertRequest {
	name: string;
	description?: string;
	alert_type: AlertType;
	module_id?: number;
	report_id?: number;
	metric_field?: string;
	aggregation?: string;
	filters?: Array<{ field: string; operator: string; value: unknown }>;
	condition_config: ConditionConfig;
	notification_config: NotificationConfig;
	check_frequency: CheckFrequency;
	check_time?: string;
	cooldown_minutes?: number;
}

export interface UpdateAlertRequest extends Partial<CreateAlertRequest> {
	is_active?: boolean;
}

/**
 * Analytics Alerts API
 */
export const analyticsAlertsApi = {
	/**
	 * Get available options for alert configuration
	 */
	async getOptions(): Promise<AlertOptions> {
		const response = await apiClient.get<{ data: AlertOptions }>('/analytics-alerts/options');
		return response.data;
	},

	/**
	 * Get alert statistics
	 */
	async getStats(): Promise<AlertStats> {
		const response = await apiClient.get<{ data: AlertStats }>('/analytics-alerts/stats');
		return response.data;
	},

	/**
	 * Get unacknowledged alerts
	 */
	async getUnacknowledged(): Promise<AlertHistory[]> {
		const response = await apiClient.get<{ data: AlertHistory[] }>('/analytics-alerts/unacknowledged');
		return response.data;
	},

	/**
	 * List all alerts
	 */
	async list(params?: { type?: AlertType; active_only?: boolean }): Promise<AnalyticsAlert[]> {
		const queryParams: Record<string, string | number | boolean | undefined> = {};
		if (params?.type) queryParams.type = params.type;
		if (params?.active_only !== undefined) queryParams.active_only = params.active_only;
		const response = await apiClient.get<{ data: AnalyticsAlert[] }>('/analytics-alerts', { params: queryParams });
		return response.data;
	},

	/**
	 * Create a new alert
	 */
	async create(data: CreateAlertRequest): Promise<AnalyticsAlert> {
		const response = await apiClient.post<{ data: AnalyticsAlert; message: string }>(
			'/analytics-alerts',
			data
		);
		return response.data;
	},

	/**
	 * Get a single alert
	 */
	async get(id: number): Promise<AnalyticsAlert> {
		const response = await apiClient.get<{ data: AnalyticsAlert }>(`/analytics-alerts/${id}`);
		return response.data;
	},

	/**
	 * Update an alert
	 */
	async update(id: number, data: UpdateAlertRequest): Promise<AnalyticsAlert> {
		const response = await apiClient.put<{ data: AnalyticsAlert; message: string }>(
			`/analytics-alerts/${id}`,
			data
		);
		return response.data;
	},

	/**
	 * Delete an alert
	 */
	async delete(id: number): Promise<void> {
		await apiClient.delete(`/analytics-alerts/${id}`);
	},

	/**
	 * Toggle alert active status
	 */
	async toggle(id: number): Promise<AnalyticsAlert> {
		const response = await apiClient.post<{ data: AnalyticsAlert; message: string }>(
			`/analytics-alerts/${id}/toggle`
		);
		return response.data;
	},

	/**
	 * Manually trigger alert check
	 */
	async check(id: number): Promise<{ triggered: boolean; alert: AnalyticsAlert }> {
		const response = await apiClient.post<{ data: { triggered: boolean; alert: AnalyticsAlert }; message: string }>(
			`/analytics-alerts/${id}/check`
		);
		return response.data;
	},

	/**
	 * Get alert history
	 */
	async getHistory(
		id: number,
		params?: { page?: number; per_page?: number }
	): Promise<{ data: AlertHistory[]; meta: { total: number; current_page: number; last_page: number } }> {
		const queryParams: Record<string, string | number | boolean | undefined> = {};
		if (params?.page) queryParams.page = params.page;
		if (params?.per_page) queryParams.per_page = params.per_page;
		const response = await apiClient.get<{
			data: AlertHistory[];
			meta: { total: number; current_page: number; last_page: number };
		}>(`/analytics-alerts/${id}/history`, { params: queryParams });
		return response;
	},

	/**
	 * Subscribe to an alert
	 */
	async subscribe(id: number, channels?: NotificationChannel[]): Promise<AlertSubscription> {
		const response = await apiClient.post<{ data: AlertSubscription; message: string }>(
			`/analytics-alerts/${id}/subscribe`,
			{ channels }
		);
		return response.data;
	},

	/**
	 * Unsubscribe from an alert
	 */
	async unsubscribe(id: number): Promise<void> {
		await apiClient.delete(`/analytics-alerts/${id}/subscribe`);
	},

	/**
	 * Mute an alert subscription
	 */
	async mute(id: number, until?: string): Promise<AlertSubscription> {
		const response = await apiClient.post<{ data: AlertSubscription; message: string }>(
			`/analytics-alerts/${id}/mute`,
			{ until }
		);
		return response.data;
	},

	/**
	 * Acknowledge an alert history entry
	 */
	async acknowledge(historyId: number, note?: string): Promise<AlertHistory> {
		const response = await apiClient.post<{ data: AlertHistory; message: string }>(
			`/analytics-alerts/history/${historyId}/acknowledge`,
			{ note }
		);
		return response.data;
	}
};

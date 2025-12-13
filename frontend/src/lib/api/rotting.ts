import { apiClient } from './client';

export type RotStatus = 'fresh' | 'warming' | 'stale' | 'rotting';

export interface RotStatusInfo {
	status: RotStatus;
	days_inactive: number;
	threshold_days: number | null;
	percentage: number;
	color: string;
	message?: string;
}

export interface RottingDeal {
	record: {
		id: number;
		module_id: number;
		data: Record<string, unknown>;
		created_by: number;
		last_activity_at: string | null;
	};
	stage: {
		id: number;
		name: string;
		color: string;
		rotting_days: number;
	};
	pipeline: {
		id: number;
		name: string;
	};
	rot_status: RotStatusInfo;
}

export interface RottingAlert {
	id: number;
	module_record_id: number;
	stage_id: number;
	user_id: number;
	alert_type: 'warning' | 'stale' | 'rotting';
	days_inactive: number;
	sent_at: string;
	acknowledged: boolean;
	acknowledged_at: string | null;
	created_at: string;
	updated_at: string;
	moduleRecord?: {
		id: number;
		data: Record<string, unknown>;
	};
	stage?: {
		id: number;
		name: string;
		color: string;
	};
}

export interface RottingAlertSetting {
	id?: number;
	user_id: number;
	pipeline_id: number | null;
	email_digest_enabled: boolean;
	digest_frequency: 'daily' | 'weekly' | 'none';
	in_app_notifications: boolean;
	exclude_weekends: boolean;
}

export interface RottingSummary {
	total: number;
	fresh: number;
	warming: number;
	stale: number;
	rotting: number;
}

interface PaginatedResponse<T> {
	data: T[];
	meta: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}

interface RottingDealsResponse extends PaginatedResponse<RottingDeal> {}

interface RottingAlertsResponse extends PaginatedResponse<RottingAlert> {}

interface RotStatusResponse {
	data: RotStatusInfo;
}

interface RottingSummaryResponse {
	data: RottingSummary;
}

interface RottingSettingsResponse {
	data: RottingAlertSetting;
}

interface AlertCountResponse {
	count: number;
}

interface MessageResponse {
	message: string;
	count?: number;
}

/**
 * Get rotting deals for current user
 */
export async function getRottingDeals(params?: {
	pipeline_id?: number;
	status?: RotStatus;
	page?: number;
	per_page?: number;
}): Promise<RottingDealsResponse> {
	const queryParams: Record<string, string> = {};
	if (params?.pipeline_id) queryParams.pipeline_id = String(params.pipeline_id);
	if (params?.status) queryParams.status = params.status;
	if (params?.page) queryParams.page = String(params.page);
	if (params?.per_page) queryParams.per_page = String(params.per_page);

	return apiClient.get<RottingDealsResponse>('/rotting/deals', queryParams);
}

/**
 * Get rot status for a specific record
 */
export async function getRecordRotStatus(recordId: number): Promise<RotStatusInfo> {
	const response = await apiClient.get<RotStatusResponse>(`/rotting/deals/${recordId}`);
	return response.data;
}

/**
 * Get rotting summary for a pipeline
 */
export async function getRottingSummary(pipelineId: number): Promise<RottingSummary> {
	const response = await apiClient.get<RottingSummaryResponse>(`/rotting/summary/${pipelineId}`);
	return response.data;
}

/**
 * Get alerts for current user
 */
export async function getRottingAlerts(params?: {
	acknowledged?: boolean;
	type?: 'warning' | 'stale' | 'rotting';
	page?: number;
	per_page?: number;
}): Promise<RottingAlertsResponse> {
	const queryParams: Record<string, string> = {};
	if (params?.acknowledged !== undefined) queryParams.acknowledged = String(params.acknowledged);
	if (params?.type) queryParams.type = params.type;
	if (params?.page) queryParams.page = String(params.page);
	if (params?.per_page) queryParams.per_page = String(params.per_page);

	return apiClient.get<RottingAlertsResponse>('/rotting/alerts', queryParams);
}

/**
 * Get count of unacknowledged alerts
 */
export async function getAlertCount(): Promise<number> {
	const response = await apiClient.get<AlertCountResponse>('/rotting/alerts/count');
	return response.count;
}

/**
 * Acknowledge a single alert
 */
export async function acknowledgeAlert(alertId: number): Promise<RottingAlert> {
	const response = await apiClient.post<{ data: RottingAlert; message: string }>(
		`/rotting/alerts/${alertId}/acknowledge`
	);
	return response.data;
}

/**
 * Acknowledge all alerts
 */
export async function acknowledgeAllAlerts(): Promise<number> {
	const response = await apiClient.post<MessageResponse>('/rotting/alerts/acknowledge-all');
	return response.count ?? 0;
}

/**
 * Get user's rotting alert settings
 */
export async function getRottingSettings(pipelineId?: number): Promise<RottingAlertSetting> {
	const queryParams: Record<string, string> = {};
	if (pipelineId) queryParams.pipeline_id = String(pipelineId);

	const response = await apiClient.get<RottingSettingsResponse>('/rotting/settings', queryParams);
	return response.data;
}

/**
 * Update user's rotting alert settings
 */
export async function updateRottingSettings(settings: Partial<RottingAlertSetting>): Promise<RottingAlertSetting> {
	const response = await apiClient.put<{ data: RottingAlertSetting; message: string }>(
		'/rotting/settings',
		settings
	);
	return response.data;
}

/**
 * Configure rotting threshold for a stage
 */
export async function configureStageRotting(
	pipelineId: number,
	stageId: number,
	rottingDays: number
): Promise<void> {
	await apiClient.put(`/rotting/pipelines/${pipelineId}/stages/${stageId}`, {
		rotting_days: rottingDays
	});
}

/**
 * Remove rotting threshold from a stage
 */
export async function removeStageRotting(pipelineId: number, stageId: number): Promise<void> {
	await apiClient.delete(`/rotting/pipelines/${pipelineId}/stages/${stageId}`);
}

/**
 * Record activity for a deal (resets rotting timer)
 */
export async function recordDealActivity(recordId: number): Promise<string> {
	const response = await apiClient.post<{ data: { last_activity_at: string }; message: string }>(
		`/rotting/record-activity/${recordId}`
	);
	return response.data.last_activity_at;
}

/**
 * Get color class for rot status
 */
export function getRotStatusColor(status: RotStatus): string {
	switch (status) {
		case 'rotting':
			return 'red';
		case 'stale':
			return 'orange';
		case 'warming':
			return 'yellow';
		default:
			return 'green';
	}
}

/**
 * Get icon for rot status
 */
export function getRotStatusIcon(status: RotStatus): string {
	switch (status) {
		case 'rotting':
			return '🔴';
		case 'stale':
			return '🟠';
		case 'warming':
			return '🟡';
		default:
			return '🟢';
	}
}

import { apiClient, type PaginatedResponse } from './client';

// Types
export type MetricType = 'revenue' | 'deals' | 'leads' | 'calls' | 'meetings' | 'activities' | 'custom';
export type PeriodType = 'month' | 'quarter' | 'year' | 'custom';
export type GoalType = 'individual' | 'team' | 'company';
export type GoalStatus = 'in_progress' | 'achieved' | 'missed' | 'paused';

export interface QuotaPeriod {
	id: number;
	name: string;
	period_type: PeriodType;
	start_date: string;
	end_date: string;
	is_active: boolean;
	quotas_count?: number;
	days_remaining?: number;
	days_total?: number;
	days_elapsed?: number;
	progress_percent?: number;
	is_current?: boolean;
	created_at: string;
	updated_at: string;
}

export interface Quota {
	id: number;
	period_id: number;
	user_id: number | null;
	team_id: number | null;
	metric_type: MetricType;
	metric_field: string | null;
	module_api_name: string | null;
	target_value: number;
	currency: string;
	current_value: number;
	attainment_percent: number;
	gap_to_target: number;
	is_achieved: boolean;
	pace_required: number | null;
	metric_label: string;
	period?: QuotaPeriod;
	user?: { id: number; name: string; avatar_url?: string };
	snapshots?: QuotaSnapshot[];
	created_at: string;
	updated_at: string;
}

export interface QuotaSnapshot {
	id: number;
	quota_id: number;
	snapshot_date: string;
	current_value: number;
	attainment_percent: number;
}

export interface Goal {
	id: number;
	name: string;
	description: string | null;
	goal_type: GoalType;
	user_id: number | null;
	team_id: number | null;
	metric_type: MetricType;
	metric_field: string | null;
	module_api_name: string | null;
	target_value: number;
	currency: string;
	start_date: string;
	end_date: string;
	current_value: number;
	attainment_percent: number;
	status: GoalStatus;
	achieved_at: string | null;
	days_remaining: number;
	progress_percent: number;
	is_overdue: boolean;
	gap_to_target: number;
	user?: { id: number; name: string };
	milestones?: GoalMilestone[];
	progressLogs?: GoalProgressLog[];
	created_at: string;
	updated_at: string;
}

export interface GoalMilestone {
	id: number;
	goal_id: number;
	name: string;
	target_value: number;
	target_date: string | null;
	is_achieved: boolean;
	achieved_at: string | null;
	display_order: number;
}

export interface GoalProgressLog {
	id: number;
	goal_id: number;
	log_date: string;
	value: number;
	change_amount: number;
	change_source: string | null;
}

export interface LeaderboardEntry {
	rank: number;
	rank_badge: string | null;
	user: { id: number; name: string; avatar?: string };
	value: number;
	target: number;
	attainment_percent: number;
	gap: number;
	trend: number;
}

export interface QuotaProgress {
	id: number;
	metric_type: MetricType;
	metric_label: string;
	target_value: number;
	current_value: number;
	attainment_percent: number;
	gap_to_target: number;
	pace_required: number | null;
	is_achieved: boolean;
	currency: string;
	period: {
		id: number;
		name: string;
		days_remaining: number;
		days_total: number;
		progress_percent: number;
	};
	trend: { date: string; value: number; attainment: number }[];
}

// Response types
interface QuotaPeriodResponse {
	data: QuotaPeriod;
	message?: string;
}

interface QuotaPeriodListResponse extends PaginatedResponse<QuotaPeriod> {}

interface QuotaResponse {
	data: Quota;
	message?: string;
}

interface QuotaListResponse extends PaginatedResponse<Quota> {}

interface GoalResponse {
	data: Goal;
	message?: string;
}

interface GoalListResponse extends PaginatedResponse<Goal> {}

interface DataResponse<T> {
	data: T;
	message?: string;
}

// Quota Period API
export async function getQuotaPeriods(params?: {
	type?: PeriodType;
	active?: boolean;
	current?: boolean;
	per_page?: number;
}): Promise<QuotaPeriodListResponse> {
	const queryParams: Record<string, string> = {};
	if (params?.type) queryParams.type = params.type;
	if (params?.active) queryParams.active = '1';
	if (params?.current) queryParams.current = '1';
	if (params?.per_page) queryParams.per_page = params.per_page.toString();

	return apiClient.get<QuotaPeriodListResponse>('/quota-periods', { params: queryParams });
}

export async function getCurrentPeriod(type: PeriodType = 'quarter'): Promise<QuotaPeriod> {
	const response = await apiClient.get<QuotaPeriodResponse>(`/quota-periods/current`, {
		params: { type }
	});
	return response.data;
}

export async function createQuotaPeriod(data: {
	name: string;
	period_type: PeriodType;
	start_date: string;
	end_date: string;
	is_active?: boolean;
}): Promise<QuotaPeriod> {
	const response = await apiClient.post<QuotaPeriodResponse>('/quota-periods', data);
	return response.data;
}

export async function generatePeriods(
	year: number,
	types: PeriodType[]
): Promise<DataResponse<QuotaPeriod[]>> {
	return apiClient.post<DataResponse<QuotaPeriod[]>>('/quota-periods/generate', { year, types });
}

// Quotas API
export async function getQuotas(params?: {
	period_id?: number;
	user_id?: number;
	metric_type?: MetricType;
	active?: boolean;
	per_page?: number;
}): Promise<QuotaListResponse> {
	const queryParams: Record<string, string> = {};
	if (params?.period_id) queryParams.period_id = params.period_id.toString();
	if (params?.user_id) queryParams.user_id = params.user_id.toString();
	if (params?.metric_type) queryParams.metric_type = params.metric_type;
	if (params?.active) queryParams.active = '1';
	if (params?.per_page) queryParams.per_page = params.per_page.toString();

	return apiClient.get<QuotaListResponse>('/quotas', { params: queryParams });
}

export async function getQuota(id: number): Promise<Quota> {
	const response = await apiClient.get<QuotaResponse>(`/quotas/${id}`);
	return response.data;
}

export async function createQuota(data: {
	period_id: number;
	user_id: number;
	metric_type: MetricType;
	target_value: number;
	metric_field?: string;
	currency?: string;
}): Promise<Quota> {
	const response = await apiClient.post<QuotaResponse>('/quotas', data);
	return response.data;
}

export async function updateQuota(id: number, data: Partial<Quota>): Promise<Quota> {
	const response = await apiClient.put<QuotaResponse>(`/quotas/${id}`, data);
	return response.data;
}

export async function deleteQuota(id: number): Promise<void> {
	await apiClient.delete(`/quotas/${id}`);
}

export async function bulkCreateQuotas(data: {
	period_id: number;
	metric_type: MetricType;
	target_value: number;
	user_ids: number[];
}): Promise<Quota[]> {
	const response = await apiClient.post<DataResponse<Quota[]>>('/quotas/bulk', data);
	return response.data;
}

export async function getMyProgress(): Promise<QuotaProgress[]> {
	const response = await apiClient.get<DataResponse<QuotaProgress[]>>('/quotas/my-progress');
	return response.data;
}

export async function getTeamProgress(periodId?: number) {
	const params: Record<string, string> = {};
	if (periodId) params.period_id = periodId.toString();
	const response = await apiClient.get<DataResponse<unknown>>('/quotas/team-progress', { params });
	return response.data;
}

export async function getLeaderboard(params?: {
	period_id?: number;
	metric_type?: MetricType;
	limit?: number;
}): Promise<{
	period: { id: number; name: string; days_remaining: number };
	metric_type: MetricType;
	entries: LeaderboardEntry[];
}> {
	const queryParams: Record<string, string> = {};
	if (params?.period_id) queryParams.period_id = params.period_id.toString();
	if (params?.metric_type) queryParams.metric_type = params.metric_type;
	if (params?.limit) queryParams.limit = params.limit.toString();

	const response = await apiClient.get<
		DataResponse<{
			period: { id: number; name: string; days_remaining: number };
			metric_type: MetricType;
			entries: LeaderboardEntry[];
		}>
	>('/quotas/leaderboard', { params: queryParams });
	return response.data;
}

export async function getMyPosition(params?: { period_id?: number; metric_type?: MetricType }) {
	const queryParams: Record<string, string> = {};
	if (params?.period_id) queryParams.period_id = params.period_id.toString();
	if (params?.metric_type) queryParams.metric_type = params.metric_type;

	const response = await apiClient.get<DataResponse<unknown>>('/quotas/my-position', {
		params: queryParams
	});
	return response.data;
}

export async function getMetricTypes(): Promise<Record<MetricType, string>> {
	const response = await apiClient.get<DataResponse<Record<MetricType, string>>>(
		'/quotas/metric-types'
	);
	return response.data;
}

export async function refreshLeaderboard(
	periodId?: number,
	metricType?: MetricType
): Promise<void> {
	await apiClient.post('/quotas/refresh-leaderboard', {
		period_id: periodId,
		metric_type: metricType
	});
}

export async function recalculateQuotas(periodId?: number): Promise<void> {
	await apiClient.post('/quotas/recalculate', { period_id: periodId });
}

// Goals API
export async function getGoals(params?: {
	user_id?: number;
	goal_type?: GoalType;
	status?: GoalStatus;
	current?: boolean;
	active?: boolean;
	per_page?: number;
}): Promise<GoalListResponse> {
	const queryParams: Record<string, string> = {};
	if (params?.user_id) queryParams.user_id = params.user_id.toString();
	if (params?.goal_type) queryParams.goal_type = params.goal_type;
	if (params?.status) queryParams.status = params.status;
	if (params?.current) queryParams.current = '1';
	if (params?.active) queryParams.active = '1';
	if (params?.per_page) queryParams.per_page = params.per_page.toString();

	return apiClient.get<GoalListResponse>('/goals', { params: queryParams });
}

export async function getGoal(id: number): Promise<Goal> {
	const response = await apiClient.get<GoalResponse>(`/goals/${id}`);
	return response.data;
}

export async function createGoal(data: {
	name: string;
	description?: string;
	goal_type: GoalType;
	user_id?: number;
	metric_type: MetricType;
	metric_field?: string;
	target_value: number;
	currency?: string;
	start_date: string;
	end_date: string;
	milestones?: { name: string; target_value: number; target_date?: string }[];
}): Promise<Goal> {
	const response = await apiClient.post<GoalResponse>('/goals', data);
	return response.data;
}

export async function updateGoal(
	id: number,
	data: Partial<Goal> & { milestones?: GoalMilestone[] }
): Promise<Goal> {
	const response = await apiClient.put<GoalResponse>(`/goals/${id}`, data);
	return response.data;
}

export async function deleteGoal(id: number): Promise<void> {
	await apiClient.delete(`/goals/${id}`);
}

export async function getGoalProgress(id: number) {
	const response = await apiClient.get<DataResponse<unknown>>(`/goals/${id}/progress`);
	return response.data;
}

export async function updateGoalProgress(
	id: number,
	currentValue: number,
	source?: string
): Promise<Goal> {
	const response = await apiClient.put<GoalResponse>(`/goals/${id}/progress`, {
		current_value: currentValue,
		source
	});
	return response.data;
}

export async function getMyGoals(params?: {
	status?: GoalStatus;
	current?: boolean;
}): Promise<Goal[]> {
	const queryParams: Record<string, string> = {};
	if (params?.status) queryParams.status = params.status;
	if (params?.current) queryParams.current = '1';

	const response = await apiClient.get<DataResponse<Goal[]>>('/goals/my-goals', {
		params: queryParams
	});
	return response.data;
}

export async function getActiveGoals(): Promise<{
	individual: Goal[];
	team: Goal[];
	company: Goal[];
}> {
	const response = await apiClient.get<
		DataResponse<{
			individual: Goal[];
			team: Goal[];
			company: Goal[];
		}>
	>('/goals/active');
	return response.data;
}

export async function pauseGoal(id: number): Promise<Goal> {
	const response = await apiClient.post<GoalResponse>(`/goals/${id}/pause`);
	return response.data;
}

export async function resumeGoal(id: number): Promise<Goal> {
	const response = await apiClient.post<GoalResponse>(`/goals/${id}/resume`);
	return response.data;
}

export async function getGoalStats(userId?: number) {
	const params: Record<string, string> = {};
	if (userId) params.user_id = userId.toString();
	const response = await apiClient.get<DataResponse<unknown>>('/goals/stats', { params });
	return response.data;
}

export async function getGoalTypes(): Promise<Record<GoalType, string>> {
	const response = await apiClient.get<DataResponse<Record<GoalType, string>>>('/goals/types');
	return response.data;
}

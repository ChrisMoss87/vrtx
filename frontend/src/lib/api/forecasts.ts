import { apiClient } from './client';

export type ForecastCategory = 'commit' | 'best_case' | 'pipeline' | 'omitted';
export type PeriodType = 'week' | 'month' | 'quarter' | 'year';

export interface ForecastDeal {
	id: number;
	name: string;
	amount: number;
	forecast_override: number | null;
	forecast_category: ForecastCategory | null;
	expected_close_date: string | null;
	stage_field_value: string | null;
	probability: number;
	owner_id: number;
}

export interface ForecastCategorySummary {
	amount: number;
	count: number;
	deals: ForecastDeal[];
}

export interface QuotaInfo {
	id: number;
	amount: number;
	attainment: number;
	remaining: number;
}

export interface ForecastSummary {
	commit: ForecastCategorySummary;
	best_case: ForecastCategorySummary;
	pipeline: ForecastCategorySummary;
	weighted: {
		amount: number;
		count: number;
	};
	closed_won: {
		amount: number;
		count: number;
	};
	quota: QuotaInfo | null;
	period: {
		type: PeriodType;
		start: string;
		end: string;
	};
}

export interface ForecastSnapshot {
	id: number;
	user_id: number | null;
	module_api_name: string;
	period_type: PeriodType;
	period_start: string;
	period_end: string;
	commit_amount: number;
	best_case_amount: number;
	pipeline_amount: number;
	weighted_amount: number;
	closed_won_amount: number;
	deal_count: number;
	snapshot_date: string;
	metadata: Record<string, unknown> | null;
}

export interface ForecastAccuracyPoint {
	period: string;
	period_start: string;
	period_end: string;
	forecasted: number;
	actual: number;
	accuracy: number | null;
	variance: number;
}

export interface ForecastAdjustment {
	id: number;
	user_id: number;
	module_record_id: number;
	adjustment_type: 'category_change' | 'amount_override' | 'close_date_change';
	old_value: string | null;
	new_value: string | null;
	reason: string | null;
	created_at: string;
	user?: {
		id: number;
		name: string;
		email: string;
	};
}

export interface SalesQuota {
	id: number;
	user_id: number | null;
	module_api_name: string | null;
	team_id: number | null;
	period_type: PeriodType;
	period_start: string;
	period_end: string;
	quota_amount: number;
	currency: string;
	notes: string | null;
	created_at: string;
	updated_at: string;
	user?: {
		id: number;
		name: string;
		email: string;
	};
}

export interface QuotaAttainment {
	quota: number | null;
	closed_won: number;
	commit: number;
	best_case: number;
	pipeline: number;
	weighted: number;
	attainment: number | null;
	remaining: number | null;
}

interface ForecastSummaryResponse {
	data: ForecastSummary;
}

interface ForecastDealsResponse {
	data: ForecastDeal[];
}

interface ForecastHistoryResponse {
	data: ForecastSnapshot[];
}

interface ForecastAccuracyResponse {
	data: ForecastAccuracyPoint[];
}

interface ForecastAdjustmentsResponse {
	data: ForecastAdjustment[];
}

interface QuotasResponse {
	data: SalesQuota[];
}

interface QuotaAttainmentResponse {
	data: QuotaAttainment;
}

/**
 * Get forecast summary for a module
 */
export async function getForecastSummary(params: {
	module_api_name: string;
	user_id?: number;
	period_type?: PeriodType;
	period_start?: string;
}): Promise<ForecastSummary> {
	const queryParams: Record<string, string> = {
		module_api_name: params.module_api_name
	};
	if (params.user_id) queryParams.user_id = String(params.user_id);
	if (params.period_type) queryParams.period_type = params.period_type;
	if (params.period_start) queryParams.period_start = params.period_start;

	const response = await apiClient.get<ForecastSummaryResponse>('/forecasts', queryParams);
	return response.data;
}

/**
 * Get deals with forecast data
 */
export async function getForecastDeals(params: {
	module_api_name: string;
	user_id?: number;
	period_type?: PeriodType;
	period_start?: string;
	category?: ForecastCategory;
}): Promise<ForecastDeal[]> {
	const queryParams: Record<string, string> = {
		module_api_name: params.module_api_name
	};
	if (params.user_id) queryParams.user_id = String(params.user_id);
	if (params.period_type) queryParams.period_type = params.period_type;
	if (params.period_start) queryParams.period_start = params.period_start;
	if (params.category) queryParams.category = params.category;

	const response = await apiClient.get<ForecastDealsResponse>('/forecasts/deals', queryParams);
	return response.data;
}

/**
 * Update a deal's forecast settings
 */
export async function updateDealForecast(
	recordId: number,
	data: {
		forecast_category?: ForecastCategory;
		forecast_override?: number;
		expected_close_date?: string;
		reason?: string;
	}
): Promise<{
	id: number;
	forecast_category: ForecastCategory | null;
	forecast_override: number | null;
	expected_close_date: string | null;
}> {
	const response = await apiClient.put<{
		data: {
			id: number;
			forecast_category: ForecastCategory | null;
			forecast_override: number | null;
			expected_close_date: string | null;
		};
		message: string;
	}>(`/forecasts/deals/${recordId}`, data);
	return response.data;
}

/**
 * Get forecast history
 */
export async function getForecastHistory(params: {
	module_api_name: string;
	user_id?: number;
	period_type?: PeriodType;
	limit?: number;
}): Promise<ForecastSnapshot[]> {
	const queryParams: Record<string, string> = {
		module_api_name: params.module_api_name
	};
	if (params.user_id) queryParams.user_id = String(params.user_id);
	if (params.period_type) queryParams.period_type = params.period_type;
	if (params.limit) queryParams.limit = String(params.limit);

	const response = await apiClient.get<ForecastHistoryResponse>('/forecasts/history', queryParams);
	return response.data;
}

/**
 * Get forecast accuracy analysis
 */
export async function getForecastAccuracy(params: {
	module_api_name: string;
	user_id?: number;
	period_type?: PeriodType;
	periods?: number;
}): Promise<ForecastAccuracyPoint[]> {
	const queryParams: Record<string, string> = {
		module_api_name: params.module_api_name
	};
	if (params.user_id) queryParams.user_id = String(params.user_id);
	if (params.period_type) queryParams.period_type = params.period_type;
	if (params.periods) queryParams.periods = String(params.periods);

	const response = await apiClient.get<ForecastAccuracyResponse>('/forecasts/accuracy', queryParams);
	return response.data;
}

/**
 * Get forecast adjustments for a deal
 */
export async function getForecastAdjustments(recordId: number): Promise<ForecastAdjustment[]> {
	const response = await apiClient.get<ForecastAdjustmentsResponse>(
		`/forecasts/deals/${recordId}/adjustments`
	);
	return response.data;
}

/**
 * Get quotas
 */
export async function getQuotas(params?: {
	user_id?: number;
	module_api_name?: string;
	period_type?: PeriodType;
	current_only?: boolean;
}): Promise<SalesQuota[]> {
	const queryParams: Record<string, string> = {};
	if (params?.user_id) queryParams.user_id = String(params.user_id);
	if (params?.module_api_name) queryParams.module_api_name = params.module_api_name;
	if (params?.period_type) queryParams.period_type = params.period_type;
	if (params?.current_only) queryParams.current_only = 'true';

	const response = await apiClient.get<QuotasResponse>('/quotas', queryParams);
	return response.data;
}

/**
 * Create a quota
 */
export async function createQuota(data: {
	user_id?: number;
	module_api_name?: string;
	team_id?: number;
	period_type: PeriodType;
	period_start: string;
	period_end: string;
	quota_amount: number;
	currency?: string;
	notes?: string;
}): Promise<SalesQuota> {
	const response = await apiClient.post<{ data: SalesQuota; message: string }>('/quotas', data);
	return response.data;
}

/**
 * Update a quota
 */
export async function updateQuota(
	quotaId: number,
	data: {
		quota_amount?: number;
		period_end?: string;
		notes?: string;
	}
): Promise<SalesQuota> {
	const response = await apiClient.put<{ data: SalesQuota; message: string }>(
		`/quotas/${quotaId}`,
		data
	);
	return response.data;
}

/**
 * Delete a quota
 */
export async function deleteQuota(quotaId: number): Promise<void> {
	await apiClient.delete(`/quotas/${quotaId}`);
}

/**
 * Get quota attainment
 */
export async function getQuotaAttainment(params: {
	module_api_name: string;
	user_id?: number;
	period_type?: PeriodType;
	period_start?: string;
}): Promise<QuotaAttainment> {
	const queryParams: Record<string, string> = {
		module_api_name: params.module_api_name
	};
	if (params.user_id) queryParams.user_id = String(params.user_id);
	if (params.period_type) queryParams.period_type = params.period_type;
	if (params.period_start) queryParams.period_start = params.period_start;

	const response = await apiClient.get<QuotaAttainmentResponse>('/quotas/attainment', queryParams);
	return response.data;
}

/**
 * Forecasts API object for convenience
 */
export const forecastsApi = {
	getSummary: getForecastSummary,
	getDeals: getForecastDeals,
	updateDeal: updateDealForecast,
	getHistory: getForecastHistory,
	getAccuracy: getForecastAccuracy,
	getAdjustments: getForecastAdjustments,
	getQuotas,
	createQuota,
	updateQuota,
	deleteQuota,
	getQuotaAttainment
};

/**
 * Format currency amount
 */
export function formatCurrency(amount: number, currency = 'USD'): string {
	return new Intl.NumberFormat('en-US', {
		style: 'currency',
		currency,
		minimumFractionDigits: 0,
		maximumFractionDigits: 0
	}).format(amount);
}

/**
 * Get category label
 */
export function getCategoryLabel(category: ForecastCategory | null): string {
	switch (category) {
		case 'commit':
			return 'Commit';
		case 'best_case':
			return 'Best Case';
		case 'pipeline':
			return 'Pipeline';
		case 'omitted':
			return 'Omitted';
		default:
			return 'Pipeline';
	}
}

/**
 * Get category color
 */
export function getCategoryColor(category: ForecastCategory | null): string {
	switch (category) {
		case 'commit':
			return 'green';
		case 'best_case':
			return 'blue';
		case 'pipeline':
			return 'gray';
		case 'omitted':
			return 'red';
		default:
			return 'gray';
	}
}

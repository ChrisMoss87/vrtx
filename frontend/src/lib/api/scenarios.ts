import { apiClient } from './client';

export interface ScenarioDeal {
	id: number;
	deal_record_id: number;
	name: string;
	amount: number;
	probability: number;
	weighted_amount: number;
	close_date: string | null;
	stage_id: number | null;
	stage_name: string | null;
	is_committed: boolean;
	is_excluded: boolean;
	has_changes: boolean;
	changes: Record<string, { from: unknown; to: unknown }>;
	notes: string | null;
	original_data: {
		amount: number;
		probability: number | null;
		close_date: string | null;
		stage_id: number | null;
	} | null;
}

export interface ScenarioMetrics {
	deal_count: number;
	committed_count: number;
	open_count: number;
	total_unweighted: number;
	total_weighted: number;
	committed_total: number;
	average_probability: number;
	average_deal_size: number;
	target_amount: number | null;
	gap_amount: number;
	progress_percent: number;
	by_stage: Array<{
		stage_id: number | null;
		stage_name: string;
		deal_count: number;
		total_amount: number;
		weighted_amount: number;
	}>;
	timeline: Array<{
		week_start: string;
		week_end: string;
		deal_count: number;
		amount: number;
		weighted: number;
		cumulative: number;
		cumulative_weighted: number;
	}>;
}

export interface Scenario {
	id: number;
	name: string;
	description: string | null;
	scenario_type: 'current' | 'best_case' | 'worst_case' | 'target_hit' | 'custom';
	period_start: string;
	period_end: string;
	total_weighted: number;
	total_unweighted: number;
	target_amount: number | null;
	deal_count: number;
	is_shared: boolean;
	is_baseline: boolean;
	settings: Record<string, unknown> | null;
	user: { id: number; name: string } | null;
	deals?: ScenarioDeal[];
	metrics?: ScenarioMetrics;
	created_at: string;
	updated_at: string;
}

export interface ScenarioComparison {
	scenario_id: number;
	scenario_name: string;
	scenario_type: string;
	metrics: ScenarioMetrics;
	delta?: {
		total_weighted: number;
		total_unweighted: number;
		deal_count: number;
		average_probability: number;
	};
}

export interface GapAnalysis {
	target: number;
	current_weighted: number;
	current_unweighted: number;
	gap: number;
	gap_percent: number;
	is_on_track: boolean;
	deal_count: number;
	average_deal_size: number;
	average_probability: number;
	recommendations: Array<{
		type: string;
		title: string;
		description: string;
		feasibility: 'high' | 'medium' | 'low';
		[key: string]: unknown;
	}>;
	top_deals: Array<{
		id: number;
		name: string;
		amount: number;
		probability: number;
		weighted: number;
		close_date: string | null;
		stage: string | null;
	}>;
}

export interface DealUpdateResult {
	deal: {
		id: number;
		deal_record_id: number;
		amount: number;
		probability: number;
		weighted_amount: number;
		close_date: string | null;
		stage_id: number | null;
		is_committed: boolean;
		has_changes: boolean;
	};
	scenario_totals: {
		total_weighted: number;
		total_unweighted: number;
		gap_amount: number;
		progress_percent: number;
	};
}

/**
 * Get all scenarios for the current user.
 */
export async function getScenarios(options?: {
	period_start?: string;
	period_end?: string;
	scenario_type?: string;
}): Promise<Scenario[]> {
	const params = new URLSearchParams();
	if (options?.period_start) params.set('period_start', options.period_start);
	if (options?.period_end) params.set('period_end', options.period_end);
	if (options?.scenario_type) params.set('scenario_type', options.scenario_type);

	const query = params.toString();
	const response = await apiClient.get<{ data: Scenario[] }>(`/scenarios${query ? `?${query}` : ''}`);
	return response.data;
}

/**
 * Get a single scenario with deals and metrics.
 */
export async function getScenario(id: number): Promise<Scenario> {
	const response = await apiClient.get<{ data: Scenario }>(`/scenarios/${id}`);
	return response.data;
}

/**
 * Create a new scenario.
 */
export async function createScenario(data: {
	name: string;
	description?: string;
	period_start: string;
	period_end: string;
	scenario_type?: string;
	target_amount?: number;
	is_shared?: boolean;
	settings?: Record<string, unknown>;
}): Promise<Scenario> {
	const response = await apiClient.post<{ data: Scenario }>('/scenarios', data);
	return response.data;
}

/**
 * Update a scenario.
 */
export async function updateScenario(
	id: number,
	data: {
		name?: string;
		description?: string;
		period_start?: string;
		period_end?: string;
		target_amount?: number;
		is_shared?: boolean;
		settings?: Record<string, unknown>;
	}
): Promise<Scenario> {
	const response = await apiClient.put<{ data: Scenario }>(`/scenarios/${id}`, data);
	return response.data;
}

/**
 * Delete a scenario.
 */
export async function deleteScenario(id: number): Promise<void> {
	await apiClient.delete(`/scenarios/${id}`);
}

/**
 * Duplicate a scenario.
 */
export async function duplicateScenario(id: number, name?: string): Promise<Scenario> {
	const response = await apiClient.post<{ data: Scenario }>(`/scenarios/${id}/duplicate`, { name });
	return response.data;
}

/**
 * Get deals in a scenario.
 */
export async function getScenarioDeals(id: number): Promise<ScenarioDeal[]> {
	const response = await apiClient.get<{ data: ScenarioDeal[] }>(`/scenarios/${id}/deals`);
	return response.data;
}

/**
 * Update a deal in a scenario.
 */
export async function updateScenarioDeal(
	scenarioId: number,
	dealId: number,
	data: {
		amount?: number;
		probability?: number;
		close_date?: string;
		stage_id?: number;
		is_committed?: boolean;
		is_excluded?: boolean;
		notes?: string;
	}
): Promise<DealUpdateResult> {
	const response = await apiClient.put<{ data: DealUpdateResult }>(
		`/scenarios/${scenarioId}/deals/${dealId}`,
		data
	);
	return response.data;
}

/**
 * Commit a deal in a scenario.
 */
export async function commitDeal(scenarioId: number, dealId: number): Promise<ScenarioDeal> {
	const response = await apiClient.post<{ data: ScenarioDeal }>(
		`/scenarios/${scenarioId}/commit/${dealId}`
	);
	return response.data;
}

/**
 * Reset a deal to original values.
 */
export async function resetDeal(scenarioId: number, dealId: number): Promise<ScenarioDeal> {
	const response = await apiClient.post<{ data: ScenarioDeal }>(
		`/scenarios/${scenarioId}/reset/${dealId}`
	);
	return response.data;
}

/**
 * Compare multiple scenarios.
 */
export async function compareScenarios(ids: number[]): Promise<ScenarioComparison[]> {
	const response = await apiClient.get<{ data: ScenarioComparison[] }>(
		`/scenarios/compare?ids=${ids.join(',')}`
	);
	return response.data;
}

/**
 * Get gap analysis.
 */
export async function getGapAnalysis(
	target: number,
	periodStart: string,
	periodEnd: string
): Promise<GapAnalysis> {
	const params = new URLSearchParams({
		target: target.toString(),
		period_start: periodStart,
		period_end: periodEnd
	});
	const response = await apiClient.get<{ data: GapAnalysis }>(`/scenarios/gap-analysis?${params}`);
	return response.data;
}

/**
 * Auto-generate a scenario.
 */
export async function autoGenerateScenario(
	type: 'current' | 'best_case' | 'worst_case' | 'target_hit',
	periodStart: string,
	periodEnd: string,
	targetAmount?: number
): Promise<Scenario> {
	const response = await apiClient.post<{ data: Scenario }>('/scenarios/auto-generate', {
		type,
		period_start: periodStart,
		period_end: periodEnd,
		target_amount: targetAmount
	});
	return response.data;
}

/**
 * Get scenario types.
 */
export async function getScenarioTypes(): Promise<Record<string, string>> {
	const response = await apiClient.get<{ data: Record<string, string> }>('/scenarios/types');
	return response.data;
}

import { apiClient } from './client';

export type MatchType = 'exact' | 'fuzzy' | 'phonetic' | 'email_domain';
export type DuplicateAction = 'warn' | 'block';
export type CandidateStatus = 'pending' | 'merged' | 'dismissed';

export interface ConditionRule {
	field: string;
	match_type: MatchType;
	threshold?: number;
}

export interface ConditionGroup {
	logic: 'and' | 'or';
	rules: (ConditionRule | ConditionGroup)[];
}

export interface DuplicateRule {
	id: number;
	name: string;
	description: string | null;
	module: {
		id: number;
		name: string;
	};
	is_active: boolean;
	action: DuplicateAction;
	conditions: ConditionGroup;
	priority: number;
	created_by: {
		id: number;
		name: string;
	} | null;
	created_at: string;
}

export interface DuplicateMatch {
	record_id: number;
	record: {
		id: number;
		data: Record<string, unknown>;
		created_at: string;
	};
	match_score: number; // Percentage 0-100
	matched_rules: {
		rule_id: number;
		rule_name: string;
		score: number;
		details: unknown;
	}[];
	action: DuplicateAction;
}

export interface DuplicateCheckResult {
	has_duplicates: boolean;
	should_block: boolean;
	duplicates: DuplicateMatch[];
}

export interface DuplicateCandidate {
	id: number;
	record_a: {
		id: number;
		data: Record<string, unknown>;
		created_at: string;
	};
	record_b: {
		id: number;
		data: Record<string, unknown>;
		created_at: string;
	};
	match_score: number;
	matched_rules: {
		rule_id: number;
		rule_name: string;
		score: number;
		details: unknown;
	}[];
	status: CandidateStatus;
	reviewed_by: {
		id: number;
		name: string;
	} | null;
	reviewed_at: string | null;
	dismiss_reason: string | null;
	created_at: string;
}

export interface MergePreview {
	record_a: {
		id: number;
		data: Record<string, unknown>;
	};
	record_b: {
		id: number;
		data: Record<string, unknown>;
	};
	preview: Record<
		string,
		{
			field: string;
			label: string;
			value_a: unknown;
			value_b: unknown;
			selected_value: unknown;
			selection: string;
			differs: boolean;
		}
	>;
	field_count: number;
	differing_fields: number;
}

export interface MergeLog {
	id: number;
	surviving_record: {
		id: number;
		data: Record<string, unknown>;
	} | null;
	merged_count: number;
	merged_record_ids: number[];
	field_selections: Record<string, string>;
	merged_by: {
		id: number;
		name: string;
	} | null;
	created_at: string;
}

export interface DuplicateStats {
	candidates: {
		pending: number;
		merged: number;
		dismissed: number;
		total: number;
	};
	rules: {
		active: number;
		total: number;
	};
}

interface CheckResponse {
	data: DuplicateCheckResult;
}

interface CandidatesResponse {
	data: DuplicateCandidate[];
	meta: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}

interface RulesResponse {
	data: DuplicateRule[];
}

interface MergeResponse {
	data: {
		id: number;
		data: Record<string, unknown>;
	};
	message: string;
}

interface PreviewResponse {
	data: MergePreview;
}

interface HistoryResponse {
	data: MergeLog[];
	meta: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}

interface StatsResponse {
	data: DuplicateStats;
}

/**
 * Check for potential duplicates (real-time on create/update)
 */
export async function checkForDuplicates(
	moduleId: number,
	data: Record<string, unknown>,
	excludeRecordId?: number
): Promise<DuplicateCheckResult> {
	const params: Record<string, string | number | Record<string, unknown>> = {
		module_id: moduleId,
		data
	};
	if (excludeRecordId) params.exclude_record_id = excludeRecordId;

	const response = await apiClient.get<CheckResponse>('/duplicates/check', params);
	return response.data;
}

/**
 * Get duplicate candidates for a module
 */
export async function getDuplicateCandidates(params: {
	module_id: number;
	status?: CandidateStatus;
	per_page?: number;
	page?: number;
}): Promise<{ data: DuplicateCandidate[]; meta: CandidatesResponse['meta'] }> {
	const queryParams: Record<string, string> = {
		module_id: String(params.module_id)
	};
	if (params.status) queryParams.status = params.status;
	if (params.per_page) queryParams.per_page = String(params.per_page);
	if (params.page) queryParams.page = String(params.page);

	const response = await apiClient.get<CandidatesResponse>('/duplicates/candidates', queryParams);
	return { data: response.data, meta: response.meta };
}

/**
 * Merge duplicate records
 */
export async function mergeRecords(
	survivingRecordId: number,
	mergeRecordIds: number[],
	fieldSelections: Record<string, string | { custom: unknown } | { record_id: number }>
): Promise<{ id: number; data: Record<string, unknown> }> {
	const response = await apiClient.post<MergeResponse>('/duplicates/merge', {
		surviving_record_id: survivingRecordId,
		merge_record_ids: mergeRecordIds,
		field_selections: fieldSelections
	});
	return response.data;
}

/**
 * Preview a merge operation
 */
export async function previewMerge(
	recordAId: number,
	recordBId: number,
	fieldSelections?: Record<string, string>
): Promise<MergePreview> {
	const response = await apiClient.post<PreviewResponse>('/duplicates/preview', {
		record_a_id: recordAId,
		record_b_id: recordBId,
		field_selections: fieldSelections ?? {}
	});
	return response.data;
}

/**
 * Dismiss a duplicate candidate
 */
export async function dismissCandidate(candidateId: number, reason?: string): Promise<void> {
	await apiClient.post('/duplicates/dismiss', {
		candidate_id: candidateId,
		reason
	});
}

/**
 * Get duplicate detection rules
 */
export async function getDuplicateRules(moduleId?: number): Promise<DuplicateRule[]> {
	const params: Record<string, string> = {};
	if (moduleId) params.module_id = String(moduleId);

	const response = await apiClient.get<RulesResponse>('/duplicates/rules', params);
	return response.data;
}

/**
 * Create a duplicate detection rule
 */
export async function createDuplicateRule(data: {
	module_id: number;
	name: string;
	description?: string;
	is_active?: boolean;
	action?: DuplicateAction;
	conditions: ConditionGroup;
	priority?: number;
}): Promise<DuplicateRule> {
	const response = await apiClient.post<{ data: DuplicateRule; message: string }>(
		'/duplicates/rules',
		data
	);
	return response.data;
}

/**
 * Update a duplicate detection rule
 */
export async function updateDuplicateRule(
	ruleId: number,
	data: {
		name?: string;
		description?: string;
		is_active?: boolean;
		action?: DuplicateAction;
		conditions?: ConditionGroup;
		priority?: number;
	}
): Promise<DuplicateRule> {
	const response = await apiClient.put<{ data: DuplicateRule; message: string }>(
		`/duplicates/rules/${ruleId}`,
		data
	);
	return response.data;
}

/**
 * Delete a duplicate detection rule
 */
export async function deleteDuplicateRule(ruleId: number): Promise<void> {
	await apiClient.delete(`/duplicates/rules/${ruleId}`);
}

/**
 * Trigger a batch scan for duplicates
 */
export async function scanForDuplicates(moduleId: number, limit?: number): Promise<void> {
	await apiClient.post('/duplicates/scan', {
		module_id: moduleId,
		limit
	});
}

/**
 * Get merge history for a module
 */
export async function getMergeHistory(params: {
	module_id: number;
	per_page?: number;
	page?: number;
}): Promise<{ data: MergeLog[]; meta: HistoryResponse['meta'] }> {
	const queryParams: Record<string, string> = {
		module_id: String(params.module_id)
	};
	if (params.per_page) queryParams.per_page = String(params.per_page);
	if (params.page) queryParams.page = String(params.page);

	const response = await apiClient.get<HistoryResponse>('/duplicates/history', queryParams);
	return { data: response.data, meta: response.meta };
}

/**
 * Get duplicate statistics
 */
export async function getDuplicateStats(moduleId?: number): Promise<DuplicateStats> {
	const params: Record<string, string> = {};
	if (moduleId) params.module_id = String(moduleId);

	const response = await apiClient.get<StatsResponse>('/duplicates/stats', params);
	return response.data;
}

/**
 * Get display name for a record
 */
export function getRecordDisplayName(
	data: Record<string, unknown>,
	primaryField?: string
): string {
	if (primaryField && data[primaryField]) {
		return String(data[primaryField]);
	}
	// Try common field names
	for (const field of ['name', 'title', 'email', 'first_name', 'company_name']) {
		if (data[field]) {
			return String(data[field]);
		}
	}
	return 'Unnamed Record';
}

/**
 * Format match score for display
 */
export function formatMatchScore(score: number): string {
	return `${Math.round(score)}%`;
}

/**
 * Get match score color class
 */
export function getMatchScoreColor(score: number): string {
	if (score >= 90) return 'text-red-600';
	if (score >= 75) return 'text-orange-600';
	if (score >= 60) return 'text-yellow-600';
	return 'text-gray-600';
}

/**
 * Get match score badge variant
 */
export function getMatchScoreBadgeVariant(
	score: number
): 'default' | 'secondary' | 'destructive' | 'outline' {
	if (score >= 90) return 'destructive';
	if (score >= 75) return 'default';
	return 'secondary';
}

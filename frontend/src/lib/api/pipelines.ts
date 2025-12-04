import { apiClient } from './client';

export interface Stage {
	id: number;
	pipeline_id: number;
	name: string;
	color: string;
	probability: number;
	display_order: number;
	is_won_stage: boolean;
	is_lost_stage: boolean;
	settings: Record<string, unknown>;
	created_at: string;
	updated_at: string;
}

export interface Pipeline {
	id: number;
	name: string;
	module_id: number;
	stage_field_api_name: string | null;
	is_active: boolean;
	settings: Record<string, unknown>;
	created_by: number | null;
	updated_by: number | null;
	created_at: string;
	updated_at: string;
	stages?: Stage[];
	module?: {
		id: number;
		name: string;
		api_name: string;
	};
}

export interface StageInput {
	id?: number;
	name: string;
	color?: string;
	probability?: number;
	display_order?: number;
	is_won_stage?: boolean;
	is_lost_stage?: boolean;
	settings?: Record<string, unknown>;
}

export interface PipelineInput {
	name: string;
	module_id: number;
	stage_field_api_name?: string;
	is_active?: boolean;
	settings?: Record<string, unknown>;
	stages?: StageInput[];
}

export interface KanbanColumn {
	stage: Stage;
	records: Array<{
		id: number;
		module_id: number;
		data: Record<string, unknown>;
		created_at: string;
		updated_at: string;
	}>;
	count: number;
	totalValue: number;
	weightedValue: number;
}

export interface KanbanData {
	pipeline: Pipeline;
	columns: KanbanColumn[];
	totals: {
		totalRecords: number;
		totalValue: number;
		weightedValue: number;
	};
}

export interface StageHistoryEntry {
	id: number;
	module_record_id: number;
	pipeline_id: number;
	from_stage_id: number | null;
	to_stage_id: number;
	changed_by: number | null;
	reason: string | null;
	duration_in_stage: number | null;
	created_at: string;
	from_stage?: Stage | null;
	to_stage?: Stage;
	changed_by_user?: {
		id: number;
		name: string;
		email: string;
	};
}

interface PipelineListResponse {
	success: boolean;
	pipelines: Pipeline[];
}

interface PipelineResponse {
	success: boolean;
	pipeline: Pipeline;
	message?: string;
}

interface KanbanResponse {
	success: boolean;
	pipeline: Pipeline;
	columns: KanbanColumn[];
	totals: {
		totalRecords: number;
		totalValue: number;
		weightedValue: number;
	};
}

interface MoveRecordResponse {
	success: boolean;
	message: string;
	record: {
		id: number;
		module_id: number;
		data: Record<string, unknown>;
	};
}

interface StageHistoryResponse {
	success: boolean;
	history: StageHistoryEntry[];
}

/**
 * Get all pipelines
 */
export async function getPipelines(params?: {
	module_id?: number;
	active?: boolean;
}): Promise<Pipeline[]> {
	const queryParams: Record<string, string> = {};
	if (params?.module_id) {
		queryParams.module_id = String(params.module_id);
	}
	if (params?.active !== undefined) {
		queryParams.active = String(params.active);
	}

	const response = await apiClient.get<PipelineListResponse>('/pipelines', queryParams);
	return response.pipelines;
}

/**
 * Get pipelines for a specific module by API name
 */
export async function getPipelinesForModule(moduleApiName: string): Promise<Pipeline[]> {
	const response = await apiClient.get<PipelineListResponse>(`/pipelines/module/${moduleApiName}`);
	return response.pipelines;
}

/**
 * Get a single pipeline by ID
 */
export async function getPipeline(id: number): Promise<Pipeline> {
	const response = await apiClient.get<PipelineResponse>(`/pipelines/${id}`);
	return response.pipeline;
}

/**
 * Create a new pipeline
 */
export async function createPipeline(data: PipelineInput): Promise<Pipeline> {
	const response = await apiClient.post<PipelineResponse>('/pipelines', data);
	return response.pipeline;
}

/**
 * Update a pipeline
 */
export async function updatePipeline(id: number, data: Partial<PipelineInput>): Promise<Pipeline> {
	const response = await apiClient.put<PipelineResponse>(`/pipelines/${id}`, data);
	return response.pipeline;
}

/**
 * Delete a pipeline
 */
export async function deletePipeline(id: number): Promise<void> {
	await apiClient.delete(`/pipelines/${id}`);
}

/**
 * Get kanban board data for a pipeline
 */
export async function getKanbanData(
	pipelineId: number,
	options?: {
		filters?: Record<string, string>;
		search?: string;
		value_field?: string;
	}
): Promise<KanbanData> {
	const params: Record<string, string> = {};
	if (options?.value_field) {
		params.value_field = options.value_field;
	}
	if (options?.search) {
		params.search = options.search;
	}
	if (options?.filters) {
		Object.entries(options.filters).forEach(([key, value]) => {
			params[`filters[${key}]`] = value;
		});
	}

	const response = await apiClient.get<KanbanResponse>(`/pipelines/${pipelineId}/kanban`, params);
	return {
		pipeline: response.pipeline,
		columns: response.columns,
		totals: response.totals
	};
}

/**
 * Move a record to a different stage
 */
export async function moveRecord(
	pipelineId: number,
	recordId: number,
	stageId: number,
	reason?: string
): Promise<MoveRecordResponse['record']> {
	const response = await apiClient.post<MoveRecordResponse>(
		`/pipelines/${pipelineId}/move-record`,
		{
			record_id: recordId,
			stage_id: stageId,
			reason
		}
	);
	return response.record;
}

/**
 * Get stage history for a record
 */
export async function getRecordStageHistory(
	pipelineId: number,
	recordId: number
): Promise<StageHistoryEntry[]> {
	const response = await apiClient.get<StageHistoryResponse>(
		`/pipelines/${pipelineId}/record/${recordId}/history`
	);
	return response.history;
}

/**
 * Reorder stages in a pipeline
 */
export async function reorderStages(pipelineId: number, stageIds: number[]): Promise<Pipeline> {
	const response = await apiClient.post<PipelineResponse>(
		`/pipelines/${pipelineId}/reorder-stages`,
		{ stages: stageIds }
	);
	return response.pipeline;
}

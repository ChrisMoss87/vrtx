import { apiClient } from './client';

// Trigger types
export type TriggerType =
	| 'record_created'
	| 'record_updated'
	| 'record_deleted'
	| 'field_changed'
	| 'time_based'
	| 'webhook'
	| 'manual'
	| 'record_saved'
	| 'related_created'
	| 'related_updated'
	| 'record_converted';

// Trigger timing
export type TriggerTiming = 'all' | 'create_only' | 'update_only';

// Field change type
export type FieldChangeType = 'any' | 'from_value' | 'to_value' | 'from_to';

// Action types
export type ActionType =
	| 'send_email'
	| 'create_record'
	| 'update_record'
	| 'delete_record'
	| 'update_field'
	| 'webhook'
	| 'assign_user'
	| 'add_tag'
	| 'remove_tag'
	| 'send_notification'
	| 'delay'
	| 'condition'
	| 'create_task'
	| 'move_stage'
	| 'update_related_record';

export interface WorkflowStep {
	id: number;
	workflow_id: number;
	order: number;
	name: string | null;
	description: string | null;
	action_type: ActionType;
	action_config: Record<string, unknown>;
	conditions: Condition[] | null;
	branch_id: string | null;
	is_parallel: boolean;
	continue_on_error: boolean;
	retry_count: number;
	retry_delay_seconds: number;
	on_success_goto: number | null;
	on_failure_goto: number | null;
	timeout_seconds: number;
	is_async: boolean;
	is_disabled: boolean;
	created_at: string;
	updated_at: string;
}

export interface WorkflowStepInput {
	id?: number;
	name?: string;
	action_type: ActionType;
	action_config: Record<string, unknown>;
	conditions?: Condition[];
	continue_on_error?: boolean;
	retry_count?: number;
	retry_delay_seconds?: number;
}

export interface Condition {
	field: string;
	operator: string;
	value: unknown;
}

export interface ConditionGroup {
	logic: 'and' | 'or';
	conditions: Condition[];
}

export interface WorkflowConditions {
	logic: 'and' | 'or';
	groups: ConditionGroup[];
}

export interface TriggerConfig {
	// For field_changed trigger
	fields?: string[];
	change_type?: FieldChangeType;
	from_value?: unknown;
	to_value?: unknown;

	// For time_based trigger
	schedule_type?: 'cron' | 'relative' | 'specific_date';
	relative_field?: string;
	relative_offset?: number;
	relative_unit?: 'hours' | 'days' | 'weeks' | 'months';

	// For related triggers
	related_module?: string;
	related_relationship?: string;
}

export interface Workflow {
	id: number;
	name: string;
	description: string | null;
	module_id: number | null;
	is_active: boolean;
	priority: number;
	trigger_type: TriggerType;
	trigger_config: TriggerConfig;
	trigger_timing: TriggerTiming;
	watched_fields: string[] | null;
	webhook_secret: string | null;
	stop_on_first_match: boolean;
	max_executions_per_day: number | null;
	executions_today: number;
	conditions: WorkflowConditions | Condition[] | null;
	run_once_per_record: boolean;
	allow_manual_trigger: boolean;
	delay_seconds: number;
	schedule_cron: string | null;
	last_run_at: string | null;
	next_run_at: string | null;
	execution_count: number;
	success_count: number;
	failure_count: number;
	created_by: number | null;
	updated_by: number | null;
	created_at: string;
	updated_at: string;
	steps?: WorkflowStep[];
	module?: {
		id: number;
		name: string;
		api_name: string;
	};
	executions?: WorkflowExecution[];
}

export interface WorkflowInput {
	name: string;
	description?: string;
	module_id?: number | null;
	is_active?: boolean;
	priority?: number;
	trigger_type: TriggerType;
	trigger_config?: TriggerConfig;
	trigger_timing?: TriggerTiming;
	watched_fields?: string[];
	webhook_secret?: string;
	stop_on_first_match?: boolean;
	max_executions_per_day?: number | null;
	conditions?: WorkflowConditions | Condition[];
	run_once_per_record?: boolean;
	allow_manual_trigger?: boolean;
	delay_seconds?: number;
	schedule_cron?: string;
	steps?: WorkflowStepInput[];
}

export interface WorkflowExecution {
	id: number;
	workflow_id: number;
	trigger_type: 'record_event' | 'scheduled' | 'manual' | 'webhook';
	trigger_record_id: number | null;
	trigger_record_type: string | null;
	status: 'pending' | 'queued' | 'running' | 'completed' | 'failed' | 'cancelled';
	queued_at: string | null;
	started_at: string | null;
	completed_at: string | null;
	duration_ms: number | null;
	context_data: Record<string, unknown>;
	steps_completed: number;
	steps_failed: number;
	steps_skipped: number;
	error_message: string | null;
	triggered_by: number | null;
	created_at: string;
	updated_at: string;
	step_logs?: WorkflowStepLog[];
}

export interface WorkflowStepLog {
	id: number;
	execution_id: number;
	step_id: number;
	status: 'pending' | 'running' | 'completed' | 'failed' | 'skipped';
	started_at: string | null;
	completed_at: string | null;
	duration_ms: number | null;
	input_data: Record<string, unknown> | null;
	output_data: Record<string, unknown> | null;
	error_message: string | null;
	error_trace: string | null;
	retry_attempt: number;
	step?: WorkflowStep;
}

export interface TriggerTypeInfo {
	[key: string]: string;
}

export interface ActionTypeInfo {
	[key: string]: {
		label: string;
		icon: string;
		description: string;
		category: string;
	};
}

// Response types
interface WorkflowListResponse {
	success: boolean;
	workflows: Workflow[];
}

interface WorkflowResponse {
	success: boolean;
	workflow: Workflow;
	message?: string;
}

interface TriggerTypesResponse {
	success: boolean;
	trigger_types: TriggerTypeInfo;
}

interface ActionTypesResponse {
	success: boolean;
	action_types: ActionTypeInfo;
}

interface ExecutionListResponse {
	success: boolean;
	executions: {
		data: WorkflowExecution[];
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}

interface ExecutionResponse {
	success: boolean;
	execution: WorkflowExecution;
}

interface TriggerResponse {
	success: boolean;
	message: string;
	execution: WorkflowExecution;
}

/**
 * Get all workflows
 */
export async function getWorkflows(params?: {
	module_id?: number;
	active?: boolean;
	trigger_type?: TriggerType;
}): Promise<Workflow[]> {
	const queryParams: Record<string, string> = {};
	if (params?.module_id) {
		queryParams.module_id = String(params.module_id);
	}
	if (params?.active !== undefined) {
		queryParams.active = String(params.active);
	}
	if (params?.trigger_type) {
		queryParams.trigger_type = params.trigger_type;
	}

	const response = await apiClient.get<WorkflowListResponse>('/workflows', queryParams);
	return response.workflows;
}

/**
 * Get available trigger types
 */
export async function getTriggerTypes(): Promise<TriggerTypeInfo> {
	const response = await apiClient.get<TriggerTypesResponse>('/workflows/trigger-types');
	return response.trigger_types;
}

/**
 * Get available action types
 */
export async function getActionTypes(): Promise<ActionTypeInfo> {
	const response = await apiClient.get<ActionTypesResponse>('/workflows/action-types');
	return response.action_types;
}

/**
 * Get a single workflow by ID
 */
export async function getWorkflow(id: number): Promise<Workflow> {
	const response = await apiClient.get<WorkflowResponse>(`/workflows/${id}`);
	return response.workflow;
}

/**
 * Create a new workflow
 */
export async function createWorkflow(data: WorkflowInput): Promise<Workflow> {
	const response = await apiClient.post<WorkflowResponse>('/workflows', data);
	return response.workflow;
}

/**
 * Update a workflow
 */
export async function updateWorkflow(id: number, data: Partial<WorkflowInput>): Promise<Workflow> {
	const response = await apiClient.put<WorkflowResponse>(`/workflows/${id}`, data);
	return response.workflow;
}

/**
 * Delete a workflow
 */
export async function deleteWorkflow(id: number): Promise<void> {
	await apiClient.delete(`/workflows/${id}`);
}

/**
 * Toggle workflow active status
 */
export async function toggleWorkflowActive(id: number): Promise<Workflow> {
	const response = await apiClient.post<WorkflowResponse>(`/workflows/${id}/toggle-active`);
	return response.workflow;
}

/**
 * Clone a workflow
 */
export async function cloneWorkflow(id: number): Promise<Workflow> {
	const response = await apiClient.post<WorkflowResponse>(`/workflows/${id}/clone`);
	return response.workflow;
}

/**
 * Manually trigger a workflow
 */
export async function triggerWorkflow(
	id: number,
	options?: {
		record_id?: number;
		context_data?: Record<string, unknown>;
	}
): Promise<WorkflowExecution> {
	const response = await apiClient.post<TriggerResponse>(`/workflows/${id}/trigger`, options);
	return response.execution;
}

/**
 * Reorder workflow steps
 */
export async function reorderWorkflowSteps(
	workflowId: number,
	stepIds: number[]
): Promise<Workflow> {
	const response = await apiClient.post<WorkflowResponse>(`/workflows/${workflowId}/reorder-steps`, {
		steps: stepIds
	});
	return response.workflow;
}

/**
 * Get workflow execution history
 */
export async function getWorkflowExecutions(
	workflowId: number,
	params?: {
		status?: WorkflowExecution['status'];
		page?: number;
		per_page?: number;
	}
): Promise<ExecutionListResponse['executions']> {
	const queryParams: Record<string, string> = {};
	if (params?.status) {
		queryParams.status = params.status;
	}
	if (params?.page) {
		queryParams.page = String(params.page);
	}
	if (params?.per_page) {
		queryParams.per_page = String(params.per_page);
	}

	const response = await apiClient.get<ExecutionListResponse>(
		`/workflows/${workflowId}/executions`,
		queryParams
	);
	return response.executions;
}

/**
 * Get a single workflow execution
 */
export async function getWorkflowExecution(
	workflowId: number,
	executionId: number
): Promise<WorkflowExecution> {
	const response = await apiClient.get<ExecutionResponse>(
		`/workflows/${workflowId}/executions/${executionId}`
	);
	return response.execution;
}

// Workflow Templates

export type TemplateCategory = 'lead' | 'deal' | 'customer' | 'data' | 'productivity' | 'communication';
export type TemplateDifficulty = 'beginner' | 'intermediate' | 'advanced';

export interface WorkflowTemplate {
	id: number;
	name: string;
	slug: string;
	description: string;
	category: TemplateCategory;
	icon: string | null;
	workflow_data: Record<string, unknown>;
	required_modules: string[] | null;
	required_fields: string[] | null;
	variable_mappings: Record<string, {
		label: string;
		description?: string;
		type: 'module' | 'field' | 'user' | 'text' | 'number';
		required?: boolean;
	}> | null;
	is_system: boolean;
	is_active: boolean;
	usage_count: number;
	difficulty: TemplateDifficulty;
	estimated_time_saved_hours: number | null;
	is_compatible?: boolean;
	missing_modules?: string[];
	created_at: string;
	updated_at: string;
}

interface TemplateListResponse {
	success: boolean;
	templates: WorkflowTemplate[];
	categories: Record<string, string>;
	difficulty_levels: Record<string, string>;
}

interface TemplateResponse {
	success: boolean;
	template: WorkflowTemplate;
}

interface TemplateUseResponse {
	success: boolean;
	message: string;
	workflow_data: Record<string, unknown>;
	template_id: number;
}

interface CategoriesResponse {
	success: boolean;
	categories: Record<string, string>;
}

interface TemplatesByCategoryResponse {
	success: boolean;
	category: string;
	category_label: string;
	templates: WorkflowTemplate[];
}

/**
 * Get all workflow templates
 */
export async function getWorkflowTemplates(params?: {
	category?: TemplateCategory;
	difficulty?: TemplateDifficulty;
	search?: string;
	popular?: boolean;
}): Promise<{ templates: WorkflowTemplate[]; categories: Record<string, string>; difficulty_levels: Record<string, string> }> {
	const queryParams: Record<string, string> = {};
	if (params?.category) {
		queryParams.category = params.category;
	}
	if (params?.difficulty) {
		queryParams.difficulty = params.difficulty;
	}
	if (params?.search) {
		queryParams.search = params.search;
	}
	if (params?.popular) {
		queryParams.popular = 'true';
	}

	const response = await apiClient.get<TemplateListResponse>('/workflows/templates', queryParams);
	return {
		templates: response.templates,
		categories: response.categories,
		difficulty_levels: response.difficulty_levels
	};
}

/**
 * Get a single workflow template
 */
export async function getWorkflowTemplate(id: number): Promise<WorkflowTemplate> {
	const response = await apiClient.get<TemplateResponse>(`/workflows/templates/${id}`);
	return response.template;
}

/**
 * Get template categories
 */
export async function getTemplateCategories(): Promise<Record<string, string>> {
	const response = await apiClient.get<CategoriesResponse>('/workflows/templates/categories');
	return response.categories;
}

/**
 * Get popular templates
 */
export async function getPopularTemplates(limit?: number): Promise<WorkflowTemplate[]> {
	const queryParams: Record<string, string> = {};
	if (limit) {
		queryParams.limit = String(limit);
	}
	const response = await apiClient.get<{ success: boolean; templates: WorkflowTemplate[] }>(
		'/workflows/templates/popular',
		queryParams
	);
	return response.templates;
}

/**
 * Get templates by category
 */
export async function getTemplatesByCategory(category: TemplateCategory): Promise<{ templates: WorkflowTemplate[]; category_label: string }> {
	const response = await apiClient.get<TemplatesByCategoryResponse>(
		`/workflows/templates/category/${category}`
	);
	return {
		templates: response.templates,
		category_label: response.category_label
	};
}

/**
 * Use a template to create workflow data
 */
export async function useWorkflowTemplate(
	id: number,
	options?: {
		name?: string;
		mappings?: Record<string, unknown>;
	}
): Promise<{ workflow_data: Record<string, unknown>; template_id: number }> {
	const response = await apiClient.post<TemplateUseResponse>(
		`/workflows/templates/${id}/use`,
		options
	);
	return {
		workflow_data: response.workflow_data,
		template_id: response.template_id
	};
}

// Workflow Versioning

export interface WorkflowVersionSummary {
	id: number;
	version_number: number;
	name: string;
	description: string | null;
	change_type: 'create' | 'update' | 'rollback' | 'restore';
	change_summary: string | null;
	changes: string[];
	is_active: boolean;
	trigger_type: string;
	step_count: number;
	created_by: {
		id: number;
		name: string;
	} | null;
	created_at: string;
}

export interface WorkflowVersionDetails extends WorkflowVersionSummary {
	workflow_id: number;
	workflow_data: Record<string, unknown>;
	steps: Array<{
		id: number;
		order: number;
		name: string | null;
		description: string | null;
		action_type: string;
		action_config: Record<string, unknown>;
		conditions: unknown[] | null;
		branch_id: string | null;
		is_parallel: boolean;
		continue_on_error: boolean;
		retry_count: number;
		retry_delay_seconds: number;
	}>;
	trigger_config: Record<string, unknown> | null;
	conditions: unknown[] | null;
	diff: {
		type: 'initial' | 'diff';
		changes: string[];
	};
}

export interface VersionComparison {
	version1: {
		id: number;
		version_number: number;
		created_at: string;
	};
	version2: {
		id: number;
		version_number: number;
		created_at: string;
	};
	changes: {
		workflow: Record<string, { old: unknown; new: unknown }>;
		steps: {
			added: unknown[];
			removed: unknown[];
			modified: Array<{ old: unknown; new: unknown }>;
			count_change: number;
		};
	};
	summary: string[];
}

interface VersionListResponse {
	success: boolean;
	versions: WorkflowVersionSummary[];
	current_version: number;
}

interface VersionDetailResponse {
	success: boolean;
	version: WorkflowVersionDetails;
}

interface VersionCompareResponse {
	success: boolean;
	comparison: VersionComparison;
}

/**
 * Get version history for a workflow
 */
export async function getWorkflowVersions(workflowId: number): Promise<{
	versions: WorkflowVersionSummary[];
	current_version: number;
}> {
	const response = await apiClient.get<VersionListResponse>(`/workflows/${workflowId}/versions`);
	return {
		versions: response.versions,
		current_version: response.current_version
	};
}

/**
 * Get a specific version's details
 */
export async function getWorkflowVersion(
	workflowId: number,
	versionId: number
): Promise<WorkflowVersionDetails> {
	const response = await apiClient.get<VersionDetailResponse>(
		`/workflows/${workflowId}/versions/${versionId}`
	);
	return response.version;
}

/**
 * Rollback workflow to a specific version
 */
export async function rollbackWorkflowToVersion(
	workflowId: number,
	versionId: number
): Promise<Workflow> {
	const response = await apiClient.post<{ success: boolean; workflow: Workflow; message: string }>(
		`/workflows/${workflowId}/versions/${versionId}/rollback`
	);
	return response.workflow;
}

/**
 * Compare two versions
 */
export async function compareWorkflowVersions(
	workflowId: number,
	versionId1: number,
	versionId2: number
): Promise<VersionComparison> {
	const response = await apiClient.get<VersionCompareResponse>(
		`/workflows/${workflowId}/versions/${versionId1}/compare/${versionId2}`
	);
	return response.comparison;
}

/**
 * Blueprint API Client
 *
 * Handles all Blueprint-related API calls for:
 * - Blueprint CRUD operations
 * - State management
 * - Transition management
 * - Runtime execution (start/complete transitions)
 * - SLA monitoring
 * - Approval workflows
 */

import { apiClient } from './client';

// ==================== Types ====================

export interface BlueprintState {
	id: number;
	blueprint_id: number;
	name: string;
	field_option_value: string | null;
	color: string | null;
	is_initial: boolean;
	is_terminal: boolean;
	position_x: number | null;
	position_y: number | null;
	metadata: Record<string, unknown> | null;
	created_at: string;
	updated_at: string;
}

export interface BlueprintTransitionCondition {
	id: number;
	transition_id: number;
	field_id: number | null;
	operator: string;
	value: string | null;
	logical_group: string;
	display_order: number;
	field?: {
		id: number;
		api_name: string;
		label: string;
		type: string;
	};
}

export interface BlueprintTransitionRequirement {
	id: number;
	transition_id: number;
	type: 'mandatory_field' | 'attachment' | 'note' | 'checklist';
	field_id: number | null;
	label: string | null;
	description: string | null;
	is_required: boolean;
	config: Record<string, unknown> | null;
	display_order: number;
	field?: {
		id: number;
		api_name: string;
		label: string;
		type: string;
	};
}

export interface BlueprintTransitionAction {
	id: number;
	transition_id: number;
	type: string;
	config: Record<string, unknown>;
	display_order: number;
	is_active: boolean;
}

export interface BlueprintApproval {
	id: number;
	transition_id: number;
	approval_type: 'specific_users' | 'role_based' | 'manager' | 'field_value';
	config: Record<string, unknown>;
	require_all: boolean;
	auto_reject_days: number | null;
	notify_on_pending: boolean;
	notify_on_complete: boolean;
}

export interface BlueprintTransition {
	id: number;
	blueprint_id: number;
	from_state_id: number | null;
	to_state_id: number;
	name: string;
	description: string | null;
	button_label: string | null;
	display_order: number;
	is_active: boolean;
	from_state?: BlueprintState;
	to_state?: BlueprintState;
	conditions?: BlueprintTransitionCondition[];
	requirements?: BlueprintTransitionRequirement[];
	actions?: BlueprintTransitionAction[];
	approval?: BlueprintApproval | null;
}

export interface BlueprintSlaEscalation {
	id: number;
	sla_id: number;
	trigger_type: 'approaching' | 'breached';
	trigger_value: number | null;
	action_type: string;
	config: Record<string, unknown>;
	display_order: number;
}

export interface BlueprintSla {
	id: number;
	blueprint_id: number;
	state_id: number;
	name: string;
	duration_hours: number;
	business_hours_only: boolean;
	exclude_weekends: boolean;
	is_active: boolean;
	state?: BlueprintState;
	escalations?: BlueprintSlaEscalation[];
}

export interface Blueprint {
	id: number;
	name: string;
	module_id: number;
	field_id: number;
	description: string | null;
	is_active: boolean;
	layout_data: Record<string, unknown> | null;
	created_at: string;
	updated_at: string;
	module?: { id: number; name: string; api_name: string };
	field?: { id: number; label: string; api_name: string; type: string };
	states?: BlueprintState[];
	transitions?: BlueprintTransition[];
	slas?: BlueprintSla[];
}

export interface RecordState {
	id: number;
	name: string;
	color: string | null;
	is_terminal: boolean;
	entered_at: string;
}

export interface AvailableTransition {
	id: number;
	name: string;
	button_label: string;
	to_state: {
		id: number;
		name: string;
		color: string | null;
	};
	has_requirements: boolean;
	requires_approval: boolean;
}

export interface SLAStatus {
	sla_id: number;
	sla_name: string;
	state_name: string;
	duration_hours: number;
	state_entered_at: string;
	due_at: string;
	percentage_elapsed: number;
	remaining_hours: number;
	remaining_seconds: number;
	status: 'active' | 'completed' | 'breached';
	is_breached: boolean;
	is_approaching: boolean;
}

export interface TransitionExecution {
	id: number;
	status: string;
	transition?: {
		id: number;
		name: string;
	};
	to_state?: {
		id: number;
		name: string;
	};
	started_at?: string;
	completed_at?: string;
}

export interface FormattedRequirement {
	id: number;
	type: 'mandatory_field' | 'attachment' | 'note' | 'checklist';
	label: string | null;
	description: string | null;
	is_required: boolean;
	display_order: number;
	field?: {
		id: number;
		api_name: string;
		label: string;
		type: string;
	};
	allowed_types?: string[];
	max_size?: number;
	min_length?: number;
	items?: Array<{ id?: string; label: string; required?: boolean }>;
}

export interface TransitionHistoryItem {
	id: number;
	transition: { id: number; name: string };
	from_state: { id: number; name: string } | null;
	to_state: { id: number; name: string };
	executed_by: { id: number; name: string } | null;
	status: string;
	started_at: string;
	completed_at: string | null;
}

export interface PendingApproval {
	id: number;
	record_id: number;
	execution: {
		id: number;
		transition: { id: number; name: string };
		blueprint: { id: number; name: string };
		module: { id: number; name: string };
	};
	requested_by: { id: number; name: string } | null;
	created_at: string;
}

// ==================== Response Types ====================

interface BlueprintsListResponse {
	blueprints: Blueprint[];
}

interface BlueprintResponse {
	blueprint: Blueprint;
}

interface StatesListResponse {
	states: BlueprintState[];
}

interface StateResponse {
	state: BlueprintState;
}

interface TransitionsListResponse {
	transitions: BlueprintTransition[];
}

interface TransitionResponse {
	transition: BlueprintTransition;
}

interface RecordStateResponse {
	blueprint: { id: number; name: string; is_active: boolean };
	current_state: RecordState | null;
	available_transitions: AvailableTransition[];
	sla_status: SLAStatus | null;
}

interface StartTransitionResponse {
	execution: TransitionExecution;
	requirements: FormattedRequirement[];
}

interface SubmitRequirementsResponse {
	execution: TransitionExecution;
	next_step: string;
}

interface CompleteExecutionResponse {
	execution: TransitionExecution;
	new_state: RecordState;
}

interface TransitionHistoryResponse {
	history: TransitionHistoryItem[];
}

interface SLAStatusResponse {
	sla_status: SLAStatus | null;
}

interface PendingApprovalsResponse {
	pending_approvals: PendingApproval[];
}

// ==================== Blueprint CRUD ====================

export async function getBlueprints(params?: {
	module_id?: number;
	active?: boolean;
}): Promise<Blueprint[]> {
	const response = await apiClient.get<BlueprintsListResponse>('/blueprints', { params });
	return response.blueprints;
}

export async function getBlueprint(id: number): Promise<Blueprint> {
	const response = await apiClient.get<BlueprintResponse>(`/blueprints/${id}`);
	return response.blueprint;
}

export async function createBlueprint(data: {
	name: string;
	module_id: number;
	field_id: number;
	description?: string;
	is_active?: boolean;
	sync_states_from_field?: boolean;
}): Promise<Blueprint> {
	const response = await apiClient.post<BlueprintResponse>('/blueprints', data);
	return response.blueprint;
}

export async function updateBlueprint(
	id: number,
	data: {
		name?: string;
		description?: string;
		is_active?: boolean;
		layout_data?: Record<string, unknown>;
	}
): Promise<Blueprint> {
	const response = await apiClient.put<BlueprintResponse>(`/blueprints/${id}`, data);
	return response.blueprint;
}

export async function updateBlueprintLayout(
	id: number,
	layoutData: Record<string, unknown>
): Promise<void> {
	await apiClient.put(`/blueprints/${id}/layout`, { layout_data: layoutData });
}

export async function deleteBlueprint(id: number): Promise<void> {
	await apiClient.delete(`/blueprints/${id}`);
}

export async function toggleBlueprintActive(id: number): Promise<Blueprint> {
	const response = await apiClient.post<BlueprintResponse>(`/blueprints/${id}/toggle-active`);
	return response.blueprint;
}

export async function syncBlueprintStates(id: number): Promise<Blueprint> {
	const response = await apiClient.post<BlueprintResponse>(`/blueprints/${id}/sync-states`);
	return response.blueprint;
}

// ==================== State Management ====================

export async function getStates(blueprintId: number): Promise<BlueprintState[]> {
	const response = await apiClient.get<StatesListResponse>(`/blueprints/${blueprintId}/states`);
	return response.states;
}

export async function createState(
	blueprintId: number,
	data: {
		name: string;
		field_option_value?: string;
		color?: string;
		is_initial?: boolean;
		is_terminal?: boolean;
		position_x?: number;
		position_y?: number;
		metadata?: Record<string, unknown>;
	}
): Promise<BlueprintState> {
	const response = await apiClient.post<StateResponse>(`/blueprints/${blueprintId}/states`, data);
	return response.state;
}

export async function updateState(
	blueprintId: number,
	stateId: number,
	data: {
		name?: string;
		field_option_value?: string;
		color?: string;
		is_initial?: boolean;
		is_terminal?: boolean;
		position_x?: number;
		position_y?: number;
		metadata?: Record<string, unknown>;
	}
): Promise<BlueprintState> {
	const response = await apiClient.put<StateResponse>(
		`/blueprints/${blueprintId}/states/${stateId}`,
		data
	);
	return response.state;
}

export async function deleteState(blueprintId: number, stateId: number): Promise<void> {
	await apiClient.delete(`/blueprints/${blueprintId}/states/${stateId}`);
}

// ==================== Transition Management ====================

export async function getTransitions(blueprintId: number): Promise<BlueprintTransition[]> {
	const response = await apiClient.get<TransitionsListResponse>(
		`/blueprints/${blueprintId}/transitions`
	);
	return response.transitions;
}

export async function createTransition(
	blueprintId: number,
	data: {
		from_state_id?: number | null;
		to_state_id: number;
		name: string;
		description?: string;
		button_label?: string;
		display_order?: number;
		is_active?: boolean;
	}
): Promise<BlueprintTransition> {
	const response = await apiClient.post<TransitionResponse>(
		`/blueprints/${blueprintId}/transitions`,
		data
	);
	return response.transition;
}

export async function updateTransition(
	blueprintId: number,
	transitionId: number,
	data: {
		from_state_id?: number | null;
		to_state_id?: number;
		name?: string;
		description?: string;
		button_label?: string;
		display_order?: number;
		is_active?: boolean;
	}
): Promise<BlueprintTransition> {
	const response = await apiClient.put<TransitionResponse>(
		`/blueprints/${blueprintId}/transitions/${transitionId}`,
		data
	);
	return response.transition;
}

export async function deleteTransition(blueprintId: number, transitionId: number): Promise<void> {
	await apiClient.delete(`/blueprints/${blueprintId}/transitions/${transitionId}`);
}

// ==================== Transition Conditions ====================

export async function getConditions(transitionId: number): Promise<BlueprintTransitionCondition[]> {
	const response = await apiClient.get<{ conditions: BlueprintTransitionCondition[] }>(
		`/blueprint-transitions/${transitionId}/conditions`
	);
	return response.conditions;
}

export async function createCondition(
	transitionId: number,
	data: {
		field_id: number;
		operator: string;
		value?: string | null;
		logical_group?: string;
		display_order?: number;
	}
): Promise<BlueprintTransitionCondition> {
	const response = await apiClient.post<{ condition: BlueprintTransitionCondition }>(
		`/blueprint-transitions/${transitionId}/conditions`,
		data
	);
	return response.condition;
}

export async function updateCondition(
	transitionId: number,
	conditionId: number,
	data: Partial<BlueprintTransitionCondition>
): Promise<BlueprintTransitionCondition> {
	const response = await apiClient.put<{ condition: BlueprintTransitionCondition }>(
		`/blueprint-transitions/${transitionId}/conditions/${conditionId}`,
		data
	);
	return response.condition;
}

export async function deleteCondition(transitionId: number, conditionId: number): Promise<void> {
	await apiClient.delete(`/blueprint-transitions/${transitionId}/conditions/${conditionId}`);
}

// ==================== Transition Requirements ====================

export async function getRequirements(transitionId: number): Promise<BlueprintTransitionRequirement[]> {
	const response = await apiClient.get<{ requirements: BlueprintTransitionRequirement[] }>(
		`/blueprint-transitions/${transitionId}/requirements`
	);
	return response.requirements;
}

export async function createRequirement(
	transitionId: number,
	data: {
		type: string;
		field_id?: number;
		label?: string;
		description?: string;
		is_required?: boolean;
		config?: Record<string, unknown>;
		display_order?: number;
	}
): Promise<BlueprintTransitionRequirement> {
	const response = await apiClient.post<{ requirement: BlueprintTransitionRequirement }>(
		`/blueprint-transitions/${transitionId}/requirements`,
		data
	);
	return response.requirement;
}

export async function updateRequirement(
	transitionId: number,
	requirementId: number,
	data: Partial<BlueprintTransitionRequirement>
): Promise<BlueprintTransitionRequirement> {
	const response = await apiClient.put<{ requirement: BlueprintTransitionRequirement }>(
		`/blueprint-transitions/${transitionId}/requirements/${requirementId}`,
		data
	);
	return response.requirement;
}

export async function deleteRequirement(transitionId: number, requirementId: number): Promise<void> {
	await apiClient.delete(`/blueprint-transitions/${transitionId}/requirements/${requirementId}`);
}

// ==================== Transition Actions ====================

export async function getActions(transitionId: number): Promise<BlueprintTransitionAction[]> {
	const response = await apiClient.get<{ actions: BlueprintTransitionAction[] }>(
		`/blueprint-transitions/${transitionId}/actions`
	);
	return response.actions;
}

export async function createAction(
	transitionId: number,
	data: {
		type: string;
		config: Record<string, unknown>;
		display_order?: number;
		is_active?: boolean;
	}
): Promise<BlueprintTransitionAction> {
	const response = await apiClient.post<{ action: BlueprintTransitionAction }>(
		`/blueprint-transitions/${transitionId}/actions`,
		data
	);
	return response.action;
}

export async function updateAction(
	transitionId: number,
	actionId: number,
	data: Partial<BlueprintTransitionAction>
): Promise<BlueprintTransitionAction> {
	const response = await apiClient.put<{ action: BlueprintTransitionAction }>(
		`/blueprint-transitions/${transitionId}/actions/${actionId}`,
		data
	);
	return response.action;
}

export async function deleteAction(transitionId: number, actionId: number): Promise<void> {
	await apiClient.delete(`/blueprint-transitions/${transitionId}/actions/${actionId}`);
}

// ==================== Transition Approval ====================

export async function getApproval(transitionId: number): Promise<BlueprintApproval | null> {
	const response = await apiClient.get<{ approval: BlueprintApproval | null }>(
		`/blueprint-transitions/${transitionId}/approval`
	);
	return response.approval;
}

export async function setApproval(
	transitionId: number,
	data: Partial<BlueprintApproval>
): Promise<BlueprintApproval> {
	const response = await apiClient.put<{ approval: BlueprintApproval }>(
		`/blueprint-transitions/${transitionId}/approval`,
		data
	);
	return response.approval;
}

export async function removeApproval(transitionId: number): Promise<void> {
	await apiClient.delete(`/blueprint-transitions/${transitionId}/approval`);
}

// ==================== SLA Management ====================

export async function getSlas(blueprintId: number): Promise<BlueprintSla[]> {
	const response = await apiClient.get<{ slas: BlueprintSla[] }>(
		`/blueprints/${blueprintId}/slas`
	);
	return response.slas;
}

export async function getSla(blueprintId: number, slaId: number): Promise<BlueprintSla> {
	const response = await apiClient.get<{ sla: BlueprintSla }>(
		`/blueprints/${blueprintId}/slas/${slaId}`
	);
	return response.sla;
}

export async function createSla(
	blueprintId: number,
	data: {
		state_id: number;
		name: string;
		duration_hours: number;
		business_hours_only?: boolean;
		exclude_weekends?: boolean;
		is_active?: boolean;
	}
): Promise<BlueprintSla> {
	const response = await apiClient.post<{ sla: BlueprintSla }>(
		`/blueprints/${blueprintId}/slas`,
		data
	);
	return response.sla;
}

export async function updateSla(
	blueprintId: number,
	slaId: number,
	data: Partial<BlueprintSla>
): Promise<BlueprintSla> {
	const response = await apiClient.put<{ sla: BlueprintSla }>(
		`/blueprints/${blueprintId}/slas/${slaId}`,
		data
	);
	return response.sla;
}

export async function deleteSla(blueprintId: number, slaId: number): Promise<void> {
	await apiClient.delete(`/blueprints/${blueprintId}/slas/${slaId}`);
}

export async function getEscalations(slaId: number): Promise<BlueprintSlaEscalation[]> {
	const response = await apiClient.get<{ escalations: BlueprintSlaEscalation[] }>(
		`/blueprint-slas/${slaId}/escalations`
	);
	return response.escalations;
}

export async function createSlaEscalation(
	slaId: number,
	data: {
		trigger_type: 'approaching' | 'breached';
		trigger_value?: number;
		action_type: string;
		config: Record<string, unknown>;
		display_order?: number;
	}
): Promise<BlueprintSlaEscalation> {
	const response = await apiClient.post<{ escalation: BlueprintSlaEscalation }>(
		`/blueprint-slas/${slaId}/escalations`,
		data
	);
	return response.escalation;
}

export async function updateSlaEscalation(
	slaId: number,
	escalationId: number,
	data: Partial<BlueprintSlaEscalation>
): Promise<BlueprintSlaEscalation> {
	const response = await apiClient.put<{ escalation: BlueprintSlaEscalation }>(
		`/blueprint-slas/${slaId}/escalations/${escalationId}`,
		data
	);
	return response.escalation;
}

export async function deleteSlaEscalation(slaId: number, escalationId: number): Promise<void> {
	await apiClient.delete(`/blueprint-slas/${slaId}/escalations/${escalationId}`);
}

// ==================== Runtime Execution ====================

export async function getRecordState(
	recordId: number,
	params: {
		blueprint_id?: number;
		module_id?: number;
		field_id?: number;
	}
): Promise<RecordStateResponse> {
	return apiClient.get<RecordStateResponse>(`/blueprint-records/${recordId}/state`, { params });
}

export async function startTransition(
	recordId: number,
	transitionId: number,
	recordData?: Record<string, unknown>
): Promise<StartTransitionResponse> {
	return apiClient.post<StartTransitionResponse>(
		`/blueprint-records/${recordId}/transitions/${transitionId}/start`,
		{ record_data: recordData }
	);
}

export async function submitRequirements(
	executionId: number,
	data: {
		fields?: Record<string, unknown>;
		attachments?: Array<{ name: string; size?: number; path?: string }>;
		note?: string;
		checklist?: Record<string | number, boolean>;
	}
): Promise<SubmitRequirementsResponse> {
	return apiClient.post<SubmitRequirementsResponse>(
		`/blueprint-executions/${executionId}/requirements`,
		data
	);
}

export async function completeExecution(executionId: number): Promise<CompleteExecutionResponse> {
	return apiClient.post<CompleteExecutionResponse>(
		`/blueprint-executions/${executionId}/complete`
	);
}

export async function cancelExecution(executionId: number): Promise<void> {
	await apiClient.post(`/blueprint-executions/${executionId}/cancel`);
}

export async function getTransitionHistory(
	recordId: number,
	blueprintId: number
): Promise<TransitionHistoryItem[]> {
	const response = await apiClient.get<TransitionHistoryResponse>(
		`/blueprint-records/${recordId}/history`,
		{ params: { blueprint_id: blueprintId } }
	);
	return response.history;
}

export async function getSLAStatus(
	recordId: number,
	blueprintId: number
): Promise<SLAStatus | null> {
	const response = await apiClient.get<SLAStatusResponse>(
		`/blueprint-records/${recordId}/sla-status`,
		{ params: { blueprint_id: blueprintId } }
	);
	return response.sla_status;
}

// ==================== Approvals ====================

export async function getPendingApprovals(): Promise<PendingApproval[]> {
	const response = await apiClient.get<PendingApprovalsResponse>('/blueprint-approvals/pending');
	return response.pending_approvals;
}

export async function approveRequest(requestId: number, comments?: string): Promise<void> {
	await apiClient.post(`/blueprint-approvals/${requestId}/approve`, { comments });
}

export async function rejectRequest(requestId: number, comments?: string): Promise<void> {
	await apiClient.post(`/blueprint-approvals/${requestId}/reject`, { comments });
}

// ==================== Utility Functions ====================

/**
 * Helper to get the color for an SLA status badge
 */
export function getSLAStatusColor(slaStatus: SLAStatus): string {
	if (slaStatus.is_breached) {
		return 'red';
	}
	if (slaStatus.is_approaching) {
		return 'orange';
	}
	return 'green';
}

/**
 * Helper to format remaining time
 */
export function formatRemainingTime(seconds: number): string {
	if (seconds <= 0) return 'Overdue';

	const hours = Math.floor(seconds / 3600);
	const minutes = Math.floor((seconds % 3600) / 60);

	if (hours > 24) {
		const days = Math.floor(hours / 24);
		return `${days}d ${hours % 24}h`;
	}

	if (hours > 0) {
		return `${hours}h ${minutes}m`;
	}

	return `${minutes}m`;
}

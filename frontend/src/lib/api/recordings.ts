import { apiClient } from './client';

// Types
export interface Recording {
	id: number;
	name: string | null;
	status: 'recording' | 'paused' | 'completed' | 'converted';
	started_at: string;
	ended_at: string | null;
	duration: number | null;
	module_id: number | null;
	module_name: string | null;
	initial_record_id: number | null;
	workflow_id: number | null;
	step_count: number;
	action_counts: Record<string, number>;
	user: { id: number; name: string } | null;
	steps?: RecordingStep[];
	created_at: string;
}

export interface RecordingStep {
	id: number;
	step_order: number;
	action_type: string;
	action_label: string;
	description: string;
	target_module: string | null;
	target_record_id: number | null;
	action_data: Record<string, unknown>;
	parameterized_data: Record<string, unknown> | null;
	is_parameterized: boolean;
	captured_at: string;
}

export interface WorkflowPreview {
	recording_id: number;
	name: string;
	module: string | null;
	steps: WorkflowPreviewStep[];
	step_count: number;
	suggested_triggers: SuggestedTrigger[];
}

export interface WorkflowPreviewStep {
	order: number;
	original_step_id: number;
	type: string;
	config: Record<string, unknown>;
	description: string;
	is_parameterized: boolean;
	action_type: string;
}

export interface SuggestedTrigger {
	type: string;
	label: string;
	config: Record<string, unknown>;
}

export type ActionType =
	| 'create_record'
	| 'update_field'
	| 'change_stage'
	| 'send_email'
	| 'create_task'
	| 'add_note'
	| 'add_tag'
	| 'remove_tag'
	| 'assign_user'
	| 'log_activity';

// Response types
interface RecordingListResponse {
	data: Recording[];
}

interface RecordingResponse {
	data: Recording;
}

interface ActiveRecordingResponse {
	data: Recording | null;
	is_recording: boolean;
}

interface RecordingStepListResponse {
	data: RecordingStep[];
}

interface RecordingStepResponse {
	data: RecordingStep;
}

interface WorkflowPreviewResponse {
	data: WorkflowPreview;
}

interface GenerateWorkflowResponse {
	data: { workflow_id: number; workflow_name: string; step_count: number };
}

interface CaptureActionResponse {
	captured: boolean;
	step: RecordingStep | null;
}

// API Functions

// Get all recordings for current user
export async function getRecordings(status?: string): Promise<Recording[]> {
	const params: Record<string, string> = {};
	if (status) params.status = status;
	const response = await apiClient.get<RecordingListResponse>('/recordings', params);
	return response.data;
}

// Get a single recording
export async function getRecording(id: number): Promise<Recording> {
	const response = await apiClient.get<RecordingResponse>(`/recordings/${id}`);
	return response.data;
}

// Get active recording for current user
export async function getActiveRecording(): Promise<{ data: Recording | null; is_recording: boolean }> {
	const response = await apiClient.get<ActiveRecordingResponse>('/recordings/active');
	return { data: response.data, is_recording: response.is_recording };
}

// Start a new recording
export async function startRecording(moduleId?: number, initialRecordId?: number): Promise<Recording> {
	const response = await apiClient.post<RecordingResponse>('/recordings/start', {
		module_id: moduleId,
		initial_record_id: initialRecordId
	});
	return response.data;
}

// Stop a recording
export async function stopRecording(id: number, name?: string): Promise<Recording> {
	const response = await apiClient.post<RecordingResponse>(`/recordings/${id}/stop`, { name });
	return response.data;
}

// Pause a recording
export async function pauseRecording(id: number): Promise<Recording> {
	const response = await apiClient.post<RecordingResponse>(`/recordings/${id}/pause`);
	return response.data;
}

// Resume a recording
export async function resumeRecording(id: number): Promise<Recording> {
	const response = await apiClient.post<RecordingResponse>(`/recordings/${id}/resume`);
	return response.data;
}

// Delete a recording
export async function deleteRecording(id: number): Promise<void> {
	await apiClient.delete(`/recordings/${id}`);
}

// Duplicate a recording
export async function duplicateRecording(id: number): Promise<Recording> {
	const response = await apiClient.post<RecordingResponse>(`/recordings/${id}/duplicate`);
	return response.data;
}

// Get steps for a recording
export async function getRecordingSteps(recordingId: number): Promise<RecordingStep[]> {
	const response = await apiClient.get<RecordingStepListResponse>(`/recordings/${recordingId}/steps`);
	return response.data;
}

// Remove a step from a recording
export async function removeStep(recordingId: number, stepId: number): Promise<void> {
	await apiClient.delete(`/recordings/${recordingId}/steps/${stepId}`);
}

// Reorder steps
export async function reorderSteps(recordingId: number, stepIds: number[]): Promise<void> {
	await apiClient.put(`/recordings/${recordingId}/steps/reorder`, { step_ids: stepIds });
}

// Parameterize a step field
export async function parameterizeStep(
	recordingId: number,
	stepId: number,
	field: string,
	referenceType: 'field' | 'current_user' | 'owner' | 'record_email' | 'custom',
	referenceField?: string
): Promise<RecordingStep> {
	const response = await apiClient.post<RecordingStepResponse>(
		`/recordings/${recordingId}/steps/${stepId}/parameterize`,
		{
			field,
			reference_type: referenceType,
			reference_field: referenceField
		}
	);
	return response.data;
}

// Reset step parameterization
export async function resetStepParameterization(recordingId: number, stepId: number): Promise<RecordingStep> {
	const response = await apiClient.delete<RecordingStepResponse>(
		`/recordings/${recordingId}/steps/${stepId}/parameterize`
	);
	return response.data;
}

// Preview recording as workflow
export async function previewWorkflow(recordingId: number): Promise<WorkflowPreview> {
	const response = await apiClient.get<WorkflowPreviewResponse>(`/recordings/${recordingId}/preview`);
	return response.data;
}

// Generate workflow from recording
export async function generateWorkflow(
	recordingId: number,
	name: string,
	triggerType: string,
	triggerConfig?: Record<string, unknown>,
	description?: string
): Promise<{ workflow_id: number; workflow_name: string; step_count: number }> {
	const response = await apiClient.post<GenerateWorkflowResponse>(
		`/recordings/${recordingId}/generate-workflow`,
		{
			name,
			trigger_type: triggerType,
			trigger_config: triggerConfig,
			description
		}
	);
	return response.data;
}

// Capture an action (called from various parts of the app when recording is active)
export async function captureAction(
	actionType: ActionType,
	data: Record<string, unknown>,
	module?: string,
	recordId?: number
): Promise<{ captured: boolean; step: RecordingStep | null }> {
	const response = await apiClient.post<CaptureActionResponse>('/recordings/capture', {
		action_type: actionType,
		data,
		module,
		record_id: recordId
	});
	return { captured: response.captured, step: response.step };
}

// Helper to get action type label
export function getActionTypeLabel(actionType: string): string {
	const labels: Record<string, string> = {
		create_record: 'Create Record',
		update_field: 'Update Field',
		change_stage: 'Change Stage',
		send_email: 'Send Email',
		create_task: 'Create Task',
		add_note: 'Add Note',
		add_tag: 'Add Tag',
		remove_tag: 'Remove Tag',
		assign_user: 'Assign User',
		log_activity: 'Log Activity'
	};
	return labels[actionType] || actionType;
}

// Helper to get action type icon
export function getActionTypeIcon(actionType: string): string {
	const icons: Record<string, string> = {
		create_record: 'Plus',
		update_field: 'Edit',
		change_stage: 'ArrowRight',
		send_email: 'Mail',
		create_task: 'CheckSquare',
		add_note: 'FileText',
		add_tag: 'Tag',
		remove_tag: 'TagOff',
		assign_user: 'UserPlus',
		log_activity: 'Activity'
	};
	return icons[actionType] || 'Circle';
}

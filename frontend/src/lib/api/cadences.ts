import { apiClient } from './client';

// Types
export type CadenceStatus = 'draft' | 'active' | 'paused' | 'archived';
export type CadenceChannel = 'email' | 'call' | 'sms' | 'linkedin' | 'task' | 'wait';
export type DelayType = 'immediate' | 'days' | 'hours' | 'business_days';
export type EnrollmentStatus = 'active' | 'paused' | 'completed' | 'replied' | 'bounced' | 'unsubscribed' | 'meeting_booked' | 'manually_removed';
export type ExecutionStatus = 'scheduled' | 'executing' | 'completed' | 'failed' | 'skipped' | 'cancelled';
export type LinkedInAction = 'connection_request' | 'message' | 'view_profile' | 'engage';

export interface Cadence {
	id: number;
	name: string;
	description: string | null;
	module_id: number;
	module?: {
		id: number;
		name: string;
		api_name: string;
	};
	status: CadenceStatus;
	entry_criteria: Record<string, unknown> | null;
	exit_criteria: Record<string, unknown> | null;
	settings: Record<string, unknown>;
	auto_enroll: boolean;
	allow_re_enrollment: boolean;
	re_enrollment_days: number | null;
	max_enrollments_per_day: number | null;
	created_by: number | null;
	creator?: {
		id: number;
		name: string;
	};
	owner_id: number | null;
	owner?: {
		id: number;
		name: string;
	};
	steps?: CadenceStep[];
	steps_count?: number;
	active_enrollments_count?: number;
	created_at: string;
	updated_at: string;
}

export interface CadenceStep {
	id: number;
	cadence_id: number;
	step_order: number;
	name: string | null;
	channel: CadenceChannel;
	delay_type: DelayType;
	delay_value: number;
	preferred_time: string | null;
	timezone: string | null;
	subject: string | null;
	content: string | null;
	template_id: number | null;
	template?: {
		id: number;
		name: string;
	};
	conditions: Record<string, unknown> | null;
	on_reply_goto_step: number | null;
	on_click_goto_step: number | null;
	on_no_response_goto_step: number | null;
	is_ab_test: boolean;
	ab_variant_of: number | null;
	ab_percentage: number | null;
	linkedin_action: LinkedInAction | null;
	task_type: string | null;
	task_assigned_to: number | null;
	is_active: boolean;
	created_at: string;
	updated_at: string;
}

export interface CadenceEnrollment {
	id: number;
	cadence_id: number;
	record_id: number;
	current_step_id: number | null;
	current_step?: CadenceStep;
	status: EnrollmentStatus;
	enrolled_at: string;
	next_step_at: string | null;
	completed_at: string | null;
	paused_at: string | null;
	exit_reason: string | null;
	enrolled_by: number | null;
	enrolled_by_user?: {
		id: number;
		name: string;
	};
	metadata: Record<string, unknown>;
	created_at: string;
	updated_at: string;
}

export interface CadenceAnalytics {
	summary: {
		total_enrollments: number;
		active_enrollments: number;
		completed_enrollments: number;
		replied_enrollments: number;
		meetings_booked: number;
		completion_rate: number;
		reply_rate: number;
		meeting_rate: number;
	};
	steps: {
		id: number;
		name: string;
		channel: CadenceChannel;
		stats: {
			total: number;
			completed: number;
			sent: number;
			opened: number;
			clicked: number;
			replied: number;
			bounced: number;
		};
	}[];
	daily_metrics: {
		date: string;
		enrollments: number;
		completions: number;
		replies: number;
		meetings_booked: number;
	}[];
}

export interface CadenceTemplate {
	id: number;
	name: string;
	description: string | null;
	category: string | null;
	steps_config: Record<string, unknown>[];
	settings: Record<string, unknown>;
	is_system: boolean;
	is_active: boolean;
	created_at: string;
}

export interface CreateCadenceRequest {
	name: string;
	description?: string;
	module_id: number;
	entry_criteria?: Record<string, unknown>;
	exit_criteria?: Record<string, unknown>;
	settings?: Record<string, unknown>;
	auto_enroll?: boolean;
	allow_re_enrollment?: boolean;
	re_enrollment_days?: number;
	max_enrollments_per_day?: number;
	owner_id?: number;
	steps?: CreateStepRequest[];
}

export interface UpdateCadenceRequest extends Partial<Omit<CreateCadenceRequest, 'module_id' | 'steps'>> {}

export interface CreateStepRequest {
	name?: string;
	channel: CadenceChannel;
	delay_type: DelayType;
	delay_value: number;
	preferred_time?: string;
	timezone?: string;
	subject?: string;
	content?: string;
	template_id?: number;
	conditions?: Record<string, unknown>;
	on_reply_goto_step?: number;
	on_click_goto_step?: number;
	on_no_response_goto_step?: number;
	is_ab_test?: boolean;
	ab_variant_of?: number;
	ab_percentage?: number;
	linkedin_action?: LinkedInAction;
	task_type?: string;
	task_assigned_to?: number;
	step_order?: number;
}

export interface UpdateStepRequest extends Partial<CreateStepRequest> {
	is_active?: boolean;
}

// API Functions

/**
 * Get cadence statuses
 */
export async function getCadenceStatuses(): Promise<Record<CadenceStatus, string>> {
	const response = await apiClient.get<{ success: boolean; data: Record<CadenceStatus, string> }>(
		'/cadences/statuses'
	);
	return response.data;
}

/**
 * Get available channels
 */
export async function getCadenceChannels(): Promise<Record<CadenceChannel, string>> {
	const response = await apiClient.get<{ success: boolean; data: Record<CadenceChannel, string> }>(
		'/cadences/channels'
	);
	return response.data;
}

/**
 * List cadences with filters
 */
export async function getCadences(params?: {
	module_id?: number;
	status?: CadenceStatus;
	owner_id?: number;
	search?: string;
	sort_field?: string;
	sort_order?: 'asc' | 'desc';
	per_page?: number;
	page?: number;
}): Promise<{
	data: Cadence[];
	meta: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}> {
	const response = await apiClient.get<{
		success: boolean;
		data: Cadence[];
		meta: {
			current_page: number;
			last_page: number;
			per_page: number;
			total: number;
		};
	}>('/cadences', params as Record<string, string>);
	return { data: response.data, meta: response.meta };
}

/**
 * Get a single cadence
 */
export async function getCadence(id: number): Promise<{ cadence: Cadence; analytics: CadenceAnalytics['summary'] }> {
	const response = await apiClient.get<{
		success: boolean;
		data: Cadence;
		analytics: CadenceAnalytics['summary'];
	}>(`/cadences/${id}`);
	return { cadence: response.data, analytics: response.analytics };
}

/**
 * Create a cadence
 */
export async function createCadence(data: CreateCadenceRequest): Promise<Cadence> {
	const response = await apiClient.post<{ success: boolean; data: Cadence; message: string }>(
		'/cadences',
		data
	);
	return response.data;
}

/**
 * Update a cadence
 */
export async function updateCadence(id: number, data: UpdateCadenceRequest): Promise<Cadence> {
	const response = await apiClient.put<{ success: boolean; data: Cadence; message: string }>(
		`/cadences/${id}`,
		data
	);
	return response.data;
}

/**
 * Delete a cadence
 */
export async function deleteCadence(id: number): Promise<void> {
	await apiClient.delete(`/cadences/${id}`);
}

/**
 * Activate a cadence
 */
export async function activateCadence(id: number): Promise<Cadence> {
	const response = await apiClient.post<{ success: boolean; data: Cadence; message: string }>(
		`/cadences/${id}/activate`
	);
	return response.data;
}

/**
 * Pause a cadence
 */
export async function pauseCadence(id: number): Promise<Cadence> {
	const response = await apiClient.post<{ success: boolean; data: Cadence; message: string }>(
		`/cadences/${id}/pause`
	);
	return response.data;
}

/**
 * Archive a cadence
 */
export async function archiveCadence(id: number): Promise<Cadence> {
	const response = await apiClient.post<{ success: boolean; data: Cadence; message: string }>(
		`/cadences/${id}/archive`
	);
	return response.data;
}

/**
 * Duplicate a cadence
 */
export async function duplicateCadence(id: number): Promise<Cadence> {
	const response = await apiClient.post<{ success: boolean; data: Cadence; message: string }>(
		`/cadences/${id}/duplicate`
	);
	return response.data;
}

/**
 * Get cadence analytics
 */
export async function getCadenceAnalytics(
	id: number,
	startDate?: string,
	endDate?: string
): Promise<CadenceAnalytics> {
	const params: Record<string, string> = {};
	if (startDate) params.start_date = startDate;
	if (endDate) params.end_date = endDate;

	const response = await apiClient.get<{ success: boolean; data: CadenceAnalytics }>(
		`/cadences/${id}/analytics`,
		params
	);
	return response.data;
}

// Step Management

/**
 * Add a step to a cadence
 */
export async function addStep(cadenceId: number, data: CreateStepRequest): Promise<CadenceStep> {
	const response = await apiClient.post<{ success: boolean; data: CadenceStep; message: string }>(
		`/cadences/${cadenceId}/steps`,
		data
	);
	return response.data;
}

/**
 * Update a step
 */
export async function updateStep(
	cadenceId: number,
	stepId: number,
	data: UpdateStepRequest
): Promise<CadenceStep> {
	const response = await apiClient.put<{ success: boolean; data: CadenceStep; message: string }>(
		`/cadences/${cadenceId}/steps/${stepId}`,
		data
	);
	return response.data;
}

/**
 * Delete a step
 */
export async function deleteStep(cadenceId: number, stepId: number): Promise<void> {
	await apiClient.delete(`/cadences/${cadenceId}/steps/${stepId}`);
}

/**
 * Reorder steps
 */
export async function reorderSteps(cadenceId: number, stepIds: number[]): Promise<void> {
	await apiClient.post(`/cadences/${cadenceId}/steps/reorder`, { step_ids: stepIds });
}

// Enrollment Management

/**
 * Get enrollments for a cadence
 */
export async function getEnrollments(
	cadenceId: number,
	params?: {
		status?: EnrollmentStatus;
		per_page?: number;
		page?: number;
	}
): Promise<{
	data: CadenceEnrollment[];
	meta: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}> {
	const response = await apiClient.get<{
		success: boolean;
		data: CadenceEnrollment[];
		meta: {
			current_page: number;
			last_page: number;
			per_page: number;
			total: number;
		};
	}>(`/cadences/${cadenceId}/enrollments`, params as Record<string, string>);
	return { data: response.data, meta: response.meta };
}

/**
 * Enroll a record
 */
export async function enrollRecord(cadenceId: number, recordId: number): Promise<CadenceEnrollment> {
	const response = await apiClient.post<{ success: boolean; data: CadenceEnrollment; message: string }>(
		`/cadences/${cadenceId}/enroll`,
		{ record_id: recordId }
	);
	return response.data;
}

/**
 * Bulk enroll records
 */
export async function bulkEnroll(
	cadenceId: number,
	recordIds: number[]
): Promise<{ success: number; failed: number; errors: Record<number, string> }> {
	const response = await apiClient.post<{
		success: boolean;
		data: { success: number; failed: number; errors: Record<number, string> };
		message: string;
	}>(`/cadences/${cadenceId}/bulk-enroll`, { record_ids: recordIds });
	return response.data;
}

/**
 * Unenroll a record
 */
export async function unenrollRecord(
	cadenceId: number,
	enrollmentId: number,
	reason?: string
): Promise<CadenceEnrollment> {
	const response = await apiClient.post<{ success: boolean; data: CadenceEnrollment; message: string }>(
		`/cadences/${cadenceId}/enrollments/${enrollmentId}/unenroll`,
		{ reason }
	);
	return response.data;
}

/**
 * Pause an enrollment
 */
export async function pauseEnrollment(cadenceId: number, enrollmentId: number): Promise<CadenceEnrollment> {
	const response = await apiClient.post<{ success: boolean; data: CadenceEnrollment; message: string }>(
		`/cadences/${cadenceId}/enrollments/${enrollmentId}/pause`
	);
	return response.data;
}

/**
 * Resume an enrollment
 */
export async function resumeEnrollment(cadenceId: number, enrollmentId: number): Promise<CadenceEnrollment> {
	const response = await apiClient.post<{ success: boolean; data: CadenceEnrollment; message: string }>(
		`/cadences/${cadenceId}/enrollments/${enrollmentId}/resume`
	);
	return response.data;
}

// Templates

/**
 * Get cadence templates
 */
export async function getCadenceTemplates(category?: string): Promise<CadenceTemplate[]> {
	const params: Record<string, string> = {};
	if (category) params.category = category;

	const response = await apiClient.get<{ success: boolean; data: CadenceTemplate[] }>(
		'/cadences/templates',
		params
	);
	return response.data;
}

/**
 * Create cadence from template
 */
export async function createFromTemplate(
	templateId: number,
	moduleId: number,
	name: string
): Promise<Cadence> {
	const response = await apiClient.post<{ success: boolean; data: Cadence; message: string }>(
		'/cadences/from-template',
		{ template_id: templateId, module_id: moduleId, name }
	);
	return response.data;
}

/**
 * Save cadence as template
 */
export async function saveAsTemplate(
	cadenceId: number,
	name: string,
	category?: string
): Promise<CadenceTemplate> {
	const response = await apiClient.post<{ success: boolean; data: CadenceTemplate; message: string }>(
		`/cadences/${cadenceId}/save-as-template`,
		{ name, category }
	);
	return response.data;
}

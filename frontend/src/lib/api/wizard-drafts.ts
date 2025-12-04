import { apiClient } from './client';

// Types
export interface WizardDraftSummary {
	id: number;
	wizard_type: string;
	reference_id: string | null;
	name: string;
	current_step_index: number;
	completion_percentage: number;
	expires_at: string | null;
	created_at: string;
	updated_at: string;
}

export interface WizardDraft extends WizardDraftSummary {
	display_name: string;
	form_data: Record<string, unknown>;
	steps_state: Array<{
		id: string;
		title: string;
		isValid?: boolean;
		isComplete?: boolean;
		isSkipped?: boolean;
	}>;
}

export interface CreateDraftPayload {
	wizard_type: string;
	reference_id?: string;
	name?: string;
	form_data: Record<string, unknown>;
	steps_state: Array<{
		id: string;
		title: string;
		isValid?: boolean;
		isComplete?: boolean;
		isSkipped?: boolean;
	}>;
	current_step_index: number;
	draft_id?: number;
}

export interface AutoSavePayload {
	draft_id: number;
	form_data: Record<string, unknown>;
	steps_state: Array<{
		id: string;
		title: string;
		isValid?: boolean;
		isComplete?: boolean;
		isSkipped?: boolean;
	}>;
	current_step_index: number;
}

// API Functions

/**
 * Get all drafts for the current user
 */
export async function getDrafts(params?: {
	wizard_type?: string;
	reference_id?: string;
}): Promise<WizardDraftSummary[]> {
	const searchParams = new URLSearchParams();
	if (params?.wizard_type) searchParams.set('wizard_type', params.wizard_type);
	if (params?.reference_id) searchParams.set('reference_id', params.reference_id);

	const queryString = searchParams.toString();
	const url = queryString ? `/wizard-drafts?${queryString}` : '/wizard-drafts';

	const response = await apiClient.get<{ success: boolean; data: WizardDraftSummary[] }>(url);
	return response.data;
}

/**
 * Get a specific draft by ID
 */
export async function getDraft(id: number): Promise<WizardDraft> {
	const response = await apiClient.get<{ success: boolean; data: WizardDraft }>(
		`/wizard-drafts/${id}`
	);
	return response.data;
}

/**
 * Create or update a draft
 */
export async function saveDraft(payload: CreateDraftPayload): Promise<WizardDraft> {
	const response = await apiClient.post<{ success: boolean; data: WizardDraft }>(
		'/wizard-drafts',
		payload
	);
	return response.data;
}

/**
 * Auto-save draft (lightweight update)
 */
export async function autoSaveDraft(
	payload: AutoSavePayload
): Promise<{ id: number; updated_at: string }> {
	const response = await apiClient.post<{
		success: boolean;
		data: { id: number; updated_at: string };
	}>('/wizard-drafts/auto-save', payload);
	return response.data;
}

/**
 * Rename a draft
 */
export async function renameDraft(
	id: number,
	name: string
): Promise<{ id: number; name: string; display_name: string }> {
	const response = await apiClient.patch<{
		success: boolean;
		data: { id: number; name: string; display_name: string };
	}>(`/wizard-drafts/${id}/rename`, { name });
	return response.data;
}

/**
 * Delete a draft
 */
export async function deleteDraft(id: number): Promise<void> {
	await apiClient.delete(`/wizard-drafts/${id}`);
}

/**
 * Delete multiple drafts
 */
export async function bulkDeleteDrafts(ids: number[]): Promise<{ deleted_count: number }> {
	const response = await apiClient.post<{
		success: boolean;
		message: string;
		deleted_count: number;
	}>('/wizard-drafts/bulk-delete', { ids });
	return { deleted_count: response.deleted_count };
}

/**
 * Make a draft permanent (remove expiration)
 */
export async function makeDraftPermanent(id: number): Promise<{ id: number; expires_at: null }> {
	const response = await apiClient.post<{
		success: boolean;
		data: { id: number; expires_at: null };
	}>(`/wizard-drafts/${id}/make-permanent`);
	return response.data;
}

/**
 * Extend draft expiration
 */
export async function extendDraftExpiration(
	id: number,
	days: number
): Promise<{ id: number; expires_at: string }> {
	const response = await apiClient.post<{
		success: boolean;
		data: { id: number; expires_at: string };
	}>(`/wizard-drafts/${id}/extend`, { days });
	return response.data;
}

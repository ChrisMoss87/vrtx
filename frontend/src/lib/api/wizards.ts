import { apiClient } from './client';

export interface WizardStep {
	id?: number;
	title: string;
	description?: string;
	type: 'form' | 'review' | 'confirmation' | 'custom';
	fields: string[];
	can_skip: boolean;
	display_order: number;
	conditional_logic?: {
		enabled: boolean;
		skipIf?: {
			field: string;
			operator: string;
			value: unknown;
		}[];
	};
	validation_rules?: Record<string, unknown>;
}

export interface WizardSettings {
	showProgress: boolean;
	allowClickNavigation: boolean;
	saveAsDraft: boolean;
}

export interface Wizard {
	id: number;
	name: string;
	api_name: string;
	description?: string;
	type: 'record_creation' | 'record_edit' | 'standalone';
	is_active: boolean;
	is_default: boolean;
	settings: WizardSettings;
	display_order: number;
	module?: {
		id: number;
		name: string;
		api_name: string;
	};
	creator?: {
		id: number;
		name: string;
	};
	steps: WizardStep[];
	step_count: number;
	field_count: number;
	created_at: string;
	updated_at: string;
}

export interface CreateWizardData {
	name: string;
	api_name?: string;
	description?: string;
	module_id?: number;
	type: 'record_creation' | 'record_edit' | 'standalone';
	is_active?: boolean;
	is_default?: boolean;
	settings?: Partial<WizardSettings>;
	steps: Omit<WizardStep, 'id' | 'display_order'>[];
}

export interface UpdateWizardData extends Partial<CreateWizardData> {
	steps?: WizardStep[];
}

export async function getWizards(params?: {
	module_id?: number;
	type?: string;
	active_only?: boolean;
}): Promise<Wizard[]> {
	const searchParams = new URLSearchParams();
	if (params?.module_id) searchParams.set('module_id', String(params.module_id));
	if (params?.type) searchParams.set('type', params.type);
	if (params?.active_only) searchParams.set('active_only', '1');

	const query = searchParams.toString();
	const response = await apiClient.get<{ data: Wizard[] }>(`/wizards${query ? `?${query}` : ''}`);
	return response.data;
}

export async function getWizard(id: number): Promise<Wizard> {
	const response = await apiClient.get<{ data: Wizard }>(`/wizards/${id}`);
	return response.data;
}

export async function getWizardsForModule(moduleId: number): Promise<Wizard[]> {
	const response = await apiClient.get<{ data: Wizard[] }>(`/wizards/module/${moduleId}`);
	return response.data;
}

export async function createWizard(data: CreateWizardData): Promise<Wizard> {
	const response = await apiClient.post<{ data: Wizard; message: string }>('/wizards', data);
	return response.data;
}

export async function updateWizard(id: number, data: UpdateWizardData): Promise<Wizard> {
	const response = await apiClient.put<{ data: Wizard; message: string }>(`/wizards/${id}`, data);
	return response.data;
}

export async function deleteWizard(id: number): Promise<void> {
	await apiClient.delete(`/wizards/${id}`);
}

export async function duplicateWizard(id: number): Promise<Wizard> {
	const response = await apiClient.post<{ data: Wizard; message: string }>(
		`/wizards/${id}/duplicate`
	);
	return response.data;
}

export async function toggleWizardActive(id: number): Promise<Wizard> {
	const response = await apiClient.post<{ data: Wizard; message: string }>(
		`/wizards/${id}/toggle-active`
	);
	return response.data;
}

export async function reorderWizards(
	wizards: { id: number; display_order: number }[]
): Promise<void> {
	await apiClient.post('/wizards/reorder', { wizards });
}

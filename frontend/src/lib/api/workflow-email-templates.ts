import { apiClient } from './client';

/**
 * Workflow email template types
 */
export interface WorkflowEmailTemplate {
	id: number;
	name: string;
	description: string | null;
	subject: string;
	body_html: string;
	body_text: string | null;
	from_name: string | null;
	from_email: string | null;
	reply_to: string | null;
	available_variables: VariableDefinition[] | null;
	category: string | null;
	is_system: boolean;
	created_by: number | null;
	updated_by: number | null;
	created_at: string;
	updated_at: string;
}

export interface VariableDefinition {
	name: string;
	description: string;
	fields?: string[];
}

export interface WorkflowEmailTemplateInput {
	name: string;
	description?: string;
	subject: string;
	body_html: string;
	body_text?: string;
	from_name?: string;
	from_email?: string;
	reply_to?: string;
	available_variables?: VariableDefinition[];
	category?: string;
}

export interface TemplatePreview {
	subject: string;
	body_html: string;
	body_text: string | null;
}

interface TemplateListResponse {
	success: boolean;
	templates: WorkflowEmailTemplate[];
}

interface TemplateResponse {
	success: boolean;
	template: WorkflowEmailTemplate;
	available_variables?: Record<string, VariableDefinition>;
	message?: string;
}

interface PreviewResponse {
	success: boolean;
	preview: TemplatePreview;
	sample_data: Record<string, unknown>;
}

interface CategoriesResponse {
	success: boolean;
	categories: string[];
}

interface VariablesResponse {
	success: boolean;
	variables: Record<string, VariableDefinition>;
}

/**
 * Get all workflow email templates
 */
export async function getWorkflowEmailTemplates(params?: {
	category?: string;
	search?: string;
	include_system?: boolean;
}): Promise<WorkflowEmailTemplate[]> {
	const response = await apiClient.get<TemplateListResponse>('/workflow-email-templates', {
		params
	});
	return response.templates;
}

/**
 * Get a single template by ID
 */
export async function getWorkflowEmailTemplate(
	id: number
): Promise<{ template: WorkflowEmailTemplate; variables: Record<string, VariableDefinition> }> {
	const response = await apiClient.get<TemplateResponse>(`/workflow-email-templates/${id}`);
	return {
		template: response.template,
		variables: response.available_variables || {}
	};
}

/**
 * Create a new template
 */
export async function createWorkflowEmailTemplate(
	data: WorkflowEmailTemplateInput
): Promise<WorkflowEmailTemplate> {
	const response = await apiClient.post<TemplateResponse>('/workflow-email-templates', data);
	return response.template;
}

/**
 * Update an existing template
 */
export async function updateWorkflowEmailTemplate(
	id: number,
	data: Partial<WorkflowEmailTemplateInput>
): Promise<WorkflowEmailTemplate> {
	const response = await apiClient.put<TemplateResponse>(`/workflow-email-templates/${id}`, data);
	return response.template;
}

/**
 * Delete a template
 */
export async function deleteWorkflowEmailTemplate(id: number): Promise<void> {
	await apiClient.delete(`/workflow-email-templates/${id}`);
}

/**
 * Duplicate a template
 */
export async function duplicateWorkflowEmailTemplate(id: number): Promise<WorkflowEmailTemplate> {
	const response = await apiClient.post<TemplateResponse>(
		`/workflow-email-templates/${id}/duplicate`
	);
	return response.template;
}

/**
 * Preview a template with sample data
 */
export async function previewWorkflowEmailTemplate(
	id: number,
	data?: Record<string, unknown>
): Promise<TemplatePreview> {
	const response = await apiClient.post<PreviewResponse>(
		`/workflow-email-templates/${id}/preview`,
		{ data }
	);
	return response.preview;
}

/**
 * Get available categories
 */
export async function getWorkflowEmailTemplateCategories(): Promise<string[]> {
	const response = await apiClient.get<CategoriesResponse>('/workflow-email-templates/categories');
	return response.categories;
}

/**
 * Get available variables documentation
 */
export async function getWorkflowEmailTemplateVariables(): Promise<
	Record<string, VariableDefinition>
> {
	const response = await apiClient.get<VariablesResponse>('/workflow-email-templates/variables');
	return response.variables;
}

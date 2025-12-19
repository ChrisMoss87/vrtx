import { apiClient } from './client';
import type { PaginatedResponse } from './client';

export type ExportStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'expired';
export type ExportFileType = 'csv' | 'xlsx';

export interface Export {
	id: number;
	module_id: number;
	user_id: number;
	name: string;
	file_name: string | null;
	file_path: string | null;
	file_type: ExportFileType;
	file_size: number | null;
	status: ExportStatus;
	total_records: number;
	exported_records: number;
	selected_fields: string[];
	filters: ExportFilter[] | null;
	sorting: ExportSort[] | null;
	export_options: ExportOptions | null;
	error_message: string | null;
	started_at: string | null;
	completed_at: string | null;
	expires_at: string | null;
	download_count: number;
	created_at: string;
	updated_at: string;
	user?: {
		id: number;
		name: string;
		email: string;
	};
}

export interface ExportFilter {
	field: string;
	operator: string;
	value: unknown;
}

export interface ExportSort {
	field: string;
	direction: 'asc' | 'desc';
}

export interface ExportOptions {
	include_headers?: boolean;
	date_format?: string;
}

export interface ExportTemplate {
	id: number;
	module_id: number;
	user_id: number | null;
	name: string;
	description: string | null;
	selected_fields: string[];
	filters: ExportFilter[] | null;
	sorting: ExportSort[] | null;
	export_options: ExportOptions | null;
	default_file_type: ExportFileType;
	is_shared: boolean;
	created_at: string;
	updated_at: string;
	user?: {
		id: number;
		name: string;
	};
}

export interface ExportShowResponse {
	export: Export;
	is_downloadable: boolean;
	download_url: string | null;
}

export interface CreateExportInput {
	name?: string;
	file_type: ExportFileType;
	selected_fields: string[];
	filters?: ExportFilter[];
	sorting?: ExportSort[];
	export_options?: ExportOptions;
}

export interface CreateExportTemplateInput {
	name: string;
	description?: string;
	selected_fields: string[];
	filters?: ExportFilter[];
	sorting?: ExportSort[];
	export_options?: ExportOptions;
	default_file_type?: ExportFileType;
	is_shared?: boolean;
}

// API Functions

export async function getExports(
	moduleApiName: string,
	params?: {
		status?: ExportStatus;
		page?: number;
		per_page?: number;
	}
): Promise<PaginatedResponse<Export>> {
	const searchParams = new URLSearchParams();
	if (params?.status) searchParams.set('status', params.status);
	if (params?.page) searchParams.set('page', params.page.toString());
	if (params?.per_page) searchParams.set('per_page', params.per_page.toString());

	const query = searchParams.toString();
	return apiClient.get(`/exports/${moduleApiName}${query ? `?${query}` : ''}`);
}

export async function getExport(moduleApiName: string, exportId: number): Promise<ExportShowResponse> {
	return apiClient.get(`/exports/${moduleApiName}/${exportId}`);
}

export async function createExport(
	moduleApiName: string,
	data: CreateExportInput
): Promise<{ export: Export; message: string }> {
	return apiClient.post(`/exports/${moduleApiName}`, data);
}

export async function downloadExport(moduleApiName: string, exportId: number): Promise<Blob> {
	const response = await fetch(`/api/v1/exports/${moduleApiName}/${exportId}/download`, {
		method: 'GET',
		headers: {
			Authorization: `Bearer ${localStorage.getItem('auth_token')}`
		}
	});

	if (!response.ok) {
		throw new Error('Download failed');
	}

	return response.blob();
}

export async function deleteExport(moduleApiName: string, exportId: number): Promise<void> {
	return apiClient.delete(`/exports/${moduleApiName}/${exportId}`);
}

// Export Templates

export async function getExportTemplates(moduleApiName: string): Promise<ExportTemplate[]> {
	return apiClient.get(`/exports/${moduleApiName}/templates`);
}

export async function createExportTemplate(
	moduleApiName: string,
	data: CreateExportTemplateInput
): Promise<ExportTemplate> {
	return apiClient.post(`/exports/${moduleApiName}/templates`, data);
}

export async function updateExportTemplate(
	moduleApiName: string,
	templateId: number,
	data: Partial<CreateExportTemplateInput>
): Promise<ExportTemplate> {
	return apiClient.put(`/exports/${moduleApiName}/templates/${templateId}`, data);
}

export async function deleteExportTemplate(moduleApiName: string, templateId: number): Promise<void> {
	return apiClient.delete(`/exports/${moduleApiName}/templates/${templateId}`);
}

export async function exportFromTemplate(
	moduleApiName: string,
	templateId: number,
	options?: {
		name?: string;
		file_type?: ExportFileType;
	}
): Promise<{ export: Export; message: string }> {
	return apiClient.post(`/exports/${moduleApiName}/templates/${templateId}/export`, options || {});
}

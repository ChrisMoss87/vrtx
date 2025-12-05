import { apiClient } from './client';
import type { PaginatedResponse } from './client';

export type ImportStatus =
	| 'pending'
	| 'validating'
	| 'validated'
	| 'importing'
	| 'completed'
	| 'failed'
	| 'cancelled';

export type ImportRowStatus = 'pending' | 'success' | 'failed' | 'skipped';

export interface Import {
	id: number;
	module_id: number;
	user_id: number;
	name: string;
	file_name: string;
	file_path: string;
	file_type: 'csv' | 'xlsx' | 'xls';
	file_size: number;
	status: ImportStatus;
	total_rows: number;
	processed_rows: number;
	successful_rows: number;
	failed_rows: number;
	skipped_rows: number;
	column_mapping: Record<string, string | null> | null;
	import_options: ImportOptions | null;
	validation_errors: Record<string, Record<string, string>> | null;
	error_message: string | null;
	started_at: string | null;
	completed_at: string | null;
	created_at: string;
	updated_at: string;
	user?: {
		id: number;
		name: string;
		email: string;
	};
}

export interface ImportOptions {
	duplicate_handling?: 'skip' | 'update' | 'create';
	duplicate_check_field?: string;
	skip_empty_rows?: boolean;
}

export interface ImportRow {
	id: number;
	import_id: number;
	row_number: number;
	original_data: Record<string, unknown>;
	mapped_data: Record<string, unknown> | null;
	status: ImportRowStatus;
	record_id: number | null;
	errors: Record<string, string> | null;
	created_at: string;
	updated_at: string;
}

export interface ImportUploadResponse {
	import: Import;
	preview: {
		headers: string[];
		preview_rows: unknown[][];
		total_rows: number;
	};
	suggested_mapping: Record<string, string | null>;
	module_fields: Array<{
		id: number;
		api_name: string;
		label: string;
		type: string;
		is_required: boolean;
	}>;
}

export interface ImportTemplateResponse {
	module: {
		name: string;
		api_name: string;
	};
	fields: Array<{
		api_name: string;
		label: string;
		type: string;
		is_required: boolean;
		description: string;
	}>;
}

export interface ImportSummary {
	total_rows: number;
	processed_rows: number;
	successful_rows: number;
	failed_rows: number;
	skipped_rows: number;
	progress_percentage: number;
}

export interface ImportShowResponse {
	import: Import;
	summary: ImportSummary;
}

// API Functions

export async function getImports(
	moduleApiName: string,
	params?: {
		status?: ImportStatus;
		page?: number;
		per_page?: number;
	}
): Promise<PaginatedResponse<Import>> {
	const searchParams = new URLSearchParams();
	if (params?.status) searchParams.set('status', params.status);
	if (params?.page) searchParams.set('page', params.page.toString());
	if (params?.per_page) searchParams.set('per_page', params.per_page.toString());

	const query = searchParams.toString();
	return apiClient.get(`/imports/${moduleApiName}${query ? `?${query}` : ''}`);
}

export async function getImport(moduleApiName: string, importId: number): Promise<ImportShowResponse> {
	return apiClient.get(`/imports/${moduleApiName}/${importId}`);
}

export async function uploadImportFile(
	moduleApiName: string,
	file: File,
	name?: string
): Promise<ImportUploadResponse> {
	const formData = new FormData();
	formData.append('file', file);
	if (name) formData.append('name', name);

	return apiClient.upload(`/imports/${moduleApiName}/upload`, formData);
}

export async function configureImport(
	moduleApiName: string,
	importId: number,
	config: {
		column_mapping: Record<string, string | null>;
		import_options?: ImportOptions;
	}
): Promise<{ import: Import; message: string }> {
	return apiClient.put(`/imports/${moduleApiName}/${importId}/configure`, config);
}

export async function validateImport(
	moduleApiName: string,
	importId: number
): Promise<{ import: Import; message: string }> {
	return apiClient.post(`/imports/${moduleApiName}/${importId}/validate`);
}

export async function executeImport(
	moduleApiName: string,
	importId: number
): Promise<{ import: Import; message: string }> {
	return apiClient.post(`/imports/${moduleApiName}/${importId}/execute`);
}

export async function cancelImport(
	moduleApiName: string,
	importId: number
): Promise<{ import: Import; message: string }> {
	return apiClient.post(`/imports/${moduleApiName}/${importId}/cancel`);
}

export async function getImportErrors(
	moduleApiName: string,
	importId: number,
	params?: {
		page?: number;
		per_page?: number;
	}
): Promise<PaginatedResponse<ImportRow>> {
	const searchParams = new URLSearchParams();
	if (params?.page) searchParams.set('page', params.page.toString());
	if (params?.per_page) searchParams.set('per_page', params.per_page.toString());

	const query = searchParams.toString();
	return apiClient.get(`/imports/${moduleApiName}/${importId}/errors${query ? `?${query}` : ''}`);
}

export async function getImportTemplate(moduleApiName: string): Promise<ImportTemplateResponse> {
	return apiClient.get(`/imports/${moduleApiName}/template`);
}

export async function deleteImport(moduleApiName: string, importId: number): Promise<void> {
	return apiClient.delete(`/imports/${moduleApiName}/${importId}`);
}

import { apiClient } from './client';
import type { FilterConfig, SortConfig } from '$lib/components/datatable/types';

export interface ModuleView {
	id: number;
	module_id: number;
	user_id: number | null;
	name: string;
	description: string | null;
	filters: FilterConfig[];
	sorting: SortConfig[];
	column_visibility: Record<string, boolean>;
	column_order: string[] | null;
	column_widths: Record<string, number> | null;
	page_size: number;
	is_default: boolean;
	is_shared: boolean;
	display_order: number;
	created_at: string;
	updated_at: string;
}

export interface ModuleDefaults {
	filters: FilterConfig[];
	sorting: SortConfig[];
	column_visibility: Record<string, boolean>;
	page_size: number;
}

export interface CreateViewRequest {
	name: string;
	description?: string;
	filters?: FilterConfig[];
	sorting?: SortConfig[];
	column_visibility?: Record<string, boolean>;
	column_order?: string[];
	column_widths?: Record<string, number>;
	page_size?: number;
	is_default?: boolean;
	is_shared?: boolean;
}

export interface UpdateViewRequest extends Partial<CreateViewRequest> {}

interface ViewsResponse {
	success: boolean;
	views: ModuleView[];
}

interface ViewResponse {
	success: boolean;
	view: ModuleView;
}

interface DefaultViewResponse {
	success: boolean;
	view: ModuleView | null;
	module_defaults: ModuleDefaults | null;
}

/**
 * Get all views for a module
 */
export async function getViews(moduleApiName: string): Promise<ModuleView[]> {
	const response = await apiClient.get<ViewsResponse>(`/views/${moduleApiName}`);
	return response.views;
}

/**
 * Get a specific view
 */
export async function getView(moduleApiName: string, viewId: number): Promise<ModuleView> {
	const response = await apiClient.get<ViewResponse>(`/views/${moduleApiName}/${viewId}`);
	return response.view;
}

/**
 * Get the default view for a module
 */
export async function getDefaultView(
	moduleApiName: string
): Promise<{ view: ModuleView | null; module_defaults: ModuleDefaults | null }> {
	const response = await apiClient.get<DefaultViewResponse>(`/views/${moduleApiName}/default`);
	return {
		view: response.view,
		module_defaults: response.module_defaults
	};
}

/**
 * Create a new view
 */
export async function createView(
	moduleApiName: string,
	data: CreateViewRequest
): Promise<ModuleView> {
	const response = await apiClient.post<ViewResponse>(`/views/${moduleApiName}`, data);
	return response.view;
}

/**
 * Update an existing view
 */
export async function updateView(
	moduleApiName: string,
	viewId: number,
	data: UpdateViewRequest
): Promise<ModuleView> {
	const response = await apiClient.put<ViewResponse>(`/views/${moduleApiName}/${viewId}`, data);
	return response.view;
}

/**
 * Delete a view
 */
export async function deleteView(moduleApiName: string, viewId: number): Promise<void> {
	await apiClient.delete(`/views/${moduleApiName}/${viewId}`);
}

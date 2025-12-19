import { apiClient } from './client';
import type { FilterConfig, SortConfig } from '$lib/components/datatable/types';

export type ViewType = 'table' | 'kanban';

export interface KanbanColumnSettings {
	color?: string;
	hidden?: boolean;
	wip_limit?: number;
}

export interface KanbanConfig {
	group_by_field: string;
	value_field?: string;
	title_field?: string;
	subtitle_field?: string;
	card_fields?: string[];
	collapsed_columns?: string[];
	hidden_columns?: string[];
	column_order?: string[];
	column_settings?: Record<string, KanbanColumnSettings>;
}

export interface ModuleView {
	id: number;
	module_id: number;
	user_id: number | null;
	name: string;
	description: string | null;
	view_type: ViewType;
	kanban_config: KanbanConfig | null;
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

export interface KanbanFieldOption {
	value: string;
	label: string;
	color: string | null;
	display_order: number;
}

export interface KanbanField {
	api_name: string;
	label: string;
	type: string;
	options: KanbanFieldOption[];
}

export interface KanbanColumn {
	id: string;
	name: string;
	color: string;
	display_order: number;
	records: KanbanRecord[];
	count: number;
	total: number;
	hidden?: boolean;
	collapsed?: boolean;
	wip_limit?: number;
}

export interface KanbanRecord {
	id: number;
	title: string;
	data: Record<string, unknown>;
	value?: number;
}

export interface KanbanData {
	columns: KanbanColumn[];
	field: {
		api_name: string;
		label: string;
	};
	config: KanbanConfig;
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
	view_type?: ViewType;
	kanban_config?: KanbanConfig;
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

/**
 * Get fields that can be used for kanban grouping
 */
export async function getKanbanFields(moduleApiName: string): Promise<KanbanField[]> {
	const response = await apiClient.get<{ success: boolean; fields: KanbanField[] }>(
		`/views/${moduleApiName}/kanban-fields`
	);
	return response.fields;
}

/**
 * Get kanban data for a view
 */
export async function getKanbanData(
	moduleApiName: string,
	viewId: number,
	search?: string,
	groupByField?: string
): Promise<KanbanData> {
	const params: Record<string, string> = {};
	if (search) params.search = search;
	if (groupByField) params.group_by_field = groupByField;

	const response = await apiClient.get<{ success: boolean } & KanbanData>(
		`/views/${moduleApiName}/${viewId}/kanban`,
		{ params }
	);
	return {
		columns: response.columns,
		field: response.field,
		config: response.config
	};
}

/**
 * Move a record to a different kanban column
 */
export async function moveKanbanRecord(
	moduleApiName: string,
	viewId: number,
	recordId: number,
	newValue: string,
	groupByField?: string
): Promise<{ old_value: string | null; new_value: string }> {
	const body: Record<string, unknown> = {
		record_id: recordId,
		new_value: newValue
	};

	// For dynamic mode (viewId = 0), include the group_by_field
	if (viewId === 0 && groupByField) {
		body.group_by_field = groupByField;
	}

	const response = await apiClient.post<{
		success: boolean;
		message: string;
		record: { id: number; old_value: string | null; new_value: string };
	}>(`/views/${moduleApiName}/${viewId}/kanban/move`, body);
	return response.record;
}

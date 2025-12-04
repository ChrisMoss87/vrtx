import { apiClient } from './client';
import type { ApiClient } from './client';

export interface Module {
	id: number;
	name: string;
	singular_name: string;
	api_name: string;
	icon: string | null;
	description: string | null;
	is_active: boolean;
	display_order: number;
	settings: ModuleSettings;
	created_at: string;
	updated_at: string | null;
	blocks?: Block[];
	// Flattened fields from all blocks (convenience property, computed client-side)
	fields?: Field[];
	// Default datatable settings
	default_filters?: any[];
	default_sorting?: { id: string; desc: boolean }[];
	default_column_visibility?: Record<string, boolean>;
	default_page_size?: number;
}

export interface ModuleSettings {
	has_import: boolean;
	has_export: boolean;
	has_mass_actions: boolean;
	has_comments: boolean;
	has_attachments: boolean;
	has_activity_log: boolean;
	has_custom_views: boolean;
	record_name_field: string | null;
	additional_settings: Record<string, unknown>;
}

export interface Block {
	id: number;
	name: string;
	type: 'section' | 'tab' | 'accordion' | 'card';
	display_order: number;
	settings: Record<string, unknown>;
	fields: Field[];
}

export interface Field {
	id: number;
	label: string;
	api_name: string;
	type: string;
	description: string | null;
	help_text: string | null;
	placeholder: string | null;
	is_required: boolean;
	is_unique: boolean;
	is_searchable: boolean;
	is_filterable: boolean;
	is_sortable: boolean;
	validation_rules: string[];
	settings: FieldSettings;
	conditional_visibility: ConditionalVisibility | null;
	field_dependency: FieldDependency | null;
	formula_definition: FormulaDefinition | null;
	default_value: string | null;
	display_order: number;
	width: number;
	options: FieldOption[];
}

export interface LookupConfiguration {
	related_module_id: number;
	display_field: string;
	search_fields: string[];
	relationship_type: 'one_to_one' | 'many_to_one' | 'many_to_many';
	cascading_field?: string;
	allow_create: boolean;
	filters?: Record<string, any>;
}

export interface FieldSettings {
	min_length?: number;
	max_length?: number;
	min_value?: number;
	max_value?: number;
	pattern?: string;
	precision?: number;
	currency_code?: string;
	currency_symbol?: string;
	rows?: number;
	min_date?: string;
	max_date?: string;
	max_files?: number;
	accepted_file_types?: string[];
	related_module_id?: number;
	related_module_name?: string;
	display_field?: string;
	search_fields?: string[];
	allow_create?: boolean;
	cascade_delete?: boolean;
	relationship_type?: 'one_to_one' | 'many_to_one' | 'many_to_many';
	formula?: string;
	formula_definition?: FormulaDefinition | null;
	conditional_visibility?: ConditionalVisibility | null;
	lookup_configuration?: LookupConfiguration | null;
	field_dependency?: FieldDependency;
	allowed_file_types?: string[];
	max_file_size?: number;
	placeholder?: string;
	depends_on?: string;
	dependency_filter?: DependencyFilter;
	additional_settings?: Record<string, unknown>;
}

export interface ConditionalVisibility {
	enabled: boolean;
	operator: 'and' | 'or';
	conditions: Condition[];
}

export interface Condition {
	field: string;
	operator:
		| 'equals'
		| 'not_equals'
		| 'contains'
		| 'not_contains'
		| 'starts_with'
		| 'ends_with'
		| 'greater_than'
		| 'less_than'
		| 'greater_than_or_equal'
		| 'less_than_or_equal'
		| 'between'
		| 'in'
		| 'not_in'
		| 'is_empty'
		| 'is_not_empty'
		| 'is_checked'
		| 'is_not_checked';
	value?: unknown;
	field_value?: string;
}

export interface FieldDependency {
	depends_on: string | null;
	filter: DependencyFilter | null;
}

export interface DependencyFilter {
	field: string;
	operator: string;
	target_field: string;
}

export interface FormulaDefinition {
	formula: string;
	formula_type: 'calculation' | 'lookup' | 'date_calculation' | 'text_manipulation' | 'conditional';
	return_type: 'number' | 'text' | 'date' | 'currency' | 'boolean' | 'percentage';
	dependencies: string[];
	recalculate_on: string[];
	additional_settings?: Record<string, unknown>;
}

export interface FieldOption {
	id: number;
	label: string;
	value: string;
	color: string | null;
	is_active: boolean;
	display_order: number;
}

export interface CreateModuleRequest {
	name: string;
	singular_name: string;
	icon?: string;
	description?: string;
	is_active?: boolean;
	display_order?: number;
	settings?: Partial<ModuleSettings>;
	default_filters?: any[];
	default_sorting?: any[];
	default_column_visibility?: Record<string, boolean>;
	default_page_size?: number;
	blocks?: CreateBlockRequest[];
}

export interface CreateBlockRequest {
	name: string;
	type: 'section' | 'tab' | 'accordion' | 'card';
	display_order?: number;
	settings?: Record<string, unknown>;
	fields?: CreateFieldRequest[];
}

export interface CreateFieldRequest {
	label: string;
	api_name?: string;
	type: string;
	description?: string;
	help_text?: string;
	placeholder?: string;
	is_required?: boolean;
	is_unique?: boolean;
	is_searchable?: boolean;
	is_filterable?: boolean;
	is_sortable?: boolean;
	default_value?: string;
	display_order?: number;
	width?: number;
	validation_rules?: string[];
	settings?: Partial<FieldSettings>;
	conditional_visibility?: ConditionalVisibility;
	field_dependency?: FieldDependency;
	formula_definition?: FormulaDefinition;
	options?: CreateFieldOptionRequest[];
}

export interface CreateFieldOptionRequest {
	label: string;
	value: string;
	color?: string;
	display_order?: number;
	metadata?: Record<string, unknown>;
}

export interface UpdateModuleRequest {
	name?: string;
	singular_name?: string;
	icon?: string;
	description?: string;
	display_order?: number;
	is_active?: boolean;
	settings?: Partial<ModuleSettings>;
	// Default datatable settings
	default_filters?: any[];
	default_sorting?: { id: string; desc: boolean }[];
	default_column_visibility?: Record<string, boolean>;
	default_page_size?: number;
	// Full blocks and fields update (for complete module editing)
	blocks?: UpdateBlockRequest[];
}

export interface UpdateBlockRequest {
	id?: number; // If provided, update existing block; otherwise create new
	name: string;
	type: 'section' | 'tab' | 'accordion' | 'card';
	display_order?: number;
	settings?: Record<string, unknown>;
	fields?: UpdateFieldRequest[];
}

export interface UpdateFieldRequest {
	id?: number; // If provided, update existing field; otherwise create new
	label: string;
	api_name?: string;
	type: string;
	description?: string;
	help_text?: string;
	placeholder?: string;
	is_required?: boolean;
	is_unique?: boolean;
	is_searchable?: boolean;
	is_filterable?: boolean;
	is_sortable?: boolean;
	default_value?: string;
	display_order?: number;
	width?: number;
	validation_rules?: string[];
	settings?: Partial<FieldSettings>;
	conditional_visibility?: ConditionalVisibility;
	field_dependency?: FieldDependency;
	formula_definition?: FormulaDefinition;
	options?: UpdateFieldOptionRequest[];
}

export interface UpdateFieldOptionRequest {
	id?: number; // If provided, update existing option; otherwise create new
	label: string;
	value: string;
	color?: string;
	display_order?: number;
}

export interface ModulesResponse {
	modules: Module[];
}

export interface ModuleResponse {
	module: Module;
}

export interface CreateModuleResponse {
	message: string;
	module: Module;
}

export class ModulesApi {
	constructor(private client: ApiClient) {}

	async getAll(): Promise<Module[]> {
		const response = await this.client.get<ModulesResponse>('/modules');
		return response.modules;
	}

	async getActive(): Promise<Module[]> {
		const response = await this.client.get<ModulesResponse>('/modules/active');
		return response.modules;
	}

	async getById(id: number): Promise<Module> {
		const response = await this.client.get<ModuleResponse>(`/modules/${id}`);
		return response.module;
	}

	async getByApiName(apiName: string): Promise<Module> {
		const response = await this.client.get<ModuleResponse>(`/modules/by-api-name/${apiName}`);
		return response.module;
	}

	async create(data: CreateModuleRequest): Promise<Module> {
		const response = await this.client.post<CreateModuleResponse>('/modules', data);
		return response.module;
	}

	async update(id: number, data: UpdateModuleRequest): Promise<Module> {
		const response = await this.client.put<CreateModuleResponse>(`/modules/${id}`, data);
		return response.module;
	}

	async delete(id: number): Promise<void> {
		await this.client.delete(`/modules/${id}`);
	}

	async toggleStatus(id: number): Promise<Module> {
		const response = await this.client.post<CreateModuleResponse>(`/modules/${id}/toggle-status`);
		return response.module;
	}
}

export const modulesApi = new ModulesApi(apiClient);

// Helper function exports for convenience
export async function getModules(): Promise<Module[]> {
	return modulesApi.getAll();
}

export async function getActiveModules(): Promise<Module[]> {
	return modulesApi.getActive();
}

export async function getModuleById(id: number): Promise<Module> {
	return modulesApi.getById(id);
}

export async function getModuleByApiName(apiName: string): Promise<Module> {
	return modulesApi.getByApiName(apiName);
}

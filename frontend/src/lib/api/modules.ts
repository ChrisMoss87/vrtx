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
	is_required: boolean;
	is_unique: boolean;
	is_searchable: boolean;
	is_filterable: boolean;
	is_sortable: boolean;
	validation_rules: string[];
	settings: FieldSettings;
	default_value: string | null;
	display_order: number;
	width: number;
	options: FieldOption[];
}

export interface FieldSettings {
	min_length?: number;
	max_length?: number;
	min_value?: number;
	max_value?: number;
	pattern?: string;
	precision?: number;
	currency_code?: string;
	related_module_id?: number;
	formula?: string;
	allowed_file_types?: string[];
	max_file_size?: number;
	additional_settings: Record<string, unknown>;
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
	type: string;
	description?: string;
	help_text?: string;
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
}

export interface UpdateModuleRequest {
	name?: string;
	singular_name?: string;
	icon?: string;
	description?: string;
	display_order?: number;
	settings?: Partial<ModuleSettings>;
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

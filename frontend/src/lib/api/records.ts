import { apiClient } from './client';
import type { ApiClient } from './client';

export interface ModuleRecord {
	id: number;
	module_id: number;
	data: Record<string, unknown>;
	created_by: number | null;
	updated_by: number | null;
	created_at: string;
	updated_at: string | null;
}

export interface RecordsResponse {
	records: ModuleRecord[];
	meta: {
		total: number;
		per_page: number;
		current_page: number;
		last_page: number;
	};
}

export interface RecordResponse {
	record: ModuleRecord;
}

export interface CreateRecordResponse {
	message: string;
	record: ModuleRecord;
}

export interface FilterOperator {
	operator: 'equals' | 'not_equals' | 'contains' | 'not_contains' | 'starts_with' | 'ends_with'
		| 'greater_than' | 'less_than' | 'greater_than_or_equal' | 'less_than_or_equal'
		| 'between' | 'in' | 'not_in' | 'is_null' | 'is_not_null'
		| 'date_equals' | 'date_before' | 'date_after' | 'date_between' | 'search';
	value?: unknown;
	fields?: string[];
	min?: number;
	max?: number;
	start?: string;
	end?: string;
}

export interface GetRecordsOptions {
	page?: number;
	per_page?: number;
	filters?: Record<string, FilterOperator>;
	sort?: Record<string, 'asc' | 'desc'>;
	search?: string;
	search_fields?: string[];
}

export class RecordsApi {
	constructor(private client: ApiClient) {}

	async getAll(moduleApiName: string, options: GetRecordsOptions = {}): Promise<RecordsResponse> {
		const params: Record<string, string> = {};

		if (options.page) params.page = String(options.page);
		if (options.per_page) params.per_page = String(options.per_page);
		if (options.filters) params.filters = JSON.stringify(options.filters);
		if (options.sort) params.sort = JSON.stringify(options.sort);
		if (options.search) params.search = options.search;
		if (options.search_fields) params.search_fields = JSON.stringify(options.search_fields);

		return this.client.get<RecordsResponse>(`/records/${moduleApiName}`, params);
	}

	async getById(moduleApiName: string, recordId: number): Promise<ModuleRecord> {
		const response = await this.client.get<RecordResponse>(`/records/${moduleApiName}/${recordId}`);
		return response.record;
	}

	async create(moduleApiName: string, data: Record<string, unknown>): Promise<ModuleRecord> {
		const response = await this.client.post<CreateRecordResponse>(`/records/${moduleApiName}`, { data });
		return response.record;
	}

	async update(moduleApiName: string, recordId: number, data: Record<string, unknown>): Promise<ModuleRecord> {
		const response = await this.client.put<CreateRecordResponse>(`/records/${moduleApiName}/${recordId}`, { data });
		return response.record;
	}

	async delete(moduleApiName: string, recordId: number): Promise<void> {
		await this.client.delete(`/records/${moduleApiName}/${recordId}`);
	}

	async bulkDelete(moduleApiName: string, recordIds: number[]): Promise<{ message: string; deleted_count: number }> {
		return this.client.post(`/records/${moduleApiName}/bulk-delete`, { record_ids: recordIds });
	}
}

export const recordsApi = new RecordsApi(apiClient);

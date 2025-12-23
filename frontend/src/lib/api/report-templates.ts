import { apiClient } from './client';
import type { ReportType, ChartType, ReportFilter, ReportGrouping, ReportAggregation, ReportSorting, ReportDateRange, Report } from './reports';

export interface ReportTemplate {
	id: number;
	name: string;
	description: string | null;
	user_id: number;
	module_id: number | null;
	type: ReportType;
	chart_type: ChartType | null;
	is_public: boolean;
	config: Record<string, any>;
	filters: ReportFilter[];
	grouping: ReportGrouping[];
	aggregations: ReportAggregation[];
	sorting: ReportSorting[];
	date_range: ReportDateRange | null;
	created_at: string;
	updated_at: string;
	module?: {
		id: number;
		name: string;
		api_name: string;
	};
	user?: {
		id: number;
		name: string;
	};
}

export interface CreateTemplateRequest {
	name: string;
	description?: string;
	module_id?: number;
	type: ReportType;
	chart_type?: ChartType;
	is_public?: boolean;
	config?: Record<string, any>;
	filters?: ReportFilter[];
	grouping?: ReportGrouping[];
	aggregations?: ReportAggregation[];
	sorting?: ReportSorting[];
	date_range?: ReportDateRange;
}

export interface UpdateTemplateRequest extends Partial<CreateTemplateRequest> {}

export interface ApplyTemplateRequest {
	name: string;
	description?: string;
	is_public?: boolean;
}

export interface CreateFromReportRequest {
	name: string;
	description?: string;
	is_public?: boolean;
}

export const reportTemplatesApi = {
	/**
	 * List templates
	 */
	async list(options?: {
		module_id?: number;
		type?: ReportType;
		search?: string;
		page?: number;
		per_page?: number;
	}): Promise<{
		data: ReportTemplate[];
		meta: { current_page: number; last_page: number; per_page: number; total: number };
	}> {
		const response = await apiClient.get<{
			data: ReportTemplate[];
			meta: { current_page: number; last_page: number; per_page: number; total: number };
		}>('/report-templates', { params: options });
		return response;
	},

	/**
	 * Get a single template
	 */
	async get(id: number): Promise<ReportTemplate> {
		const response = await apiClient.get<{ data: ReportTemplate }>(`/report-templates/${id}`);
		return response.data;
	},

	/**
	 * Create a new template
	 */
	async create(data: CreateTemplateRequest): Promise<ReportTemplate> {
		const response = await apiClient.post<{ data: ReportTemplate }>('/report-templates', data);
		return response.data;
	},

	/**
	 * Update a template
	 */
	async update(id: number, data: UpdateTemplateRequest): Promise<ReportTemplate> {
		const response = await apiClient.put<{ data: ReportTemplate }>(`/report-templates/${id}`, data);
		return response.data;
	},

	/**
	 * Delete a template
	 */
	async delete(id: number): Promise<void> {
		await apiClient.delete(`/report-templates/${id}`);
	},

	/**
	 * Apply a template to create a new report
	 */
	async apply(id: number, data: ApplyTemplateRequest): Promise<Report> {
		const response = await apiClient.post<{ data: Report }>(`/report-templates/${id}/apply`, data);
		return response.data;
	},

	/**
	 * Create a template from an existing report
	 */
	async createFromReport(reportId: number, data: CreateFromReportRequest): Promise<ReportTemplate> {
		const response = await apiClient.post<{ data: ReportTemplate }>(`/report-templates/from-report/${reportId}`, data);
		return response.data;
	}
};

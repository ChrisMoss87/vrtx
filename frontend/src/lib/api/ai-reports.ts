import { apiClient } from './client';

export interface GeneratedReportConfig {
	name: string;
	description?: string;
	module_api_name: string;
	type: 'table' | 'chart' | 'summary' | 'matrix' | 'pivot';
	chart_type?: string;
	filters: Array<{
		field: string;
		operator: string;
		value: unknown;
	}>;
	grouping: Array<{
		field: string;
		sort?: 'asc' | 'desc';
	}>;
	aggregations: Array<{
		field: string;
		function: string;
		alias?: string;
	}>;
	date_range?: {
		preset?: string;
		field?: string;
		start?: string;
		end?: string;
	};
	ai_generated?: boolean;
	ai_enabled?: boolean;
	message?: string;
}

export interface ReportSuggestion {
	type: 'filter' | 'grouping' | 'aggregation' | 'visualization' | 'date_range';
	title: string;
	description: string;
	config_change: Record<string, unknown>;
}

export interface ParsedFilter {
	field: string;
	operator: string;
	value: unknown;
	ai_enabled?: boolean;
	message?: string;
}

export interface AiReportStatus {
	available: boolean;
	message: string;
}

/**
 * AI Report Generation API
 */
export const aiReportsApi = {
	/**
	 * Check if AI report generation is available
	 */
	async getStatus(): Promise<AiReportStatus> {
		const response = await apiClient.get<{ data: AiReportStatus }>('/ai/reports/status');
		return response.data;
	},

	/**
	 * Generate a report configuration from natural language
	 */
	async generate(prompt: string): Promise<GeneratedReportConfig> {
		const response = await apiClient.post<{ data: GeneratedReportConfig; message: string }>(
			'/ai/reports/generate',
			{ prompt }
		);
		return response.data;
	},

	/**
	 * Create a report from natural language and optionally save it
	 */
	async createReport(prompt: string, save: boolean = false): Promise<GeneratedReportConfig> {
		const response = await apiClient.post<{ data: GeneratedReportConfig; message: string }>(
			'/ai/reports/create',
			{ prompt, save }
		);
		return response.data;
	},

	/**
	 * Get suggestions for improving a report
	 */
	async getSuggestions(reportId: number): Promise<ReportSuggestion[]> {
		const response = await apiClient.get<{ data: ReportSuggestion[] }>(
			`/ai/reports/suggest/${reportId}`
		);
		return response.data;
	},

	/**
	 * Parse a natural language filter condition
	 */
	async parseFilter(condition: string, moduleApiName: string): Promise<ParsedFilter> {
		const response = await apiClient.post<{ data: ParsedFilter }>('/ai/reports/parse-filter', {
			condition,
			module_api_name: moduleApiName
		});
		return response.data;
	}
};

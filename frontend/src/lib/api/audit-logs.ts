import { apiClient } from './client';

export interface AuditLog {
	id: number;
	user_id: number | null;
	event: 'created' | 'updated' | 'deleted' | 'restored' | 'force_deleted' | 'attached' | 'detached' | 'synced';
	auditable_type: string;
	auditable_id: number;
	old_values: Record<string, unknown> | null;
	new_values: Record<string, unknown> | null;
	url: string | null;
	ip_address: string | null;
	user_agent: string | null;
	tags: string[] | null;
	batch_id: string | null;
	created_at: string;
	updated_at: string;
	user?: {
		id: number;
		name: string;
		email: string;
	};
}

export interface AuditLogListParams {
	auditable_type?: string;
	auditable_id?: number;
	event?: string;
	user_id?: number;
	from_date?: string;
	to_date?: string;
	per_page?: number;
	page?: number;
}

export interface AuditLogListResponse {
	data: AuditLog[];
	meta?: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}

export const auditLogsApi = {
	async list(params: AuditLogListParams = {}): Promise<AuditLogListResponse> {
		const searchParams = new URLSearchParams();
		Object.entries(params).forEach(([key, value]) => {
			if (value !== undefined && value !== null) {
				searchParams.append(key, String(value));
			}
		});
		const queryString = searchParams.toString();
		const url = queryString ? `/audit-logs?${queryString}` : '/audit-logs';
		return apiClient.get<AuditLogListResponse>(url);
	},

	async forRecord(auditableType: string, auditableId: number): Promise<AuditLogListResponse> {
		const params = new URLSearchParams({
			auditable_type: auditableType,
			auditable_id: String(auditableId)
		});
		return apiClient.get<AuditLogListResponse>(`/audit-logs/for-record?${params.toString()}`);
	},

	async get(id: number): Promise<{ data: AuditLog }> {
		return apiClient.get<{ data: AuditLog }>(`/audit-logs/${id}`);
	},

	async summary(params?: { from_date?: string; to_date?: string }): Promise<{ data: Record<string, number> }> {
		const searchParams = new URLSearchParams();
		if (params) {
			Object.entries(params).forEach(([key, value]) => {
				if (value) searchParams.append(key, value);
			});
		}
		const queryString = searchParams.toString();
		const url = queryString ? `/audit-logs/summary?${queryString}` : '/audit-logs/summary';
		return apiClient.get<{ data: Record<string, number> }>(url);
	},

	async forUser(userId: number): Promise<AuditLogListResponse> {
		return apiClient.get<AuditLogListResponse>(`/audit-logs/user/${userId}`);
	},

	async compare(log1Id: number, log2Id: number): Promise<{ data: { log1: AuditLog; log2: AuditLog; diff: Record<string, { old: unknown; new: unknown }> } }> {
		return apiClient.get(`/audit-logs/compare/${log1Id}/${log2Id}`);
	}
};

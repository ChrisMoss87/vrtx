import { apiClient } from './client';

// API Keys
export interface ApiKey {
	id: number;
	name: string;
	prefix: string;
	description: string | null;
	scopes: string[];
	allowed_ips: string[] | null;
	is_active: boolean;
	rate_limit: number | null;
	expires_at: string | null;
	last_used_at: string | null;
	last_used_ip: string | null;
	request_count: number;
	created_at: string;
	updated_at: string;
}

export interface CreateApiKeyRequest {
	name: string;
	description?: string;
	scopes: string[];
	allowed_ips?: string[];
	expires_at?: string;
	rate_limit?: number;
}

export interface UpdateApiKeyRequest {
	name?: string;
	description?: string;
	scopes?: string[];
	allowed_ips?: string[];
	is_active?: boolean;
	rate_limit?: number;
}

export interface ApiKeyResponse {
	api_key: ApiKey;
	secret?: string;
	warning?: string;
}

export interface ApiKeysListResponse {
	data: ApiKey[];
	available_scopes: Record<string, string>;
}

export const apiKeys = {
	list: async (includeInactive = true): Promise<ApiKeysListResponse> => {
		return apiClient.get<ApiKeysListResponse>(`/api-keys?include_inactive=${includeInactive}`);
	},

	create: async (data: CreateApiKeyRequest): Promise<ApiKeyResponse & { message: string }> => {
		return apiClient.post<ApiKeyResponse & { message: string }>('/api-keys', data);
	},

	get: async (id: number): Promise<{ data: ApiKey; usage_stats: unknown }> => {
		return apiClient.get<{ data: ApiKey; usage_stats: unknown }>(`/api-keys/${id}`);
	},

	update: async (id: number, data: UpdateApiKeyRequest): Promise<{ message: string; api_key: ApiKey }> => {
		return apiClient.put<{ message: string; api_key: ApiKey }>(`/api-keys/${id}`, data);
	},

	revoke: async (id: number): Promise<{ message: string }> => {
		return apiClient.post<{ message: string }>(`/api-keys/${id}/revoke`);
	},

	regenerate: async (id: number): Promise<ApiKeyResponse & { message: string }> => {
		return apiClient.post<ApiKeyResponse & { message: string }>(`/api-keys/${id}/regenerate`);
	},

	delete: async (id: number): Promise<{ message: string }> => {
		return apiClient.delete<{ message: string }>(`/api-keys/${id}`);
	},

	logs: async (id: number, params?: { status?: string; per_page?: number; page?: number }) => {
		const searchParams = new URLSearchParams();
		if (params?.status) searchParams.set('status', params.status);
		if (params?.per_page) searchParams.set('per_page', params.per_page.toString());
		if (params?.page) searchParams.set('page', params.page.toString());
		return apiClient.get(`/api-keys/${id}/logs?${searchParams}`);
	}
};

// Webhooks (Outgoing)
export interface Webhook {
	id: number;
	name: string;
	description: string | null;
	url: string;
	events: string[];
	module: { id: number; name: string; api_name: string } | null;
	headers: Record<string, string> | null;
	is_active: boolean;
	verify_ssl: boolean;
	timeout: number;
	retry_count: number;
	retry_delay: number;
	last_triggered_at: string | null;
	last_status: string | null;
	success_count: number;
	failure_count: number;
	created_at: string;
	updated_at: string;
}

export interface WebhookDelivery {
	id: number;
	event: string;
	status: 'pending' | 'success' | 'failed';
	attempts: number;
	response_code: number | null;
	response_time_ms: number | null;
	error_message: string | null;
	delivered_at: string | null;
	next_retry_at: string | null;
	created_at: string;
	payload?: unknown;
	response_body?: string;
}

export interface CreateWebhookRequest {
	name: string;
	description?: string;
	url: string;
	events: string[];
	module_id?: number;
	headers?: Record<string, string>;
	verify_ssl?: boolean;
	timeout?: number;
	retry_count?: number;
	retry_delay?: number;
}

export interface UpdateWebhookRequest {
	name?: string;
	description?: string;
	url?: string;
	events?: string[];
	module_id?: number;
	headers?: Record<string, string>;
	is_active?: boolean;
	verify_ssl?: boolean;
	timeout?: number;
	retry_count?: number;
	retry_delay?: number;
}

export interface WebhooksListResponse {
	data: Webhook[];
	available_events: string[];
}

export const webhooks = {
	list: async (activeOnly = false): Promise<WebhooksListResponse> => {
		return apiClient.get<WebhooksListResponse>(`/webhooks?active_only=${activeOnly}`);
	},

	create: async (data: CreateWebhookRequest): Promise<{ message: string; webhook: Webhook; secret: string; warning: string }> => {
		return apiClient.post<{ message: string; webhook: Webhook; secret: string; warning: string }>('/webhooks', data);
	},

	get: async (id: number): Promise<{ webhook: Webhook; recent_deliveries: WebhookDelivery[]; delivery_stats: Record<string, number> }> => {
		return apiClient.get<{ webhook: Webhook; recent_deliveries: WebhookDelivery[]; delivery_stats: Record<string, number> }>(`/webhooks/${id}`);
	},

	update: async (id: number, data: UpdateWebhookRequest): Promise<{ message: string; webhook: Webhook }> => {
		return apiClient.put<{ message: string; webhook: Webhook }>(`/webhooks/${id}`, data);
	},

	delete: async (id: number): Promise<{ message: string }> => {
		return apiClient.delete<{ message: string }>(`/webhooks/${id}`);
	},

	rotateSecret: async (id: number): Promise<{ message: string; secret: string; warning: string }> => {
		return apiClient.post<{ message: string; secret: string; warning: string }>(`/webhooks/${id}/rotate-secret`);
	},

	test: async (id: number): Promise<{ message: string; delivery_id: number }> => {
		return apiClient.post<{ message: string; delivery_id: number }>(`/webhooks/${id}/test`);
	},

	deliveries: async (id: number, params?: { status?: string; per_page?: number; page?: number }) => {
		const searchParams = new URLSearchParams();
		if (params?.status) searchParams.set('status', params.status);
		if (params?.per_page) searchParams.set('per_page', params.per_page.toString());
		if (params?.page) searchParams.set('page', params.page.toString());
		return apiClient.get(`/webhooks/${id}/deliveries?${searchParams}`);
	},

	getDelivery: async (webhookId: number, deliveryId: number): Promise<{ delivery: WebhookDelivery }> => {
		return apiClient.get<{ delivery: WebhookDelivery }>(`/webhooks/${webhookId}/deliveries/${deliveryId}`);
	},

	retryDelivery: async (webhookId: number, deliveryId: number): Promise<{ message: string }> => {
		return apiClient.post<{ message: string }>(`/webhooks/${webhookId}/deliveries/${deliveryId}/retry`);
	}
};

// Incoming Webhooks
export interface IncomingWebhook {
	id: number;
	name: string;
	description: string | null;
	module: { id: number; name: string; api_name: string } | null;
	field_mapping: Record<string, string>;
	action: 'create' | 'update' | 'upsert';
	upsert_field: string | null;
	is_active: boolean;
	url: string;
	received_count: number;
	last_received_at: string | null;
	created_at: string;
	updated_at: string;
	token_prefix?: string;
}

export interface IncomingWebhookLog {
	id: number;
	status: 'success' | 'failed' | 'invalid';
	record_id: number | null;
	error_message: string | null;
	ip_address: string;
	created_at: string;
}

export interface CreateIncomingWebhookRequest {
	name: string;
	description?: string;
	module_id: number;
	field_mapping: Record<string, string>;
	action: 'create' | 'update' | 'upsert';
	upsert_field?: string;
}

export interface UpdateIncomingWebhookRequest {
	name?: string;
	description?: string;
	module_id?: number;
	field_mapping?: Record<string, string>;
	action?: 'create' | 'update' | 'upsert';
	upsert_field?: string;
	is_active?: boolean;
}

export interface IncomingWebhooksListResponse {
	data: IncomingWebhook[];
	available_actions: Record<string, string>;
}

export const incomingWebhooks = {
	list: async (activeOnly = false): Promise<IncomingWebhooksListResponse> => {
		return apiClient.get<IncomingWebhooksListResponse>(`/incoming-webhooks?active_only=${activeOnly}`);
	},

	create: async (data: CreateIncomingWebhookRequest): Promise<{ message: string; webhook: IncomingWebhook; url: string; token: string; warning: string }> => {
		return apiClient.post<{ message: string; webhook: IncomingWebhook; url: string; token: string; warning: string }>('/incoming-webhooks', data);
	},

	get: async (id: number): Promise<{ webhook: IncomingWebhook; recent_logs: IncomingWebhookLog[]; stats: Record<string, number> }> => {
		return apiClient.get<{ webhook: IncomingWebhook; recent_logs: IncomingWebhookLog[]; stats: Record<string, number> }>(`/incoming-webhooks/${id}`);
	},

	update: async (id: number, data: UpdateIncomingWebhookRequest): Promise<{ message: string; webhook: IncomingWebhook }> => {
		return apiClient.put<{ message: string; webhook: IncomingWebhook }>(`/incoming-webhooks/${id}`, data);
	},

	delete: async (id: number): Promise<{ message: string }> => {
		return apiClient.delete<{ message: string }>(`/incoming-webhooks/${id}`);
	},

	regenerateToken: async (id: number): Promise<{ message: string; url: string; token: string; warning: string }> => {
		return apiClient.post<{ message: string; url: string; token: string; warning: string }>(`/incoming-webhooks/${id}/regenerate-token`);
	},

	logs: async (id: number, params?: { status?: string; per_page?: number; page?: number }) => {
		const searchParams = new URLSearchParams();
		if (params?.status) searchParams.set('status', params.status);
		if (params?.per_page) searchParams.set('per_page', params.per_page.toString());
		if (params?.page) searchParams.set('page', params.page.toString());
		return apiClient.get(`/incoming-webhooks/${id}/logs?${searchParams}`);
	}
};

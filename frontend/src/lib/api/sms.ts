import { apiClient } from './client';

// Types
export interface SmsConnection {
	id: number;
	name: string;
	provider: 'twilio' | 'vonage' | 'messagebird' | 'plivo';
	phone_number: string;
	is_active: boolean;
	is_verified: boolean;
	capabilities: string[] | null;
	settings: Record<string, unknown> | null;
	daily_limit: number;
	monthly_limit: number;
	last_used_at: string | null;
	messages_count?: number;
	campaigns_count?: number;
	created_at: string;
}

export interface SmsTemplate {
	id: number;
	name: string;
	content: string;
	category: 'marketing' | 'transactional' | 'support' | null;
	is_active: boolean;
	merge_fields: string[] | null;
	character_count: number;
	segment_count: number;
	created_by: number | null;
	usage_count: number;
	last_used_at: string | null;
	creator?: { id: number; name: string } | null;
	messages_count?: number;
	created_at: string;
}

export interface SmsMessage {
	id: number;
	connection_id: number;
	template_id: number | null;
	direction: 'inbound' | 'outbound';
	from_number: string;
	to_number: string;
	content: string;
	status: 'pending' | 'queued' | 'sent' | 'delivered' | 'failed' | 'undelivered';
	provider_message_id: string | null;
	error_code: string | null;
	error_message: string | null;
	segment_count: number;
	cost: number | null;
	module_record_id: number | null;
	module_api_name: string | null;
	campaign_id: number | null;
	sent_by: number | null;
	sent_at: string | null;
	delivered_at: string | null;
	connection?: { id: number; name: string; phone_number: string };
	template?: { id: number; name: string } | null;
	sender?: { id: number; name: string } | null;
	created_at: string;
}

export interface SmsCampaign {
	id: number;
	name: string;
	description: string | null;
	connection_id: number;
	template_id: number | null;
	message_content: string | null;
	status: 'draft' | 'scheduled' | 'sending' | 'sent' | 'paused' | 'cancelled';
	target_module: string | null;
	target_filters: Record<string, unknown>[] | null;
	phone_field: string;
	scheduled_at: string | null;
	started_at: string | null;
	completed_at: string | null;
	total_recipients: number;
	sent_count: number;
	delivered_count: number;
	failed_count: number;
	opted_out_count: number;
	reply_count: number;
	created_by: number | null;
	connection?: { id: number; name: string; phone_number?: string };
	template?: { id: number; name: string } | null;
	creator?: { id: number; name: string } | null;
	created_at: string;
}

export interface SmsOptOut {
	id: number;
	phone_number: string;
	type: 'all' | 'marketing' | 'transactional';
	reason: string | null;
	connection_id: number | null;
	opted_out_at: string;
	opted_in_at: string | null;
	is_active: boolean;
	connection?: { id: number; name: string } | null;
	created_at: string;
}

export interface SmsCampaignStats {
	total_recipients: number;
	sent_count: number;
	delivered_count: number;
	failed_count: number;
	opted_out_count: number;
	reply_count: number;
	delivery_rate: number;
	failure_rate: number;
	progress: number;
}

// Connection API
export const smsConnectionsApi = {
	async list(): Promise<SmsConnection[]> {
		const response = await apiClient.get<{ data: SmsConnection[] }>('/sms/connections');
		return response.data;
	},

	async create(data: {
		name: string;
		provider: 'twilio' | 'vonage' | 'messagebird' | 'plivo';
		phone_number: string;
		account_sid: string;
		auth_token: string;
		messaging_service_sid?: string;
		capabilities?: string[];
		settings?: Record<string, unknown>;
		daily_limit?: number;
		monthly_limit?: number;
	}): Promise<SmsConnection> {
		const response = await apiClient.post<{ data: SmsConnection }>('/sms/connections', data);
		return response.data;
	},

	async get(id: number): Promise<SmsConnection> {
		const response = await apiClient.get<{ data: SmsConnection }>(`/sms/connections/${id}`);
		return response.data;
	},

	async update(id: number, data: Partial<{
		name: string;
		provider: 'twilio' | 'vonage' | 'messagebird' | 'plivo';
		phone_number: string;
		account_sid: string;
		auth_token: string;
		messaging_service_sid: string;
		is_active: boolean;
		capabilities: string[];
		settings: Record<string, unknown>;
		daily_limit: number;
		monthly_limit: number;
	}>): Promise<SmsConnection> {
		const response = await apiClient.put<{ data: SmsConnection }>(`/sms/connections/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/sms/connections/${id}`);
	},

	async verify(id: number): Promise<{ data: SmsConnection; verification: { success: boolean; account_name?: string; error_message?: string } }> {
		const response = await apiClient.post<{ data: SmsConnection; verification: { success: boolean; account_name?: string; error_message?: string } }>(`/sms/connections/${id}/verify`);
		return response;
	},

	async getStats(id: number): Promise<{
		today_count: number;
		month_count: number;
		daily_limit: number;
		monthly_limit: number;
		daily_remaining: number;
		monthly_remaining: number;
	}> {
		const response = await apiClient.get<{ data: {
			today_count: number;
			month_count: number;
			daily_limit: number;
			monthly_limit: number;
			daily_remaining: number;
			monthly_remaining: number;
		} }>(`/sms/connections/${id}/stats`);
		return response.data;
	}
};

// Template API
export const smsTemplatesApi = {
	async list(params?: { category?: string; active_only?: boolean }): Promise<SmsTemplate[]> {
		const response = await apiClient.get<{ data: SmsTemplate[] }>('/sms/templates', { params });
		return response.data;
	},

	async create(data: {
		name: string;
		content: string;
		category?: 'marketing' | 'transactional' | 'support';
		is_active?: boolean;
	}): Promise<SmsTemplate> {
		const response = await apiClient.post<{ data: SmsTemplate }>('/sms/templates', data);
		return response.data;
	},

	async get(id: number): Promise<SmsTemplate> {
		const response = await apiClient.get<{ data: SmsTemplate }>(`/sms/templates/${id}`);
		return response.data;
	},

	async update(id: number, data: Partial<{
		name: string;
		content: string;
		category: 'marketing' | 'transactional' | 'support';
		is_active: boolean;
	}>): Promise<SmsTemplate> {
		const response = await apiClient.put<{ data: SmsTemplate }>(`/sms/templates/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/sms/templates/${id}`);
	},

	async preview(id: number, sampleData?: Record<string, string>): Promise<{
		original: string;
		rendered: string;
		character_count: number;
		segment_count: number;
		merge_fields: string[];
	}> {
		const response = await apiClient.post<{ data: {
			original: string;
			rendered: string;
			character_count: number;
			segment_count: number;
			merge_fields: string[];
		} }>(`/sms/templates/${id}/preview`, { sample_data: sampleData });
		return response.data;
	},

	async duplicate(id: number): Promise<SmsTemplate> {
		const response = await apiClient.post<{ data: SmsTemplate }>(`/sms/templates/${id}/duplicate`);
		return response.data;
	}
};

// Message API
export const smsMessagesApi = {
	async list(params?: {
		connection_id?: number;
		direction?: 'inbound' | 'outbound';
		status?: string;
		phone?: string;
		module_api_name?: string;
		module_record_id?: number;
		per_page?: number;
		page?: number;
	}): Promise<{ data: SmsMessage[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
		const response = await apiClient.get<{ data: SmsMessage[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>('/sms/messages', { params });
		return response;
	},

	async send(data: {
		connection_id: number;
		to: string;
		content?: string;
		template_id?: number;
		merge_data?: Record<string, string>;
		module_record_id?: number;
		module_api_name?: string;
	}): Promise<SmsMessage> {
		const response = await apiClient.post<{ data: SmsMessage }>('/sms/messages', data);
		return response.data;
	},

	async get(id: number): Promise<SmsMessage> {
		const response = await apiClient.get<{ data: SmsMessage }>(`/sms/messages/${id}`);
		return response.data;
	},

	async getConversation(phone: string, connectionId: number, limit?: number): Promise<SmsMessage[]> {
		const response = await apiClient.get<{ data: SmsMessage[] }>('/sms/messages/conversation', {
			params: { phone, connection_id: connectionId, limit }
		});
		return response.data;
	},

	async getForRecord(moduleApiName: string, recordId: number): Promise<SmsMessage[]> {
		const response = await apiClient.get<{ data: SmsMessage[] }>('/sms/messages/for-record', {
			params: { module_api_name: moduleApiName, module_record_id: recordId }
		});
		return response.data;
	}
};

// Campaign API
export const smsCampaignsApi = {
	async list(params?: { status?: string; per_page?: number; page?: number }): Promise<{ data: SmsCampaign[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
		const response = await apiClient.get<{ data: SmsCampaign[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>('/sms/campaigns', { params });
		return response;
	},

	async create(data: {
		name: string;
		description?: string;
		connection_id: number;
		template_id?: number;
		message_content?: string;
		target_module?: string;
		target_filters?: Record<string, unknown>[];
		phone_field?: string;
		scheduled_at?: string;
	}): Promise<SmsCampaign> {
		const response = await apiClient.post<{ data: SmsCampaign }>('/sms/campaigns', data);
		return response.data;
	},

	async get(id: number): Promise<{ data: SmsCampaign; stats: SmsCampaignStats }> {
		const response = await apiClient.get<{ data: SmsCampaign; stats: SmsCampaignStats }>(`/sms/campaigns/${id}`);
		return response;
	},

	async update(id: number, data: Partial<{
		name: string;
		description: string;
		connection_id: number;
		template_id: number;
		message_content: string;
		target_module: string;
		target_filters: Record<string, unknown>[];
		phone_field: string;
		scheduled_at: string;
	}>): Promise<SmsCampaign> {
		const response = await apiClient.put<{ data: SmsCampaign }>(`/sms/campaigns/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/sms/campaigns/${id}`);
	},

	async schedule(id: number, scheduledAt: string): Promise<SmsCampaign> {
		const response = await apiClient.post<{ data: SmsCampaign }>(`/sms/campaigns/${id}/schedule`, { scheduled_at: scheduledAt });
		return response.data;
	},

	async sendNow(id: number): Promise<SmsCampaign> {
		const response = await apiClient.post<{ data: SmsCampaign }>(`/sms/campaigns/${id}/send-now`);
		return response.data;
	},

	async pause(id: number): Promise<SmsCampaign> {
		const response = await apiClient.post<{ data: SmsCampaign }>(`/sms/campaigns/${id}/pause`);
		return response.data;
	},

	async cancel(id: number): Promise<SmsCampaign> {
		const response = await apiClient.post<{ data: SmsCampaign }>(`/sms/campaigns/${id}/cancel`);
		return response.data;
	},

	async preview(id: number, _sampleData?: Record<string, string>): Promise<{
		original: string;
		rendered: string;
		character_count: number;
		segment_count: number;
		merge_fields: string[];
	}> {
		const response = await apiClient.get<{ data: {
			original: string;
			rendered: string;
			character_count: number;
			segment_count: number;
			merge_fields: string[];
		} }>(`/sms/campaigns/${id}/preview`);
		return response.data;
	},

	async getRecipients(id: number): Promise<{
		count: number;
		sample: { id: number; name: string; phone: string | null }[];
	}> {
		const response = await apiClient.get<{ data: {
			count: number;
			sample: { id: number; name: string; phone: string | null }[];
		} }>(`/sms/campaigns/${id}/recipients`);
		return response.data;
	},

	async getStats(id: number): Promise<SmsCampaignStats> {
		const response = await apiClient.get<{ data: SmsCampaignStats }>(`/sms/campaigns/${id}/stats`);
		return response.data;
	}
};

// Opt-out API
export const smsOptOutsApi = {
	async list(params?: {
		active_only?: boolean;
		type?: 'all' | 'marketing' | 'transactional';
		search?: string;
		per_page?: number;
		page?: number;
	}): Promise<{ data: SmsOptOut[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
		const response = await apiClient.get<{ data: SmsOptOut[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>('/sms/opt-outs', { params });
		return response;
	},

	async optOut(data: {
		phone_number: string;
		type: 'all' | 'marketing' | 'transactional';
		reason?: string;
		connection_id?: number;
	}): Promise<SmsOptOut> {
		const response = await apiClient.post<{ data: SmsOptOut }>('/sms/opt-outs', data);
		return response.data;
	},

	async check(phoneNumber: string, type?: 'all' | 'marketing' | 'transactional'): Promise<{ phone_number: string; is_opted_out: boolean }> {
		const response = await apiClient.get<{ data: { phone_number: string; is_opted_out: boolean } }>('/sms/opt-outs/check', {
			params: { phone_number: phoneNumber, type }
		});
		return response.data;
	},

	async optIn(phoneNumber: string, type?: 'all' | 'marketing' | 'transactional'): Promise<{ phone_number: string; opted_in: boolean }> {
		const response = await apiClient.post<{ data: { phone_number: string; opted_in: boolean } }>('/sms/opt-outs/opt-in', {
			phone_number: phoneNumber,
			type
		});
		return response.data;
	},

	async bulkOptOut(phoneNumbers: string[], type: 'all' | 'marketing' | 'transactional', reason?: string): Promise<{ processed: number }> {
		const response = await apiClient.post<{ data: { processed: number } }>('/sms/opt-outs/bulk', {
			phone_numbers: phoneNumbers,
			type,
			reason
		});
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/sms/opt-outs/${id}`);
	}
};

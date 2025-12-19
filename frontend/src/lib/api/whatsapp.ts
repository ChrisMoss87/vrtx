import { apiClient } from './client';

// Types
export interface WhatsappConnection {
	id: number;
	name: string;
	phone_number_id: string;
	waba_id: string | null;
	display_phone_number: string | null;
	verified_name: string | null;
	quality_rating: 'GREEN' | 'YELLOW' | 'RED' | null;
	messaging_limit: string | null;
	webhook_verify_token: string;
	is_active: boolean;
	is_verified: boolean;
	settings: Record<string, unknown> | null;
	last_synced_at: string | null;
	conversations_count?: number;
	templates_count?: number;
	created_at: string;
}

export interface WhatsappTemplate {
	id: number;
	connection_id: number;
	template_id: string | null;
	name: string;
	language: string;
	category: 'UTILITY' | 'MARKETING' | 'AUTHENTICATION';
	status: 'PENDING' | 'APPROVED' | 'REJECTED' | 'PAUSED' | 'DISABLED';
	rejection_reason: string | null;
	components: TemplateComponent[];
	example: Record<string, unknown> | null;
	created_by: number | null;
	submitted_at: string | null;
	approved_at: string | null;
	connection?: { id: number; name: string };
	creator?: { id: number; name: string };
	messages_count?: number;
	created_at: string;
}

export interface TemplateComponent {
	type: 'HEADER' | 'BODY' | 'FOOTER' | 'BUTTONS';
	format?: 'TEXT' | 'IMAGE' | 'VIDEO' | 'DOCUMENT';
	text?: string;
	buttons?: TemplateButton[];
}

export interface TemplateButton {
	type: 'QUICK_REPLY' | 'URL' | 'PHONE_NUMBER';
	text: string;
	url?: string;
	phone_number?: string;
}

export interface WhatsappConversation {
	id: number;
	connection_id: number;
	contact_wa_id: string;
	contact_phone: string;
	contact_name: string | null;
	module_record_id: number | null;
	module_api_name: string | null;
	status: 'open' | 'pending' | 'closed';
	assigned_to: number | null;
	is_resolved: boolean;
	last_message_at: string | null;
	last_incoming_at: string | null;
	last_outgoing_at: string | null;
	unread_count: number;
	metadata: Record<string, unknown> | null;
	display_name: string;
	connection?: { id: number; name: string; display_phone_number: string | null };
	assigned_user?: { id: number; name: string } | null;
	messages?: WhatsappMessage[];
	messages_count?: number;
	created_at: string;
}

export interface WhatsappMessage {
	id: number;
	conversation_id: number;
	connection_id: number;
	wa_message_id: string | null;
	direction: 'inbound' | 'outbound';
	type: 'text' | 'template' | 'image' | 'video' | 'audio' | 'document' | 'sticker' | 'location' | 'contacts' | 'interactive' | 'reaction' | 'button';
	content: string | null;
	media: {
		id?: string;
		url?: string;
		mime_type?: string;
		filename?: string;
	} | null;
	template_id: number | null;
	template_params: Record<string, unknown> | null;
	status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
	error_code: string | null;
	error_message: string | null;
	sent_by: number | null;
	context_message_id: string | null;
	sent_at: string | null;
	delivered_at: string | null;
	read_at: string | null;
	sender?: { id: number; name: string } | null;
	template?: { id: number; name: string } | null;
	created_at: string;
}

// Connection API
export const whatsappConnectionsApi = {
	async list(): Promise<WhatsappConnection[]> {
		const response = await apiClient.get<{ data: WhatsappConnection[] }>('/whatsapp/connections');
		return response.data;
	},

	async create(data: {
		name: string;
		phone_number_id: string;
		waba_id?: string;
		access_token: string;
		display_phone_number?: string;
		settings?: Record<string, unknown>;
	}): Promise<WhatsappConnection> {
		const response = await apiClient.post<{ data: WhatsappConnection }>('/whatsapp/connections', data);
		return response.data;
	},

	async get(id: number): Promise<WhatsappConnection> {
		const response = await apiClient.get<{ data: WhatsappConnection }>(`/whatsapp/connections/${id}`);
		return response.data;
	},

	async update(id: number, data: {
		name?: string;
		phone_number_id?: string;
		waba_id?: string;
		access_token?: string;
		display_phone_number?: string;
		is_active?: boolean;
		settings?: Record<string, unknown>;
	}): Promise<WhatsappConnection> {
		const response = await apiClient.put<{ data: WhatsappConnection }>(`/whatsapp/connections/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/whatsapp/connections/${id}`);
	},

	async verify(id: number): Promise<WhatsappConnection> {
		const response = await apiClient.post<{ data: WhatsappConnection }>(`/whatsapp/connections/${id}/verify`);
		return response.data;
	},

	async getWebhookConfig(id: number): Promise<{
		webhook_url: string;
		verify_token: string;
		instructions: string[];
	}> {
		const response = await apiClient.get<{ data: { webhook_url: string; verify_token: string; instructions: string[] } }>(
			`/whatsapp/connections/${id}/webhook-config`
		);
		return response.data;
	}
};

// Template API
export const whatsappTemplatesApi = {
	async list(params?: { connection_id?: number; category?: string; status?: string }): Promise<WhatsappTemplate[]> {
		const response = await apiClient.get<{ data: WhatsappTemplate[] }>('/whatsapp/templates', { params });
		return response.data;
	},

	async create(data: {
		connection_id: number;
		name: string;
		language: string;
		category: 'UTILITY' | 'MARKETING' | 'AUTHENTICATION';
		components: TemplateComponent[];
		example?: Record<string, unknown>;
		submit_to_meta?: boolean;
	}): Promise<WhatsappTemplate> {
		const response = await apiClient.post<{ data: WhatsappTemplate }>('/whatsapp/templates', data);
		return response.data;
	},

	async get(id: number): Promise<WhatsappTemplate> {
		const response = await apiClient.get<{ data: WhatsappTemplate }>(`/whatsapp/templates/${id}`);
		return response.data;
	},

	async update(id: number, data: {
		name?: string;
		language?: string;
		category?: 'UTILITY' | 'MARKETING' | 'AUTHENTICATION';
		components?: TemplateComponent[];
		example?: Record<string, unknown>;
	}): Promise<WhatsappTemplate> {
		const response = await apiClient.put<{ data: WhatsappTemplate }>(`/whatsapp/templates/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/whatsapp/templates/${id}`);
	},

	async submit(id: number): Promise<WhatsappTemplate> {
		const response = await apiClient.post<{ data: WhatsappTemplate }>(`/whatsapp/templates/${id}/submit`);
		return response.data;
	},

	async syncStatus(id: number): Promise<WhatsappTemplate> {
		const response = await apiClient.post<{ data: WhatsappTemplate }>(`/whatsapp/templates/${id}/sync-status`);
		return response.data;
	},

	async preview(id: number, params?: { body_params?: string[]; header_params?: string[] }): Promise<{
		header: string | null;
		body: string;
		footer: string | null;
		buttons: TemplateButton[];
	}> {
		const response = await apiClient.post<{ data: { header: string | null; body: string; footer: string | null; buttons: TemplateButton[] } }>(
			`/whatsapp/templates/${id}/preview`,
			params
		);
		return response.data;
	}
};

// Conversation API
export const whatsappConversationsApi = {
	async list(params?: {
		connection_id?: number;
		status?: string;
		assigned_to?: string | number;
		module_api_name?: string;
		module_record_id?: number;
		search?: string;
	}): Promise<{ data: WhatsappConversation[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
		const response = await apiClient.get<{
			data: WhatsappConversation[];
			meta: { current_page: number; last_page: number; per_page: number; total: number };
		}>('/whatsapp/conversations', { params });
		return response;
	},

	async get(id: number): Promise<WhatsappConversation> {
		const response = await apiClient.get<{ data: WhatsappConversation }>(`/whatsapp/conversations/${id}`);
		return response.data;
	},

	async getMessages(id: number, page?: number): Promise<{ data: WhatsappMessage[]; meta: { current_page: number; last_page: number } }> {
		const response = await apiClient.get<{ data: WhatsappMessage[]; meta: { current_page: number; last_page: number } }>(
			`/whatsapp/conversations/${id}/messages`,
			{ params: page ? { page } : undefined }
		);
		return response;
	},

	async sendMessage(id: number, data: {
		type: 'text' | 'template' | 'image' | 'video' | 'audio' | 'document';
		content?: string;
		template_id?: number;
		template_params?: Record<string, string[]>;
		media_url?: string;
		caption?: string;
		filename?: string;
	}): Promise<WhatsappMessage> {
		const response = await apiClient.post<{ data: WhatsappMessage }>(`/whatsapp/conversations/${id}/messages`, data);
		return response.data;
	},

	async assign(id: number, userId: number): Promise<WhatsappConversation> {
		const response = await apiClient.post<{ data: WhatsappConversation }>(`/whatsapp/conversations/${id}/assign`, { user_id: userId });
		return response.data;
	},

	async close(id: number): Promise<WhatsappConversation> {
		const response = await apiClient.post<{ data: WhatsappConversation }>(`/whatsapp/conversations/${id}/close`);
		return response.data;
	},

	async reopen(id: number): Promise<WhatsappConversation> {
		const response = await apiClient.post<{ data: WhatsappConversation }>(`/whatsapp/conversations/${id}/reopen`);
		return response.data;
	},

	async linkToRecord(id: number, moduleApiName: string, recordId: number): Promise<WhatsappConversation> {
		const response = await apiClient.post<{ data: WhatsappConversation }>(`/whatsapp/conversations/${id}/link-record`, {
			module_api_name: moduleApiName,
			module_record_id: recordId
		});
		return response.data;
	},

	async findByPhone(phone: string): Promise<WhatsappConversation[]> {
		const response = await apiClient.get<{ data: WhatsappConversation[] }>('/whatsapp/conversations/by-phone', { params: { phone } });
		return response.data;
	},

	async startConversation(data: {
		connection_id: number;
		phone: string;
		name?: string;
		template_id: number;
		template_params?: Record<string, string[]>;
		module_api_name?: string;
		module_record_id?: number;
	}): Promise<{ conversation: WhatsappConversation; message: WhatsappMessage }> {
		const response = await apiClient.post<{ data: { conversation: WhatsappConversation; message: WhatsappMessage } }>(
			'/whatsapp/conversations/start',
			data
		);
		return response.data;
	}
};

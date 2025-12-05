import { apiClient } from './client';

// Types
export interface EmailAccount {
	id: number;
	name: string;
	email_address: string;
	provider: 'imap' | 'gmail' | 'outlook' | 'smtp_only';
	imap_host: string | null;
	imap_port: number;
	imap_encryption: 'ssl' | 'tls' | 'none';
	smtp_host: string;
	smtp_port: number;
	smtp_encryption: 'ssl' | 'tls' | 'none';
	username: string | null;
	is_active: boolean;
	is_default: boolean;
	sync_enabled: boolean;
	sync_folders: string[];
	signature: string | null;
	last_sync_at: string | null;
	created_at: string;
	updated_at: string;
}

export interface EmailMessage {
	id: number;
	account_id: number;
	user_id: number | null;
	message_id: string | null;
	thread_id: string | null;
	parent_id: number | null;
	direction: 'inbound' | 'outbound';
	status: 'draft' | 'queued' | 'sent' | 'failed' | 'received';
	from_email: string;
	from_name: string | null;
	to_emails: EmailRecipient[];
	cc_emails: EmailRecipient[];
	bcc_emails: EmailRecipient[];
	reply_to: string | null;
	subject: string | null;
	body_html: string | null;
	body_text: string | null;
	headers: Record<string, string> | null;
	folder: string;
	is_read: boolean;
	is_starred: boolean;
	is_important: boolean;
	has_attachments: boolean;
	attachments: EmailAttachment[];
	tracking_id: string | null;
	opened_at: string | null;
	open_count: number;
	clicked_at: string | null;
	click_count: number;
	linked_record_type: string | null;
	linked_record_id: number | null;
	template_id: number | null;
	sent_at: string | null;
	received_at: string | null;
	scheduled_at: string | null;
	failed_reason: string | null;
	created_at: string;
	updated_at: string;
	account?: { id: number; name: string; email_address: string };
	template?: EmailTemplate;
	replies?: EmailMessage[];
}

export interface EmailRecipient {
	email: string;
	name?: string;
}

export interface EmailAttachment {
	filename: string;
	mime_type: string;
	size: number;
	part_number?: string;
	path?: string;
	content?: string;
}

export interface EmailTemplate {
	id: number;
	name: string;
	description: string | null;
	type: 'user' | 'system' | 'workflow';
	module_id: number | null;
	subject: string;
	body_html: string;
	body_text: string | null;
	variables: string[];
	attachments: EmailAttachment[];
	is_active: boolean;
	is_default: boolean;
	category: string | null;
	tags: string[];
	usage_count: number;
	created_by: number | null;
	updated_by: number | null;
	created_at: string;
	updated_at: string;
	module?: { id: number; name: string; api_name: string };
}

export interface CreateAccountData {
	name: string;
	email_address: string;
	provider: EmailAccount['provider'];
	imap_host?: string;
	imap_port?: number;
	imap_encryption?: 'ssl' | 'tls' | 'none';
	smtp_host: string;
	smtp_port?: number;
	smtp_encryption?: 'ssl' | 'tls' | 'none';
	username?: string;
	password?: string;
	signature?: string;
	sync_folders?: string[];
	is_default?: boolean;
}

export interface CreateEmailData {
	account_id: number;
	to: string[];
	cc?: string[];
	bcc?: string[];
	subject?: string;
	body_html?: string;
	body_text?: string;
	reply_to?: string;
	thread_id?: string;
	parent_id?: number;
	linked_record_type?: string;
	linked_record_id?: number;
	template_id?: number;
}

export interface CreateTemplateData {
	name: string;
	description?: string;
	type?: EmailTemplate['type'];
	module_id?: number;
	subject: string;
	body_html: string;
	body_text?: string;
	variables?: string[];
	attachments?: EmailAttachment[];
	is_active?: boolean;
	is_default?: boolean;
	category?: string;
	tags?: string[];
}

export interface EmailFilters {
	account_id?: number;
	folder?: string;
	direction?: 'inbound' | 'outbound';
	status?: EmailMessage['status'];
	is_read?: boolean;
	is_starred?: boolean;
	search?: string;
	linked_record_type?: string;
	linked_record_id?: number;
	per_page?: number;
}

export interface TemplateFilters {
	type?: EmailTemplate['type'];
	module_id?: number;
	category?: string;
	is_active?: boolean;
	search?: string;
	per_page?: number;
}

// Email Accounts API
export const emailAccountsApi = {
	list: async () => {
		const response = await apiClient.get<{ data: EmailAccount[] }>('/email-accounts');
		return response.data;
	},

	get: async (id: number) => {
		const response = await apiClient.get<{ data: EmailAccount }>(`/email-accounts/${id}`);
		return response.data;
	},

	create: async (data: CreateAccountData) => {
		const response = await apiClient.post<{ data: EmailAccount; message: string }>(
			'/email-accounts',
			data
		);
		return response;
	},

	update: async (id: number, data: Partial<CreateAccountData>) => {
		const response = await apiClient.put<{ data: EmailAccount; message: string }>(
			`/email-accounts/${id}`,
			data
		);
		return response;
	},

	delete: async (id: number) => {
		const response = await apiClient.delete<{ message: string }>(`/email-accounts/${id}`);
		return response;
	},

	testConnection: async (id: number) => {
		const response = await apiClient.post<{ success: boolean; message: string }>(
			`/email-accounts/${id}/test`
		);
		return response;
	},

	sync: async (id: number) => {
		const response = await apiClient.post<{ success: boolean; message: string; count: number }>(
			`/email-accounts/${id}/sync`
		);
		return response;
	},

	getFolders: async (id: number) => {
		const response = await apiClient.get<{ data: string[] }>(`/email-accounts/${id}/folders`);
		return response.data;
	}
};

// Email Messages API
export const emailsApi = {
	list: async (filters?: EmailFilters) => {
		const response = await apiClient.get<{
			data: EmailMessage[];
			meta: { current_page: number; last_page: number; per_page: number; total: number };
		}>('/emails', { params: filters as Record<string, string | number | boolean | undefined> });
		return response;
	},

	get: async (id: number) => {
		const response = await apiClient.get<{ data: EmailMessage }>(`/emails/${id}`);
		return response.data;
	},

	create: async (data: CreateEmailData) => {
		const response = await apiClient.post<{ data: EmailMessage; message: string }>(
			'/emails',
			data
		);
		return response;
	},

	update: async (id: number, data: Partial<CreateEmailData>) => {
		const response = await apiClient.put<{ data: EmailMessage; message: string }>(
			`/emails/${id}`,
			data
		);
		return response;
	},

	delete: async (id: number) => {
		const response = await apiClient.delete<{ message: string }>(`/emails/${id}`);
		return response;
	},

	send: async (id: number) => {
		const response = await apiClient.post<{ data: EmailMessage; message: string }>(
			`/emails/${id}/send`
		);
		return response;
	},

	schedule: async (id: number, scheduledAt: string) => {
		const response = await apiClient.post<{ data: EmailMessage; message: string }>(
			`/emails/${id}/schedule`,
			{ scheduled_at: scheduledAt }
		);
		return response;
	},

	reply: async (id: number, data: { body_html: string; body_text?: string; cc?: string[]; bcc?: string[] }) => {
		const response = await apiClient.post<{ data: EmailMessage; message: string }>(
			`/emails/${id}/reply`,
			data
		);
		return response;
	},

	forward: async (id: number, data: { to: string[]; body_html?: string; cc?: string[]; bcc?: string[] }) => {
		const response = await apiClient.post<{ data: EmailMessage; message: string }>(
			`/emails/${id}/forward`,
			data
		);
		return response;
	},

	markRead: async (id: number) => {
		const response = await apiClient.post<{ data: EmailMessage; message: string }>(
			`/emails/${id}/mark-read`
		);
		return response;
	},

	markUnread: async (id: number) => {
		const response = await apiClient.post<{ data: EmailMessage; message: string }>(
			`/emails/${id}/mark-unread`
		);
		return response;
	},

	toggleStar: async (id: number) => {
		const response = await apiClient.post<{ data: EmailMessage; message: string }>(
			`/emails/${id}/toggle-star`
		);
		return response;
	},

	moveToFolder: async (id: number, folder: string) => {
		const response = await apiClient.post<{ data: EmailMessage; message: string }>(
			`/emails/${id}/move`,
			{ folder }
		);
		return response;
	},

	getThread: async (id: number) => {
		const response = await apiClient.get<{ data: EmailMessage[] }>(`/emails/${id}/thread`);
		return response.data;
	},

	bulkMarkRead: async (ids: number[]) => {
		const response = await apiClient.post<{ message: string }>('/emails/bulk-read', { ids });
		return response;
	},

	bulkDelete: async (ids: number[]) => {
		const response = await apiClient.post<{ message: string }>('/emails/bulk-delete', { ids });
		return response;
	}
};

// Email Templates API
export const emailTemplatesApi = {
	list: async (filters?: TemplateFilters) => {
		const response = await apiClient.get<{
			data: EmailTemplate[];
			meta: { current_page: number; last_page: number; per_page: number; total: number };
		}>('/email-templates', { params: filters as Record<string, string | number | boolean | undefined> });
		return response;
	},

	get: async (id: number) => {
		const response = await apiClient.get<{
			data: EmailTemplate;
			variables: Record<string, string>;
		}>(`/email-templates/${id}`);
		return response;
	},

	create: async (data: CreateTemplateData) => {
		const response = await apiClient.post<{ data: EmailTemplate; message: string }>(
			'/email-templates',
			data
		);
		return response;
	},

	update: async (id: number, data: Partial<CreateTemplateData>) => {
		const response = await apiClient.put<{ data: EmailTemplate; message: string }>(
			`/email-templates/${id}`,
			data
		);
		return response;
	},

	delete: async (id: number) => {
		const response = await apiClient.delete<{ message: string }>(`/email-templates/${id}`);
		return response;
	},

	duplicate: async (id: number) => {
		const response = await apiClient.post<{ data: EmailTemplate; message: string }>(
			`/email-templates/${id}/duplicate`
		);
		return response;
	},

	preview: async (id: number, data?: Record<string, unknown>) => {
		const response = await apiClient.post<{
			data: { subject: string; body_html: string; body_text: string };
			variables_used: Record<string, unknown>;
		}>(`/email-templates/${id}/preview`, { data });
		return response;
	},

	getCategories: async () => {
		const response = await apiClient.get<{ data: string[] }>('/email-templates/categories');
		return response.data;
	}
};

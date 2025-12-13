import { apiClient } from './client';

// Types
export interface SharedInbox {
	id: number;
	name: string;
	email: string;
	description: string | null;
	type: 'support' | 'sales' | 'general';
	is_active: boolean;
	is_connected: boolean;
	last_synced_at: string | null;
	assignment_method: 'round_robin' | 'load_balanced' | 'manual';
	default_assignee_id: number | null;
	conversations_count?: number;
	members_count?: number;
	stats?: InboxStats;
	created_at: string;
}

export interface SharedInboxMember {
	id: number;
	inbox_id: number;
	user_id: number;
	role: 'admin' | 'member';
	can_reply: boolean;
	can_assign: boolean;
	can_close: boolean;
	receives_notifications: boolean;
	active_conversation_limit: number | null;
	current_active_count: number;
	user?: { id: number; name: string; email: string };
}

export interface InboxConversation {
	id: number;
	inbox_id: number;
	subject: string;
	status: 'open' | 'pending' | 'resolved' | 'closed';
	priority: 'low' | 'normal' | 'high' | 'urgent';
	channel: 'email' | 'live_chat' | 'whatsapp' | 'sms';
	assigned_to: number | null;
	contact_id: number | null;
	contact_email: string | null;
	contact_name: string | null;
	contact_phone: string | null;
	snippet: string | null;
	first_response_at: string | null;
	resolved_at: string | null;
	last_message_at: string | null;
	message_count: number;
	response_time_seconds: number | null;
	is_spam: boolean;
	is_starred: boolean;
	tags: string[] | null;
	inbox?: { id: number; name: string; email: string };
	assignee?: { id: number; name: string };
	messages?: InboxMessage[];
	created_at: string;
}

export interface InboxMessage {
	id: number;
	conversation_id: number;
	direction: 'inbound' | 'outbound';
	type: 'original' | 'reply' | 'forward' | 'note' | 'auto_reply';
	from_email: string | null;
	from_name: string | null;
	to_emails: string[] | null;
	cc_emails: string[] | null;
	subject: string | null;
	body_text: string | null;
	body_html: string | null;
	attachments: MessageAttachment[] | null;
	status: 'draft' | 'queued' | 'sent' | 'delivered' | 'failed';
	sent_by: number | null;
	sender?: { id: number; name: string };
	sent_at: string | null;
	delivered_at: string | null;
	read_at: string | null;
	created_at: string;
}

export interface MessageAttachment {
	name: string;
	path?: string;
	mime_type: string;
	size: number;
}

export interface InboxCannedResponse {
	id: number;
	inbox_id: number | null;
	name: string;
	shortcut: string | null;
	category: string | null;
	subject: string | null;
	body: string;
	attachments: MessageAttachment[] | null;
	created_by: number | null;
	is_active: boolean;
	use_count: number;
	creator?: { id: number; name: string };
	created_at: string;
}

export interface InboxRule {
	id: number;
	inbox_id: number;
	name: string;
	description: string | null;
	priority: number;
	conditions: RuleCondition[];
	condition_match: 'all' | 'any';
	actions: RuleAction[];
	is_active: boolean;
	stop_processing: boolean;
	triggered_count: number;
	last_triggered_at: string | null;
	creator?: { id: number; name: string };
	created_at: string;
}

export interface RuleCondition {
	field: string;
	operator: string;
	value: string;
}

export interface RuleAction {
	type: string;
	value?: string | number;
}

export interface InboxStats {
	total: number;
	open: number;
	pending: number;
	resolved: number;
	unassigned: number;
	spam: number;
	starred: number;
	avg_response_time: number | null;
}

// Shared Inbox API
export const sharedInboxApi = {
	async list(params?: { active_only?: boolean }): Promise<SharedInbox[]> {
		const response = await apiClient.get<{ data: SharedInbox[] }>('/inboxes', { params });
		return response.data;
	},

	async create(data: {
		name: string;
		email: string;
		description?: string;
		type?: 'support' | 'sales' | 'general';
		imap_host?: string;
		imap_port?: number;
		imap_encryption?: 'ssl' | 'tls' | 'none';
		smtp_host?: string;
		smtp_port?: number;
		smtp_encryption?: 'ssl' | 'tls' | 'none';
		username?: string;
		password?: string;
		assignment_method?: 'round_robin' | 'load_balanced' | 'manual';
	}): Promise<SharedInbox> {
		const response = await apiClient.post<{ data: SharedInbox }>('/inboxes', data);
		return response.data;
	},

	async get(id: number): Promise<SharedInbox> {
		const response = await apiClient.get<{ data: SharedInbox }>(`/inboxes/${id}`);
		return response.data;
	},

	async update(id: number, data: Partial<{
		name: string;
		email: string;
		description: string;
		type: 'support' | 'sales' | 'general';
		imap_host: string;
		imap_port: number;
		imap_encryption: 'ssl' | 'tls' | 'none';
		smtp_host: string;
		smtp_port: number;
		smtp_encryption: 'ssl' | 'tls' | 'none';
		username: string;
		password: string;
		is_active: boolean;
		default_assignee_id: number;
		assignment_method: 'round_robin' | 'load_balanced' | 'manual';
	}>): Promise<SharedInbox> {
		const response = await apiClient.put<{ data: SharedInbox }>(`/inboxes/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/inboxes/${id}`);
	},

	async verify(id: number): Promise<{ data: SharedInbox; verification: { imap: { success: boolean; error?: string }; smtp: { success: boolean; error?: string } } }> {
		const response = await apiClient.post<{ data: SharedInbox; verification: { imap: { success: boolean; error?: string }; smtp: { success: boolean; error?: string } } }>(`/inboxes/${id}/verify`);
		return response;
	},

	async sync(id: number): Promise<{ data: SharedInbox; synced_count: number }> {
		const response = await apiClient.post<{ data: SharedInbox; synced_count: number }>(`/inboxes/${id}/sync`);
		return response;
	},

	async getStats(id: number): Promise<InboxStats> {
		const response = await apiClient.get<{ data: InboxStats }>(`/inboxes/${id}/stats`);
		return response.data;
	},

	// Members
	async getMembers(id: number): Promise<SharedInboxMember[]> {
		const response = await apiClient.get<{ data: SharedInboxMember[] }>(`/inboxes/${id}/members`);
		return response.data;
	},

	async addMember(inboxId: number, data: {
		user_id: number;
		role?: 'admin' | 'member';
		can_reply?: boolean;
		can_assign?: boolean;
		can_close?: boolean;
		receives_notifications?: boolean;
		active_conversation_limit?: number;
	}): Promise<SharedInboxMember> {
		const response = await apiClient.post<{ data: SharedInboxMember }>(`/inboxes/${inboxId}/members`, data);
		return response.data;
	},

	async updateMember(inboxId: number, memberId: number, data: Partial<{
		role: 'admin' | 'member';
		can_reply: boolean;
		can_assign: boolean;
		can_close: boolean;
		receives_notifications: boolean;
		active_conversation_limit: number;
	}>): Promise<SharedInboxMember> {
		const response = await apiClient.put<{ data: SharedInboxMember }>(`/inboxes/${inboxId}/members/${memberId}`, data);
		return response.data;
	},

	async removeMember(inboxId: number, memberId: number): Promise<void> {
		await apiClient.delete(`/inboxes/${inboxId}/members/${memberId}`);
	},

	// Rules
	async getRules(inboxId: number, activeOnly?: boolean): Promise<InboxRule[]> {
		const response = await apiClient.get<{ data: InboxRule[] }>(`/inboxes/${inboxId}/rules`, { params: { active_only: activeOnly } });
		return response.data;
	},

	async createRule(inboxId: number, data: {
		name: string;
		description?: string;
		priority?: number;
		conditions: RuleCondition[];
		condition_match?: 'all' | 'any';
		actions: RuleAction[];
		is_active?: boolean;
		stop_processing?: boolean;
	}): Promise<InboxRule> {
		const response = await apiClient.post<{ data: InboxRule }>(`/inboxes/${inboxId}/rules`, data);
		return response.data;
	},

	async updateRule(inboxId: number, ruleId: number, data: Partial<{
		name: string;
		description: string;
		priority: number;
		conditions: RuleCondition[];
		condition_match: 'all' | 'any';
		actions: RuleAction[];
		is_active: boolean;
		stop_processing: boolean;
	}>): Promise<InboxRule> {
		const response = await apiClient.put<{ data: InboxRule }>(`/inboxes/${inboxId}/rules/${ruleId}`, data);
		return response.data;
	},

	async deleteRule(inboxId: number, ruleId: number): Promise<void> {
		await apiClient.delete(`/inboxes/${inboxId}/rules/${ruleId}`);
	},

	async reorderRules(inboxId: number, ruleIds: number[]): Promise<void> {
		await apiClient.post(`/inboxes/${inboxId}/rules/reorder`, { rule_ids: ruleIds });
	},

	async toggleRule(inboxId: number, ruleId: number): Promise<InboxRule> {
		const response = await apiClient.post<{ data: InboxRule }>(`/inboxes/${inboxId}/rules/${ruleId}/toggle`);
		return response.data;
	}
};

// Conversation API
export const inboxConversationApi = {
	async list(params?: {
		inbox_id?: number;
		status?: string;
		assigned_to?: number | 'unassigned' | 'me';
		priority?: string;
		channel?: string;
		starred?: boolean;
		include_spam?: boolean;
		search?: string;
		tag?: string;
		per_page?: number;
		page?: number;
	}): Promise<{ data: InboxConversation[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
		const response = await apiClient.get<{ data: InboxConversation[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>('/inbox-conversations', { params });
		return response;
	},

	async get(id: number): Promise<InboxConversation> {
		const response = await apiClient.get<{ data: InboxConversation }>(`/inbox-conversations/${id}`);
		return response.data;
	},

	async update(id: number, data: Partial<{
		status: string;
		priority: string;
		assigned_to: number | null;
		tags: string[];
	}>): Promise<InboxConversation> {
		const response = await apiClient.put<{ data: InboxConversation }>(`/inbox-conversations/${id}`, data);
		return response.data;
	},

	async reply(id: number, data: {
		body: string;
		cc?: string[];
		bcc?: string[];
	}): Promise<InboxMessage> {
		const response = await apiClient.post<{ data: InboxMessage }>(`/inbox-conversations/${id}/reply`, data);
		return response.data;
	},

	async addNote(id: number, body: string): Promise<InboxMessage> {
		const response = await apiClient.post<{ data: InboxMessage }>(`/inbox-conversations/${id}/note`, { body });
		return response.data;
	},

	async assign(id: number, userId: number | null): Promise<InboxConversation> {
		const response = await apiClient.post<{ data: InboxConversation }>(`/inbox-conversations/${id}/assign`, { user_id: userId });
		return response.data;
	},

	async resolve(id: number): Promise<InboxConversation> {
		const response = await apiClient.post<{ data: InboxConversation }>(`/inbox-conversations/${id}/resolve`);
		return response.data;
	},

	async reopen(id: number): Promise<InboxConversation> {
		const response = await apiClient.post<{ data: InboxConversation }>(`/inbox-conversations/${id}/reopen`);
		return response.data;
	},

	async close(id: number): Promise<InboxConversation> {
		const response = await apiClient.post<{ data: InboxConversation }>(`/inbox-conversations/${id}/close`);
		return response.data;
	},

	async markAsSpam(id: number): Promise<InboxConversation> {
		const response = await apiClient.post<{ data: InboxConversation }>(`/inbox-conversations/${id}/spam`);
		return response.data;
	},

	async toggleStar(id: number): Promise<InboxConversation> {
		const response = await apiClient.post<{ data: InboxConversation }>(`/inbox-conversations/${id}/star`);
		return response.data;
	},

	async addTag(id: number, tag: string): Promise<InboxConversation> {
		const response = await apiClient.post<{ data: InboxConversation }>(`/inbox-conversations/${id}/tags`, { tag });
		return response.data;
	},

	async removeTag(id: number, tag: string): Promise<InboxConversation> {
		const response = await apiClient.delete<{ data: InboxConversation }>(`/inbox-conversations/${id}/tags?tag=${encodeURIComponent(tag)}`);
		return response.data;
	},

	async merge(primaryId: number, conversationIds: number[]): Promise<InboxConversation> {
		const response = await apiClient.post<{ data: InboxConversation }>(`/inbox-conversations/${primaryId}/merge`, { conversation_ids: conversationIds });
		return response.data;
	},

	async bulkAssign(conversationIds: number[], userId: number): Promise<{ assigned_count: number }> {
		const response = await apiClient.post<{ assigned_count: number }>('/inbox-conversations/bulk-assign', { conversation_ids: conversationIds, user_id: userId });
		return response;
	},

	async bulkResolve(conversationIds: number[]): Promise<{ resolved_count: number }> {
		const response = await apiClient.post<{ resolved_count: number }>('/inbox-conversations/bulk-resolve', { conversation_ids: conversationIds });
		return response;
	}
};

// Canned Response API
export const inboxCannedResponseApi = {
	async list(params?: {
		inbox_id?: number;
		category?: string;
		search?: string;
	}): Promise<InboxCannedResponse[]> {
		const response = await apiClient.get<{ data: InboxCannedResponse[] }>('/inbox-canned-responses', { params });
		return response.data;
	},

	async create(data: {
		inbox_id?: number;
		name: string;
		shortcut?: string;
		category?: string;
		subject?: string;
		body: string;
	}): Promise<InboxCannedResponse> {
		const response = await apiClient.post<{ data: InboxCannedResponse }>('/inbox-canned-responses', data);
		return response.data;
	},

	async get(id: number): Promise<InboxCannedResponse> {
		const response = await apiClient.get<{ data: InboxCannedResponse }>(`/inbox-canned-responses/${id}`);
		return response.data;
	},

	async update(id: number, data: Partial<{
		inbox_id: number;
		name: string;
		shortcut: string;
		category: string;
		subject: string;
		body: string;
		is_active: boolean;
	}>): Promise<InboxCannedResponse> {
		const response = await apiClient.put<{ data: InboxCannedResponse }>(`/inbox-canned-responses/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/inbox-canned-responses/${id}`);
	},

	async render(id: number, variables?: Record<string, string>): Promise<{ subject: string | null; body: string }> {
		const response = await apiClient.post<{ data: { subject: string | null; body: string } }>(`/inbox-canned-responses/${id}/render`, { variables });
		return response.data;
	},

	async getCategories(inboxId?: number): Promise<string[]> {
		const response = await apiClient.get<{ data: string[] }>('/inbox-canned-responses/categories', { params: { inbox_id: inboxId } });
		return response.data;
	},

	async findByShortcut(shortcut: string, inboxId?: number): Promise<InboxCannedResponse | null> {
		try {
			const response = await apiClient.get<{ data: InboxCannedResponse }>('/inbox-canned-responses/by-shortcut', { params: { shortcut, inbox_id: inboxId } });
			return response.data;
		} catch {
			return null;
		}
	}
};

// Rule meta API
export const inboxRuleMetaApi = {
	async getFields(): Promise<{ value: string; label: string }[]> {
		const response = await apiClient.get<{ data: { value: string; label: string }[] }>('/inbox-rules/fields');
		return response.data;
	},

	async getOperators(): Promise<{ value: string; label: string }[]> {
		const response = await apiClient.get<{ data: { value: string; label: string }[] }>('/inbox-rules/operators');
		return response.data;
	},

	async getActions(): Promise<{ value: string; label: string; requires_value: boolean }[]> {
		const response = await apiClient.get<{ data: { value: string; label: string; requires_value: boolean }[] }>('/inbox-rules/actions');
		return response.data;
	}
};

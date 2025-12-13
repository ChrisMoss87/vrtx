import { apiClient } from './client';

// Types
export interface TeamChatConnection {
	id: number;
	name: string;
	provider: 'slack' | 'teams';
	workspace_id: string | null;
	workspace_name: string | null;
	webhook_url: string | null;
	is_active: boolean;
	is_verified: boolean;
	scopes: string[] | null;
	settings: Record<string, unknown> | null;
	last_synced_at: string | null;
	channels_count?: number;
	notifications_count?: number;
	messages_count?: number;
	created_at: string;
}

export interface TeamChatChannel {
	id: number;
	connection_id: number;
	channel_id: string;
	name: string;
	description: string | null;
	is_private: boolean;
	is_archived: boolean;
	member_count: number;
	last_activity_at: string | null;
	created_at: string;
}

export interface TeamChatNotification {
	id: number;
	connection_id: number;
	channel_id: number | null;
	name: string;
	description: string | null;
	trigger_event: string;
	trigger_module: string | null;
	trigger_conditions: TriggerCondition[] | null;
	message_template: string;
	include_mentions: boolean;
	mention_field: string | null;
	is_active: boolean;
	triggered_count: number;
	last_triggered_at: string | null;
	created_by: number | null;
	connection?: { id: number; name: string; provider: string };
	channel?: { id: number; name: string };
	creator?: { id: number; name: string } | null;
	messages_count?: number;
	created_at: string;
}

export interface TriggerCondition {
	field: string;
	operator: string;
	value: unknown;
}

export interface TeamChatMessage {
	id: number;
	connection_id: number;
	channel_id: number | null;
	notification_id: number | null;
	message_id: string | null;
	content: string;
	attachments: unknown[] | null;
	status: 'pending' | 'sent' | 'delivered' | 'failed';
	error_code: string | null;
	error_message: string | null;
	module_record_id: number | null;
	module_api_name: string | null;
	sent_by: number | null;
	sent_at: string | null;
	connection?: { id: number; name: string; provider: string };
	channel?: { id: number; name: string };
	notification?: { id: number; name: string } | null;
	sender?: { id: number; name: string } | null;
	created_at: string;
}

export interface TeamChatUserMapping {
	id: number;
	connection_id: number;
	user_id: number;
	external_user_id: string;
	external_username: string | null;
	external_email: string | null;
	is_verified: boolean;
	user?: { id: number; name: string; email: string };
}

export interface TriggerEvent {
	value: string;
	label: string;
}

// Connection API
export const teamChatConnectionsApi = {
	async list(): Promise<TeamChatConnection[]> {
		const response = await apiClient.get<{ data: TeamChatConnection[] }>('/team-chat/connections');
		return response.data;
	},

	async create(data: {
		name: string;
		provider: 'slack' | 'teams';
		access_token: string;
		bot_token?: string;
		webhook_url?: string;
		settings?: Record<string, unknown>;
	}): Promise<TeamChatConnection> {
		const response = await apiClient.post<{ data: TeamChatConnection }>('/team-chat/connections', data);
		return response.data;
	},

	async get(id: number): Promise<TeamChatConnection> {
		const response = await apiClient.get<{ data: TeamChatConnection }>(`/team-chat/connections/${id}`);
		return response.data;
	},

	async update(id: number, data: Partial<{
		name: string;
		access_token: string;
		bot_token: string;
		webhook_url: string;
		is_active: boolean;
		settings: Record<string, unknown>;
	}>): Promise<TeamChatConnection> {
		const response = await apiClient.put<{ data: TeamChatConnection }>(`/team-chat/connections/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/team-chat/connections/${id}`);
	},

	async verify(id: number): Promise<{ data: TeamChatConnection; verification: { success: boolean; error?: string } }> {
		const response = await apiClient.post<{ data: TeamChatConnection; verification: { success: boolean; error?: string } }>(`/team-chat/connections/${id}/verify`);
		return response;
	},

	async syncChannels(id: number): Promise<{ data: TeamChatChannel[]; synced_count: number }> {
		const response = await apiClient.post<{ data: TeamChatChannel[]; synced_count: number }>(`/team-chat/connections/${id}/sync-channels`);
		return response;
	},

	async syncUsers(id: number): Promise<{ data: TeamChatUserMapping[]; synced_count: number }> {
		const response = await apiClient.post<{ data: TeamChatUserMapping[]; synced_count: number }>(`/team-chat/connections/${id}/sync-users`);
		return response;
	},

	async getChannels(id: number): Promise<TeamChatChannel[]> {
		const response = await apiClient.get<{ data: TeamChatChannel[] }>(`/team-chat/connections/${id}/channels`);
		return response.data;
	},

	async getUserMappings(id: number): Promise<TeamChatUserMapping[]> {
		const response = await apiClient.get<{ data: TeamChatUserMapping[] }>(`/team-chat/connections/${id}/user-mappings`);
		return response.data;
	}
};

// Notification API
export const teamChatNotificationsApi = {
	async list(params?: {
		connection_id?: number;
		trigger_event?: string;
		active_only?: boolean;
	}): Promise<TeamChatNotification[]> {
		const response = await apiClient.get<{ data: TeamChatNotification[] }>('/team-chat/notifications', { params });
		return response.data;
	},

	async create(data: {
		connection_id: number;
		channel_id?: number;
		name: string;
		description?: string;
		trigger_event: string;
		trigger_module?: string;
		trigger_conditions?: TriggerCondition[];
		message_template: string;
		include_mentions?: boolean;
		mention_field?: string;
		is_active?: boolean;
	}): Promise<TeamChatNotification> {
		const response = await apiClient.post<{ data: TeamChatNotification }>('/team-chat/notifications', data);
		return response.data;
	},

	async get(id: number): Promise<TeamChatNotification> {
		const response = await apiClient.get<{ data: TeamChatNotification }>(`/team-chat/notifications/${id}`);
		return response.data;
	},

	async update(id: number, data: Partial<{
		connection_id: number;
		channel_id: number;
		name: string;
		description: string;
		trigger_event: string;
		trigger_module: string;
		trigger_conditions: TriggerCondition[];
		message_template: string;
		include_mentions: boolean;
		mention_field: string;
		is_active: boolean;
	}>): Promise<TeamChatNotification> {
		const response = await apiClient.put<{ data: TeamChatNotification }>(`/team-chat/notifications/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/team-chat/notifications/${id}`);
	},

	async test(id: number, sampleData?: Record<string, string>): Promise<{ data: TeamChatMessage; rendered_content: string }> {
		const response = await apiClient.post<{ data: TeamChatMessage; rendered_content: string }>(`/team-chat/notifications/${id}/test`, { sample_data: sampleData });
		return response;
	},

	async duplicate(id: number): Promise<TeamChatNotification> {
		const response = await apiClient.post<{ data: TeamChatNotification }>(`/team-chat/notifications/${id}/duplicate`);
		return response.data;
	},

	async getEvents(): Promise<TriggerEvent[]> {
		const response = await apiClient.get<{ data: TriggerEvent[] }>('/team-chat/notifications/events');
		return response.data;
	}
};

// Message API
export const teamChatMessagesApi = {
	async list(params?: {
		connection_id?: number;
		channel_id?: number;
		status?: string;
		module_api_name?: string;
		module_record_id?: number;
		per_page?: number;
		page?: number;
	}): Promise<{ data: TeamChatMessage[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
		const response = await apiClient.get<{ data: TeamChatMessage[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>('/team-chat/messages', { params });
		return response;
	},

	async send(data: {
		connection_id: number;
		channel_id: number;
		content: string;
		module_record_id?: number;
		module_api_name?: string;
	}): Promise<TeamChatMessage> {
		const response = await apiClient.post<{ data: TeamChatMessage }>('/team-chat/messages', data);
		return response.data;
	},

	async get(id: number): Promise<TeamChatMessage> {
		const response = await apiClient.get<{ data: TeamChatMessage }>(`/team-chat/messages/${id}`);
		return response.data;
	},

	async getForRecord(moduleApiName: string, recordId: number): Promise<TeamChatMessage[]> {
		const response = await apiClient.get<{ data: TeamChatMessage[] }>('/team-chat/messages/for-record', {
			params: { module_api_name: moduleApiName, module_record_id: recordId }
		});
		return response.data;
	},

	async retry(id: number): Promise<TeamChatMessage> {
		const response = await apiClient.post<{ data: TeamChatMessage }>(`/team-chat/messages/${id}/retry`);
		return response.data;
	}
};

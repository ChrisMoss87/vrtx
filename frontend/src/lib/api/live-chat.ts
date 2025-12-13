import { apiClient } from './client';

// Types
export interface ChatWidget {
	id: number;
	name: string;
	widget_key: string;
	is_active: boolean;
	is_online: boolean;
	settings?: ChatWidgetSettings;
	styling?: ChatWidgetStyling;
	routing_rules?: Record<string, unknown>;
	business_hours?: Record<string, BusinessHours>;
	allowed_domains?: string[];
	conversations_count: number;
	visitors_count: number;
	embed_code?: string;
	created_at: string;
}

export interface ChatWidgetSettings {
	position: 'bottom-right' | 'bottom-left';
	greeting_message: string;
	offline_message: string;
	require_email: boolean;
	require_name: boolean;
	show_avatar: boolean;
	sound_enabled: boolean;
	auto_open_delay: number;
}

export interface ChatWidgetStyling {
	primary_color: string;
	text_color: string;
	background_color: string;
	launcher_icon: string;
	header_text: string;
	border_radius: number;
}

export interface BusinessHours {
	enabled: boolean;
	start: string;
	end: string;
}

export interface ChatVisitor {
	id: number;
	name: string | null;
	email: string | null;
	location: string | null;
	current_page: string | null;
}

export interface ChatConversation {
	id: number;
	status: 'open' | 'pending' | 'closed';
	priority: 'low' | 'normal' | 'high' | 'urgent';
	department: string | null;
	subject: string | null;
	tags: string[] | null;
	message_count: number;
	visitor_message_count: number;
	agent_message_count: number;
	rating: number | null;
	rating_comment: string | null;
	first_response_at: string | null;
	resolved_at: string | null;
	last_message_at: string | null;
	created_at: string;
	visitor: ChatVisitor | null;
	assigned_agent: { id: number; name: string } | null;
	widget: { id: number; name: string } | null;
	messages?: ChatMessage[];
}

export interface ChatMessage {
	id: number;
	sender_type: 'visitor' | 'agent' | 'system';
	sender_id: number | null;
	sender_name: string;
	content: string;
	content_type: 'text' | 'html' | 'image' | 'file';
	attachments: string[] | null;
	is_internal: boolean;
	read_at: string | null;
	created_at: string;
}

export interface ChatAgentStatus {
	user_id: number;
	user_name: string | null;
	status: 'online' | 'away' | 'busy' | 'offline';
	max_conversations: number;
	active_conversations: number;
	is_available: boolean;
	departments: string[] | null;
	last_activity_at: string | null;
}

export interface ChatCannedResponse {
	id: number;
	shortcut: string;
	title: string;
	content: string;
	category: string | null;
	is_global: boolean;
	usage_count: number;
	created_by: number;
}

export interface ChatAnalytics {
	overview: {
		total_conversations: number;
		change_percent: number | null;
		open_conversations: number;
		closed_conversations: number;
		avg_first_response_minutes: number;
		avg_resolution_minutes: number;
		avg_rating: number | null;
		total_messages: number;
		period: string;
	};
	by_hour: { hour: number; label: string; count: number }[];
	ratings: { rating: number; count: number }[];
	visitors: {
		total_visitors: number;
		new_visitors: number;
		returning_visitors: number;
		countries: Record<string, number>;
	};
}

export interface AgentPerformance {
	user_id: number;
	user_name: string;
	status: string;
	total_conversations: number;
	closed_conversations: number;
	avg_first_response_minutes: number;
	avg_resolution_minutes: number;
	avg_rating: number | null;
	total_messages: number;
}

// Widget API
export const chatWidgetsApi = {
	async list(): Promise<ChatWidget[]> {
		const response = await apiClient.get<{ data: ChatWidget[] }>('/chat/widgets');
		return response.data;
	},

	async create(data: {
		name: string;
		is_active?: boolean;
		settings?: Partial<ChatWidgetSettings>;
		styling?: Partial<ChatWidgetStyling>;
		routing_rules?: Record<string, unknown>;
		business_hours?: Record<string, BusinessHours>;
		allowed_domains?: string[];
	}): Promise<ChatWidget> {
		const response = await apiClient.post<{ data: ChatWidget }>('/chat/widgets', data);
		return response.data;
	},

	async get(id: number): Promise<ChatWidget> {
		const response = await apiClient.get<{ data: ChatWidget }>(`/chat/widgets/${id}`);
		return response.data;
	},

	async update(id: number, data: Partial<Parameters<typeof this.create>[0]>): Promise<ChatWidget> {
		const response = await apiClient.put<{ data: ChatWidget }>(`/chat/widgets/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/chat/widgets/${id}`);
	},

	async getEmbedCode(id: number): Promise<{ embed_code: string; widget_key: string }> {
		const response = await apiClient.get<{ data: { embed_code: string; widget_key: string } }>(
			`/chat/widgets/${id}/embed`
		);
		return response.data;
	},

	async getAnalytics(id: number, period?: string): Promise<ChatAnalytics> {
		const response = await apiClient.get<{ data: ChatAnalytics }>(`/chat/widgets/${id}/analytics`, {
			params: period ? { period } : undefined
		});
		return response.data;
	}
};

// Conversation API
export const chatConversationsApi = {
	async list(params?: {
		status?: string;
		assigned_to?: string | number;
		widget_id?: number;
	}): Promise<{ data: ChatConversation[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
		const response = await apiClient.get<{
			data: ChatConversation[];
			meta: { current_page: number; last_page: number; per_page: number; total: number };
		}>('/chat/conversations', { params });
		return response;
	},

	async get(id: number): Promise<ChatConversation> {
		const response = await apiClient.get<{ data: ChatConversation }>(`/chat/conversations/${id}`);
		return response.data;
	},

	async update(
		id: number,
		data: {
			status?: string;
			priority?: string;
			department?: string;
			subject?: string;
			tags?: string[];
		}
	): Promise<ChatConversation> {
		const response = await apiClient.put<{ data: ChatConversation }>(`/chat/conversations/${id}`, data);
		return response.data;
	},

	async assign(id: number, userId: number): Promise<ChatConversation> {
		const response = await apiClient.post<{ data: ChatConversation }>(`/chat/conversations/${id}/assign`, {
			user_id: userId
		});
		return response.data;
	},

	async close(id: number): Promise<ChatConversation> {
		const response = await apiClient.post<{ data: ChatConversation }>(`/chat/conversations/${id}/close`);
		return response.data;
	},

	async reopen(id: number): Promise<ChatConversation> {
		const response = await apiClient.post<{ data: ChatConversation }>(`/chat/conversations/${id}/reopen`);
		return response.data;
	},

	async getMessages(id: number): Promise<ChatMessage[]> {
		const response = await apiClient.get<{ data: ChatMessage[] }>(`/chat/conversations/${id}/messages`);
		return response.data;
	},

	async sendMessage(
		id: number,
		data: {
			content: string;
			content_type?: string;
			attachments?: string[];
			is_internal?: boolean;
		}
	): Promise<ChatMessage> {
		const response = await apiClient.post<{ data: ChatMessage }>(`/chat/conversations/${id}/messages`, data);
		return response.data;
	}
};

// Agent API
export const chatAgentsApi = {
	async getStatus(): Promise<ChatAgentStatus> {
		const response = await apiClient.get<{ data: ChatAgentStatus }>('/chat/agents/status');
		return response.data;
	},

	async updateStatus(data: {
		status: 'online' | 'away' | 'busy' | 'offline';
		max_conversations?: number;
		departments?: string[];
	}): Promise<ChatAgentStatus> {
		const response = await apiClient.put<{ data: ChatAgentStatus }>('/chat/agents/status', data);
		return response.data;
	},

	async list(): Promise<ChatAgentStatus[]> {
		const response = await apiClient.get<{ data: ChatAgentStatus[] }>('/chat/agents');
		return response.data;
	},

	async getPerformance(period?: string): Promise<AgentPerformance[]> {
		const response = await apiClient.get<{ data: AgentPerformance[] }>('/chat/agents/performance', {
			params: period ? { period } : undefined
		});
		return response.data;
	}
};

// Canned Responses API
export const chatCannedResponsesApi = {
	async list(): Promise<ChatCannedResponse[]> {
		const response = await apiClient.get<{ data: ChatCannedResponse[] }>('/chat/canned-responses');
		return response.data;
	},

	async search(query: string): Promise<ChatCannedResponse[]> {
		const response = await apiClient.get<{ data: ChatCannedResponse[] }>('/chat/canned-responses/search', {
			params: { q: query }
		});
		return response.data;
	},

	async create(data: {
		shortcut: string;
		title: string;
		content: string;
		category?: string;
		is_global?: boolean;
	}): Promise<ChatCannedResponse> {
		const response = await apiClient.post<{ data: ChatCannedResponse }>('/chat/canned-responses', data);
		return response.data;
	},

	async update(id: number, data: Partial<Parameters<typeof this.create>[0]>): Promise<ChatCannedResponse> {
		const response = await apiClient.put<{ data: ChatCannedResponse }>(`/chat/canned-responses/${id}`, data);
		return response.data;
	},

	async delete(id: number): Promise<void> {
		await apiClient.delete(`/chat/canned-responses/${id}`);
	},

	async use(id: number, variables?: Record<string, string>): Promise<{ content: string }> {
		const response = await apiClient.post<{ data: { content: string } }>(`/chat/canned-responses/${id}/use`, {
			variables
		});
		return response.data;
	}
};

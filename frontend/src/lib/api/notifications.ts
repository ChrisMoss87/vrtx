import { apiClient } from './client';

// Notification categories
export type NotificationCategory =
	| 'approvals'
	| 'assignments'
	| 'mentions'
	| 'updates'
	| 'reminders'
	| 'deals'
	| 'tasks'
	| 'system';

// Email frequency options
export type EmailFrequency = 'immediate' | 'hourly' | 'daily' | 'weekly';

export interface Notification {
	id: number;
	type: string;
	category: NotificationCategory;
	title: string;
	body: string | null;
	icon: string | null;
	icon_color: string | null;
	action_url: string | null;
	action_label: string | null;
	data: Record<string, unknown> | null;
	read_at: string | null;
	created_at: string;
}

export interface NotificationPreference {
	category: NotificationCategory;
	label: string;
	description: string;
	icon: string;
	in_app: boolean;
	email: boolean;
	push: boolean;
	email_frequency: EmailFrequency;
}

export interface NotificationSchedule {
	dnd_enabled: boolean;
	quiet_hours_enabled: boolean;
	quiet_hours_start: string | null;
	quiet_hours_end: string | null;
	weekend_notifications: boolean;
	timezone: string;
}

export interface NotificationListResponse {
	data: Notification[];
	meta: {
		unread_count: number;
	};
}

export interface NotificationPreferencesResponse {
	data: NotificationPreference[];
	meta: {
		frequencies: Array<{ value: EmailFrequency; label: string }>;
	};
}

export const notificationsApi = {
	/**
	 * Get user's notifications
	 */
	async list(params?: {
		category?: NotificationCategory;
		unread_only?: boolean;
		limit?: number;
		offset?: number;
	}): Promise<NotificationListResponse> {
		const searchParams = new URLSearchParams();
		if (params?.category) searchParams.set('category', params.category);
		if (params?.unread_only) searchParams.set('unread_only', 'true');
		if (params?.limit) searchParams.set('limit', params.limit.toString());
		if (params?.offset) searchParams.set('offset', params.offset.toString());

		const query = searchParams.toString();
		return apiClient.get<NotificationListResponse>(`/notifications${query ? `?${query}` : ''}`);
	},

	/**
	 * Get unread notification count
	 */
	async getUnreadCount(category?: NotificationCategory): Promise<{ data: { count: number } }> {
		const query = category ? `?category=${category}` : '';
		return apiClient.get<{ data: { count: number } }>(`/notifications/unread-count${query}`);
	},

	/**
	 * Mark a notification as read
	 */
	async markAsRead(id: number): Promise<{ message: string }> {
		return apiClient.post<{ message: string }>(`/notifications/${id}/read`);
	},

	/**
	 * Mark all notifications as read
	 */
	async markAllAsRead(category?: NotificationCategory): Promise<{ message: string; data: { count: number } }> {
		return apiClient.post<{ message: string; data: { count: number } }>('/notifications/mark-all-read', {
			category
		});
	},

	/**
	 * Archive a notification
	 */
	async archive(id: number): Promise<{ message: string }> {
		return apiClient.post<{ message: string }>(`/notifications/${id}/archive`);
	},

	/**
	 * Get notification preferences
	 */
	async getPreferences(): Promise<NotificationPreferencesResponse> {
		return apiClient.get<NotificationPreferencesResponse>('/notifications/preferences');
	},

	/**
	 * Update notification preferences
	 */
	async updatePreferences(
		preferences: Array<{
			category: NotificationCategory;
			in_app?: boolean;
			email?: boolean;
			push?: boolean;
			email_frequency?: EmailFrequency;
		}>
	): Promise<{ message: string }> {
		return apiClient.put<{ message: string }>('/notifications/preferences', { preferences });
	},

	/**
	 * Get notification schedule (quiet hours, DND)
	 */
	async getSchedule(): Promise<{ data: NotificationSchedule }> {
		return apiClient.get<{ data: NotificationSchedule }>('/notifications/schedule');
	},

	/**
	 * Update notification schedule
	 */
	async updateSchedule(schedule: Partial<NotificationSchedule>): Promise<{ message: string; data: NotificationSchedule }> {
		return apiClient.put<{ message: string; data: NotificationSchedule }>('/notifications/schedule', schedule);
	}
};

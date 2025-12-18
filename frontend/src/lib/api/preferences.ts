import { apiClient } from './client';

export interface UserPreferences {
	// Display
	sidebar_style?: 'rail' | 'collapsible';
	theme?: 'light' | 'dark' | 'system';
	compact_mode?: boolean;
	default_landing_page?: 'dashboard' | 'modules' | string;

	// Tables & Lists
	default_rows_per_page?: 10 | 25 | 50 | 100;
	default_list_view?: 'table' | 'kanban' | 'cards';

	// Notifications
	email_notifications?: boolean;
	desktop_notifications?: boolean;
	notification_sounds?: boolean;

	// Date & Time
	date_format?: 'MM/DD/YYYY' | 'DD/MM/YYYY' | 'YYYY-MM-DD';
	time_format?: '12h' | '24h';
	week_starts_on?: 'sunday' | 'monday';
	timezone?: string;

	// Communication
	email_signature?: string;
	calendar_sync?: boolean;

	[key: string]: unknown;
}

export const preferencesApi = {
	/**
	 * Get all user preferences
	 */
	async getAll(): Promise<{ data: UserPreferences }> {
		return apiClient.get<{ data: UserPreferences }>('/preferences');
	},

	/**
	 * Get a specific preference
	 */
	async get<T = unknown>(key: string): Promise<{ data: { key: string; value: T } }> {
		return apiClient.get<{ data: { key: string; value: T } }>(`/preferences/${key}`);
	},

	/**
	 * Update multiple preferences
	 */
	async update(preferences: Partial<UserPreferences>): Promise<{ data: UserPreferences }> {
		return apiClient.put<{ data: UserPreferences }>('/preferences', { preferences });
	},

	/**
	 * Set a single preference
	 */
	async set<T = unknown>(key: string, value: T): Promise<{ data: { key: string; value: T } }> {
		return apiClient.post<{ data: { key: string; value: T } }>('/preferences/set', { key, value });
	}
};

import { apiClient } from './client';

export interface UserPreferences {
	sidebar_style?: 'rail' | 'collapsible';
	theme?: 'light' | 'dark' | 'system';
	compact_mode?: boolean;
	notifications_enabled?: boolean;
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

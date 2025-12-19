import { apiClient } from './client';

export interface UserSearchResult {
	id: string;
	name: string;
	email: string;
	avatar?: string;
}

interface UserSearchResponse {
	success: boolean;
	users: UserSearchResult[];
}

export interface Role {
	id: number;
	name: string;
}

export interface User {
	id: number;
	name: string;
	email: string;
	email_verified_at: string | null;
	is_active?: boolean;
	created_at: string;
	updated_at: string;
	roles: Role[];
}

export interface UserSession {
	id: string;
	ip_address: string | null;
	user_agent: string | null;
	last_activity: string;
}

export interface UserListParams {
	search?: string;
	role?: string;
	status?: 'active' | 'inactive';
	per_page?: number;
	page?: number;
}

export interface UserListResponse {
	data: User[];
	meta: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}

export interface CreateUserData {
	name: string;
	email: string;
	password?: string;
	roles?: number[];
	send_invite?: boolean;
}

export interface UpdateUserData {
	name?: string;
	email?: string;
	roles?: number[];
}

/**
 * Search users for mentions
 */
export async function searchUsers(query: string, limit: number = 10): Promise<UserSearchResult[]> {
	const response = await apiClient.get<UserSearchResponse>('/users/search', {
		params: {
			q: query,
			limit: String(limit)
		}
	});
	return response.users;
}

export const usersApi = {
	/**
	 * List all users with pagination
	 */
	async list(params: UserListParams = {}): Promise<UserListResponse> {
		const searchParams = new URLSearchParams();
		Object.entries(params).forEach(([key, value]) => {
			if (value !== undefined && value !== null) {
				searchParams.append(key, String(value));
			}
		});
		const queryString = searchParams.toString();
		const url = queryString ? `/users?${queryString}` : '/users';
		return apiClient.get<UserListResponse>(url);
	},

	/**
	 * Get a single user by ID
	 */
	async get(id: number): Promise<{ data: User }> {
		return apiClient.get<{ data: User }>(`/users/${id}`);
	},

	/**
	 * Create a new user
	 */
	async create(data: CreateUserData): Promise<{ message: string; data: User }> {
		return apiClient.post<{ message: string; data: User }>('/users', data);
	},

	/**
	 * Update an existing user
	 */
	async update(id: number, data: UpdateUserData): Promise<{ message: string; data: User }> {
		return apiClient.put<{ message: string; data: User }>(`/users/${id}`, data);
	},

	/**
	 * Delete a user
	 */
	async delete(id: number): Promise<{ message: string }> {
		return apiClient.delete<{ message: string }>(`/users/${id}`);
	},

	/**
	 * Toggle user active status
	 */
	async toggleStatus(id: number): Promise<{ message: string; data: { id: number; is_active: boolean } }> {
		return apiClient.post<{ message: string; data: { id: number; is_active: boolean } }>(
			`/users/${id}/toggle-status`
		);
	},

	/**
	 * Reset user password
	 */
	async resetPassword(
		id: number,
		data?: { new_password?: string; send_email?: boolean }
	): Promise<{ message: string; data?: { temporary_password: string } }> {
		return apiClient.post<{ message: string; data?: { temporary_password: string } }>(
			`/users/${id}/reset-password`,
			data || {}
		);
	},

	/**
	 * Get user sessions
	 */
	async getSessions(id: number): Promise<{ data: UserSession[] }> {
		return apiClient.get<{ data: UserSession[] }>(`/users/${id}/sessions`);
	},

	/**
	 * Revoke a specific user session
	 */
	async revokeSession(userId: number, sessionId: string): Promise<{ message: string }> {
		return apiClient.delete<{ message: string }>(`/users/${userId}/sessions/${sessionId}`);
	},

	/**
	 * Revoke all user sessions
	 */
	async revokeAllSessions(userId: number): Promise<{ message: string }> {
		return apiClient.delete<{ message: string }>(`/users/${userId}/sessions`);
	}
};

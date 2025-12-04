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

/**
 * Search users for mentions
 */
export async function searchUsers(query: string, limit: number = 10): Promise<UserSearchResult[]> {
	const response = await apiClient.get<UserSearchResponse>('/users/search', {
		q: query,
		limit: String(limit)
	});
	return response.users;
}

import { apiClient } from './client';
import { authStore } from '$lib/stores/auth.svelte';

interface RegisterData {
	name: string;
	email: string;
	password: string;
	password_confirmation: string;
}

interface LoginData {
	email: string;
	password: string;
}

interface AuthResponse {
	data: {
		user: {
			id: number;
			name: string;
			email: string;
		};
		token: string;
	};
	message: string;
}

export const authApi = {
	async register(data: RegisterData): Promise<AuthResponse> {
		const response = await apiClient.post<AuthResponse>('/auth/register', data);
		authStore.setAuth(response.data.user, response.data.token);
		apiClient.setAuthToken(response.data.token);
		return response;
	},

	async login(data: LoginData): Promise<AuthResponse> {
		const response = await apiClient.post<AuthResponse>('/auth/login', data);
		authStore.setAuth(response.data.user, response.data.token);
		apiClient.setAuthToken(response.data.token);
		return response;
	},

	async logout(): Promise<void> {
		try {
			await apiClient.post('/auth/logout');
		} finally {
			authStore.clearAuth();
			apiClient.removeAuthToken();
		}
	},

	async me(): Promise<{ data: { id: number; name: string; email: string } }> {
		return apiClient.get('/auth/me');
	}
};

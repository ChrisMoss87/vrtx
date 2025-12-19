import { browser } from '$app/environment';
import { goto } from '$app/navigation';
import { ApiError, type ApiErrorData } from './utils';

interface FetchOptions extends RequestInit {
	params?: Record<string, string | number | boolean | undefined>;
	responseType?: 'json' | 'blob';
}

export interface PaginatedResponse<T> {
	data: T[];
	current_page: number;
	last_page: number;
	per_page: number;
	total: number;
	from: number | null;
	to: number | null;
}

export class ApiClient {
	private defaultHeaders: Record<string, string>;

	constructor() {
		this.defaultHeaders = {
			'Content-Type': 'application/json',
			Accept: 'application/json'
		};
	}

	// Get API URL dynamically at request time to ensure correct tenant domain
	private getBaseUrl(): string {
		if (!browser) {
			return 'http://localhost:8000/api/v1';
		}
		// Use current browser origin for tenant-aware API calls
		return `${window.location.origin}/api/v1`;
	}

	private buildUrl(endpoint: string, params?: Record<string, string | number | boolean | undefined>): string {
		const baseUrl = this.getBaseUrl();
		// Remove leading slash from endpoint if present
		const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
		const url = new URL(cleanEndpoint, baseUrl.endsWith('/') ? baseUrl : baseUrl + '/');
		if (params) {
			Object.entries(params).forEach(([key, value]) => {
				if (value !== undefined) {
					url.searchParams.append(key, String(value));
				}
			});
		}
		return url.toString();
	}

	private async request<T>(endpoint: string, options: FetchOptions = {}): Promise<T> {
		const { params, ...fetchOptions } = options;
		const url = this.buildUrl(endpoint, params);

		// Get auth token from localStorage if available
		const headers: Record<string, string> = {
			...this.defaultHeaders,
			...((fetchOptions.headers as Record<string, string>) || {})
		};

		if (browser) {
			const token = localStorage.getItem('auth_token');
			if (token) {
				headers['Authorization'] = `Bearer ${token}`;
			}
		}

		const response = await fetch(url, {
			...fetchOptions,
			headers
		});

		if (!response.ok) {
			const errorData: ApiErrorData = await response
				.json()
				.catch(() => ({ message: response.statusText }));

			// Log in development
			if (import.meta.env.DEV) {
				console.error('API Error Response:', {
					status: response.status,
					statusText: response.statusText,
					url,
					data: errorData
				});
			}

			// Handle 401 Unauthorized - redirect to login
			if (response.status === 401 && browser) {
				// Clear stored auth data
				localStorage.removeItem('auth_token');
				localStorage.removeItem('auth_user');

				// Get current path for redirect after login
				const currentPath = window.location.pathname;
				const redirectUrl =
					currentPath !== '/login' ? `?redirect=${encodeURIComponent(currentPath)}` : '';

				// Redirect to login page
				goto(`/login${redirectUrl}`);
			}

			// Create properly typed ApiError
			throw new ApiError(
				errorData.message || errorData.error || 'API request failed',
				response.status,
				response.statusText,
				errorData
			);
		}

		return response.json();
	}

	async get<T>(endpoint: string, options?: { params?: Record<string, string | number | boolean | undefined>; responseType?: 'json' | 'blob' }): Promise<T> {
		return this.request<T>(endpoint, { method: 'GET', ...options });
	}

	async post<T>(endpoint: string, data?: unknown): Promise<T> {
		return this.request<T>(endpoint, {
			method: 'POST',
			body: JSON.stringify(data)
		});
	}

	async put<T>(endpoint: string, data?: unknown): Promise<T> {
		return this.request<T>(endpoint, {
			method: 'PUT',
			body: JSON.stringify(data)
		});
	}

	async patch<T>(endpoint: string, data?: unknown): Promise<T> {
		return this.request<T>(endpoint, {
			method: 'PATCH',
			body: JSON.stringify(data)
		});
	}

	async delete<T>(endpoint: string): Promise<T> {
		return this.request<T>(endpoint, { method: 'DELETE' });
	}

	async upload<T>(endpoint: string, formData: FormData): Promise<T> {
		const baseUrl = this.getBaseUrl();
		const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
		const url = new URL(cleanEndpoint, baseUrl.endsWith('/') ? baseUrl : baseUrl + '/');

		const headers: Record<string, string> = {
			Accept: 'application/json'
		};

		if (browser) {
			const token = localStorage.getItem('auth_token');
			if (token) {
				headers['Authorization'] = `Bearer ${token}`;
			}
		}

		const response = await fetch(url.toString(), {
			method: 'POST',
			headers,
			body: formData
		});

		if (!response.ok) {
			const errorData: ApiErrorData = await response
				.json()
				.catch(() => ({ message: response.statusText }));

			throw new ApiError(
				errorData.message || errorData.error || 'Upload failed',
				response.status,
				response.statusText,
				errorData
			);
		}

		return response.json();
	}

	setAuthToken(token: string) {
		this.defaultHeaders = {
			...this.defaultHeaders,
			Authorization: `Bearer ${token}`
		};
	}

	removeAuthToken() {
		const headers = { ...this.defaultHeaders };
		delete headers['Authorization'];
		this.defaultHeaders = headers;
	}
}

export const apiClient = new ApiClient();

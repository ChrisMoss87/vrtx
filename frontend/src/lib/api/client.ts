import { browser } from '$app/environment';

interface FetchOptions extends RequestInit {
	params?: Record<string, string>;
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

	private buildUrl(endpoint: string, params?: Record<string, string>): string {
		const baseUrl = this.getBaseUrl();
		// Remove leading slash from endpoint if present
		const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
		const url = new URL(cleanEndpoint, baseUrl.endsWith('/') ? baseUrl : baseUrl + '/');
		if (params) {
			Object.entries(params).forEach(([key, value]) => {
				url.searchParams.append(key, value);
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
			const errorData = await response.json().catch(() => ({ message: response.statusText }));
			console.error('API Error Response:', {
				status: response.status,
				statusText: response.statusText,
				url,
				data: errorData
			});

			// Create error object with more details
			const error: any = new Error(errorData.message || 'API request failed');
			error.response = {
				status: response.status,
				statusText: response.statusText,
				data: errorData
			};
			throw error;
		}

		return response.json();
	}

	async get<T>(endpoint: string, params?: Record<string, string>): Promise<T> {
		return this.request<T>(endpoint, { method: 'GET', params });
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

import { env } from '$env/dynamic/public';

const API_URL = env.PUBLIC_API_URL || 'https://api.vrtx.local';

interface FetchOptions extends RequestInit {
	params?: Record<string, string>;
}

export class ApiClient {
	private baseUrl: string;
	private defaultHeaders: HeadersInit;

	constructor(baseUrl: string = API_URL) {
		this.baseUrl = baseUrl;
		this.defaultHeaders = {
			'Content-Type': 'application/json',
			Accept: 'application/json'
		};
	}

	private buildUrl(endpoint: string, params?: Record<string, string>): string {
		const url = new URL(endpoint, this.baseUrl);
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

		const response = await fetch(url, {
			...fetchOptions,
			headers: {
				...this.defaultHeaders,
				...fetchOptions.headers
			}
		});

		if (!response.ok) {
			const error = await response.json().catch(() => ({ message: response.statusText }));
			throw new Error(error.message || 'API request failed');
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

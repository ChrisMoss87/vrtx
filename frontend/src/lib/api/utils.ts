/**
 * Shared API utilities for DRY code and consistent patterns
 */

import type { Result } from '$lib/utils/tryCatch';
import { tryCatch } from '$lib/utils/tryCatch';

// ============================================================================
// Query Parameter Builder
// ============================================================================

/**
 * Filter value type for query parameters
 */
export type QueryParamValue = string | number | boolean | undefined | null;

/**
 * Options for building query parameters
 */
export interface QueryParamOptions {
	/** Only include keys that are in this list */
	includeOnly?: string[];
	/** Exclude these keys from the result */
	exclude?: string[];
	/** Custom transformers for specific keys */
	transformers?: Record<string, (value: unknown) => string | undefined>;
}

/**
 * Build query parameters from an object, filtering out undefined/null values
 * and converting all values to strings.
 *
 * @example
 * const params = buildQueryParams({
 *   module_id: 1,
 *   active: true,
 *   search: undefined,
 *   name: 'test'
 * });
 * // Result: { module_id: '1', active: 'true', name: 'test' }
 *
 * @example
 * // With options
 * const params = buildQueryParams(
 *   { a: 1, b: 2, c: 3 },
 *   { includeOnly: ['a', 'b'] }
 * );
 * // Result: { a: '1', b: '2' }
 */
export function buildQueryParams(
	params: Record<string, QueryParamValue> | undefined | null,
	options?: QueryParamOptions
): Record<string, string> {
	if (!params) return {};

	const result: Record<string, string> = {};

	for (const [key, value] of Object.entries(params)) {
		// Skip if value is undefined or null
		if (value === undefined || value === null) continue;

		// Skip if not in includeOnly list (when provided)
		if (options?.includeOnly && !options.includeOnly.includes(key)) continue;

		// Skip if in exclude list
		if (options?.exclude?.includes(key)) continue;

		// Apply custom transformer if provided
		if (options?.transformers?.[key]) {
			const transformed = options.transformers[key](value);
			if (transformed !== undefined) {
				result[key] = transformed;
			}
			continue;
		}

		// Convert to string
		result[key] = String(value);
	}

	return result;
}

/**
 * Build pagination query parameters
 */
export function buildPaginationParams(params?: {
	page?: number;
	per_page?: number;
	sort_by?: string;
	sort_direction?: 'asc' | 'desc';
}): Record<string, string> {
	return buildQueryParams(params);
}

// ============================================================================
// API Error Types
// ============================================================================

/**
 * Structured API error with response details
 */
export class ApiError extends Error {
	readonly status: number;
	readonly statusText: string;
	readonly data: ApiErrorData;
	readonly isApiError = true as const;

	constructor(message: string, status: number, statusText: string, data: ApiErrorData) {
		super(message);
		this.name = 'ApiError';
		this.status = status;
		this.statusText = statusText;
		this.data = data;
	}

	/**
	 * Check if error is a validation error (422)
	 */
	isValidationError(): boolean {
		return this.status === 422;
	}

	/**
	 * Check if error is unauthorized (401)
	 */
	isUnauthorized(): boolean {
		return this.status === 401;
	}

	/**
	 * Check if error is forbidden (403)
	 */
	isForbidden(): boolean {
		return this.status === 403;
	}

	/**
	 * Check if error is not found (404)
	 */
	isNotFound(): boolean {
		return this.status === 404;
	}

	/**
	 * Check if error is a server error (5xx)
	 */
	isServerError(): boolean {
		return this.status >= 500;
	}

	/**
	 * Get validation errors if this is a validation error
	 */
	getValidationErrors(): Record<string, string[]> {
		if (this.isValidationError() && this.data.errors) {
			return this.data.errors;
		}
		return {};
	}

	/**
	 * Get first validation error for a field
	 */
	getFieldError(field: string): string | undefined {
		const errors = this.getValidationErrors();
		return errors[field]?.[0];
	}

	/**
	 * Get all validation errors as a flat array of messages
	 */
	getAllValidationMessages(): string[] {
		const errors = this.getValidationErrors();
		return Object.values(errors).flat();
	}
}

/**
 * API error response data structure
 */
export interface ApiErrorData {
	message?: string;
	error?: string;
	errors?: Record<string, string[]>;
	[key: string]: unknown;
}

/**
 * Type guard to check if an error is an ApiError
 */
export function isApiError(error: unknown): error is ApiError {
	return error instanceof ApiError || (error as ApiError)?.isApiError === true;
}

// ============================================================================
// Safe API Call Wrapper
// ============================================================================

/**
 * Options for safe API calls
 */
export interface SafeApiCallOptions {
	/** Custom error message to use instead of API error message */
	errorMessage?: string;
	/** Whether to log errors to console (default: true in development) */
	logErrors?: boolean;
}

/**
 * Wrap an API call in a try-catch and return a Result type.
 * This is a convenience wrapper around tryCatch that provides
 * better typing for API errors.
 *
 * @example
 * const { data, error } = await safeApiCall(getUsers());
 * if (error) {
 *   if (isApiError(error) && error.isValidationError()) {
 *     // Handle validation errors
 *   }
 *   toast.error(error.message);
 *   return;
 * }
 * // data is typed correctly
 */
export async function safeApiCall<T>(
	promise: Promise<T>,
	options?: SafeApiCallOptions
): Promise<Result<T, ApiError | Error>> {
	const result = await tryCatch<T, ApiError | Error>(promise);

	if (result.error) {
		// Log errors in development
		if (options?.logErrors !== false && import.meta.env.DEV) {
			console.error('[API Error]', result.error);
		}

		// Override error message if provided
		if (options?.errorMessage && result.error instanceof Error) {
			result.error.message = options.errorMessage;
		}
	}

	return result;
}

// ============================================================================
// Response Unwrapping Utilities
// ============================================================================

/**
 * Common API response wrapper types
 */
export interface DataResponse<T> {
	data: T;
	message?: string;
}

export interface SuccessResponse<T> {
	success: boolean;
	data?: T;
	message?: string;
}

export interface ItemResponse<K extends string, T> {
	success?: boolean;
	message?: string;
	[key: string]: T | boolean | string | undefined;
}

export interface ListResponse<K extends string, T> {
	success?: boolean;
	[key: string]: T[] | boolean | undefined;
}

/**
 * Unwrap a { data: T } response
 */
export function unwrapData<T>(response: DataResponse<T>): T {
	return response.data;
}

/**
 * Unwrap a response with a named property
 *
 * @example
 * const workflows = unwrapProperty(response, 'workflows');
 */
export function unwrapProperty<T>(response: Record<string, unknown>, key: string): T {
	return response[key] as T;
}

// ============================================================================
// Pagination Types
// ============================================================================

/**
 * Standard pagination metadata
 */
export interface PaginationMeta {
	current_page: number;
	last_page: number;
	per_page: number;
	total: number;
	from?: number | null;
	to?: number | null;
}

/**
 * Paginated response structure
 */
export interface PaginatedResponse<T> {
	data: T[];
	meta?: PaginationMeta;
	current_page?: number;
	last_page?: number;
	per_page?: number;
	total?: number;
	from?: number | null;
	to?: number | null;
}

/**
 * Normalize pagination metadata from various response formats
 */
export function normalizePagination<T>(response: PaginatedResponse<T>): {
	data: T[];
	meta: PaginationMeta;
} {
	const meta: PaginationMeta = response.meta ?? {
		current_page: response.current_page ?? 1,
		last_page: response.last_page ?? 1,
		per_page: response.per_page ?? 25,
		total: response.total ?? response.data.length,
		from: response.from ?? null,
		to: response.to ?? null
	};

	return { data: response.data, meta };
}

// ============================================================================
// Request Helpers
// ============================================================================

/**
 * Create a debounced API call function
 *
 * @example
 * const debouncedSearch = createDebouncedApiCall(searchUsers, 300);
 * // In component:
 * onInput={(e) => debouncedSearch(e.target.value)}
 */
export function createDebouncedApiCall<TArgs extends unknown[], TResult>(
	fn: (...args: TArgs) => Promise<TResult>,
	delay: number
): (...args: TArgs) => Promise<TResult> {
	let timeoutId: ReturnType<typeof setTimeout> | null = null;
	let pendingPromise: Promise<TResult> | null = null;
	let resolvePromise: ((value: TResult) => void) | null = null;
	let rejectPromise: ((error: unknown) => void) | null = null;

	return (...args: TArgs): Promise<TResult> => {
		// Clear existing timeout
		if (timeoutId) {
			clearTimeout(timeoutId);
		}

		// Create new promise if none exists
		if (!pendingPromise) {
			pendingPromise = new Promise<TResult>((resolve, reject) => {
				resolvePromise = resolve;
				rejectPromise = reject;
			});
		}

		// Set new timeout
		timeoutId = setTimeout(async () => {
			try {
				const result = await fn(...args);
				resolvePromise?.(result);
			} catch (error) {
				rejectPromise?.(error);
			} finally {
				// Reset for next call
				pendingPromise = null;
				resolvePromise = null;
				rejectPromise = null;
				timeoutId = null;
			}
		}, delay);

		return pendingPromise;
	};
}

/**
 * Create a throttled API call function
 */
export function createThrottledApiCall<TArgs extends unknown[], TResult>(
	fn: (...args: TArgs) => Promise<TResult>,
	limit: number
): (...args: TArgs) => Promise<TResult> | undefined {
	let lastCall = 0;
	let pendingPromise: Promise<TResult> | null = null;

	return (...args: TArgs): Promise<TResult> | undefined => {
		const now = Date.now();

		if (now - lastCall >= limit) {
			lastCall = now;
			pendingPromise = fn(...args);
			return pendingPromise;
		}

		return pendingPromise ?? undefined;
	};
}

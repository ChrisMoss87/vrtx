/**
 * Lookup API Client
 *
 * Handles dynamic lookup searches for lookup fields
 */

import { apiClient } from './client';

export interface LookupResult {
	id: number;
	label: string;
	data: Record<string, unknown>;
}

export interface LookupSearchParams {
	q?: string;
	display_field?: string;
	limit?: number;
	selected_ids?: number[];
}

interface LookupResponse {
	results: LookupResult[];
}

/**
 * Search records for a lookup field
 */
export async function searchLookup(
	moduleApiName: string,
	params: LookupSearchParams = {}
): Promise<LookupResult[]> {
	const searchParams: Record<string, string> = {};

	if (params.q) {
		searchParams.q = params.q;
	}
	if (params.display_field) {
		searchParams.display_field = params.display_field;
	}
	if (params.limit) {
		searchParams.limit = params.limit.toString();
	}
	if (params.selected_ids && params.selected_ids.length > 0) {
		searchParams['selected_ids[]'] = params.selected_ids.join(',');
	}

	const response = await apiClient.get<LookupResponse>(
		`/records/${moduleApiName}/lookup`,
		searchParams
	);

	return response.results;
}

/**
 * Debounce helper for search input
 */
export function debounce<T extends (...args: unknown[]) => unknown>(
	fn: T,
	delay: number
): (...args: Parameters<T>) => void {
	let timeoutId: ReturnType<typeof setTimeout>;
	return (...args: Parameters<T>) => {
		clearTimeout(timeoutId);
		timeoutId = setTimeout(() => fn(...args), delay);
	};
}

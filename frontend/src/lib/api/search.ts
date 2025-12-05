import { apiClient } from './client';

export interface SearchResult {
	id: number;
	module_api_name: string;
	module_name: string;
	module_icon: string;
	primary_value: string;
	secondary_value: string | null;
}

export interface GroupedSearchResult {
	module: {
		api_name: string;
		name: string;
		icon: string;
	};
	results: Array<{
		id: number;
		primary_value: string;
		secondary_value: string | null;
		metadata: Record<string, unknown> | null;
	}>;
}

export interface SearchResponse {
	results: GroupedSearchResult[];
	total: number;
	query: string;
}

export interface QuickSearchResponse {
	results: SearchResult[];
	suggestions?: string[];
	type: 'results' | 'recent';
}

export interface SearchSuggestions {
	recent: string[];
	saved: Array<{
		name: string;
		query: string;
		saved: boolean;
	}>;
	modules: Array<{
		type: 'module';
		name: string;
		api_name: string;
		icon: string;
	}>;
}

export interface SavedSearch {
	id: number;
	name: string;
	query: string;
	type: string;
	module_api_name: string | null;
	filters: Record<string, unknown> | null;
	is_pinned: boolean;
	use_count: number;
	last_used_at: string | null;
}

export interface SearchHistoryItem {
	id: number;
	query: string;
	type: string;
	module_api_name: string | null;
	results_count: number;
	created_at: string;
}

export interface CommandPaletteData {
	modules: Array<{
		id: string;
		name: string;
		api_name: string;
		icon: string;
		type: 'navigation';
	}>;
	actions: Array<{
		id: string;
		name: string;
		icon: string;
		shortcut: string | null;
	}>;
	pinned_searches: Array<{
		id: string;
		name: string;
		query: string;
		type: 'saved_search';
	}>;
}

export const search = {
	/**
	 * Global search across all modules
	 */
	search: async (query: string, modules?: string[], limit = 20): Promise<SearchResponse> => {
		const params = new URLSearchParams();
		params.set('q', query);
		if (modules && modules.length > 0) {
			params.set('modules', modules.join(','));
		}
		params.set('limit', limit.toString());
		return apiClient.get<SearchResponse>(`/search?${params}`);
	},

	/**
	 * Quick search for instant results (as-you-type)
	 */
	quickSearch: async (query: string, limit = 8): Promise<QuickSearchResponse> => {
		const params = new URLSearchParams();
		params.set('q', query);
		params.set('limit', limit.toString());
		return apiClient.get<QuickSearchResponse>(`/search/quick?${params}`);
	},

	/**
	 * Get search suggestions
	 */
	suggestions: async (query?: string): Promise<SearchSuggestions> => {
		const params = query ? `?q=${encodeURIComponent(query)}` : '';
		return apiClient.get<SearchSuggestions>(`/search/suggestions${params}`);
	},

	/**
	 * Get search history
	 */
	history: async (limit = 20): Promise<{ history: SearchHistoryItem[] }> => {
		return apiClient.get<{ history: SearchHistoryItem[] }>(`/search/history?limit=${limit}`);
	},

	/**
	 * Clear search history
	 */
	clearHistory: async (): Promise<{ message: string }> => {
		return apiClient.delete<{ message: string }>('/search/history');
	},

	/**
	 * Get saved searches
	 */
	savedSearches: async (): Promise<{ data: SavedSearch[] }> => {
		return apiClient.get<{ data: SavedSearch[] }>('/search/saved');
	},

	/**
	 * Save a search
	 */
	saveSearch: async (data: {
		name: string;
		query: string;
		type?: string;
		module_api_name?: string;
		filters?: Record<string, unknown>;
		is_pinned?: boolean;
	}): Promise<{ message: string; search: { id: number; name: string; query: string; is_pinned: boolean } }> => {
		return apiClient.post<{ message: string; search: { id: number; name: string; query: string; is_pinned: boolean } }>('/search/saved', data);
	},

	/**
	 * Delete a saved search
	 */
	deleteSavedSearch: async (id: number): Promise<{ message: string }> => {
		return apiClient.delete<{ message: string }>(`/search/saved/${id}`);
	},

	/**
	 * Toggle pin status of a saved search
	 */
	togglePin: async (id: number): Promise<{ message: string; is_pinned: boolean }> => {
		return apiClient.post<{ message: string; is_pinned: boolean }>(`/search/saved/${id}/toggle-pin`);
	},

	/**
	 * Reindex search data (admin)
	 */
	reindex: async (module?: string): Promise<{ message: string; count: number }> => {
		const body = module ? { module } : {};
		return apiClient.post<{ message: string; count: number }>('/search/reindex', body);
	},

	/**
	 * Get command palette data
	 */
	commands: async (): Promise<CommandPaletteData> => {
		return apiClient.get<CommandPaletteData>('/search/commands');
	}
};

import { writable, derived, get } from 'svelte/store';
import { browser } from '$app/environment';
import { getActiveModules, type Module } from '$lib/api/modules';

const STORAGE_KEY = 'vrtx_modules_cache';
const FAVORITES_KEY = 'vrtx_favorite_modules';

// Get cached modules from localStorage
function getCachedModules(): Module[] {
	if (!browser) return [];
	try {
		const stored = localStorage.getItem(STORAGE_KEY);
		if (stored) {
			return JSON.parse(stored);
		}
	} catch (e) {
		console.error('Failed to parse cached modules:', e);
	}
	return [];
}

// Get favorite module api_names from localStorage
function getStoredFavorites(): string[] {
	if (!browser) return ['leads', 'deals']; // Default favorites
	try {
		const stored = localStorage.getItem(FAVORITES_KEY);
		if (stored) {
			return JSON.parse(stored);
		}
	} catch (e) {
		console.error('Failed to parse stored favorites:', e);
	}
	return ['leads', 'deals']; // Default favorites
}

function createModulesStore() {
	const { subscribe, set, update } = writable<Module[]>(getCachedModules());
	const loading = writable<boolean>(false);

	return {
		subscribe,
		loading: { subscribe: loading.subscribe },

		/**
		 * Load modules from backend and cache them
		 */
		async load() {
			loading.set(true);
			try {
				const modules = await getActiveModules();
				// Sort by display_order
				modules.sort((a, b) => a.display_order - b.display_order);

				// Cache modules
				if (browser) {
					localStorage.setItem(STORAGE_KEY, JSON.stringify(modules));
				}

				set(modules);
				return modules;
			} catch (error) {
				console.error('Failed to load modules:', error);
				throw error;
			} finally {
				loading.set(false);
			}
		},

		/**
		 * Refresh modules (alias for load, for semantic clarity)
		 */
		async refresh() {
			return this.load();
		},

		/**
		 * Update local state without fetching from server
		 * Useful after reordering on the settings page
		 */
		setModules(modules: Module[]) {
			const sorted = [...modules].sort((a, b) => a.display_order - b.display_order);
			if (browser) {
				localStorage.setItem(STORAGE_KEY, JSON.stringify(sorted));
			}
			set(sorted);
		},

		/**
		 * Get current modules without subscribing
		 */
		get(): Module[] {
			return get({ subscribe });
		}
	};
}

function createFavoritesStore() {
	const { subscribe, set, update } = writable<string[]>(getStoredFavorites());

	return {
		subscribe,

		/**
		 * Set favorite modules
		 */
		setFavorites(apiNames: string[]) {
			if (browser) {
				localStorage.setItem(FAVORITES_KEY, JSON.stringify(apiNames));
			}
			set(apiNames);
		},

		/**
		 * Toggle a module as favorite
		 */
		toggle(apiName: string) {
			update(favorites => {
				const newFavorites = favorites.includes(apiName)
					? favorites.filter(f => f !== apiName)
					: [...favorites, apiName];

				if (browser) {
					localStorage.setItem(FAVORITES_KEY, JSON.stringify(newFavorites));
				}

				return newFavorites;
			});
		},

		/**
		 * Check if a module is favorited
		 */
		isFavorite(apiName: string): boolean {
			return get({ subscribe }).includes(apiName);
		},

		/**
		 * Get current favorites without subscribing
		 */
		get(): string[] {
			return get({ subscribe });
		}
	};
}

export const modulesStore = createModulesStore();
export const favoritesStore = createFavoritesStore();

// Derived store that combines modules with their favorite status
export const modulesWithFavorites = derived(
	[modulesStore, favoritesStore],
	([$modules, $favorites]) => {
		return $modules.map(m => ({
			...m,
			isFavorite: $favorites.includes(m.api_name)
		}));
	}
);

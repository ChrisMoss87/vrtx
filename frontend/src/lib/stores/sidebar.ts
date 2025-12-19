import { writable, get } from 'svelte/store';
import { browser } from '$app/environment';
import { preferencesApi } from '$lib/api/preferences';

export type SidebarStyle = 'rail' | 'collapsible';

const STORAGE_KEY = 'vrtx_sidebar_style';

// Migrate old values to new names
function migrateStyle(style: string | null): SidebarStyle {
	if (style === 'zoho' || style === 'rail') return 'rail';
	if (style === 'figma' || style === 'collapsible') return 'collapsible';
	return 'collapsible';
}

// Get initial value from localStorage or default to 'collapsible'
function getInitialStyle(): SidebarStyle {
	if (!browser) return 'collapsible';
	const stored = localStorage.getItem(STORAGE_KEY);
	return migrateStyle(stored);
}

function createSidebarStore() {
	const { subscribe, set } = writable<SidebarStyle>(getInitialStyle());

	return {
		subscribe,

		/**
		 * Set the sidebar style and persist to backend
		 */
		async setStyle(style: SidebarStyle) {
			// Update local state immediately
			if (browser) {
				localStorage.setItem(STORAGE_KEY, style);
			}
			set(style);

			// Persist to backend
			try {
				await preferencesApi.set('sidebar_style', style);
			} catch (error) {
				console.error('Failed to save sidebar preference:', error);
			}
		},

		/**
		 * Toggle between rail and collapsible
		 */
		async toggle() {
			const current = get({ subscribe });
			const newStyle = current === 'rail' ? 'collapsible' : 'rail';
			await this.setStyle(newStyle);
		},

		/**
		 * Load preference from backend (called on app init)
		 */
		async load() {
			try {
				const response = await preferencesApi.getAll();
				const prefs = response.data;
				if (prefs?.sidebar_style) {
					// Migrate old values from backend
					const style = migrateStyle(prefs.sidebar_style as string);
					if (browser) {
						localStorage.setItem(STORAGE_KEY, style);
					}
					set(style);
					// If we migrated, update the backend with new value
					if (prefs.sidebar_style !== style) {
						await preferencesApi.set('sidebar_style', style);
					}
				}
			} catch (error) {
				console.error('Failed to load sidebar preference:', error);
			}
		},

		/**
		 * Set style without persisting (for initial load from backend)
		 */
		setFromBackend(style: SidebarStyle) {
			if (browser) {
				localStorage.setItem(STORAGE_KEY, style);
			}
			set(style);
		}
	};
}

export const sidebarStyle = createSidebarStore();

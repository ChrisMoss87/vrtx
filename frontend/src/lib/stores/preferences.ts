import { writable, derived, get } from 'svelte/store';
import { browser } from '$app/environment';
import { preferencesApi, type UserPreferences } from '$lib/api/preferences';

const STORAGE_KEY = 'vrtx_preferences';

// Default preferences
const defaultPreferences: UserPreferences = {
	// Display
	sidebar_style: 'collapsible',
	theme: 'system',
	compact_mode: false,
	default_landing_page: 'dashboard',

	// Tables & Lists
	default_rows_per_page: 25,
	default_list_view: 'table',

	// Notifications
	email_notifications: true,
	desktop_notifications: true,
	notification_sounds: true,

	// Date & Time
	date_format: 'MM/DD/YYYY',
	time_format: '12h',
	week_starts_on: 'sunday',
	timezone: browser ? Intl.DateTimeFormat().resolvedOptions().timeZone : 'America/New_York',

	// Communication
	email_signature: '',
	calendar_sync: false
};

function getStoredPreferences(): UserPreferences {
	if (!browser) return defaultPreferences;
	try {
		const stored = localStorage.getItem(STORAGE_KEY);
		if (stored) {
			return { ...defaultPreferences, ...JSON.parse(stored) };
		}
	} catch (e) {
		console.error('Failed to parse stored preferences:', e);
	}
	return defaultPreferences;
}

function createPreferencesStore() {
	const { subscribe, set, update } = writable<UserPreferences>(getStoredPreferences());

	return {
		subscribe,

		/**
		 * Load preferences from backend
		 */
		async load() {
			try {
				const response = await preferencesApi.getAll();
				const prefs = { ...defaultPreferences, ...response.data };
				if (browser) {
					localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
				}
				set(prefs);
				// Apply theme on load
				this.applyTheme(prefs.theme || 'system');
				// Apply compact mode on load
				this.applyCompactMode(prefs.compact_mode || false);
			} catch (error) {
				console.error('Failed to load preferences:', error);
			}
		},

		/**
		 * Set a single preference
		 */
		async set<K extends keyof UserPreferences>(key: K, value: UserPreferences[K]) {
			update((prefs) => {
				const updated = { ...prefs, [key]: value };
				if (browser) {
					localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
				}
				return updated;
			});

			// Apply side effects
			if (key === 'theme') {
				this.applyTheme(value as 'light' | 'dark' | 'system');
			} else if (key === 'compact_mode') {
				this.applyCompactMode(value as boolean);
			}

			// Persist to backend
			try {
				await preferencesApi.set(key as string, value);
			} catch (error) {
				console.error(`Failed to save preference ${String(key)}:`, error);
			}
		},

		/**
		 * Get current value of a preference
		 */
		get<K extends keyof UserPreferences>(key: K): UserPreferences[K] {
			const prefs = get({ subscribe });
			return prefs[key] ?? defaultPreferences[key];
		},

		/**
		 * Apply theme to document
		 */
		applyTheme(theme: 'light' | 'dark' | 'system') {
			if (!browser) return;

			const isDark =
				theme === 'dark' ||
				(theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

			if (isDark) {
				document.documentElement.classList.add('dark');
			} else {
				document.documentElement.classList.remove('dark');
			}
		},

		/**
		 * Apply compact mode to document
		 */
		applyCompactMode(compact: boolean) {
			if (!browser) return;

			if (compact) {
				document.documentElement.classList.add('compact');
			} else {
				document.documentElement.classList.remove('compact');
			}
		}
	};
}

export const preferences = createPreferencesStore();

// Derived stores for easy access to individual preferences
export const theme = derived(preferences, ($p) => $p.theme ?? 'system');
export const compactMode = derived(preferences, ($p) => $p.compact_mode ?? false);
export const defaultRowsPerPage = derived(preferences, ($p) => $p.default_rows_per_page ?? 25);
export const defaultListView = derived(preferences, ($p) => $p.default_list_view ?? 'table');
export const dateFormat = derived(preferences, ($p) => $p.date_format ?? 'MM/DD/YYYY');
export const timeFormat = derived(preferences, ($p) => $p.time_format ?? '12h');
export const weekStartsOn = derived(preferences, ($p) => $p.week_starts_on ?? 'sunday');
export const timezone = derived(preferences, ($p) => $p.timezone ?? 'America/New_York');
export const emailNotifications = derived(preferences, ($p) => $p.email_notifications ?? true);
export const desktopNotifications = derived(preferences, ($p) => $p.desktop_notifications ?? true);
export const notificationSounds = derived(preferences, ($p) => $p.notification_sounds ?? true);
export const emailSignature = derived(preferences, ($p) => $p.email_signature ?? '');
export const calendarSync = derived(preferences, ($p) => $p.calendar_sync ?? false);
export const defaultLandingPage = derived(preferences, ($p) => $p.default_landing_page ?? 'dashboard');

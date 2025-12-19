import { writable, derived, get } from 'svelte/store';
import { apiClient } from '$lib/api/client';

export interface UsageStats {
	used: number;
	limit: number | null;
	remaining: number | null;
	percentage: number | null;
	allowed: boolean;
}

export interface LicenseState {
	plan: string;
	status: string;
	billing_cycle: string;
	user_count: number;
	plugins: string[];
	features: string[];
	usage: Record<string, UsageStats>;
	trial_ends_at: string | null;
	current_period_end: string | null;
	loading: boolean;
	error: string | null;
}

const initialState: LicenseState = {
	plan: 'free',
	status: 'active',
	billing_cycle: 'monthly',
	user_count: 1,
	plugins: [],
	features: [],
	usage: {},
	trial_ends_at: null,
	current_period_end: null,
	loading: true,
	error: null
};

function createLicenseStore() {
	const { subscribe, set, update } = writable<LicenseState>(initialState);

	return {
		subscribe,

		/**
		 * Load license state from API
		 */
		async load() {
			update((s) => ({ ...s, loading: true, error: null }));

			try {
				const response = await apiClient.get<LicenseState>('/billing/license');
				set({
					...response,
					loading: false,
					error: null
				});
			} catch (error: unknown) {
				const message = error instanceof Error ? error.message : 'Failed to load license';
				update((s) => ({
					...s,
					loading: false,
					error: message
				}));
			}
		},

		/**
		 * Check if a specific plugin is licensed
		 */
		hasPlugin(slug: string): boolean {
			const state = get({ subscribe });
			return state.plugins.includes(slug);
		},

		/**
		 * Check if a specific feature is enabled
		 */
		hasFeature(key: string): boolean {
			const state = get({ subscribe });
			return state.features.includes(key);
		},

		/**
		 * Check if current plan meets minimum requirement
		 */
		hasPlan(requiredPlan: string): boolean {
			const planHierarchy: Record<string, number> = {
				free: 0,
				starter: 1,
				professional: 2,
				business: 3,
				enterprise: 4
			};

			const state = get({ subscribe });
			const currentLevel = planHierarchy[state.plan] ?? 0;
			const requiredLevel = planHierarchy[requiredPlan] ?? 0;

			return currentLevel >= requiredLevel;
		},

		/**
		 * Check usage limit for a metric
		 */
		checkUsage(metric: string): UsageStats | null {
			const state = get({ subscribe });
			return state.usage[metric] ?? null;
		},

		/**
		 * Clear store and reset to initial state
		 */
		reset() {
			set(initialState);
		}
	};
}

export const license = createLicenseStore();

// Derived stores for common checks
export const isPro = derived(license, ($l) =>
	['professional', 'business', 'enterprise'].includes($l.plan)
);

export const isBusiness = derived(license, ($l) => ['business', 'enterprise'].includes($l.plan));

export const isEnterprise = derived(license, ($l) => $l.plan === 'enterprise');

export const isTrialing = derived(license, ($l) => $l.status === 'trialing');

export const isPastDue = derived(license, ($l) => $l.status === 'past_due');

export const isCancelled = derived(license, ($l) => $l.status === 'cancelled');

// Helper to get trial days remaining
export const trialDaysRemaining = derived(license, ($l) => {
	if (!$l.trial_ends_at) return null;
	const endDate = new Date($l.trial_ends_at);
	const now = new Date();
	const diffTime = endDate.getTime() - now.getTime();
	const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
	return Math.max(0, diffDays);
});

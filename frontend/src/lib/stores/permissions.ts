import { writable, derived, get } from 'svelte/store';
import { browser } from '$app/environment';
import type { UserPermissions, ModulePermission } from '$lib/api/rbac';
import { getMyPermissions } from '$lib/api/rbac';

interface PermissionsState {
	loaded: boolean;
	loading: boolean;
	userId: number | null;
	isAdmin: boolean;
	roles: string[];
	systemPermissions: string[];
	modulePermissions: Record<string, ModulePermission>;
}

const initialState: PermissionsState = {
	loaded: false,
	loading: false,
	userId: null,
	isAdmin: false,
	roles: [],
	systemPermissions: [],
	modulePermissions: {}
};

function createPermissionsStore() {
	const { subscribe, set, update } = writable<PermissionsState>(initialState);

	return {
		subscribe,

		/**
		 * Load permissions from API
		 */
		async load() {
			if (!browser) return;

			update((state) => ({ ...state, loading: true }));

			try {
				const permissions = await getMyPermissions();
				set({
					loaded: true,
					loading: false,
					userId: permissions.user_id,
					isAdmin: permissions.is_admin,
					roles: permissions.roles,
					systemPermissions: permissions.system_permissions,
					modulePermissions: permissions.module_permissions
				});
			} catch {
				update((state) => ({ ...state, loading: false }));
			}
		},

		/**
		 * Reset permissions (on logout)
		 */
		reset() {
			set(initialState);
		},

		/**
		 * Check if user has a system permission
		 */
		hasPermission(permission: string): boolean {
			const state = get({ subscribe });
			if (state.isAdmin) return true;
			return state.systemPermissions.includes(permission);
		},

		/**
		 * Check if user has a role
		 */
		hasRole(role: string): boolean {
			const state = get({ subscribe });
			return state.roles.includes(role);
		},

		/**
		 * Check if user can perform action on a module
		 */
		canAccessModule(moduleApiName: string, action: 'view' | 'create' | 'edit' | 'delete' | 'export' | 'import'): boolean {
			const state = get({ subscribe });
			if (state.isAdmin) return true;

			const modulePerms = state.modulePermissions[moduleApiName];
			if (!modulePerms) return false;

			switch (action) {
				case 'view':
					return modulePerms.can_view;
				case 'create':
					return modulePerms.can_create;
				case 'edit':
					return modulePerms.can_edit;
				case 'delete':
					return modulePerms.can_delete;
				case 'export':
					return modulePerms.can_export;
				case 'import':
					return modulePerms.can_import;
				default:
					return false;
			}
		},

		/**
		 * Get record access level for a module
		 */
		getRecordAccessLevel(moduleApiName: string): 'own' | 'team' | 'all' | 'none' {
			const state = get({ subscribe });
			if (state.isAdmin) return 'all';

			const modulePerms = state.modulePermissions[moduleApiName];
			return modulePerms?.record_access_level ?? 'none';
		},

		/**
		 * Get hidden fields for a module
		 */
		getHiddenFields(moduleApiName: string): string[] {
			const state = get({ subscribe });
			if (state.isAdmin) return [];

			const modulePerms = state.modulePermissions[moduleApiName];
			return modulePerms?.field_restrictions ?? [];
		},

		/**
		 * Check if a field is hidden
		 */
		isFieldHidden(moduleApiName: string, fieldApiName: string): boolean {
			const hiddenFields = this.getHiddenFields(moduleApiName);
			return hiddenFields.includes(fieldApiName);
		}
	};
}

export const permissions = createPermissionsStore();

// Derived stores for common checks
export const isAdmin = derived(permissions, ($permissions) => $permissions.isAdmin);
export const isLoaded = derived(permissions, ($permissions) => $permissions.loaded);
export const userRoles = derived(permissions, ($permissions) => $permissions.roles);

// Helper function to check permission (can be used outside of Svelte components)
export function hasPermission(permission: string): boolean {
	return permissions.hasPermission(permission);
}

export function canAccessModule(moduleApiName: string, action: 'view' | 'create' | 'edit' | 'delete' | 'export' | 'import'): boolean {
	return permissions.canAccessModule(moduleApiName, action);
}

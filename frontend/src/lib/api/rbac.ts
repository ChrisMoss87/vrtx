import { apiClient } from './client';

// Types
export interface Role {
	id: number;
	name: string;
	permissions: string[];
	users_count: number;
}

export interface Permission {
	id: number;
	name: string;
	action: string;
}

export interface PermissionCategory {
	category: string;
	permissions: Permission[];
}

export interface ModulePermission {
	module_id: number;
	module_name: string;
	module_api_name: string;
	can_view: boolean;
	can_create: boolean;
	can_edit: boolean;
	can_delete: boolean;
	can_export: boolean;
	can_import: boolean;
	record_access_level: 'own' | 'team' | 'all' | 'none';
	field_restrictions: string[];
}

export interface UserPermissions {
	user_id: number;
	is_admin: boolean;
	roles: string[];
	system_permissions: string[];
	module_permissions: Record<string, ModulePermission>;
}

export interface User {
	id: number;
	name: string;
	email: string;
}

// API functions

/**
 * Get current user's full permissions
 */
export async function getMyPermissions(): Promise<UserPermissions> {
	const response = await apiClient.get<{ data: UserPermissions }>('/rbac/my-permissions');
	return response.data;
}

/**
 * Get all roles
 */
export async function getRoles(): Promise<Role[]> {
	const response = await apiClient.get<{ data: Role[] }>('/rbac/roles');
	return response.data;
}

/**
 * Get a single role with full details
 */
export async function getRole(
	id: number
): Promise<Role & { module_permissions: ModulePermission[] }> {
	const response = await apiClient.get<{
		data: Role & { module_permissions: ModulePermission[] };
	}>(`/rbac/roles/${id}`);
	return response.data;
}

/**
 * Create a new role
 */
export async function createRole(data: {
	name: string;
	permissions?: string[];
}): Promise<Role> {
	const response = await apiClient.post<{ data: Role; message: string }>('/rbac/roles', data);
	return response.data;
}

/**
 * Update a role
 */
export async function updateRole(
	id: number,
	data: { name?: string; permissions?: string[] }
): Promise<Role> {
	const response = await apiClient.put<{ data: Role; message: string }>(`/rbac/roles/${id}`, data);
	return response.data;
}

/**
 * Delete a role
 */
export async function deleteRole(id: number): Promise<void> {
	await apiClient.delete(`/rbac/roles/${id}`);
}

/**
 * Get all available permissions grouped by category
 */
export async function getPermissions(): Promise<PermissionCategory[]> {
	const response = await apiClient.get<{ data: PermissionCategory[] }>('/rbac/permissions');
	return response.data;
}

/**
 * Get module permissions for a role
 */
export async function getModulePermissions(roleId: number): Promise<ModulePermission[]> {
	const response = await apiClient.get<{ data: ModulePermission[] }>(
		`/rbac/roles/${roleId}/module-permissions`
	);
	return response.data;
}

/**
 * Update module permission for a role
 */
export async function updateModulePermission(
	roleId: number,
	data: Partial<ModulePermission> & { module_id: number }
): Promise<ModulePermission> {
	const response = await apiClient.put<{ data: ModulePermission; message: string }>(
		`/rbac/roles/${roleId}/module-permissions`,
		data
	);
	return response.data;
}

/**
 * Bulk update module permissions for a role
 */
export async function bulkUpdateModulePermissions(
	roleId: number,
	permissions: Array<Partial<ModulePermission> & { module_id: number }>
): Promise<void> {
	await apiClient.put(`/rbac/roles/${roleId}/module-permissions/bulk`, { permissions });
}

/**
 * Get users for a role
 */
export async function getRoleUsers(roleId: number): Promise<User[]> {
	const response = await apiClient.get<{ data: User[] }>(`/rbac/roles/${roleId}/users`);
	return response.data;
}

/**
 * Assign role to user
 */
export async function assignRoleToUser(userId: number, roleId: number): Promise<void> {
	await apiClient.post('/rbac/users/assign-role', { user_id: userId, role_id: roleId });
}

/**
 * Remove role from user
 */
export async function removeRoleFromUser(userId: number, roleId: number): Promise<void> {
	await apiClient.post('/rbac/users/remove-role', { user_id: userId, role_id: roleId });
}

/**
 * Get user permissions
 */
export async function getUserPermissions(
	userId: number
): Promise<{ user_id: number; roles: string[]; permissions: string[] }> {
	const response = await apiClient.get<{
		data: { user_id: number; roles: string[]; permissions: string[] };
	}>(`/rbac/users/${userId}/permissions`);
	return response.data;
}

/**
 * Sync user roles (replace all roles)
 */
export async function syncUserRoles(
	userId: number,
	roleIds: number[]
): Promise<{ user_id: number; roles: { id: number; name: string }[] }> {
	const response = await apiClient.put<{
		data: { user_id: number; roles: { id: number; name: string }[] };
	}>(`/rbac/users/${userId}/roles`, { roles: roleIds });
	return response.data;
}

<script lang="ts">
	import { onMount } from 'svelte';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import * as Table from '$lib/components/ui/table';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Badge } from '$lib/components/ui/badge';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Select from '$lib/components/ui/select';
	import { toast } from 'svelte-sonner';
	import {
		getRoles,
		getRole,
		createRole,
		updateRole,
		deleteRole,
		getPermissions,
		getModulePermissions,
		bulkUpdateModulePermissions,
		type Role,
		type PermissionCategory,
		type ModulePermission
	} from '$lib/api/rbac';
	import { Shield, Plus, Pencil, Trash2, Users, ChevronRight, Lock, Database } from 'lucide-svelte';

	let roles: Role[] = [];
	let permissionCategories: PermissionCategory[] = [];
	let loading = true;

	// Dialog states
	let showCreateDialog = false;
	let showEditDialog = false;
	let showDeleteDialog = false;
	let selectedRole: (Role & { module_permissions?: ModulePermission[] }) | null = null;

	// Form state
	let newRoleName = '';
	let selectedPermissions: string[] = [];
	let modulePermissions: ModulePermission[] = [];
	let activeTab = 'system';

	const ACCESS_LEVELS = [
		{ value: 'none', label: 'No Access' },
		{ value: 'own', label: 'Own Records Only' },
		{ value: 'team', label: 'Team Records' },
		{ value: 'all', label: 'All Records' }
	];

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			[roles, permissionCategories] = await Promise.all([getRoles(), getPermissions()]);
		} catch (error) {
			toast.error('Failed to load roles');
			console.error(error);
		} finally {
			loading = false;
		}
	}

	async function openEditDialog(role: Role) {
		try {
			const fullRole = await getRole(role.id);
			selectedRole = fullRole;
			selectedPermissions = [...fullRole.permissions];
			modulePermissions = fullRole.module_permissions || [];
			showEditDialog = true;
		} catch (error) {
			toast.error('Failed to load role details');
		}
	}

	async function handleCreateRole() {
		if (!newRoleName.trim()) {
			toast.error('Role name is required');
			return;
		}

		try {
			await createRole({ name: newRoleName, permissions: selectedPermissions });
			toast.success('Role created successfully');
			showCreateDialog = false;
			newRoleName = '';
			selectedPermissions = [];
			await loadData();
		} catch (error: unknown) {
			const errorMessage =
				error instanceof Error
					? error.message
					: (error as { response?: { data?: { message?: string } } })?.response?.data?.message ||
						'Failed to create role';
			toast.error(errorMessage);
		}
	}

	async function handleUpdateRole() {
		if (!selectedRole) return;

		try {
			await updateRole(selectedRole.id, {
				name: selectedRole.name,
				permissions: selectedPermissions
			});

			// Update module permissions
			if (modulePermissions.length > 0) {
				await bulkUpdateModulePermissions(
					selectedRole.id,
					modulePermissions.map((mp) => ({
						module_id: mp.module_id,
						can_view: mp.can_view,
						can_create: mp.can_create,
						can_edit: mp.can_edit,
						can_delete: mp.can_delete,
						can_export: mp.can_export,
						can_import: mp.can_import,
						record_access_level: mp.record_access_level,
						field_restrictions: mp.field_restrictions
					}))
				);
			}

			toast.success('Role updated successfully');
			showEditDialog = false;
			selectedRole = null;
			await loadData();
		} catch (error: unknown) {
			const errorMessage =
				error instanceof Error
					? error.message
					: (error as { response?: { data?: { message?: string } } })?.response?.data?.message ||
						'Failed to update role';
			toast.error(errorMessage);
		}
	}

	async function handleDeleteRole() {
		if (!selectedRole) return;

		try {
			await deleteRole(selectedRole.id);
			toast.success('Role deleted successfully');
			showDeleteDialog = false;
			selectedRole = null;
			await loadData();
		} catch (error: unknown) {
			const errorMessage =
				error instanceof Error
					? error.message
					: (error as { response?: { data?: { message?: string } } })?.response?.data?.message ||
						'Cannot delete this role';
			toast.error(errorMessage);
		}
	}

	function togglePermission(permission: string) {
		if (selectedPermissions.includes(permission)) {
			selectedPermissions = selectedPermissions.filter((p) => p !== permission);
		} else {
			selectedPermissions = [...selectedPermissions, permission];
		}
	}

	function toggleAllInCategory(category: PermissionCategory, checked: boolean) {
		const categoryPermissions = category.permissions.map((p) => p.name);
		if (checked) {
			selectedPermissions = [...new Set([...selectedPermissions, ...categoryPermissions])];
		} else {
			selectedPermissions = selectedPermissions.filter((p) => !categoryPermissions.includes(p));
		}
	}

	function isCategoryFullySelected(category: PermissionCategory): boolean {
		return category.permissions.every((p) => selectedPermissions.includes(p.name));
	}

	function updateModulePermission(
		moduleId: number,
		field: keyof ModulePermission,
		value: boolean | string | string[]
	) {
		modulePermissions = modulePermissions.map((mp) =>
			mp.module_id === moduleId ? { ...mp, [field]: value } : mp
		);
	}

	function isSystemRole(roleName: string): boolean {
		return ['admin', 'manager', 'sales_rep', 'read_only'].includes(roleName);
	}
</script>

<svelte:head>
	<title>Role Management | Settings</title>
</svelte:head>

<div class="container mx-auto max-w-6xl space-y-6 p-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div class="flex items-center gap-3">
			<Shield class="h-8 w-8 text-primary" />
			<div>
				<h1 class="text-2xl font-bold">Role Management</h1>
				<p class="text-muted-foreground">Manage roles and permissions for your organization</p>
			</div>
		</div>
		<Button
			onclick={() => {
				newRoleName = '';
				selectedPermissions = [];
				showCreateDialog = true;
			}}
		>
			<Plus class="mr-2 h-4 w-4" />
			Create Role
		</Button>
	</div>

	<!-- Roles List -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Roles</Card.Title>
			<Card.Description>Define what each role can access in your CRM</Card.Description>
		</Card.Header>
		<Card.Content>
			{#if loading}
				<div class="flex h-32 items-center justify-center">
					<div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
				</div>
			{:else}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Role</Table.Head>
							<Table.Head>Permissions</Table.Head>
							<Table.Head>Users</Table.Head>
							<Table.Head class="w-[100px]">Actions</Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each roles as role}
							<Table.Row>
								<Table.Cell>
									<div class="flex items-center gap-2">
										<span class="font-medium">{role.name}</span>
										{#if isSystemRole(role.name)}
											<Badge variant="secondary">System</Badge>
										{/if}
									</div>
								</Table.Cell>
								<Table.Cell>
									<span class="text-muted-foreground">{role.permissions.length} permissions</span>
								</Table.Cell>
								<Table.Cell>
									<div class="flex items-center gap-1">
										<Users class="h-4 w-4 text-muted-foreground" />
										<span>{role.users_count}</span>
									</div>
								</Table.Cell>
								<Table.Cell>
									<div class="flex items-center gap-2">
										<Button variant="ghost" size="icon" onclick={() => openEditDialog(role)}>
											<Pencil class="h-4 w-4" />
										</Button>
										{#if !isSystemRole(role.name)}
											<Button
												variant="ghost"
												size="icon"
												onclick={() => {
													selectedRole = role;
													showDeleteDialog = true;
												}}
											>
												<Trash2 class="h-4 w-4 text-destructive" />
											</Button>
										{/if}
									</div>
								</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
			{/if}
		</Card.Content>
	</Card.Root>
</div>

<!-- Create Role Dialog -->
<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
		<Dialog.Header>
			<Dialog.Title>Create New Role</Dialog.Title>
			<Dialog.Description>
				Create a new role and assign system permissions
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4">
			<div class="space-y-2">
				<Label for="role-name">Role Name</Label>
				<Input id="role-name" bind:value={newRoleName} placeholder="e.g., Support Agent" />
			</div>

			<div class="space-y-2">
				<Label>System Permissions</Label>
				<div class="max-h-[400px] space-y-4 overflow-y-auto rounded-lg border p-4">
					{#each permissionCategories as category}
						<div class="space-y-2">
							<div class="flex items-center gap-2">
								<Checkbox
									checked={isCategoryFullySelected(category)}
									onCheckedChange={(checked) =>
										toggleAllInCategory(category, checked === true)}
								/>
								<span class="font-medium capitalize">{category.category}</span>
							</div>
							<div class="ml-6 grid grid-cols-2 gap-2">
								{#each category.permissions as permission}
									<div class="flex items-center gap-2">
										<Checkbox
											checked={selectedPermissions.includes(permission.name)}
											onCheckedChange={() => togglePermission(permission.name)}
										/>
										<span class="text-sm capitalize">{permission.action}</span>
									</div>
								{/each}
							</div>
						</div>
					{/each}
				</div>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showCreateDialog = false)}>Cancel</Button>
			<Button onclick={handleCreateRole}>Create Role</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Edit Role Dialog -->
<Dialog.Root bind:open={showEditDialog}>
	<Dialog.Content class="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
		<Dialog.Header>
			<Dialog.Title>Edit Role: {selectedRole?.name}</Dialog.Title>
			<Dialog.Description>
				Modify permissions for this role
			</Dialog.Description>
		</Dialog.Header>

		{#if selectedRole}
			<Tabs.Root bind:value={activeTab}>
				<Tabs.List class="grid w-full grid-cols-2">
					<Tabs.Trigger value="system" class="flex items-center gap-2">
						<Lock class="h-4 w-4" />
						System Permissions
					</Tabs.Trigger>
					<Tabs.Trigger value="modules" class="flex items-center gap-2">
						<Database class="h-4 w-4" />
						Module Permissions
					</Tabs.Trigger>
				</Tabs.List>

				<Tabs.Content value="system" class="mt-4">
					<div class="max-h-[400px] space-y-4 overflow-y-auto rounded-lg border p-4">
						{#each permissionCategories as category}
							<div class="space-y-2">
								<div class="flex items-center gap-2">
									<Checkbox
										checked={isCategoryFullySelected(category)}
										onCheckedChange={(checked) =>
											toggleAllInCategory(category, checked === true)}
									/>
									<span class="font-medium capitalize">{category.category}</span>
								</div>
								<div class="ml-6 grid grid-cols-2 gap-2">
									{#each category.permissions as permission}
										<div class="flex items-center gap-2">
											<Checkbox
												checked={selectedPermissions.includes(permission.name)}
												onCheckedChange={() => togglePermission(permission.name)}
											/>
											<span class="text-sm capitalize">{permission.action}</span>
										</div>
									{/each}
								</div>
							</div>
						{/each}
					</div>
				</Tabs.Content>

				<Tabs.Content value="modules" class="mt-4">
					<div class="max-h-[400px] overflow-y-auto">
						<Table.Root>
							<Table.Header>
								<Table.Row>
									<Table.Head>Module</Table.Head>
									<Table.Head class="text-center">View</Table.Head>
									<Table.Head class="text-center">Create</Table.Head>
									<Table.Head class="text-center">Edit</Table.Head>
									<Table.Head class="text-center">Delete</Table.Head>
									<Table.Head>Record Access</Table.Head>
								</Table.Row>
							</Table.Header>
							<Table.Body>
								{#each modulePermissions as mp}
									<Table.Row>
										<Table.Cell class="font-medium">{mp.module_name}</Table.Cell>
										<Table.Cell class="text-center">
											<Checkbox
												checked={mp.can_view}
												onCheckedChange={(checked) =>
													updateModulePermission(mp.module_id, 'can_view', checked === true)}
											/>
										</Table.Cell>
										<Table.Cell class="text-center">
											<Checkbox
												checked={mp.can_create}
												onCheckedChange={(checked) =>
													updateModulePermission(mp.module_id, 'can_create', checked === true)}
											/>
										</Table.Cell>
										<Table.Cell class="text-center">
											<Checkbox
												checked={mp.can_edit}
												onCheckedChange={(checked) =>
													updateModulePermission(mp.module_id, 'can_edit', checked === true)}
											/>
										</Table.Cell>
										<Table.Cell class="text-center">
											<Checkbox
												checked={mp.can_delete}
												onCheckedChange={(checked) =>
													updateModulePermission(mp.module_id, 'can_delete', checked === true)}
											/>
										</Table.Cell>
										<Table.Cell>
											<Select.Root
												type="single"
												value={mp.record_access_level}
												onValueChange={(newValue) => {
													if (newValue) {
														updateModulePermission(
															mp.module_id,
															'record_access_level',
															newValue
														);
													}
												}}
											>
												<Select.Trigger class="w-[140px]">
													<span>
														{ACCESS_LEVELS.find((l) => l.value === mp.record_access_level)?.label || 'Select...'}
													</span>
												</Select.Trigger>
												<Select.Content>
													{#each ACCESS_LEVELS as level}
														<Select.Item value={level.value}>{level.label}</Select.Item>
													{/each}
												</Select.Content>
											</Select.Root>
										</Table.Cell>
									</Table.Row>
								{/each}
							</Table.Body>
						</Table.Root>
					</div>
				</Tabs.Content>
			</Tabs.Root>
		{/if}

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showEditDialog = false)}>Cancel</Button>
			<Button onclick={handleUpdateRole}>Save Changes</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root bind:open={showDeleteDialog}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Role</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete the role "{selectedRole?.name}"? Users with this role will
				lose their associated permissions.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
				onclick={handleDeleteRole}
			>
				Delete
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>

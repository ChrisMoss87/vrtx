<script lang="ts">
	import { onMount } from 'svelte';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import * as Table from '$lib/components/ui/table';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { toast } from 'svelte-sonner';
	import { usersApi, type User, type CreateUserData, type UpdateUserData } from '$lib/api/users';
	import { getRoles, type Role } from '$lib/api/rbac';
	import {
		Users,
		Plus,
		Pencil,
		Trash2,
		Search,
		KeyRound,
		Shield,
		Mail,
		Loader2,
		ChevronLeft,
		ChevronRight,
		Copy,
		Check
	} from 'lucide-svelte';

	let users = $state<User[]>([]);
	let roles = $state<Role[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let searchTimeout = $state<ReturnType<typeof setTimeout> | null>(null);

	// Pagination
	let currentPage = $state(1);
	let totalPages = $state(1);
	let totalUsers = $state(0);
	let perPage = $state(25);

	// Dialog states
	let showCreateDialog = $state(false);
	let showEditDialog = $state(false);
	let showDeleteDialog = $state(false);
	let showResetPasswordDialog = $state(false);
	let selectedUser = $state<User | null>(null);
	let saving = $state(false);

	// Form state
	let formName = $state('');
	let formEmail = $state('');
	let formPassword = $state('');
	let formRoles = $state<number[]>([]);
	let formSendInvite = $state(true);

	// Password reset
	let tempPassword = $state<string | null>(null);
	let passwordCopied = $state(false);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [usersResponse, rolesData] = await Promise.all([
				usersApi.list({ page: currentPage, per_page: perPage, search: searchQuery || undefined }),
				getRoles()
			]);
			users = usersResponse.data;
			roles = rolesData;
			if (usersResponse.meta) {
				totalPages = usersResponse.meta.last_page;
				totalUsers = usersResponse.meta.total;
				currentPage = usersResponse.meta.current_page;
			}
		} catch (error) {
			toast.error('Failed to load users');
			console.error(error);
		} finally {
			loading = false;
		}
	}

	async function loadUsers() {
		try {
			const response = await usersApi.list({
				page: currentPage,
				per_page: perPage,
				search: searchQuery || undefined
			});
			users = response.data;
			if (response.meta) {
				totalPages = response.meta.last_page;
				totalUsers = response.meta.total;
			}
		} catch (error) {
			toast.error('Failed to load users');
		}
	}

	function handleSearchInput(value: string) {
		searchQuery = value;
		if (searchTimeout) clearTimeout(searchTimeout);
		searchTimeout = setTimeout(() => {
			currentPage = 1;
			loadUsers();
		}, 300);
	}

	function openCreateDialog() {
		formName = '';
		formEmail = '';
		formPassword = '';
		formRoles = [];
		formSendInvite = true;
		showCreateDialog = true;
	}

	function openEditDialog(user: User) {
		selectedUser = user;
		formName = user.name;
		formEmail = user.email;
		formRoles = user.roles.map((r) => r.id);
		showEditDialog = true;
	}

	function openDeleteDialog(user: User) {
		selectedUser = user;
		showDeleteDialog = true;
	}

	function openResetPasswordDialog(user: User) {
		selectedUser = user;
		tempPassword = null;
		passwordCopied = false;
		showResetPasswordDialog = true;
	}

	async function handleCreateUser() {
		if (!formName.trim() || !formEmail.trim()) {
			toast.error('Name and email are required');
			return;
		}

		saving = true;
		try {
			const data: CreateUserData = {
				name: formName,
				email: formEmail,
				roles: formRoles.length > 0 ? formRoles : undefined,
				send_invite: formSendInvite
			};
			if (formPassword) {
				data.password = formPassword;
			}

			await usersApi.create(data);
			toast.success('User created successfully');
			showCreateDialog = false;
			await loadUsers();
		} catch (error: any) {
			const message = error?.response?.data?.message || error?.message || 'Failed to create user';
			toast.error(message);
		} finally {
			saving = false;
		}
	}

	async function handleUpdateUser() {
		if (!selectedUser || !formName.trim() || !formEmail.trim()) {
			toast.error('Name and email are required');
			return;
		}

		saving = true;
		try {
			const data: UpdateUserData = {
				name: formName,
				email: formEmail,
				roles: formRoles
			};

			await usersApi.update(selectedUser.id, data);
			toast.success('User updated successfully');
			showEditDialog = false;
			selectedUser = null;
			await loadUsers();
		} catch (error: any) {
			const message = error?.response?.data?.message || error?.message || 'Failed to update user';
			toast.error(message);
		} finally {
			saving = false;
		}
	}

	async function handleDeleteUser() {
		if (!selectedUser) return;

		saving = true;
		try {
			await usersApi.delete(selectedUser.id);
			toast.success('User deleted successfully');
			showDeleteDialog = false;
			selectedUser = null;
			await loadUsers();
		} catch (error: any) {
			const message = error?.response?.data?.message || error?.message || 'Failed to delete user';
			toast.error(message);
		} finally {
			saving = false;
		}
	}

	async function handleResetPassword() {
		if (!selectedUser) return;

		saving = true;
		try {
			const response = await usersApi.resetPassword(selectedUser.id);
			if (response.data?.temporary_password) {
				tempPassword = response.data.temporary_password;
				toast.success('Password reset successfully');
			} else {
				toast.success(response.message);
				showResetPasswordDialog = false;
			}
		} catch (error: any) {
			const message = error?.response?.data?.message || error?.message || 'Failed to reset password';
			toast.error(message);
		} finally {
			saving = false;
		}
	}

	async function copyPassword() {
		if (tempPassword) {
			await navigator.clipboard.writeText(tempPassword);
			passwordCopied = true;
			toast.success('Password copied to clipboard');
			setTimeout(() => {
				passwordCopied = false;
			}, 2000);
		}
	}

	function goToPage(page: number) {
		if (page >= 1 && page <= totalPages) {
			currentPage = page;
			loadUsers();
		}
	}

	function toggleRole(roleId: number) {
		if (formRoles.includes(roleId)) {
			formRoles = formRoles.filter((id) => id !== roleId);
		} else {
			formRoles = [...formRoles, roleId];
		}
	}

	function formatDate(dateString: string): string {
		return new Date(dateString).toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric'
		});
	}
</script>

<svelte:head>
	<title>Users | VRTX</title>
</svelte:head>

<div class="max-w-6xl space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Users</h1>
			<p class="text-muted-foreground">Manage users and their access</p>
		</div>
		<Button onclick={openCreateDialog}>
			<Plus class="mr-2 h-4 w-4" />
			Add User
		</Button>
	</div>

	<!-- Search -->
	<Card.Root>
		<Card.Content class="pt-6">
			<div class="relative">
				<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
				<Input
					type="text"
					placeholder="Search users by name or email..."
					value={searchQuery}
					oninput={(e) => handleSearchInput(e.currentTarget.value)}
					class="pl-9"
				/>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Users Table -->
	<Card.Root>
		<Card.Header>
			<div class="flex items-center justify-between">
				<Card.Title class="flex items-center gap-2">
					<Users class="h-5 w-5" />
					Users
				</Card.Title>
				<Badge variant="secondary">{totalUsers} users</Badge>
			</div>
		</Card.Header>
		<Card.Content>
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
				</div>
			{:else if users.length === 0}
				<div class="text-center py-12 text-muted-foreground">
					<Users class="mx-auto h-12 w-12 mb-4 opacity-50" />
					<p>No users found</p>
				</div>
			{:else}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Name</Table.Head>
							<Table.Head>Email</Table.Head>
							<Table.Head>Roles</Table.Head>
							<Table.Head>Created</Table.Head>
							<Table.Head class="text-right">Actions</Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each users as user (user.id)}
							<Table.Row>
								<Table.Cell class="font-medium">{user.name}</Table.Cell>
								<Table.Cell>
									<div class="flex items-center gap-2">
										<Mail class="h-4 w-4 text-muted-foreground" />
										{user.email}
									</div>
								</Table.Cell>
								<Table.Cell>
									<div class="flex flex-wrap gap-1">
										{#each user.roles as role}
											<Badge variant="outline" class="text-xs">
												<Shield class="mr-1 h-3 w-3" />
												{role.name}
											</Badge>
										{/each}
										{#if user.roles.length === 0}
											<span class="text-muted-foreground text-sm">No roles</span>
										{/if}
									</div>
								</Table.Cell>
								<Table.Cell class="text-muted-foreground">
									{formatDate(user.created_at)}
								</Table.Cell>
								<Table.Cell class="text-right">
									<div class="flex justify-end gap-1">
										<Button
											variant="ghost"
											size="icon"
											onclick={() => openResetPasswordDialog(user)}
											title="Reset Password"
										>
											<KeyRound class="h-4 w-4" />
										</Button>
										<Button
											variant="ghost"
											size="icon"
											onclick={() => openEditDialog(user)}
											title="Edit User"
										>
											<Pencil class="h-4 w-4" />
										</Button>
										<Button
											variant="ghost"
											size="icon"
											onclick={() => openDeleteDialog(user)}
											title="Delete User"
										>
											<Trash2 class="h-4 w-4 text-destructive" />
										</Button>
									</div>
								</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>

				<!-- Pagination -->
				{#if totalPages > 1}
					<div class="flex items-center justify-between mt-4 pt-4 border-t">
						<p class="text-sm text-muted-foreground">
							Page {currentPage} of {totalPages}
						</p>
						<div class="flex items-center gap-2">
							<Button
								variant="outline"
								size="sm"
								onclick={() => goToPage(currentPage - 1)}
								disabled={currentPage === 1}
							>
								<ChevronLeft class="h-4 w-4" />
								Previous
							</Button>
							<Button
								variant="outline"
								size="sm"
								onclick={() => goToPage(currentPage + 1)}
								disabled={currentPage === totalPages}
							>
								Next
								<ChevronRight class="h-4 w-4" />
							</Button>
						</div>
					</div>
				{/if}
			{/if}
		</Card.Content>
	</Card.Root>
</div>

<!-- Create User Dialog -->
<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title>Create User</Dialog.Title>
			<Dialog.Description>Add a new user to the system.</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="name">Name</Label>
				<Input id="name" bind:value={formName} placeholder="John Doe" />
			</div>

			<div class="space-y-2">
				<Label for="email">Email</Label>
				<Input id="email" type="email" bind:value={formEmail} placeholder="john@example.com" />
			</div>

			<div class="space-y-2">
				<Label for="password">Password (optional)</Label>
				<Input
					id="password"
					type="password"
					bind:value={formPassword}
					placeholder="Leave blank to auto-generate"
				/>
				<p class="text-xs text-muted-foreground">
					If left blank, a random password will be generated.
				</p>
			</div>

			<div class="space-y-2">
				<Label>Roles</Label>
				<div class="grid grid-cols-2 gap-2">
					{#each roles as role}
						<label
							class="flex items-center gap-2 rounded-md border p-2 cursor-pointer hover:bg-muted/50"
						>
							<Checkbox checked={formRoles.includes(role.id)} onCheckedChange={() => toggleRole(role.id)} />
							<span class="text-sm">{role.name}</span>
						</label>
					{/each}
				</div>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showCreateDialog = false)}>Cancel</Button>
			<Button onclick={handleCreateUser} disabled={saving}>
				{#if saving}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Create User
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Edit User Dialog -->
<Dialog.Root bind:open={showEditDialog}>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title>Edit User</Dialog.Title>
			<Dialog.Description>Update user details and roles.</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="edit-name">Name</Label>
				<Input id="edit-name" bind:value={formName} />
			</div>

			<div class="space-y-2">
				<Label for="edit-email">Email</Label>
				<Input id="edit-email" type="email" bind:value={formEmail} />
			</div>

			<div class="space-y-2">
				<Label>Roles</Label>
				<div class="grid grid-cols-2 gap-2">
					{#each roles as role}
						<label
							class="flex items-center gap-2 rounded-md border p-2 cursor-pointer hover:bg-muted/50"
						>
							<Checkbox checked={formRoles.includes(role.id)} onCheckedChange={() => toggleRole(role.id)} />
							<span class="text-sm">{role.name}</span>
						</label>
					{/each}
				</div>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showEditDialog = false)}>Cancel</Button>
			<Button onclick={handleUpdateUser} disabled={saving}>
				{#if saving}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Save Changes
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Delete User Dialog -->
<AlertDialog.Root bind:open={showDeleteDialog}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete User?</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete <strong>{selectedUser?.name}</strong>? This action cannot
				be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel disabled={saving}>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				onclick={handleDeleteUser}
				disabled={saving}
				class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
			>
				{#if saving}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Delete
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>

<!-- Reset Password Dialog -->
<Dialog.Root bind:open={showResetPasswordDialog}>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title>Reset Password</Dialog.Title>
			<Dialog.Description>
				Reset the password for <strong>{selectedUser?.name}</strong>.
			</Dialog.Description>
		</Dialog.Header>

		<div class="py-4">
			{#if tempPassword}
				<div class="space-y-4">
					<div class="rounded-lg bg-muted p-4">
						<p class="text-sm text-muted-foreground mb-2">Temporary Password:</p>
						<div class="flex items-center gap-2">
							<code class="flex-1 rounded bg-background px-3 py-2 font-mono text-sm">
								{tempPassword}
							</code>
							<Button variant="outline" size="icon" onclick={copyPassword}>
								{#if passwordCopied}
									<Check class="h-4 w-4 text-green-500" />
								{:else}
									<Copy class="h-4 w-4" />
								{/if}
							</Button>
						</div>
					</div>
					<p class="text-sm text-muted-foreground">
						Please securely share this password with the user. They should change it after logging
						in.
					</p>
				</div>
			{:else}
				<p class="text-muted-foreground">
					This will generate a new temporary password for the user. The current password will no
					longer work.
				</p>
			{/if}
		</div>

		<Dialog.Footer>
			{#if tempPassword}
				<Button onclick={() => (showResetPasswordDialog = false)}>Done</Button>
			{:else}
				<Button variant="outline" onclick={() => (showResetPasswordDialog = false)}>Cancel</Button>
				<Button onclick={handleResetPassword} disabled={saving}>
					{#if saving}
						<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					{/if}
					Reset Password
				</Button>
			{/if}
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

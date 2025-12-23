<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Share2, Search, UserPlus, User, Loader2, Trash2 } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { apiClient } from '$lib/api/client';
	import { usersApi } from '$lib/api/users';

	interface DashboardShare {
		id: number;
		dashboard_id: number;
		user_id: number | null;
		team_id: number | null;
		permission: 'view' | 'edit';
		type: 'user' | 'team';
		user?: { id: number; name: string; email: string };
		team?: { id: number; name: string };
	}

	interface Props {
		dashboardId: number;
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
	}

	let { dashboardId, open = $bindable(false), onOpenChange }: Props = $props();

	let shares = $state<DashboardShare[]>([]);
	let loading = $state(true);
	let saving = $state(false);
	let search = $state('');
	let permission = $state<'view' | 'edit'>('view');

	// For search results
	let users = $state<{ id: number; name: string; email: string }[]>([]);
	let selectedIds = $state<number[]>([]);

	const filteredUsers = $derived(
		users.filter(
			(u) =>
				(u.name.toLowerCase().includes(search.toLowerCase()) ||
					u.email.toLowerCase().includes(search.toLowerCase())) &&
				!shares.some((s) => s.type === 'user' && s.user?.id === u.id)
		)
	);

	$effect(() => {
		if (open) {
			loadData();
		}
	});

	async function loadData() {
		loading = true;
		try {
			const [sharesData, usersData] = await Promise.all([
				apiClient.get<{ data: DashboardShare[] }>(`/dashboards/${dashboardId}/shares`).then(r => r.data),
				usersApi.list({ per_page: 100 })
			]);
			shares = sharesData;
			users = usersData.data.map((u) => ({ id: u.id, name: u.name, email: u.email }));
		} catch (error) {
			console.error('Failed to load data:', error);
			toast.error('Failed to load sharing data');
		} finally {
			loading = false;
		}
	}

	function toggleSelection(id: number) {
		if (selectedIds.includes(id)) {
			selectedIds = selectedIds.filter((i) => i !== id);
		} else {
			selectedIds = [...selectedIds, id];
		}
	}

	async function handleShare() {
		if (selectedIds.length === 0) {
			toast.error('Please select at least one user');
			return;
		}

		saving = true;
		try {
			const shareRequests = selectedIds.map((id) => ({
				type: 'user',
				id,
				permission
			}));

			await apiClient.post(`/dashboards/${dashboardId}/shares`, { shares: shareRequests });
			toast.success('Dashboard shared successfully');

			// Reload shares
			const sharesData = await apiClient.get<{ data: DashboardShare[] }>(`/dashboards/${dashboardId}/shares`);
			shares = sharesData.data;
			selectedIds = [];
			search = '';
		} catch (error) {
			console.error('Failed to share dashboard:', error);
			toast.error('Failed to share dashboard');
		} finally {
			saving = false;
		}
	}

	async function handleRemoveShare(share: DashboardShare) {
		try {
			const targetId = share.user?.id || share.team?.id;
			await apiClient.delete(`/dashboards/${dashboardId}/shares/${share.type}/${targetId}`);
			shares = shares.filter((s) => s.id !== share.id);
			toast.success('Share removed');
		} catch (error) {
			console.error('Failed to remove share:', error);
			toast.error('Failed to remove share');
		}
	}

	function handleOpenChange(value: boolean) {
		open = value;
		if (!value) {
			selectedIds = [];
			search = '';
		}
		onOpenChange?.(value);
	}
</script>

<Dialog.Root bind:open onOpenChange={handleOpenChange}>
	<Dialog.Content class="max-w-lg max-h-[80vh] flex flex-col">
		<Dialog.Header>
			<Dialog.Title class="flex items-center gap-2">
				<Share2 class="h-5 w-5" />
				Share Dashboard
			</Dialog.Title>
			<Dialog.Description>Share this dashboard with specific users</Dialog.Description>
		</Dialog.Header>

		<div class="flex-1 overflow-hidden flex flex-col space-y-4 py-4">
			{#if loading}
				<div class="space-y-2">
					<Skeleton class="h-10 w-full" />
					<Skeleton class="h-24 w-full" />
				</div>
			{:else}
				<!-- Add new shares -->
				<div class="space-y-3">
					<div class="flex gap-2">
						<div class="relative flex-1">
							<Search
								class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
							/>
							<Input placeholder="Search users..." class="pl-9" bind:value={search} />
						</div>

						<Select.Root
							type="single"
							value={permission}
							onValueChange={(v) => {
								if (v) permission = v as 'view' | 'edit';
							}}
						>
							<Select.Trigger class="w-24">
								<span>{permission === 'view' ? 'View' : 'Edit'}</span>
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="view">View</Select.Item>
								<Select.Item value="edit">Edit</Select.Item>
							</Select.Content>
						</Select.Root>
					</div>

					<!-- Search results -->
					<div class="border rounded-lg max-h-32 overflow-auto">
						{#if filteredUsers.length === 0}
							<p class="p-3 text-sm text-muted-foreground text-center">
								{search ? 'No users found' : 'All users already have access'}
							</p>
						{:else}
							{#each filteredUsers as user}
								<button
									type="button"
									class="w-full flex items-center gap-3 p-2 hover:bg-muted text-left transition-colors {selectedIds.includes(
										user.id
									)
										? 'bg-primary/10'
										: ''}"
									onclick={() => toggleSelection(user.id)}
								>
									<div class="h-8 w-8 rounded-full bg-muted flex items-center justify-center">
										<User class="h-4 w-4" />
									</div>
									<div class="flex-1 min-w-0">
										<p class="text-sm font-medium truncate">{user.name}</p>
										<p class="text-xs text-muted-foreground truncate">{user.email}</p>
									</div>
									{#if selectedIds.includes(user.id)}
										<Badge variant="default" class="text-xs">Selected</Badge>
									{/if}
								</button>
							{/each}
						{/if}
					</div>

					{#if selectedIds.length > 0}
						<Button onclick={handleShare} disabled={saving} class="w-full">
							{#if saving}
								<Loader2 class="mr-2 h-4 w-4 animate-spin" />
							{:else}
								<UserPlus class="mr-2 h-4 w-4" />
							{/if}
							Share with {selectedIds.length} user{selectedIds.length > 1 ? 's' : ''}
						</Button>
					{/if}
				</div>

				<!-- Current shares -->
				{#if shares.length > 0}
					<div class="space-y-2">
						<Label class="text-sm font-medium">Currently shared with</Label>
						<div class="border rounded-lg divide-y max-h-40 overflow-auto">
							{#each shares as share}
								<div class="flex items-center justify-between p-2">
									<div class="flex items-center gap-2">
										<div class="h-8 w-8 rounded-full bg-muted flex items-center justify-center">
											<User class="h-4 w-4" />
										</div>
										<div>
											<p class="text-sm font-medium">{share.user?.name || share.team?.name}</p>
											{#if share.user?.email}
												<p class="text-xs text-muted-foreground">{share.user.email}</p>
											{/if}
										</div>
									</div>
									<div class="flex items-center gap-2">
										<Badge variant={share.permission === 'edit' ? 'default' : 'secondary'}>
											{share.permission}
										</Badge>
										<Button
											variant="ghost"
											size="icon"
											class="h-8 w-8"
											onclick={() => handleRemoveShare(share)}
										>
											<Trash2 class="h-4 w-4" />
										</Button>
									</div>
								</div>
							{/each}
						</div>
					</div>
				{/if}
			{/if}
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (open = false)}>Done</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

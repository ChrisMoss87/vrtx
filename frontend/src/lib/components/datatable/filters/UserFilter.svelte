<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Loader2, Search, X, User, Users } from 'lucide-svelte';
	import axios from 'axios';
	import type { FilterConfig } from '../types';

	interface UserRecord {
		id: number;
		name: string;
		email?: string;
		avatar_url?: string;
	}

	interface Props {
		field: string;
		initialValue?: FilterConfig;
		onApply: (filter: FilterConfig | null) => void;
		onClose?: () => void;
	}

	let { field, initialValue, onApply, onClose }: Props = $props();

	let searchQuery = $state('');
	let loading = $state(true);
	let users = $state<UserRecord[]>([]);
	let filteredUsers = $state<UserRecord[]>([]);
	let selectedIds = $state<number[]>(
		initialValue?.value
			? Array.isArray(initialValue.value)
				? initialValue.value.map(Number)
				: [Number(initialValue.value)]
			: []
	);

	// Fetch users on mount
	onMount(async () => {
		try {
			const token = localStorage.getItem('auth_token');
			const response = await axios.get('/api/v1/users', {
				headers: token ? { Authorization: `Bearer ${token}` } : {}
			});
			users = response.data.data || response.data || [];
			filteredUsers = users;
		} catch (error) {
			console.error('Failed to fetch users:', error);
			users = [];
			filteredUsers = [];
		} finally {
			loading = false;
		}
	});

	// Filter users based on search
	$effect(() => {
		if (!searchQuery.trim()) {
			filteredUsers = users;
		} else {
			const query = searchQuery.toLowerCase();
			filteredUsers = users.filter(
				(u) =>
					u.name.toLowerCase().includes(query) ||
					(u.email && u.email.toLowerCase().includes(query))
			);
		}
	});

	function toggleUser(userId: number) {
		if (selectedIds.includes(userId)) {
			selectedIds = selectedIds.filter((id) => id !== userId);
		} else {
			selectedIds = [...selectedIds, userId];
		}
	}

	function handleApply() {
		if (selectedIds.length === 0) {
			onApply(null);
		} else if (selectedIds.length === 1) {
			onApply({
				field,
				operator: 'equals',
				value: selectedIds[0]
			});
		} else {
			onApply({
				field,
				operator: 'in',
				value: selectedIds
			});
		}
		onClose?.();
	}

	function handleClear() {
		selectedIds = [];
		searchQuery = '';
		onApply(null);
		onClose?.();
	}

	function selectAll() {
		selectedIds = filteredUsers.map((u) => u.id);
	}

	function selectNone() {
		selectedIds = [];
	}

	// Get selected user names for display
	const selectedNames = $derived(
		users.filter((u) => selectedIds.includes(u.id)).map((u) => u.name)
	);
</script>

<div class="w-[280px] space-y-3 p-4">
	<!-- Search -->
	<div class="relative">
		<Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
		<Input
			type="text"
			placeholder="Search users..."
			bind:value={searchQuery}
			class="pl-9 h-9"
		/>
	</div>

	<!-- Selected count -->
	{#if selectedIds.length > 0}
		<div class="flex items-center justify-between text-xs">
			<span class="text-muted-foreground">{selectedIds.length} selected</span>
			<Button variant="ghost" size="sm" class="h-6 px-2 text-xs" onclick={selectNone}>
				Clear selection
			</Button>
		</div>
	{/if}

	<!-- User list -->
	<div class="max-h-[200px] overflow-y-auto border rounded-md">
		{#if loading}
			<div class="flex items-center justify-center p-6">
				<Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
			</div>
		{:else if filteredUsers.length === 0}
			<div class="p-4 text-center text-sm text-muted-foreground">
				{searchQuery ? 'No users found' : 'No users available'}
			</div>
		{:else}
			<div class="divide-y">
				{#each filteredUsers as user (user.id)}
					{@const isSelected = selectedIds.includes(user.id)}
					<button
						type="button"
						class="flex w-full items-center gap-3 p-2.5 text-left hover:bg-accent transition-colors {isSelected ? 'bg-primary/5' : ''}"
						onclick={() => toggleUser(user.id)}
					>
						<Checkbox checked={isSelected} tabindex={-1} />
						<div class="flex items-center gap-2 min-w-0 flex-1">
							<div class="flex h-7 w-7 items-center justify-center rounded-full bg-muted flex-shrink-0">
								<User class="h-4 w-4 text-muted-foreground" />
							</div>
							<div class="min-w-0 flex-1">
								<p class="text-sm font-medium truncate">{user.name}</p>
								{#if user.email}
									<p class="text-xs text-muted-foreground truncate">{user.email}</p>
								{/if}
							</div>
						</div>
					</button>
				{/each}
			</div>
		{/if}
	</div>

	<!-- Quick actions -->
	{#if !loading && filteredUsers.length > 0}
		<div class="flex items-center gap-2 text-xs">
			<Button variant="ghost" size="sm" class="h-6 px-2" onclick={selectAll}>
				Select all
			</Button>
			<span class="text-muted-foreground">|</span>
			<Button variant="ghost" size="sm" class="h-6 px-2" onclick={() => onApply({ field, operator: 'is_empty', value: '' })}>
				Unassigned
			</Button>
		</div>
	{/if}

	<!-- Actions -->
	<div class="flex items-center justify-between gap-2 pt-2 border-t">
		<Button variant="ghost" size="sm" onclick={handleClear}>
			<X class="mr-1.5 h-3.5 w-3.5" />
			Clear
		</Button>
		<Button size="sm" onclick={handleApply}>
			Apply
		</Button>
	</div>
</div>

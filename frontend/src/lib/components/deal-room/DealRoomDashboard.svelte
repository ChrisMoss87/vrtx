<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Plus, Search, Users, FileText, MessageSquare, CheckSquare, ExternalLink } from 'lucide-svelte';
	import { getDealRooms, type DealRoom } from '$lib/api/deal-rooms';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import CreateRoomModal from './CreateRoomModal.svelte';

	let rooms: DealRoom[] = [];
	let loading = true;
	let searchQuery = '';
	let statusFilter = 'active';
	let showCreateModal = false;

	onMount(async () => {
		await loadRooms();
	});

	async function loadRooms() {
		loading = true;
		const { data, error } = await tryCatch(getDealRooms(statusFilter));
		loading = false;

		if (error) {
			toast.error('Failed to load deal rooms');
			return;
		}

		rooms = data ?? [];
	}

	function getStatusColor(status: string): string {
		switch (status) {
			case 'active':
				return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
			case 'won':
				return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
			case 'lost':
				return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
			case 'archived':
				return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
			default:
				return 'bg-gray-100 text-gray-800';
		}
	}

	$: filteredRooms = rooms.filter((room) =>
		room.name.toLowerCase().includes(searchQuery.toLowerCase())
	);
</script>

<div class="p-6 space-y-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Deal Rooms</h1>
			<p class="text-muted-foreground">Collaborative spaces for your deals</p>
		</div>
		<Button onclick={() => (showCreateModal = true)}>
			<Plus class="mr-2 h-4 w-4" />
			Create Room
		</Button>
	</div>

	<!-- Filters -->
	<div class="flex items-center gap-4">
		<div class="relative flex-1 max-w-sm">
			<Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
			<Input bind:value={searchQuery} placeholder="Search rooms..." class="pl-9" />
		</div>

		<Select.Root
			type="single"
			value={statusFilter}
			onValueChange={(val) => {
				if (val) {
					statusFilter = val;
					loadRooms();
				}
			}}
		>
			<Select.Trigger class="w-[150px]">
				<span>{statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1)}</span>
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="active">Active</Select.Item>
				<Select.Item value="won">Won</Select.Item>
				<Select.Item value="lost">Lost</Select.Item>
				<Select.Item value="archived">Archived</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Room List -->
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
		</div>
	{:else if filteredRooms.length === 0}
		<div class="text-center py-12">
			<Users class="mx-auto h-12 w-12 text-muted-foreground/50" />
			<h3 class="mt-4 text-lg font-medium">No deal rooms found</h3>
			<p class="text-muted-foreground mt-2">Create a room to start collaborating with stakeholders</p>
			<Button class="mt-4" onclick={() => (showCreateModal = true)}>
				<Plus class="mr-2 h-4 w-4" />
				Create Room
			</Button>
		</div>
	{:else}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each filteredRooms as room}
				<a
					href="/deal-rooms/{room.id}"
					class="block rounded-lg border bg-card p-5 hover:shadow-md transition-shadow"
				>
					<div class="flex items-start justify-between">
						<div class="flex-1 min-w-0">
							<h3 class="font-semibold truncate">{room.name}</h3>
							{#if room.description}
								<p class="text-sm text-muted-foreground mt-1 line-clamp-2">{room.description}</p>
							{/if}
						</div>
						<span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full {getStatusColor(room.status)}">
							{room.status}
						</span>
					</div>

					<!-- Progress -->
					{#if room.progress}
						<div class="mt-4">
							<div class="flex justify-between text-xs text-muted-foreground mb-1">
								<span>Action Plan</span>
								<span>{room.progress.percentage}%</span>
							</div>
							<div class="h-1.5 bg-muted rounded-full">
								<div
									class="h-1.5 bg-primary rounded-full transition-all"
									style="width: {room.progress.percentage}%"
								></div>
							</div>
						</div>
					{/if}

					<!-- Stats -->
					<div class="mt-4 flex items-center gap-4 text-sm text-muted-foreground">
						<div class="flex items-center gap-1">
							<Users class="h-4 w-4" />
							<span>{room.member_count}</span>
						</div>
						<div class="flex items-center gap-1">
							<CheckSquare class="h-4 w-4" />
							<span>{room.action_items_count}</span>
						</div>
						<div class="flex items-center gap-1">
							<FileText class="h-4 w-4" />
							<span>{room.documents_count}</span>
						</div>
						<div class="flex items-center gap-1">
							<MessageSquare class="h-4 w-4" />
							<span>{room.messages_count}</span>
						</div>
					</div>
				</a>
			{/each}
		</div>
	{/if}
</div>

{#if showCreateModal}
	<CreateRoomModal
		onClose={() => (showCreateModal = false)}
		onCreated={(room) => {
			showCreateModal = false;
			rooms = [room, ...rooms];
		}}
	/>
{/if}

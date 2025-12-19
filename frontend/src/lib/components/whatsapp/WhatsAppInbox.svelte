<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { Tabs, TabsContent, TabsList, TabsTrigger } from '$lib/components/ui/tabs';
	import * as Select from '$lib/components/ui/select';
	import { whatsappConversationsApi, type WhatsappConversation } from '$lib/api/whatsapp';
	import { Phone, Clock, Search, RefreshCw, User } from 'lucide-svelte';

	interface Props {
		onSelectConversation?: (conversation: WhatsappConversation) => void;
		selectedId?: number;
	}

	let { onSelectConversation, selectedId }: Props = $props();

	let loading = $state(true);
	let conversations = $state<WhatsappConversation[]>([]);
	let searchQuery = $state('');
	let activeTab = $state('mine');
	let statusFilter = $state('');

	const statusOptions = [
		{ value: '', label: 'All Status' },
		{ value: 'open', label: 'Open' },
		{ value: 'pending', label: 'Pending' },
		{ value: 'closed', label: 'Closed' }
	];

	async function loadConversations() {
		loading = true;
		try {
			const params: Record<string, string | number> = {};
			if (activeTab === 'mine') params.assigned_to = 'me';
			if (activeTab === 'unassigned') params.assigned_to = 'unassigned';
			if (statusFilter) params.status = statusFilter;

			const result = await whatsappConversationsApi.list(params);
			conversations = result.data;
		} catch (err) {
			console.error('Failed to load conversations:', err);
		}
		loading = false;
	}

	function formatTime(dateStr: string | null): string {
		if (!dateStr) return '';
		const date = new Date(dateStr);
		const now = new Date();
		const diffMs = now.getTime() - date.getTime();
		const diffMins = Math.floor(diffMs / (1000 * 60));

		if (diffMins < 1) return 'Just now';
		if (diffMins < 60) return `${diffMins}m ago`;
		if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`;
		return date.toLocaleDateString();
	}

	function getStatusColor(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
		switch (status) {
			case 'open':
				return 'default';
			case 'pending':
				return 'secondary';
			case 'closed':
				return 'outline';
			default:
				return 'secondary';
		}
	}

	const filteredConversations = $derived(
		conversations.filter((c) => {
			if (!searchQuery) return true;
			const query = searchQuery.toLowerCase();
			return (
				c.contact_name?.toLowerCase().includes(query) ||
				c.contact_phone.toLowerCase().includes(query) ||
				c.display_name.toLowerCase().includes(query)
			);
		})
	);

	onMount(loadConversations);

	$effect(() => {
		if (activeTab || statusFilter !== undefined) {
			loadConversations();
		}
	});
</script>

<div class="flex flex-col h-full border-r">
	<!-- Search & Filter -->
	<div class="p-3 border-b space-y-2">
		<div class="relative">
			<Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
			<Input
				type="search"
				placeholder="Search conversations..."
				class="pl-8"
				bind:value={searchQuery}
			/>
		</div>
		<div class="flex gap-2">
			<Select.Root type="single" bind:value={statusFilter}>
				<Select.Trigger class="flex-1 h-8 text-xs">
					{statusOptions.find(o => o.value === statusFilter)?.label || 'All Status'}
				</Select.Trigger>
				<Select.Content>
					{#each statusOptions as opt}
						<Select.Item value={opt.value} label={opt.label}>{opt.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
			<Button variant="ghost" size="icon" onclick={loadConversations} class="h-8 w-8">
				<RefreshCw class="h-4 w-4 {loading ? 'animate-spin' : ''}" />
			</Button>
		</div>
	</div>

	<!-- Tabs -->
	<Tabs bind:value={activeTab} class="flex-1 flex flex-col">
		<div class="border-b px-2">
			<TabsList class="w-full justify-start h-9 bg-transparent">
				<TabsTrigger value="mine" class="text-xs">Mine</TabsTrigger>
				<TabsTrigger value="unassigned" class="text-xs">Unassigned</TabsTrigger>
				<TabsTrigger value="all" class="text-xs">All</TabsTrigger>
			</TabsList>
		</div>

		<div class="flex-1 overflow-y-auto">
			{#if loading && conversations.length === 0}
				<div class="flex items-center justify-center h-32">
					<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
				</div>
			{:else if filteredConversations.length === 0}
				<div class="flex flex-col items-center justify-center h-32 text-muted-foreground">
					<Phone class="h-8 w-8 mb-2 opacity-50" />
					<p class="text-sm">No conversations</p>
				</div>
			{:else}
				<div class="divide-y">
					{#each filteredConversations as conversation}
						<button
							class="w-full text-left p-3 hover:bg-muted/50 transition-colors {selectedId === conversation.id ? 'bg-muted' : ''}"
							onclick={() => onSelectConversation?.(conversation)}
						>
							<div class="flex items-start gap-3">
								<div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center shrink-0">
									<Phone class="h-5 w-5 text-green-600" />
								</div>
								<div class="flex-1 min-w-0">
									<div class="flex items-center justify-between gap-2">
										<span class="font-medium text-sm truncate">{conversation.display_name}</span>
										<Badge variant={getStatusColor(conversation.status)} class="text-xs shrink-0">
											{conversation.status}
										</Badge>
									</div>
									<p class="text-xs text-muted-foreground truncate">{conversation.contact_phone}</p>
									<div class="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
										{#if conversation.unread_count > 0}
											<Badge variant="default" class="h-5 text-xs">
												{conversation.unread_count}
											</Badge>
										{/if}
										<span class="flex items-center gap-1">
											<Clock class="h-3 w-3" />
											{formatTime(conversation.last_message_at)}
										</span>
										{#if conversation.assigned_user}
											<span class="flex items-center gap-1">
												<User class="h-3 w-3" />
												{conversation.assigned_user.name}
											</span>
										{/if}
									</div>
								</div>
							</div>
						</button>
					{/each}
				</div>
			{/if}
		</div>
	</Tabs>
</div>

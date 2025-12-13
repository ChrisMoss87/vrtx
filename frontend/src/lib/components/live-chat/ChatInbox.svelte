<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { Tabs, TabsContent, TabsList, TabsTrigger } from '$lib/components/ui/tabs';
	import * as Select from '$lib/components/ui/select';
	import {
		chatConversationsApi,
		chatAgentsApi,
		type ChatConversation,
		type ChatAgentStatus
	} from '$lib/api/live-chat';
	import {
		MessageSquare,
		User,
		Clock,
		Search,
		RefreshCw,
		Circle
	} from 'lucide-svelte';

	interface Props {
		onSelectConversation?: (conversation: ChatConversation) => void;
		selectedId?: number;
	}

	let { onSelectConversation, selectedId }: Props = $props();

	let loading = $state(true);
	let conversations = $state<ChatConversation[]>([]);
	let agentStatus = $state<ChatAgentStatus | null>(null);
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

			const result = await chatConversationsApi.list(params);
			conversations = result.data;
		} catch (err) {
			console.error('Failed to load conversations:', err);
		}
		loading = false;
	}

	async function loadAgentStatus() {
		try {
			agentStatus = await chatAgentsApi.getStatus();
		} catch (err) {
			console.error('Failed to load agent status:', err);
		}
	}

	async function updateAgentStatus(status: 'online' | 'away' | 'busy' | 'offline') {
		try {
			agentStatus = await chatAgentsApi.updateStatus({ status });
		} catch (err) {
			console.error('Failed to update status:', err);
		}
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

	function getPriorityColor(priority: string): string {
		switch (priority) {
			case 'urgent':
				return 'bg-red-500';
			case 'high':
				return 'bg-orange-500';
			case 'normal':
				return 'bg-blue-500';
			default:
				return 'bg-gray-500';
		}
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
				c.visitor?.name?.toLowerCase().includes(query) ||
				c.visitor?.email?.toLowerCase().includes(query) ||
				c.subject?.toLowerCase().includes(query)
			);
		})
	);

	onMount(() => {
		loadConversations();
		loadAgentStatus();
	});

	$effect(() => {
		loadConversations();
	});
</script>

<div class="flex flex-col h-full border-r">
	<!-- Agent Status Bar -->
	{#if agentStatus}
		<div class="p-3 border-b flex items-center justify-between bg-muted/30">
			<div class="flex items-center gap-2">
				<Circle
					class="h-3 w-3 {agentStatus.status === 'online'
						? 'text-green-500 fill-green-500'
						: agentStatus.status === 'away'
							? 'text-yellow-500 fill-yellow-500'
							: agentStatus.status === 'busy'
								? 'text-red-500 fill-red-500'
								: 'text-gray-400 fill-gray-400'}"
				/>
				<span class="text-sm font-medium capitalize">{agentStatus.status}</span>
			</div>
			<Select.Root
				type="single"
				onValueChange={(v) => v && updateAgentStatus(v as 'online' | 'away' | 'busy' | 'offline')}
			>
				<Select.Trigger class="w-28 h-7 text-xs">
					Set Status
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="online">Online</Select.Item>
					<Select.Item value="away">Away</Select.Item>
					<Select.Item value="busy">Busy</Select.Item>
					<Select.Item value="offline">Offline</Select.Item>
				</Select.Content>
			</Select.Root>
		</div>
	{/if}

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
					<MessageSquare class="h-8 w-8 mb-2 opacity-50" />
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
								<div
									class="w-2 h-2 rounded-full mt-2 {getPriorityColor(conversation.priority)}"
								></div>
								<div class="flex-1 min-w-0">
									<div class="flex items-center justify-between gap-2">
										<span class="font-medium text-sm truncate">
											{conversation.visitor?.name || conversation.visitor?.email || `Visitor #${conversation.visitor?.id}`}
										</span>
										<Badge variant={getStatusColor(conversation.status)} class="text-xs shrink-0">
											{conversation.status}
										</Badge>
									</div>
									{#if conversation.subject}
										<p class="text-xs text-muted-foreground truncate mt-0.5">
											{conversation.subject}
										</p>
									{/if}
									<div class="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
										<span class="flex items-center gap-1">
											<MessageSquare class="h-3 w-3" />
											{conversation.message_count}
										</span>
										<span class="flex items-center gap-1">
											<Clock class="h-3 w-3" />
											{formatTime(conversation.last_message_at)}
										</span>
										{#if conversation.assigned_agent}
											<span class="flex items-center gap-1">
												<User class="h-3 w-3" />
												{conversation.assigned_agent.name}
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

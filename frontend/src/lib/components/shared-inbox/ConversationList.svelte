<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { inboxConversationApi, type InboxConversation } from '$lib/api/shared-inbox';
	import {
		Search,
		RefreshCw,
		Star,
		StarOff,
		Mail,
		MessageSquare,
		Clock,
		User,
		AlertTriangle,
		CheckCircle,
		Filter
	} from 'lucide-svelte';

	interface Props {
		inboxId?: number;
		onSelect?: (conversation: InboxConversation) => void;
	}

	let { inboxId, onSelect }: Props = $props();

	let conversations = $state<InboxConversation[]>([]);
	let loading = $state(true);
	let selectedIds = $state<Set<number>>(new Set());

	let filters = $state({
		status: '',
		assigned_to: '',
		priority: '',
		search: ''
	});

	let pagination = $state({
		currentPage: 1,
		lastPage: 1,
		total: 0
	});

	async function loadConversations() {
		loading = true;
		try {
			const params: Record<string, string | number | boolean> = {
				page: pagination.currentPage
			};
			if (inboxId) params.inbox_id = inboxId;
			if (filters.status) params.status = filters.status;
			if (filters.assigned_to) params.assigned_to = filters.assigned_to;
			if (filters.priority) params.priority = filters.priority;
			if (filters.search) params.search = filters.search;

			const result = await inboxConversationApi.list(params);
			conversations = result.data;
			pagination = {
				currentPage: result.meta.current_page,
				lastPage: result.meta.last_page,
				total: result.meta.total
			};
		} catch (err) {
			console.error('Failed to load conversations:', err);
		} finally {
			loading = false;
		}
	}

	async function toggleStar(conversation: InboxConversation, e: Event) {
		e.stopPropagation();
		try {
			const updated = await inboxConversationApi.toggleStar(conversation.id);
			const idx = conversations.findIndex((c) => c.id === conversation.id);
			if (idx !== -1) {
				conversations[idx] = updated;
			}
		} catch (err) {
			console.error('Failed to toggle star:', err);
		}
	}

	function toggleSelection(id: number) {
		const newSet = new Set(selectedIds);
		if (newSet.has(id)) {
			newSet.delete(id);
		} else {
			newSet.add(id);
		}
		selectedIds = newSet;
	}

	function selectAll() {
		if (selectedIds.size === conversations.length) {
			selectedIds = new Set();
		} else {
			selectedIds = new Set(conversations.map((c) => c.id));
		}
	}

	async function bulkResolve() {
		if (selectedIds.size === 0) return;
		try {
			await inboxConversationApi.bulkResolve(Array.from(selectedIds));
			selectedIds = new Set();
			loadConversations();
		} catch (err) {
			console.error('Failed to bulk resolve:', err);
		}
	}

	function getPriorityBadge(priority: string) {
		switch (priority) {
			case 'urgent':
				return { variant: 'destructive' as const, class: '' };
			case 'high':
				return { variant: 'default' as const, class: 'bg-orange-500' };
			case 'normal':
				return { variant: 'secondary' as const, class: '' };
			case 'low':
				return { variant: 'outline' as const, class: '' };
			default:
				return { variant: 'outline' as const, class: '' };
		}
	}

	function getStatusIcon(status: string) {
		switch (status) {
			case 'open':
				return Mail;
			case 'pending':
				return Clock;
			case 'resolved':
				return CheckCircle;
			default:
				return MessageSquare;
		}
	}

	function formatTime(dateStr: string | null): string {
		if (!dateStr) return '';
		const date = new Date(dateStr);
		const now = new Date();
		const diff = now.getTime() - date.getTime();

		if (diff < 60000) return 'Just now';
		if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
		if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
		if (diff < 604800000) return `${Math.floor(diff / 86400000)}d ago`;

		return date.toLocaleDateString();
	}

	$effect(() => {
		loadConversations();
	});

	$effect(() => {
		// Reload when filters change
		pagination.currentPage = 1;
		loadConversations();
	});
</script>

<div class="flex flex-col h-full">
	<!-- Toolbar -->
	<div class="border-b p-3 space-y-3">
		<div class="flex items-center gap-2">
			<div class="relative flex-1">
				<Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
				<Input
					class="pl-9"
					placeholder="Search conversations..."
					bind:value={filters.search}
					onkeydown={(e) => e.key === 'Enter' && loadConversations()}
				/>
			</div>
			<Button variant="outline" size="icon" onclick={loadConversations}>
				<RefreshCw class="h-4 w-4" />
			</Button>
		</div>

		<div class="flex items-center gap-2 flex-wrap">
			<Select.Root
				type="single"
				value={filters.status}
				onValueChange={(v) => (filters.status = v ?? '')}
			>
				<Select.Trigger class="w-32">
					<span>{filters.status || 'All Status'}</span>
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="">All Status</Select.Item>
					<Select.Item value="open">Open</Select.Item>
					<Select.Item value="pending">Pending</Select.Item>
					<Select.Item value="resolved">Resolved</Select.Item>
					<Select.Item value="closed">Closed</Select.Item>
				</Select.Content>
			</Select.Root>

			<Select.Root
				type="single"
				value={filters.assigned_to}
				onValueChange={(v) => (filters.assigned_to = v ?? '')}
			>
				<Select.Trigger class="w-36">
					<span>{filters.assigned_to === 'me' ? 'Assigned to me' : filters.assigned_to === 'unassigned' ? 'Unassigned' : 'All Assignees'}</span>
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="">All Assignees</Select.Item>
					<Select.Item value="me">Assigned to me</Select.Item>
					<Select.Item value="unassigned">Unassigned</Select.Item>
				</Select.Content>
			</Select.Root>

			<Select.Root
				type="single"
				value={filters.priority}
				onValueChange={(v) => (filters.priority = v ?? '')}
			>
				<Select.Trigger class="w-32">
					<span class="capitalize">{filters.priority || 'All Priority'}</span>
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="">All Priority</Select.Item>
					<Select.Item value="urgent">Urgent</Select.Item>
					<Select.Item value="high">High</Select.Item>
					<Select.Item value="normal">Normal</Select.Item>
					<Select.Item value="low">Low</Select.Item>
				</Select.Content>
			</Select.Root>

			{#if selectedIds.size > 0}
				<div class="ml-auto flex items-center gap-2">
					<span class="text-sm text-muted-foreground">{selectedIds.size} selected</span>
					<Button variant="outline" size="sm" onclick={bulkResolve}>
						<CheckCircle class="mr-1 h-3 w-3" />
						Resolve
					</Button>
				</div>
			{/if}
		</div>
	</div>

	<!-- List -->
	<div class="flex-1 overflow-y-auto">
		{#if loading}
			<div class="flex items-center justify-center py-8">
				<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
			</div>
		{:else if conversations.length === 0}
			<div class="flex flex-col items-center justify-center py-12 text-muted-foreground">
				<Mail class="h-12 w-12 mb-4" />
				<p>No conversations found</p>
			</div>
		{:else}
			<div class="divide-y">
				{#each conversations as conversation (conversation.id)}
					{@const StatusIcon = getStatusIcon(conversation.status)}
					{@const priorityBadge = getPriorityBadge(conversation.priority)}
					<div
						class={`flex items-start gap-3 p-3 hover:bg-muted/50 cursor-pointer transition-colors ${selectedIds.has(conversation.id) ? 'bg-muted/30' : ''}`}
						role="button"
						tabindex="0"
						onclick={() => onSelect?.(conversation)}
						onkeydown={(e) => e.key === 'Enter' && onSelect?.(conversation)}
					>
						<div class="pt-1" onclick={(e) => e.stopPropagation()}>
							<Checkbox
								checked={selectedIds.has(conversation.id)}
								onCheckedChange={() => toggleSelection(conversation.id)}
							/>
						</div>

						<div class="flex-1 min-w-0">
							<div class="flex items-center gap-2 mb-1">
								<span class="font-medium truncate">{conversation.contact_name || conversation.contact_email || 'Unknown'}</span>
								{#if conversation.priority !== 'normal'}
									<Badge variant={priorityBadge.variant} class={priorityBadge.class}>
										{conversation.priority}
									</Badge>
								{/if}
								{#if conversation.is_spam}
									<Badge variant="destructive">Spam</Badge>
								{/if}
							</div>

							<div class="text-sm font-medium truncate">{conversation.subject}</div>

							{#if conversation.snippet}
								<p class="text-sm text-muted-foreground truncate">{conversation.snippet}</p>
							{/if}

							<div class="flex items-center gap-3 mt-1 text-xs text-muted-foreground">
								<span class="flex items-center gap-1">
									<StatusIcon class="h-3 w-3" />
									{conversation.status}
								</span>
								{#if conversation.assignee}
									<span class="flex items-center gap-1">
										<User class="h-3 w-3" />
										{conversation.assignee.name}
									</span>
								{/if}
								<span>{conversation.message_count} messages</span>
								<span>{formatTime(conversation.last_message_at)}</span>
							</div>

							{#if conversation.tags && conversation.tags.length > 0}
								<div class="flex items-center gap-1 mt-1">
									{#each conversation.tags.slice(0, 3) as tag}
										<Badge variant="outline" class="text-xs">{tag}</Badge>
									{/each}
									{#if conversation.tags.length > 3}
										<Badge variant="outline" class="text-xs">+{conversation.tags.length - 3}</Badge>
									{/if}
								</div>
							{/if}
						</div>

						<div class="flex flex-col items-end gap-1">
							<button
								class="p-1 hover:bg-muted rounded"
								onclick={(e) => toggleStar(conversation, e)}
							>
								{#if conversation.is_starred}
									<Star class="h-4 w-4 fill-yellow-400 text-yellow-400" />
								{:else}
									<StarOff class="h-4 w-4 text-muted-foreground" />
								{/if}
							</button>
						</div>
					</div>
				{/each}
			</div>
		{/if}
	</div>

	<!-- Pagination -->
	{#if pagination.lastPage > 1}
		<div class="border-t p-3 flex items-center justify-between">
			<p class="text-sm text-muted-foreground">
				Page {pagination.currentPage} of {pagination.lastPage}
			</p>
			<div class="flex items-center gap-2">
				<Button
					variant="outline"
					size="sm"
					disabled={pagination.currentPage <= 1}
					onclick={() => {
						pagination.currentPage--;
						loadConversations();
					}}
				>
					Previous
				</Button>
				<Button
					variant="outline"
					size="sm"
					disabled={pagination.currentPage >= pagination.lastPage}
					onclick={() => {
						pagination.currentPage++;
						loadConversations();
					}}
				>
					Next
				</Button>
			</div>
		</div>
	{/if}
</div>

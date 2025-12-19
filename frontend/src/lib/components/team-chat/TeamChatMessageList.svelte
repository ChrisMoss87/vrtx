<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import {
		teamChatMessagesApi,
		teamChatConnectionsApi,
		type TeamChatMessage,
		type TeamChatConnection
	} from '$lib/api/team-chat';
	import {
		RefreshCw,
		MessageSquare,
		CheckCircle,
		XCircle,
		Clock,
		Send,
		RotateCcw,
		Filter,
		Hash,
		ExternalLink
	} from 'lucide-svelte';

	interface Props {
		moduleApiName?: string;
		recordId?: number;
	}

	let { moduleApiName, recordId }: Props = $props();

	let messages = $state<TeamChatMessage[]>([]);
	let connections = $state<TeamChatConnection[]>([]);
	let loading = $state(true);
	let retrying = $state<number | null>(null);

	let filters = $state({
		connection_id: 0,
		status: ''
	});

	let pagination = $state({
		currentPage: 1,
		lastPage: 1,
		total: 0
	});

	async function loadMessages() {
		loading = true;
		try {
			if (moduleApiName && recordId) {
				messages = await teamChatMessagesApi.getForRecord(moduleApiName, recordId);
			} else {
				const params: Record<string, number | string> = {
					page: pagination.currentPage
				};
				if (filters.connection_id) params.connection_id = filters.connection_id;
				if (filters.status) params.status = filters.status;

				const result = await teamChatMessagesApi.list(params);
				messages = result.data;
				pagination = {
					currentPage: result.meta.current_page,
					lastPage: result.meta.last_page,
					total: result.meta.total
				};
			}
		} catch (err) {
			console.error('Failed to load messages:', err);
		} finally {
			loading = false;
		}
	}

	async function loadConnections() {
		try {
			connections = await teamChatConnectionsApi.list();
		} catch (err) {
			console.error('Failed to load connections:', err);
		}
	}

	async function retryMessage(message: TeamChatMessage) {
		retrying = message.id;
		try {
			const newMessage = await teamChatMessagesApi.retry(message.id);
			const idx = messages.findIndex((m) => m.id === message.id);
			if (idx !== -1) {
				messages = [...messages.slice(0, idx + 1), newMessage, ...messages.slice(idx + 1)];
			}
		} catch (err) {
			console.error('Failed to retry message:', err);
		} finally {
			retrying = null;
		}
	}

	function getStatusBadge(status: string) {
		switch (status) {
			case 'sent':
			case 'delivered':
				return { variant: 'default' as const, class: 'bg-green-500', icon: CheckCircle };
			case 'pending':
				return { variant: 'secondary' as const, class: '', icon: Clock };
			case 'failed':
				return { variant: 'destructive' as const, class: '', icon: XCircle };
			default:
				return { variant: 'outline' as const, class: '', icon: MessageSquare };
		}
	}

	function formatDate(dateStr: string): string {
		const date = new Date(dateStr);
		return date.toLocaleString();
	}

	function truncateContent(content: string, maxLength = 100): string {
		if (content.length <= maxLength) return content;
		return content.substring(0, maxLength) + '...';
	}

	$effect(() => {
		loadConnections();
	});

	$effect(() => {
		loadMessages();
	});

	$effect(() => {
		// Reload when filters change
		if (filters.connection_id !== undefined || filters.status !== undefined) {
			pagination.currentPage = 1;
			loadMessages();
		}
	});
</script>

<div class="space-y-6">
	{#if !moduleApiName}
		<div class="flex items-center justify-between">
			<div>
				<h2 class="text-lg font-semibold">Message History</h2>
				<p class="text-sm text-muted-foreground">View all messages sent to Slack and Teams</p>
			</div>
			<Button variant="outline" onclick={() => loadMessages()}>
				<RefreshCw class="mr-2 h-4 w-4" />
				Refresh
			</Button>
		</div>

		<!-- Filters -->
		<Card.Root>
			<Card.Content class="py-4">
				<div class="flex items-center gap-4">
					<Filter class="h-4 w-4 text-muted-foreground" />
					<div class="flex items-center gap-2">
						<Select.Root
							type="single"
							value={filters.connection_id ? String(filters.connection_id) : ''}
							onValueChange={(v) => {
								filters.connection_id = v ? parseInt(v) : 0;
							}}
						>
							<Select.Trigger class="w-48">
								<span>
									{#if filters.connection_id}
										{connections.find((c) => c.id === filters.connection_id)?.name ??
											'All Connections'}
									{:else}
										All Connections
									{/if}
								</span>
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="">All Connections</Select.Item>
								{#each connections as conn (conn.id)}
									<Select.Item value={String(conn.id)}>
										{conn.provider === 'slack' ? '🔵' : '🟣'}
										{conn.name}
									</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>
					<div class="flex items-center gap-2">
						<Select.Root
							type="single"
							value={filters.status}
							onValueChange={(v) => {
								filters.status = v ?? '';
							}}
						>
							<Select.Trigger class="w-36">
								<span>{filters.status || 'All Status'}</span>
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="">All Status</Select.Item>
								<Select.Item value="pending">Pending</Select.Item>
								<Select.Item value="sent">Sent</Select.Item>
								<Select.Item value="delivered">Delivered</Select.Item>
								<Select.Item value="failed">Failed</Select.Item>
							</Select.Content>
						</Select.Root>
					</div>
					{#if filters.connection_id || filters.status}
						<Button
							variant="ghost"
							size="sm"
							onclick={() => {
								filters = { connection_id: 0, status: '' };
							}}
						>
							Clear filters
						</Button>
					{/if}
				</div>
			</Card.Content>
		</Card.Root>
	{/if}

	{#if loading}
		<div class="flex items-center justify-center py-8">
			<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
		</div>
	{:else if messages.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<MessageSquare class="h-12 w-12 text-muted-foreground mb-4" />
				<h3 class="font-medium mb-2">No Messages</h3>
				<p class="text-sm text-muted-foreground">
					{#if moduleApiName}
						No team chat messages for this record
					{:else}
						Messages will appear here when notifications are triggered
					{/if}
				</p>
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="space-y-2">
			{#each messages as message (message.id)}
				{@const statusInfo = getStatusBadge(message.status)}
				{@const StatusIcon = statusInfo.icon}
				<Card.Root>
					<Card.Content class="py-3">
						<div class="flex items-start gap-3">
							<div class="flex-1 min-w-0">
								<div class="flex items-center gap-2 mb-1">
									{#if message.connection}
										<span class="text-sm">
											{message.connection.provider === 'slack' ? '🔵' : '🟣'}
										</span>
									{/if}
									{#if message.channel}
										<span class="text-sm flex items-center gap-1">
											<Hash class="h-3 w-3" />
											{message.channel.name}
										</span>
									{/if}
									<Badge variant={statusInfo.variant} class={statusInfo.class}>
										<StatusIcon class="mr-1 h-3 w-3" />
										{message.status}
									</Badge>
									{#if message.notification}
										<Badge variant="outline">{message.notification.name}</Badge>
									{/if}
								</div>
								<p class="text-sm text-muted-foreground whitespace-pre-wrap">
									{truncateContent(message.content)}
								</p>
								<div class="flex items-center gap-3 mt-2 text-xs text-muted-foreground">
									<span>{formatDate(message.created_at)}</span>
									{#if message.sender}
										<span>by {message.sender.name}</span>
									{/if}
									{#if message.module_api_name && message.module_record_id}
										<a
											href="/records/{message.module_api_name}/{message.module_record_id}"
											class="flex items-center gap-1 text-primary hover:underline"
										>
											<ExternalLink class="h-3 w-3" />
											View Record
										</a>
									{/if}
								</div>
								{#if message.status === 'failed' && message.error_message}
									<p class="text-xs text-destructive mt-1">Error: {message.error_message}</p>
								{/if}
							</div>
							<div class="flex items-center gap-1">
								{#if message.status === 'failed'}
									<Button
										variant="outline"
										size="sm"
										onclick={() => retryMessage(message)}
										disabled={retrying === message.id}
									>
										{#if retrying === message.id}
											<RefreshCw class="h-4 w-4 animate-spin" />
										{:else}
											<RotateCcw class="h-4 w-4" />
										{/if}
									</Button>
								{/if}
							</div>
						</div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>

		<!-- Pagination -->
		{#if !moduleApiName && pagination.lastPage > 1}
			<div class="flex items-center justify-between">
				<p class="text-sm text-muted-foreground">
					Page {pagination.currentPage} of {pagination.lastPage} ({pagination.total} messages)
				</p>
				<div class="flex items-center gap-2">
					<Button
						variant="outline"
						size="sm"
						disabled={pagination.currentPage <= 1}
						onclick={() => {
							pagination.currentPage--;
							loadMessages();
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
							loadMessages();
						}}
					>
						Next
					</Button>
				</div>
			</div>
		{/if}
	{/if}
</div>

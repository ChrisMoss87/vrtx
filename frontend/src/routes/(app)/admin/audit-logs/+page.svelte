<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Table from '$lib/components/ui/table';
	import {
		auditLogsApi,
		getEventLabel,
		type AuditLog,
		type AuditEvent,
		type AuditLogFilters
	} from '$lib/api/activity';
	import { toast } from 'svelte-sonner';
	import {
		History,
		Search,
		Filter,
		RefreshCw,
		User,
		Calendar,
		ChevronLeft,
		ChevronRight,
		Eye,
		FileText,
		AlertCircle,
		CheckCircle,
		Trash2,
		RotateCcw,
		LogIn,
		LogOut,
		Link2,
		Unlink
	} from 'lucide-svelte';

	// State
	let logs = $state<AuditLog[]>([]);
	let loading = $state(true);
	let selectedLog = $state<AuditLog | null>(null);
	let showDetailDialog = $state(false);

	// Pagination
	let currentPage = $state(1);
	let lastPage = $state(1);
	let perPage = $state(25);
	let total = $state(0);

	// Filters
	let filterEvent = $state<AuditEvent | ''>('');
	let filterUserId = $state<string>('');
	let filterStartDate = $state('');
	let filterEndDate = $state('');
	let showFilters = $state(false);

	const eventOptions: { value: AuditEvent; label: string }[] = [
		{ value: 'created', label: 'Created' },
		{ value: 'updated', label: 'Updated' },
		{ value: 'deleted', label: 'Deleted' },
		{ value: 'restored', label: 'Restored' },
		{ value: 'login', label: 'Login' },
		{ value: 'logout', label: 'Logout' },
		{ value: 'failed_login', label: 'Failed Login' }
	];

	async function loadLogs() {
		loading = true;
		try {
			const filters: AuditLogFilters = {
				per_page: perPage
			};
			if (filterEvent) filters.event = filterEvent;
			if (filterUserId) filters.user_id = parseInt(filterUserId);
			if (filterStartDate) filters.start_date = filterStartDate;
			if (filterEndDate) filters.end_date = filterEndDate;

			const response = await auditLogsApi.list(filters);
			logs = response.data;
			currentPage = response.meta.current_page;
			lastPage = response.meta.last_page;
			total = response.meta.total;
		} catch (e) {
			toast.error('Failed to load audit logs');
		} finally {
			loading = false;
		}
	}

	function clearFilters() {
		filterEvent = '';
		filterUserId = '';
		filterStartDate = '';
		filterEndDate = '';
		loadLogs();
	}

	function viewLogDetail(log: AuditLog) {
		selectedLog = log;
		showDetailDialog = true;
	}

	function getEventIcon(event: AuditEvent) {
		switch (event) {
			case 'created':
				return CheckCircle;
			case 'updated':
				return FileText;
			case 'deleted':
				return Trash2;
			case 'restored':
				return RotateCcw;
			case 'login':
				return LogIn;
			case 'logout':
				return LogOut;
			case 'failed_login':
				return AlertCircle;
			case 'attached':
				return Link2;
			case 'detached':
				return Unlink;
			default:
				return History;
		}
	}

	function getEventColor(event: AuditEvent): string {
		switch (event) {
			case 'created':
				return 'bg-green-500/10 text-green-700 border-green-500/20';
			case 'updated':
				return 'bg-blue-500/10 text-blue-700 border-blue-500/20';
			case 'deleted':
			case 'force_deleted':
				return 'bg-red-500/10 text-red-700 border-red-500/20';
			case 'restored':
				return 'bg-purple-500/10 text-purple-700 border-purple-500/20';
			case 'login':
			case 'logout':
				return 'bg-gray-500/10 text-gray-700 border-gray-500/20';
			case 'failed_login':
				return 'bg-orange-500/10 text-orange-700 border-orange-500/20';
			default:
				return 'bg-gray-500/10 text-gray-700 border-gray-500/20';
		}
	}

	function formatTimestamp(timestamp: string): string {
		return new Date(timestamp).toLocaleString();
	}

	function formatEntityType(type: string): string {
		// Convert "App\\Models\\Deal" to "Deal"
		const parts = type.split('\\');
		return parts[parts.length - 1] ?? type;
	}

	function formatChangeValue(value: unknown): string {
		if (value === null || value === undefined) return '(empty)';
		if (typeof value === 'object') return JSON.stringify(value);
		return String(value);
	}

	$effect(() => {
		loadLogs();
	});
</script>

<svelte:head>
	<title>Audit Logs | Admin | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold flex items-center gap-2">
				<History class="h-6 w-6" />
				Audit Logs
			</h1>
			<p class="text-muted-foreground">Track all changes and actions across your CRM</p>
		</div>
		<div class="flex items-center gap-2">
			<Button variant="outline" onclick={() => (showFilters = !showFilters)}>
				<Filter class="mr-2 h-4 w-4" />
				Filters
			</Button>
			<Button variant="outline" onclick={loadLogs}>
				<RefreshCw class="mr-2 h-4 w-4" />
				Refresh
			</Button>
		</div>
	</div>

	<!-- Filters Panel -->
	{#if showFilters}
		<Card>
			<CardContent class="pt-6">
				<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
					<div class="space-y-2">
						<Label>Event Type</Label>
						<Select.Root type="single" bind:value={filterEvent}>
							<Select.Trigger>
								{filterEvent ? eventOptions.find((o) => o.value === filterEvent)?.label : 'All events'}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="">All events</Select.Item>
								{#each eventOptions as option}
									<Select.Item value={option.value}>{option.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>
					<div class="space-y-2">
						<Label>User ID</Label>
						<Input type="number" bind:value={filterUserId} placeholder="Filter by user ID" />
					</div>
					<div class="space-y-2">
						<Label>Start Date</Label>
						<Input type="date" bind:value={filterStartDate} />
					</div>
					<div class="space-y-2">
						<Label>End Date</Label>
						<Input type="date" bind:value={filterEndDate} />
					</div>
				</div>
				<div class="flex justify-end gap-2 mt-4">
					<Button variant="outline" onclick={clearFilters}>Clear</Button>
					<Button onclick={loadLogs}>
						<Search class="mr-2 h-4 w-4" />
						Apply Filters
					</Button>
				</div>
			</CardContent>
		</Card>
	{/if}

	<!-- Stats Summary -->
	<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
		<Card>
			<CardContent class="pt-4">
				<div class="text-2xl font-bold">{total}</div>
				<div class="text-sm text-muted-foreground">Total Entries</div>
			</CardContent>
		</Card>
		<Card>
			<CardContent class="pt-4">
				<div class="text-2xl font-bold">{logs.filter((l) => l.event === 'created').length}</div>
				<div class="text-sm text-muted-foreground">Creates (This Page)</div>
			</CardContent>
		</Card>
		<Card>
			<CardContent class="pt-4">
				<div class="text-2xl font-bold">{logs.filter((l) => l.event === 'updated').length}</div>
				<div class="text-sm text-muted-foreground">Updates (This Page)</div>
			</CardContent>
		</Card>
		<Card>
			<CardContent class="pt-4">
				<div class="text-2xl font-bold">{logs.filter((l) => l.event === 'deleted').length}</div>
				<div class="text-sm text-muted-foreground">Deletes (This Page)</div>
			</CardContent>
		</Card>
	</div>

	<!-- Logs Table -->
	<Card>
		<CardContent class="p-0">
			{#if loading}
				<div class="p-6 space-y-4">
					{#each Array(5) as _}
						<Skeleton class="h-12 w-full" />
					{/each}
				</div>
			{:else if logs.length === 0}
				<div class="p-12 text-center text-muted-foreground">
					<History class="h-12 w-12 mx-auto mb-4 opacity-50" />
					<p>No audit logs found</p>
				</div>
			{:else}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head class="w-[180px]">Timestamp</Table.Head>
							<Table.Head>Event</Table.Head>
							<Table.Head>Entity</Table.Head>
							<Table.Head>User</Table.Head>
							<Table.Head>Description</Table.Head>
							<Table.Head class="w-[80px]">Actions</Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each logs as log}
							<Table.Row>
								<Table.Cell class="text-sm">
									<div class="flex items-center gap-2">
										<Calendar class="h-4 w-4 text-muted-foreground" />
										{formatTimestamp(log.created_at)}
									</div>
								</Table.Cell>
								<Table.Cell>
									{@const EventIcon = getEventIcon(log.event)}
									<Badge class={getEventColor(log.event)}>
										<EventIcon class="mr-1 h-3 w-3" />
										{getEventLabel(log.event)}
									</Badge>
								</Table.Cell>
								<Table.Cell>
									<div class="font-medium">{formatEntityType(log.auditable_type)}</div>
									<div class="text-xs text-muted-foreground">ID: {log.auditable_id}</div>
								</Table.Cell>
								<Table.Cell>
									{#if log.user}
										<div class="flex items-center gap-2">
											<User class="h-4 w-4 text-muted-foreground" />
											<span>{log.user.name}</span>
										</div>
									{:else}
										<span class="text-muted-foreground">System</span>
									{/if}
								</Table.Cell>
								<Table.Cell>
									<div class="max-w-[300px] truncate text-sm text-muted-foreground">
										{log.event_description || `${getEventLabel(log.event)} ${formatEntityType(log.auditable_type)}`}
									</div>
								</Table.Cell>
								<Table.Cell>
									<Button variant="ghost" size="icon" onclick={() => viewLogDetail(log)}>
										<Eye class="h-4 w-4" />
									</Button>
								</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>

				<!-- Pagination -->
				<div class="flex items-center justify-between px-4 py-3 border-t">
					<div class="text-sm text-muted-foreground">
						Showing {(currentPage - 1) * perPage + 1} to {Math.min(currentPage * perPage, total)} of {total} entries
					</div>
					<div class="flex items-center gap-2">
						<Button
							variant="outline"
							size="sm"
							disabled={currentPage === 1}
							onclick={() => {
								currentPage--;
								loadLogs();
							}}
						>
							<ChevronLeft class="h-4 w-4" />
						</Button>
						<span class="text-sm">
							Page {currentPage} of {lastPage}
						</span>
						<Button
							variant="outline"
							size="sm"
							disabled={currentPage === lastPage}
							onclick={() => {
								currentPage++;
								loadLogs();
							}}
						>
							<ChevronRight class="h-4 w-4" />
						</Button>
					</div>
				</div>
			{/if}
		</CardContent>
	</Card>
</div>

<!-- Log Detail Dialog -->
<Dialog.Root bind:open={showDetailDialog}>
	<Dialog.Content class="max-w-2xl max-h-[90vh] overflow-y-auto">
		{#if selectedLog}
			{@const DetailEventIcon = getEventIcon(selectedLog.event)}
			<Dialog.Header>
				<Dialog.Title class="flex items-center gap-2">
					<DetailEventIcon class="h-5 w-5" />
					Audit Log Details
				</Dialog.Title>
				<Dialog.Description>
					{getEventLabel(selectedLog.event)} - {formatEntityType(selectedLog.auditable_type)} #{selectedLog.auditable_id}
				</Dialog.Description>
			</Dialog.Header>

			<div class="space-y-4">
				<!-- Metadata -->
				<div class="grid grid-cols-2 gap-4 text-sm">
					<div>
						<span class="text-muted-foreground">Timestamp:</span>
						<span class="ml-2 font-medium">{formatTimestamp(selectedLog.created_at)}</span>
					</div>
					<div>
						<span class="text-muted-foreground">User:</span>
						<span class="ml-2 font-medium">{selectedLog.user?.name ?? 'System'}</span>
					</div>
					{#if selectedLog.ip_address}
						<div>
							<span class="text-muted-foreground">IP Address:</span>
							<span class="ml-2 font-mono text-xs">{selectedLog.ip_address}</span>
						</div>
					{/if}
					{#if selectedLog.url}
						<div>
							<span class="text-muted-foreground">URL:</span>
							<span class="ml-2 font-mono text-xs truncate">{selectedLog.url}</span>
						</div>
					{/if}
				</div>

				<!-- Changed Fields -->
				{#if selectedLog.changed_fields && selectedLog.changed_fields.length > 0}
					<div>
						<h4 class="font-medium mb-2">Changed Fields</h4>
						<div class="flex flex-wrap gap-2">
							{#each selectedLog.changed_fields as field}
								<Badge variant="outline">{field}</Badge>
							{/each}
						</div>
					</div>
				{/if}

				<!-- Diff View -->
				{#if selectedLog.diff && Object.keys(selectedLog.diff).length > 0}
					<div>
						<h4 class="font-medium mb-2">Changes</h4>
						<div class="rounded-lg border divide-y">
							{#each Object.entries(selectedLog.diff) as [field, change]}
								<div class="p-3">
									<div class="font-medium text-sm mb-1">{field}</div>
									<div class="grid grid-cols-2 gap-4 text-sm">
										<div class="bg-red-50 dark:bg-red-950/20 rounded p-2">
											<span class="text-xs text-muted-foreground block mb-1">Before</span>
											<span class="line-through opacity-70">{formatChangeValue(change.old)}</span>
										</div>
										<div class="bg-green-50 dark:bg-green-950/20 rounded p-2">
											<span class="text-xs text-muted-foreground block mb-1">After</span>
											<span>{formatChangeValue(change.new)}</span>
										</div>
									</div>
								</div>
							{/each}
						</div>
					</div>
				{:else if selectedLog.new_values}
					<div>
						<h4 class="font-medium mb-2">New Values</h4>
						<pre class="rounded-lg bg-muted p-3 text-xs overflow-x-auto">{JSON.stringify(selectedLog.new_values, null, 2)}</pre>
					</div>
				{/if}

				<!-- Old Values (for deletes) -->
				{#if selectedLog.event === 'deleted' && selectedLog.old_values}
					<div>
						<h4 class="font-medium mb-2">Deleted Data</h4>
						<pre class="rounded-lg bg-muted p-3 text-xs overflow-x-auto">{JSON.stringify(selectedLog.old_values, null, 2)}</pre>
					</div>
				{/if}

				<!-- Tags -->
				{#if selectedLog.tags && selectedLog.tags.length > 0}
					<div>
						<h4 class="font-medium mb-2">Tags</h4>
						<div class="flex flex-wrap gap-2">
							{#each selectedLog.tags as tag}
								<Badge variant="secondary">{tag}</Badge>
							{/each}
						</div>
					</div>
				{/if}

				<!-- User Agent -->
				{#if selectedLog.user_agent}
					<div>
						<h4 class="font-medium mb-2">User Agent</h4>
						<p class="text-xs text-muted-foreground font-mono break-all">{selectedLog.user_agent}</p>
					</div>
				{/if}
			</div>

			<Dialog.Footer>
				<Button variant="outline" onclick={() => (showDetailDialog = false)}>Close</Button>
			</Dialog.Footer>
		{/if}
	</Dialog.Content>
</Dialog.Root>

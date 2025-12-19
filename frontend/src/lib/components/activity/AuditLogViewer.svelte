<script lang="ts">
	import {
		History,
		ChevronDown,
		ChevronUp,
		Plus,
		Minus,
		Edit,
		Trash,
		RotateCcw,
		RefreshCw,
		User,
		Clock
	} from 'lucide-svelte';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import * as Collapsible from '$lib/components/ui/collapsible';
	import * as Dialog from '$lib/components/ui/dialog';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Avatar, AvatarFallback } from '$lib/components/ui/avatar';
	import { Label } from '$lib/components/ui/label';
	import { auditLogsApi, type AuditLog, type AuditEvent, getEventLabel } from '$lib/api/activity';
	import { cn } from '$lib/utils';
	import { formatDistanceToNow, format } from 'date-fns';

	interface Props {
		auditableType: string;
		auditableId: number;
		logs?: AuditLog[];
		limit?: number;
		showSummary?: boolean;
		class?: string;
	}

	let {
		auditableType,
		auditableId,
		logs = $bindable([]),
		limit = 50,
		showSummary = true,
		class: className = ''
	}: Props = $props();

	// State
	let isLoading = $state(false);
	let expandedIds = $state<Set<number>>(new Set());
	let selectedLog = $state<AuditLog | null>(null);
	let summary = $state<{
		total_changes: number;
		event_counts: Record<string, number>;
		unique_users: number;
		first_change_at: string | null;
		last_change_at: string | null;
		last_change_by: { id: number; name: string; email: string } | null;
	} | null>(null);

	// Load logs and summary
	$effect(() => {
		if (auditableType && auditableId) {
			loadLogs();
			if (showSummary) {
				loadSummary();
			}
		}
	});

	async function loadLogs() {
		isLoading = true;
		try {
			logs = await auditLogsApi.getForRecord(auditableType, auditableId, limit);
		} catch (error) {
			console.error('Failed to load audit logs:', error);
		} finally {
			isLoading = false;
		}
	}

	async function loadSummary() {
		try {
			summary = await auditLogsApi.getSummary(auditableType, auditableId);
		} catch (error) {
			console.error('Failed to load summary:', error);
		}
	}

	async function viewDetails(log: AuditLog) {
		try {
			selectedLog = await auditLogsApi.get(log.id);
		} catch (error) {
			console.error('Failed to load log details:', error);
			selectedLog = log;
		}
	}

	function toggleExpanded(id: number) {
		const newSet = new Set(expandedIds);
		if (newSet.has(id)) {
			newSet.delete(id);
		} else {
			newSet.add(id);
		}
		expandedIds = newSet;
	}

	function getEventIcon(event: AuditEvent) {
		const icons = {
			created: Plus,
			updated: Edit,
			deleted: Trash,
			restored: RotateCcw,
			force_deleted: Trash,
			attached: Plus,
			detached: Minus,
			synced: RefreshCw,
			login: User,
			logout: User,
			failed_login: User
		};
		return icons[event] ?? History;
	}

	function getEventColor(event: AuditEvent): string {
		const colors: Record<AuditEvent, string> = {
			created: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
			updated: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
			deleted: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
			restored: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
			force_deleted: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
			attached: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
			detached: 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
			synced: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900 dark:text-cyan-300',
			login: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
			logout: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
			failed_login: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
		};
		return colors[event] ?? 'bg-gray-100 text-gray-700';
	}

	function formatDate(dateString: string): string {
		return formatDistanceToNow(new Date(dateString), { addSuffix: true });
	}

	function formatValue(value: unknown): string {
		if (value === null || value === undefined) return '(empty)';
		if (typeof value === 'boolean') return value ? 'Yes' : 'No';
		if (typeof value === 'object') return JSON.stringify(value);
		return String(value);
	}

	function getInitials(name: string): string {
		return name
			.split(' ')
			.map(n => n[0])
			.join('')
			.toUpperCase()
			.slice(0, 2);
	}
</script>

<div class={cn('flex flex-col', className)}>
	<!-- Summary -->
	{#if showSummary && summary}
		<div class="mb-4 rounded-lg border bg-muted/30 p-4">
			<div class="grid grid-cols-2 gap-4 md:grid-cols-4">
				<div>
					<div class="text-2xl font-bold">{summary.total_changes}</div>
					<div class="text-sm text-muted-foreground">Total Changes</div>
				</div>
				<div>
					<div class="text-2xl font-bold">{summary.unique_users}</div>
					<div class="text-sm text-muted-foreground">Contributors</div>
				</div>
				<div>
					<div class="text-sm font-medium">
						{summary.first_change_at ? format(new Date(summary.first_change_at), 'MMM d, yyyy') : '-'}
					</div>
					<div class="text-sm text-muted-foreground">First Change</div>
				</div>
				<div>
					<div class="text-sm font-medium">
						{summary.last_change_at ? formatDate(summary.last_change_at) : '-'}
					</div>
					<div class="text-sm text-muted-foreground">Last Change</div>
				</div>
			</div>
		</div>
	{/if}

	<!-- Header -->
	<div class="mb-4 flex items-center justify-between">
		<div class="flex items-center gap-2">
			<History class="h-4 w-4 text-muted-foreground" />
			<h3 class="font-medium">Audit History</h3>
			<Badge variant="secondary">{logs.length}</Badge>
		</div>
		<Button variant="ghost" size="sm" onclick={loadLogs} disabled={isLoading}>
			<RefreshCw class={cn('h-4 w-4', isLoading && 'animate-spin')} />
		</Button>
	</div>

	<!-- Logs -->
	{#if isLoading}
		<div class="flex items-center justify-center py-8">
			<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
		</div>
	{:else if logs.length === 0}
		<div class="py-8 text-center text-muted-foreground">
			<History class="mx-auto mb-2 h-8 w-8 opacity-50" />
			<p>No audit history</p>
		</div>
	{:else}
		<div class="space-y-2">
			{#each logs as log (log.id)}
				{@const Icon = getEventIcon(log.event)}
				{@const isExpanded = expandedIds.has(log.id)}

				<div class="rounded-lg border bg-card">
					<button
						type="button"
						class="flex w-full items-center gap-3 p-3 text-left hover:bg-muted/50"
						onclick={() => toggleExpanded(log.id)}
					>
						<div class={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-full', getEventColor(log.event))}>
							<Icon class="h-4 w-4" />
						</div>

						<div class="min-w-0 flex-1">
							<div class="flex items-center gap-2">
								<span class="font-medium">{log.event_description}</span>
								{#if log.changed_fields.length > 0}
									<Badge variant="outline" class="text-xs">
										{log.changed_fields.length} field{log.changed_fields.length !== 1 ? 's' : ''}
									</Badge>
								{/if}
							</div>
							<div class="flex items-center gap-2 text-xs text-muted-foreground">
								{#if log.user}
									<span>{log.user.name}</span>
									<span>•</span>
								{/if}
								<span>{formatDate(log.created_at)}</span>
								{#if log.ip_address}
									<span>•</span>
									<span>{log.ip_address}</span>
								{/if}
							</div>
						</div>

						<div class="shrink-0">
							{#if isExpanded}
								<ChevronUp class="h-4 w-4 text-muted-foreground" />
							{:else}
								<ChevronDown class="h-4 w-4 text-muted-foreground" />
							{/if}
						</div>
					</button>

					{#if isExpanded}
						<div class="border-t px-3 py-2">
							{#if log.changed_fields.length > 0}
								<div class="space-y-1">
									{#each log.changed_fields as field}
										{@const oldValue = log.old_values?.[field]}
										{@const newValue = log.new_values?.[field]}
										<div class="flex items-start gap-2 text-sm">
											<span class="w-32 shrink-0 font-medium text-muted-foreground">{field}</span>
											{#if log.event === 'created'}
												<span class="text-green-600">{formatValue(newValue)}</span>
											{:else if log.event === 'deleted'}
												<span class="text-red-600 line-through">{formatValue(oldValue)}</span>
											{:else}
												<span class="text-muted-foreground line-through">{formatValue(oldValue)}</span>
												<span class="text-muted-foreground">→</span>
												<span>{formatValue(newValue)}</span>
											{/if}
										</div>
									{/each}
								</div>
							{:else if log.event === 'created' && log.new_values}
								<div class="space-y-1">
									{#each Object.entries(log.new_values) as [field, value]}
										<div class="flex items-start gap-2 text-sm">
											<span class="w-32 shrink-0 font-medium text-muted-foreground">{field}</span>
											<span class="text-green-600">{formatValue(value)}</span>
										</div>
									{/each}
								</div>
							{:else}
								<p class="text-sm text-muted-foreground">No field changes recorded</p>
							{/if}

							<div class="mt-2 flex justify-end">
								<Button variant="ghost" size="sm" onclick={() => viewDetails(log)}>
									View Full Details
								</Button>
							</div>
						</div>
					{/if}
				</div>
			{/each}
		</div>
	{/if}
</div>

<!-- Detail Dialog -->
<Dialog.Root open={!!selectedLog} onOpenChange={(open) => !open && (selectedLog = null)}>
	<Dialog.Content class="max-w-2xl max-h-[80vh] overflow-y-auto">
		<Dialog.Header>
			<Dialog.Title>Audit Log Details</Dialog.Title>
		</Dialog.Header>

		{#if selectedLog}
			<div class="space-y-4 py-4">
				<!-- Event info -->
				<div class="grid grid-cols-2 gap-4">
					<div>
						<Label class="text-muted-foreground">Event</Label>
						<div class="font-medium">{getEventLabel(selectedLog.event)}</div>
					</div>
					<div>
						<Label class="text-muted-foreground">Date</Label>
						<div class="font-medium">{format(new Date(selectedLog.created_at), 'PPpp')}</div>
					</div>
					<div>
						<Label class="text-muted-foreground">User</Label>
						<div class="font-medium">{selectedLog.user?.name ?? 'System'}</div>
					</div>
					<div>
						<Label class="text-muted-foreground">IP Address</Label>
						<div class="font-medium">{selectedLog.ip_address ?? '-'}</div>
					</div>
				</div>

				<!-- Changes diff -->
				{#if selectedLog.diff && Object.keys(selectedLog.diff).length > 0}
					<div>
						<Label class="text-muted-foreground">Changes</Label>
						<div class="mt-2 rounded-lg border">
							{#each Object.entries(selectedLog.diff) as [field, change], i}
								<div class={cn('flex items-start gap-4 p-3', i > 0 && 'border-t')}>
									<div class="w-32 shrink-0 font-medium">{field}</div>
									<div class="flex-1 space-y-1">
										{#if change.old !== null}
											<div class="flex items-center gap-2">
												<Minus class="h-3 w-3 text-red-500" />
												<span class="text-red-600 line-through">{formatValue(change.old)}</span>
											</div>
										{/if}
										{#if change.new !== null}
											<div class="flex items-center gap-2">
												<Plus class="h-3 w-3 text-green-500" />
												<span class="text-green-600">{formatValue(change.new)}</span>
											</div>
										{/if}
									</div>
								</div>
							{/each}
						</div>
					</div>
				{/if}

				<!-- Meta -->
				{#if selectedLog.user_agent}
					<div>
						<Label class="text-muted-foreground">User Agent</Label>
						<div class="text-sm">{selectedLog.user_agent}</div>
					</div>
				{/if}

				{#if selectedLog.url}
					<div>
						<Label class="text-muted-foreground">URL</Label>
						<div class="truncate text-sm">{selectedLog.url}</div>
					</div>
				{/if}

				{#if selectedLog.tags && selectedLog.tags.length > 0}
					<div>
						<Label class="text-muted-foreground">Tags</Label>
						<div class="mt-1 flex flex-wrap gap-1">
							{#each selectedLog.tags as tag}
								<Badge variant="secondary">{tag}</Badge>
							{/each}
						</div>
					</div>
				{/if}
			</div>
		{/if}

		<Dialog.Footer>
			<Button onclick={() => selectedLog = null}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

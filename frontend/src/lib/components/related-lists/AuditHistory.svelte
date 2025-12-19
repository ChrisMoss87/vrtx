<script lang="ts">
	import { onMount } from 'svelte';
	import { auditLogsApi, type AuditLog } from '$lib/api/audit-logs';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import {
		History,
		Plus,
		Pencil,
		Trash2,
		RotateCcw,
		Link,
		Unlink,
		RefreshCw,
		ChevronDown,
		ChevronUp,
		User,
		ArrowRight
	} from 'lucide-svelte';
	import { formatDistanceToNow, parseISO } from 'date-fns';

	interface Props {
		auditableType: string;
		auditableId: number;
		limit?: number;
	}

	let { auditableType, auditableId, limit = 10 }: Props = $props();

	let logs = $state<AuditLog[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);
	let expanded = $state(true);
	let showAll = $state(false);

	const eventIcons: Record<string, typeof History> = {
		created: Plus,
		updated: Pencil,
		deleted: Trash2,
		restored: RotateCcw,
		force_deleted: Trash2,
		attached: Link,
		detached: Unlink,
		synced: RefreshCw
	};

	const eventColors: Record<string, string> = {
		created: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
		updated: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
		deleted: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
		restored: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
		force_deleted: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
		attached: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
		detached: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
		synced: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
	};

	const eventLabels: Record<string, string> = {
		created: 'Created',
		updated: 'Updated',
		deleted: 'Deleted',
		restored: 'Restored',
		force_deleted: 'Permanently Deleted',
		attached: 'Attached',
		detached: 'Detached',
		synced: 'Synced'
	};

	async function loadLogs() {
		loading = true;
		error = null;

		try {
			const response = await auditLogsApi.forRecord(auditableType, auditableId);
			const allLogs = response.data || [];
			logs = showAll ? allLogs : allLogs.slice(0, limit);
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load audit history';
		} finally {
			loading = false;
		}
	}

	function formatDate(dateString: string): string {
		return formatDistanceToNow(parseISO(dateString), { addSuffix: true });
	}

	function getChangedFields(log: AuditLog): Array<{ field: string; old: unknown; new: unknown }> {
		if (log.event !== 'updated' || !log.old_values || !log.new_values) return [];

		const changes: Array<{ field: string; old: unknown; new: unknown }> = [];
		const allKeys = new Set([...Object.keys(log.old_values), ...Object.keys(log.new_values)]);

		for (const key of allKeys) {
			const oldVal = log.old_values[key];
			const newVal = log.new_values[key];
			if (JSON.stringify(oldVal) !== JSON.stringify(newVal)) {
				changes.push({ field: key, old: oldVal, new: newVal });
			}
		}

		return changes;
	}

	function formatValue(value: unknown): string {
		if (value === null || value === undefined) return '(empty)';
		if (typeof value === 'boolean') return value ? 'Yes' : 'No';
		if (typeof value === 'object') return JSON.stringify(value);
		return String(value);
	}

	function formatFieldName(field: string): string {
		return field
			.replace(/_/g, ' ')
			.replace(/\b\w/g, (l) => l.toUpperCase());
	}

	onMount(() => {
		loadLogs();
	});

	$effect(() => {
		if (auditableType && auditableId) {
			loadLogs();
		}
	});
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<button
			class="flex items-center gap-2 hover:text-primary transition-colors"
			onclick={() => (expanded = !expanded)}
		>
			{#if expanded}
				<ChevronUp class="h-4 w-4" />
			{:else}
				<ChevronDown class="h-4 w-4" />
			{/if}
			<History class="h-4 w-4" />
			<Card.Title class="text-base">Change History</Card.Title>
			{#if !loading}
				<Badge variant="secondary" class="ml-2">{logs.length}</Badge>
			{/if}
		</button>
	</Card.Header>

	{#if expanded}
		<Card.Content>
			{#if loading}
				<div class="space-y-3">
					{#each [1, 2, 3] as _}
						<div class="flex gap-3">
							<Skeleton class="h-6 w-6 rounded flex-shrink-0" />
							<div class="flex-1 space-y-2">
								<Skeleton class="h-4 w-1/2" />
								<Skeleton class="h-3 w-3/4" />
							</div>
						</div>
					{/each}
				</div>
			{:else if error}
				<p class="text-sm text-destructive">{error}</p>
			{:else if logs.length === 0}
				<p class="text-sm text-muted-foreground text-center py-4">No changes recorded</p>
			{:else}
				<div class="space-y-3">
					{#each logs as log (log.id)}
						{@const Icon = eventIcons[log.event] || History}
						{@const colorClass = eventColors[log.event] || eventColors.updated}
						{@const changes = getChangedFields(log)}

						<div class="flex gap-3 pb-3 border-b last:border-0 last:pb-0">
							<div class="flex-shrink-0">
								<div class="flex h-6 w-6 items-center justify-center rounded {colorClass}">
									<Icon class="h-3 w-3" />
								</div>
							</div>

							<div class="flex-1 min-w-0">
								<div class="flex items-center gap-2 flex-wrap">
									<Badge variant="outline" class="text-xs {colorClass}">
										{eventLabels[log.event] || log.event}
									</Badge>
									<span class="text-xs text-muted-foreground">{formatDate(log.created_at)}</span>
								</div>

								{#if log.user}
									<div class="flex items-center gap-1 mt-1 text-xs text-muted-foreground">
										<User class="h-3 w-3" />
										<span>{log.user.name}</span>
									</div>
								{/if}

								{#if changes.length > 0}
									<div class="mt-2 space-y-1">
										{#each changes.slice(0, 3) as change}
											<div class="text-xs bg-muted/50 rounded px-2 py-1">
												<span class="font-medium">{formatFieldName(change.field)}:</span>
												<span class="text-muted-foreground ml-1">
													{formatValue(change.old)}
												</span>
												<ArrowRight class="inline h-3 w-3 mx-1 text-muted-foreground" />
												<span>{formatValue(change.new)}</span>
											</div>
										{/each}
										{#if changes.length > 3}
											<p class="text-xs text-muted-foreground">
												+{changes.length - 3} more changes
											</p>
										{/if}
									</div>
								{:else if log.event === 'created' && log.new_values}
									<p class="text-xs text-muted-foreground mt-1">
										Record created with {Object.keys(log.new_values).length} fields
									</p>
								{/if}
							</div>
						</div>
					{/each}
				</div>

				{#if !showAll && logs.length >= limit}
					<Button
						variant="ghost"
						size="sm"
						class="w-full mt-4"
						onclick={() => {
							showAll = true;
							loadLogs();
						}}
					>
						Show full history
					</Button>
				{/if}
			{/if}
		</Card.Content>
	{/if}
</Card.Root>

<script lang="ts">
	import { onMount } from 'svelte';
	import { auditLogsApi, type AuditLog, type AuditLogListParams } from '$lib/api/audit-logs';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import * as Collapsible from '$lib/components/ui/collapsible';
	import {
		ClipboardList,
		Loader2,
		ChevronDown,
		ChevronUp,
		Plus,
		Pencil,
		Trash2,
		RotateCcw,
		Link,
		Unlink,
		RefreshCw,
		User,
		Calendar,
		Filter,
		Search,
		ChevronLeft,
		ChevronRight
	} from 'lucide-svelte';

	let logs = $state<AuditLog[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);
	let expandedIds = $state<Set<number>>(new Set());

	// Filters
	let eventFilter = $state<string>('');
	let userIdFilter = $state<string>('');
	let fromDate = $state<string>('');
	let toDate = $state<string>('');
	let searchQuery = $state<string>('');

	// Pagination
	let currentPage = $state(1);
	let totalPages = $state(1);
	let totalRecords = $state(0);
	let perPage = $state(25);

	const eventTypes = [
		{ value: '', label: 'All Events' },
		{ value: 'created', label: 'Created' },
		{ value: 'updated', label: 'Updated' },
		{ value: 'deleted', label: 'Deleted' },
		{ value: 'restored', label: 'Restored' },
		{ value: 'attached', label: 'Attached' },
		{ value: 'detached', label: 'Detached' }
	];

	async function loadLogs() {
		loading = true;
		error = null;

		try {
			const params: AuditLogListParams = {
				page: currentPage,
				per_page: perPage
			};

			if (eventFilter) params.event = eventFilter;
			if (userIdFilter) params.user_id = parseInt(userIdFilter);
			if (fromDate) params.from_date = fromDate;
			if (toDate) params.to_date = toDate;

			const response = await auditLogsApi.list(params);
			logs = response.data;
			if (response.meta) {
				totalPages = response.meta.last_page;
				totalRecords = response.meta.total;
				currentPage = response.meta.current_page;
			}
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load audit logs';
		} finally {
			loading = false;
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

	function getEventIcon(event: string) {
		switch (event) {
			case 'created':
				return Plus;
			case 'updated':
				return Pencil;
			case 'deleted':
				return Trash2;
			case 'restored':
				return RotateCcw;
			case 'attached':
				return Link;
			case 'detached':
				return Unlink;
			case 'synced':
				return RefreshCw;
			default:
				return ClipboardList;
		}
	}

	function getEventBadgeVariant(event: string): 'default' | 'secondary' | 'destructive' | 'outline' {
		switch (event) {
			case 'created':
				return 'default';
			case 'deleted':
			case 'force_deleted':
				return 'destructive';
			case 'updated':
				return 'secondary';
			default:
				return 'outline';
		}
	}

	function formatAuditableType(type: string): string {
		// Convert "App\Models\ModuleRecord" to "Module Record"
		const parts = type.split('\\');
		const className = parts[parts.length - 1];
		return className.replace(/([A-Z])/g, ' $1').trim();
	}

	function formatValue(value: unknown): string {
		if (value === null || value === undefined) return '—';
		if (typeof value === 'object') return JSON.stringify(value, null, 2);
		return String(value);
	}

	function applyFilters() {
		currentPage = 1;
		loadLogs();
	}

	function clearFilters() {
		eventFilter = '';
		userIdFilter = '';
		fromDate = '';
		toDate = '';
		searchQuery = '';
		currentPage = 1;
		loadLogs();
	}

	function goToPage(page: number) {
		if (page >= 1 && page <= totalPages) {
			currentPage = page;
			loadLogs();
		}
	}

	onMount(() => {
		loadLogs();
	});

	const filteredLogs = $derived(
		searchQuery
			? logs.filter(
					(log) =>
						log.user?.name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
						log.user?.email?.toLowerCase().includes(searchQuery.toLowerCase()) ||
						formatAuditableType(log.auditable_type).toLowerCase().includes(searchQuery.toLowerCase())
				)
			: logs
	);

	const hasActiveFilters = $derived(eventFilter || userIdFilter || fromDate || toDate);
</script>

<svelte:head>
	<title>Audit Logs | VRTX</title>
</svelte:head>

<div class="max-w-6xl space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Audit Logs</h1>
			<p class="text-muted-foreground">View system activity and change history</p>
		</div>
		<Button variant="outline" onclick={() => loadLogs()}>
			<RefreshCw class="mr-2 h-4 w-4" />
			Refresh
		</Button>
	</div>

	<!-- Filters -->
	<Card>
		<CardHeader class="pb-3">
			<CardTitle class="flex items-center gap-2 text-base">
				<Filter class="h-4 w-4" />
				Filters
			</CardTitle>
		</CardHeader>
		<CardContent>
			<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
				<!-- Search -->
				<div class="space-y-2">
					<Label>Search</Label>
					<div class="relative">
						<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
						<Input
							type="text"
							placeholder="Search by user or type..."
							bind:value={searchQuery}
							class="pl-9"
						/>
					</div>
				</div>

				<!-- Event Type -->
				<div class="space-y-2">
					<Label>Event Type</Label>
					<Select.Root type="single" bind:value={eventFilter}>
						<Select.Trigger>
							{eventTypes.find((e) => e.value === eventFilter)?.label || 'All Events'}
						</Select.Trigger>
						<Select.Content>
							{#each eventTypes as event}
								<Select.Item value={event.value} label={event.label}>{event.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<!-- From Date -->
				<div class="space-y-2">
					<Label>From Date</Label>
					<Input type="date" bind:value={fromDate} />
				</div>

				<!-- To Date -->
				<div class="space-y-2">
					<Label>To Date</Label>
					<Input type="date" bind:value={toDate} />
				</div>
			</div>

			<div class="mt-4 flex gap-2">
				<Button onclick={applyFilters}>Apply Filters</Button>
				{#if hasActiveFilters}
					<Button variant="outline" onclick={clearFilters}>Clear Filters</Button>
				{/if}
			</div>
		</CardContent>
	</Card>

	<!-- Audit Logs List -->
	<Card>
		<CardHeader class="pb-3">
			<div class="flex items-center justify-between">
				<CardTitle class="flex items-center gap-2">
					<ClipboardList class="h-5 w-5" />
					Activity Log
				</CardTitle>
				<Badge variant="secondary">{totalRecords} records</Badge>
			</div>
		</CardHeader>
		<CardContent>
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
				</div>
			{:else if error}
				<div class="rounded-lg border border-destructive p-4 text-destructive">
					{error}
				</div>
			{:else if filteredLogs.length === 0}
				<div class="text-center py-12 text-muted-foreground">
					<ClipboardList class="mx-auto h-12 w-12 mb-4 opacity-50" />
					<p>No audit logs found</p>
				</div>
			{:else}
				<div class="space-y-2">
					{#each filteredLogs as log (log.id)}
						{@const EventIcon = getEventIcon(log.event)}
						{@const isExpanded = expandedIds.has(log.id)}
						<Collapsible.Root open={isExpanded}>
							<div class="rounded-lg border">
								<Collapsible.Trigger
									class="w-full flex items-center gap-4 p-4 text-left hover:bg-muted/50 transition-colors"
									onclick={() => toggleExpanded(log.id)}
								>
									<div class="rounded-full p-2 bg-muted">
										<EventIcon class="h-4 w-4" />
									</div>

									<div class="flex-1 min-w-0">
										<div class="flex items-center gap-2 flex-wrap">
											<Badge variant={getEventBadgeVariant(log.event)}>
												{log.event}
											</Badge>
											<span class="font-medium">
												{formatAuditableType(log.auditable_type)}
											</span>
											<span class="text-muted-foreground">#{log.auditable_id}</span>
										</div>

										<div class="flex items-center gap-4 mt-1 text-sm text-muted-foreground">
											{#if log.user}
												<span class="flex items-center gap-1">
													<User class="h-3 w-3" />
													{log.user.name}
												</span>
											{:else}
												<span class="flex items-center gap-1">
													<User class="h-3 w-3" />
													System
												</span>
											{/if}
											<span class="flex items-center gap-1">
												<Calendar class="h-3 w-3" />
												{new Date(log.created_at).toLocaleString()}
											</span>
										</div>
									</div>

									{#if isExpanded}
										<ChevronUp class="h-5 w-5 text-muted-foreground" />
									{:else}
										<ChevronDown class="h-5 w-5 text-muted-foreground" />
									{/if}
								</Collapsible.Trigger>

								<Collapsible.Content>
									<div class="border-t px-4 py-4 bg-muted/30">
										{#if log.event === 'updated' && log.old_values && log.new_values}
											<div class="space-y-3">
												<h4 class="font-medium text-sm">Changes</h4>
												<div class="grid gap-2">
													{#each Object.keys(log.new_values) as field}
														{@const oldVal = log.old_values?.[field]}
														{@const newVal = log.new_values?.[field]}
														{#if JSON.stringify(oldVal) !== JSON.stringify(newVal)}
															<div class="rounded border bg-background p-3">
																<div class="text-sm font-medium text-muted-foreground mb-1">
																	{field.replace(/_/g, ' ')}
																</div>
																<div class="grid grid-cols-2 gap-4 text-sm">
																	<div>
																		<span class="text-xs text-muted-foreground block mb-1">Before</span>
																		<code class="text-destructive bg-destructive/10 px-1 py-0.5 rounded">
																			{formatValue(oldVal)}
																		</code>
																	</div>
																	<div>
																		<span class="text-xs text-muted-foreground block mb-1">After</span>
																		<code class="text-green-600 bg-green-500/10 px-1 py-0.5 rounded">
																			{formatValue(newVal)}
																		</code>
																	</div>
																</div>
															</div>
														{/if}
													{/each}
												</div>
											</div>
										{:else if log.event === 'created' && log.new_values}
											<div class="space-y-3">
												<h4 class="font-medium text-sm">Created Values</h4>
												<div class="grid gap-2 md:grid-cols-2">
													{#each Object.entries(log.new_values) as [field, value]}
														<div class="rounded border bg-background p-2">
															<span class="text-xs text-muted-foreground">{field.replace(/_/g, ' ')}</span>
															<div class="text-sm font-medium truncate">{formatValue(value)}</div>
														</div>
													{/each}
												</div>
											</div>
										{:else if log.event === 'deleted' && log.old_values}
											<div class="space-y-3">
												<h4 class="font-medium text-sm">Deleted Values</h4>
												<div class="grid gap-2 md:grid-cols-2">
													{#each Object.entries(log.old_values) as [field, value]}
														<div class="rounded border bg-background p-2">
															<span class="text-xs text-muted-foreground">{field.replace(/_/g, ' ')}</span>
															<div class="text-sm font-medium truncate text-destructive">{formatValue(value)}</div>
														</div>
													{/each}
												</div>
											</div>
										{:else}
											<p class="text-sm text-muted-foreground">No detailed changes available</p>
										{/if}

										{#if log.ip_address || log.url}
											<div class="mt-4 pt-4 border-t">
												<h4 class="font-medium text-sm mb-2">Request Details</h4>
												<div class="text-sm text-muted-foreground space-y-1">
													{#if log.ip_address}
														<p>IP Address: {log.ip_address}</p>
													{/if}
													{#if log.url}
														<p class="truncate">URL: {log.url}</p>
													{/if}
												</div>
											</div>
										{/if}
									</div>
								</Collapsible.Content>
							</div>
						</Collapsible.Root>
					{/each}
				</div>

				<!-- Pagination -->
				{#if totalPages > 1}
					<div class="flex items-center justify-between mt-6 pt-4 border-t">
						<p class="text-sm text-muted-foreground">
							Page {currentPage} of {totalPages} ({totalRecords} total records)
						</p>
						<div class="flex items-center gap-2">
							<Button
								variant="outline"
								size="sm"
								onclick={() => goToPage(currentPage - 1)}
								disabled={currentPage === 1}
							>
								<ChevronLeft class="h-4 w-4" />
								Previous
							</Button>
							<Button
								variant="outline"
								size="sm"
								onclick={() => goToPage(currentPage + 1)}
								disabled={currentPage === totalPages}
							>
								Next
								<ChevronRight class="h-4 w-4" />
							</Button>
						</div>
					</div>
				{/if}
			{/if}
		</CardContent>
	</Card>
</div>

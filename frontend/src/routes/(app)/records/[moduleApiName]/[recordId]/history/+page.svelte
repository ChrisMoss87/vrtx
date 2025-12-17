<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import * as Tabs from '$lib/components/ui/tabs';
	import {
		getRecordHistory,
		getTimelineMarkers,
		getRecordAtTimestamp,
		getRecordComparison,
		createSnapshot,
		type HistoryEntry,
		type TimelineMarker,
		type ComparisonResult
	} from '$lib/api/time-machine';
	import { recordsApi } from '$lib/api/records';
	import { TimeSlider, HistoricalRecordView, RecordDiff } from '$lib/components/time-machine';
	import { toast } from 'svelte-sonner';
	import {
		ArrowLeft,
		Clock,
		GitCompare,
		Camera,
		User,
		Calendar,
		RefreshCw,
		History,
		X
	} from 'lucide-svelte';

	const moduleApiName = $derived($page.params.moduleApiName ?? '');
	const recordId = $derived(parseInt($page.params.recordId ?? '0'));

	// State
	let record = $state<{ id: number; data: Record<string, unknown> } | null>(null);
	let history = $state<HistoryEntry[]>([]);
	let markers = $state<TimelineMarker[]>([]);
	let selectedTimestamp = $state<string | null>(null);
	let historicalData = $state<Record<string, unknown> | null>(null);
	let fields = $state<Record<string, { label: string; type: string }>>({});
	let activeTab = $state('timeline');

	// Comparison state
	let comparingDates = $state(false);
	let compareFromTimestamp = $state<string | null>(null);
	let compareToTimestamp = $state<string | null>(null);
	let comparisonResult = $state<ComparisonResult | null>(null);

	// Loading states
	let loadingRecord = $state(true);
	let loadingHistory = $state(true);
	let loadingMarkers = $state(true);
	let loadingHistorical = $state(false);
	let creatingSnapshot = $state(false);

	async function loadRecord() {
		loadingRecord = true;
		try {
			const moduleRecord = await recordsApi.getById(moduleApiName, recordId);
			record = { id: moduleRecord.id, data: moduleRecord.data };
		} catch (e) {
			toast.error('Failed to load record');
		} finally {
			loadingRecord = false;
		}
	}

	async function loadHistory() {
		loadingHistory = true;
		try {
			history = await getRecordHistory(moduleApiName, recordId, { limit: 50 });
		} catch (e) {
			toast.error('Failed to load history');
		} finally {
			loadingHistory = false;
		}
	}

	async function loadMarkers() {
		loadingMarkers = true;
		try {
			markers = await getTimelineMarkers(moduleApiName, recordId);
		} catch (e) {
			toast.error('Failed to load timeline');
		} finally {
			loadingMarkers = false;
		}
	}

	async function handleTimestampSelect(eventData: { timestamp: string; marker: TimelineMarker | null }) {
		const { timestamp } = eventData;
		selectedTimestamp = timestamp;

		if (comparingDates) {
			if (!compareFromTimestamp) {
				compareFromTimestamp = timestamp;
				toast.info('Now select the second date to compare');
			} else {
				compareToTimestamp = timestamp;
				await loadComparison();
			}
			return;
		}

		loadingHistorical = true;
		try {
			const data = await getRecordAtTimestamp(moduleApiName, recordId, timestamp);
			historicalData = data.data;
			fields = data.fields;
		} catch (e) {
			toast.error('Failed to load historical state');
		} finally {
			loadingHistorical = false;
		}
	}

	async function loadComparison() {
		if (!compareFromTimestamp || !compareToTimestamp) return;

		loadingHistorical = true;
		try {
			const fromDate = new Date(compareFromTimestamp);
			const toDate = new Date(compareToTimestamp);
			const actualFrom = fromDate < toDate ? compareFromTimestamp : compareToTimestamp;
			const actualTo = fromDate < toDate ? compareToTimestamp : compareFromTimestamp;

			comparisonResult = await getRecordComparison(moduleApiName, recordId, actualFrom, actualTo);
			comparingDates = false;
			activeTab = 'compare';
		} catch (e) {
			toast.error('Failed to load comparison');
		} finally {
			loadingHistorical = false;
		}
	}

	function startComparison() {
		comparingDates = true;
		compareFromTimestamp = null;
		compareToTimestamp = null;
		comparisonResult = null;
		toast.info('Select the first date on the timeline');
	}

	function cancelComparison() {
		comparingDates = false;
		compareFromTimestamp = null;
		compareToTimestamp = null;
	}

	async function handleCreateSnapshot() {
		creatingSnapshot = true;
		try {
			await createSnapshot(moduleApiName, recordId);
			toast.success('Snapshot created');
			await loadMarkers();
		} catch (e) {
			toast.error('Failed to create snapshot');
		} finally {
			creatingSnapshot = false;
		}
	}

	function viewCurrent() {
		selectedTimestamp = null;
		historicalData = null;
	}

	function getEntryIcon(type: string) {
		switch (type) {
			case 'stage_change':
				return 'bg-purple-500';
			case 'field_change':
				return 'bg-blue-500';
			case 'manual':
				return 'bg-green-500';
			default:
				return 'bg-gray-400';
		}
	}

	function getEntryLabel(type: string) {
		switch (type) {
			case 'stage_change':
				return 'Stage Change';
			case 'field_change':
				return 'Field Change';
			case 'manual':
				return 'Snapshot';
			case 'daily':
				return 'Daily Backup';
			default:
				return type;
		}
	}

	function getRecordName(): string {
		if (!record) return 'Record';
		const data = record.data;
		return (data.name as string) || (data.title as string) || `Record #${record.id}`;
	}

	$effect(() => {
		loadRecord();
		loadHistory();
		loadMarkers();
	});
</script>

<svelte:head>
	<title>History - {getRecordName()} | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<div class="flex items-center gap-4">
		<Button variant="ghost" size="icon" onclick={() => goto(`/records/${moduleApiName}/${recordId}`)}>
			<ArrowLeft class="h-5 w-5" />
		</Button>
		<div class="flex-1">
			<h1 class="text-2xl font-bold flex items-center gap-2">
				<History class="h-6 w-6" />
				Record History
			</h1>
			<p class="text-muted-foreground">
				{loadingRecord ? 'Loading...' : getRecordName()}
			</p>
		</div>
		<div class="flex items-center gap-2">
			{#if comparingDates}
				<Button variant="outline" onclick={cancelComparison}>
					<X class="mr-2 h-4 w-4" />
					Cancel
				</Button>
				<span class="text-sm text-muted-foreground">
					{compareFromTimestamp ? 'Select second date...' : 'Select first date...'}
				</span>
			{:else}
				<Button variant="outline" onclick={startComparison}>
					<GitCompare class="mr-2 h-4 w-4" />
					Compare Dates
				</Button>
				<Button variant="outline" onclick={handleCreateSnapshot} disabled={creatingSnapshot}>
					<Camera class="mr-2 h-4 w-4" />
					{creatingSnapshot ? 'Creating...' : 'Create Snapshot'}
				</Button>
			{/if}
		</div>
	</div>

	<!-- Timeline Slider -->
	<Card>
		<CardHeader class="pb-2">
			<CardTitle class="text-base">Timeline</CardTitle>
			<CardDescription>Click or drag to select a point in time</CardDescription>
		</CardHeader>
		<CardContent>
			{#if loadingMarkers}
				<Skeleton class="h-16 w-full" />
			{:else}
				<TimeSlider
					{markers}
					bind:selectedTimestamp
					onSelect={handleTimestampSelect}
				/>
				<div class="flex items-center gap-4 text-xs text-muted-foreground mt-4">
					<span class="flex items-center gap-1">
						<span class="w-2 h-2 rounded-full bg-purple-500"></span>
						Stage change
					</span>
					<span class="flex items-center gap-1">
						<span class="w-2 h-2 rounded-full bg-blue-500"></span>
						Field change
					</span>
					<span class="flex items-center gap-1">
						<span class="w-2 h-2 rounded-full bg-green-500"></span>
						Manual snapshot
					</span>
					<span class="flex items-center gap-1">
						<span class="w-2 h-2 rounded-full bg-gray-400"></span>
						Daily backup
					</span>
				</div>
			{/if}
		</CardContent>
	</Card>

	<Tabs.Root bind:value={activeTab}>
		<Tabs.List>
			<Tabs.Trigger value="timeline">
				<Clock class="mr-2 h-4 w-4" />
				Activity Log
			</Tabs.Trigger>
			<Tabs.Trigger value="view" disabled={!selectedTimestamp && !historicalData}>
				Historical View
			</Tabs.Trigger>
			<Tabs.Trigger value="compare" disabled={!comparisonResult}>
				Comparison
			</Tabs.Trigger>
		</Tabs.List>

		<Tabs.Content value="timeline" class="mt-4">
			<Card>
				<CardContent class="p-0">
					{#if loadingHistory}
						<div class="p-6 space-y-4">
							{#each Array(5) as _}
								<div class="flex gap-4">
									<Skeleton class="h-10 w-10 rounded-full" />
									<div class="flex-1 space-y-2">
										<Skeleton class="h-4 w-1/3" />
										<Skeleton class="h-3 w-2/3" />
									</div>
								</div>
							{/each}
						</div>
					{:else if history.length === 0}
						<div class="p-12 text-center text-muted-foreground">
							<History class="h-12 w-12 mx-auto mb-4 opacity-50" />
							<p>No history entries yet</p>
						</div>
					{:else}
						<div class="divide-y">
							{#each history as entry}
								<button
									class="w-full flex items-start gap-4 p-4 hover:bg-muted/50 transition-colors text-left"
									onclick={() => handleTimestampSelect({ timestamp: entry.timestamp, marker: null })}
								>
									<div class="flex-shrink-0 mt-1">
										<div class={`w-3 h-3 rounded-full ${getEntryIcon(entry.type)}`}></div>
									</div>
									<div class="flex-1 min-w-0">
										<div class="flex items-center gap-2 mb-1">
											<Badge variant="outline" class="text-xs">
												{getEntryLabel(entry.type)}
											</Badge>
											{#if entry.changes.fields_changed?.length}
												<span class="text-xs text-muted-foreground">
													{entry.changes.fields_changed.length} field(s) changed
												</span>
											{/if}
										</div>
										{#if entry.changes.changes}
											<div class="text-sm text-muted-foreground">
												{#each Object.entries(entry.changes.changes).slice(0, 2) as [field, change]}
													<p class="truncate">
														<span class="font-medium">{field}:</span>
														<span class="line-through opacity-50">{change.old ?? '(empty)'}</span>
														→ {change.new ?? '(empty)'}
													</p>
												{/each}
												{#if Object.keys(entry.changes.changes).length > 2}
													<p class="text-xs">+{Object.keys(entry.changes.changes).length - 2} more changes</p>
												{/if}
											</div>
										{/if}
										{#if entry.changes.note}
											<p class="text-sm text-muted-foreground mt-1">{entry.changes.note}</p>
										{/if}
									</div>
									<div class="text-right text-sm text-muted-foreground flex-shrink-0">
										<div class="flex items-center gap-1">
											<Calendar class="h-3 w-3" />
											{new Date(entry.timestamp).toLocaleDateString()}
										</div>
										<div class="text-xs">
											{new Date(entry.timestamp).toLocaleTimeString()}
										</div>
										{#if entry.created_by}
											<div class="flex items-center gap-1 mt-1 justify-end">
												<User class="h-3 w-3" />
												{entry.created_by.name}
											</div>
										{/if}
									</div>
								</button>
							{/each}
						</div>
					{/if}
				</CardContent>
			</Card>
		</Tabs.Content>

		<Tabs.Content value="view" class="mt-4">
			<Card>
				<CardContent class="p-6">
					{#if loadingHistorical}
						<div class="flex items-center justify-center py-12">
							<RefreshCw class="h-8 w-8 animate-spin text-muted-foreground" />
						</div>
					{:else if historicalData && selectedTimestamp && record}
						<div class="mb-4 flex items-center justify-between">
							<div>
								<p class="text-sm text-muted-foreground">
									Viewing record as of {new Date(selectedTimestamp).toLocaleString()}
								</p>
							</div>
							<Button variant="outline" size="sm" onclick={viewCurrent}>
								View Current
							</Button>
						</div>
						<HistoricalRecordView
							data={historicalData}
							{fields}
							timestamp={selectedTimestamp}
							currentData={record.data}
						/>
					{:else}
						<div class="text-center py-12 text-muted-foreground">
							<Clock class="h-12 w-12 mx-auto mb-4 opacity-50" />
							<p>Select a point on the timeline to view the historical state</p>
						</div>
					{/if}
				</CardContent>
			</Card>
		</Tabs.Content>

		<Tabs.Content value="compare" class="mt-4">
			<Card>
				<CardContent class="p-6">
					{#if comparisonResult}
						<RecordDiff comparison={comparisonResult} />
					{:else}
						<div class="text-center py-12 text-muted-foreground">
							<GitCompare class="h-12 w-12 mx-auto mb-4 opacity-50" />
							<p>Click "Compare Dates" and select two points to compare</p>
						</div>
					{/if}
				</CardContent>
			</Card>
		</Tabs.Content>
	</Tabs.Root>
</div>

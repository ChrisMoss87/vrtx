<script lang="ts">
	import { onMount } from 'svelte';
	import { X, Clock, GitCompare, Camera, ChevronLeft, ChevronRight } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Tabs from '$lib/components/ui/tabs';
	import TimeSlider from './TimeSlider.svelte';
	import HistoricalRecordView from './HistoricalRecordView.svelte';
	import RecordDiff from './RecordDiff.svelte';
	import {
		getTimelineMarkers,
		getRecordAtTimestamp,
		getRecordComparison,
		createSnapshot,
		type TimelineMarker,
		type ComparisonResult
	} from '$lib/api/time-machine';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	export let moduleApiName: string;
	export let recordId: number;
	export let recordName: string = 'Record';
	export let currentData: Record<string, unknown>;
	export let open = false;

	let markers: TimelineMarker[] = [];
	let selectedTimestamp: string | null = null;
	let historicalData: Record<string, unknown> | null = null;
	let fields: Record<string, { label: string; type: string }> = {};
	let loading = false;
	let activeTab = 'view';

	// Comparison mode
	let compareFromTimestamp: string | null = null;
	let compareToTimestamp: string | null = null;
	let comparisonResult: ComparisonResult | null = null;
	let comparingDates = false;

	onMount(async () => {
		if (open) {
			await loadMarkers();
		}
	});

	$: if (open && markers.length === 0) {
		loadMarkers();
	}

	async function loadMarkers() {
		loading = true;
		const { data, error } = await tryCatch(getTimelineMarkers(moduleApiName, recordId));
		loading = false;

		if (error) {
			toast.error('Failed to load history');
			return;
		}

		markers = data;
	}

	async function handleTimestampSelect(event: CustomEvent<{ timestamp: string; marker: TimelineMarker | null }>) {
		const { timestamp } = event.detail;
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

		loading = true;
		const { data, error } = await tryCatch(
			getRecordAtTimestamp(moduleApiName, recordId, timestamp)
		);
		loading = false;

		if (error) {
			toast.error('Failed to load historical state');
			return;
		}

		historicalData = data.data;
		fields = data.fields;
	}

	async function loadComparison() {
		if (!compareFromTimestamp || !compareToTimestamp) return;

		loading = true;

		// Ensure from is before to
		const fromDate = new Date(compareFromTimestamp);
		const toDate = new Date(compareToTimestamp);
		const actualFrom = fromDate < toDate ? compareFromTimestamp : compareToTimestamp;
		const actualTo = fromDate < toDate ? compareToTimestamp : compareFromTimestamp;

		const { data, error } = await tryCatch(
			getRecordComparison(moduleApiName, recordId, actualFrom, actualTo)
		);
		loading = false;

		if (error) {
			toast.error('Failed to load comparison');
			return;
		}

		comparisonResult = data;
		comparingDates = false;
		activeTab = 'compare';
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
		loading = true;
		const { data, error } = await tryCatch(createSnapshot(moduleApiName, recordId));
		loading = false;

		if (error) {
			toast.error('Failed to create snapshot');
			return;
		}

		toast.success('Snapshot created');
		await loadMarkers();
	}

	function handleClose() {
		open = false;
		selectedTimestamp = null;
		historicalData = null;
		comparingDates = false;
		comparisonResult = null;
	}

	function viewCurrent() {
		selectedTimestamp = null;
		historicalData = null;
	}
</script>

<Dialog.Root bind:open onOpenChange={(isOpen) => !isOpen && handleClose()}>
	<Dialog.Content class="max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
		<Dialog.Header>
			<Dialog.Title class="flex items-center gap-2">
				<Clock class="h-5 w-5" />
				Time Machine: {recordName}
			</Dialog.Title>
			<Dialog.Description>
				View how this record looked at any point in time
			</Dialog.Description>
		</Dialog.Header>

		<div class="flex-1 overflow-auto space-y-4 py-4">
			<!-- Timeline slider -->
			<div class="px-2">
				<TimeSlider
					{markers}
					{selectedTimestamp}
					on:select={handleTimestampSelect}
				/>
			</div>

			<!-- Marker legend -->
			<div class="flex items-center gap-4 text-xs text-muted-foreground px-2">
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
					Daily snapshot
				</span>
			</div>

			<!-- Actions bar -->
			<div class="flex items-center gap-2 px-2">
				{#if comparingDates}
					<Button variant="outline" size="sm" onclick={cancelComparison}>
						<X class="h-4 w-4 mr-1" />
						Cancel comparison
					</Button>
					<span class="text-sm text-muted-foreground">
						{#if compareFromTimestamp && !compareToTimestamp}
							Select second date...
						{:else}
							Select first date...
						{/if}
					</span>
				{:else}
					<Button variant="outline" size="sm" onclick={startComparison}>
						<GitCompare class="h-4 w-4 mr-1" />
						Compare dates
					</Button>
					<Button variant="outline" size="sm" onclick={handleCreateSnapshot} disabled={loading}>
						<Camera class="h-4 w-4 mr-1" />
						Create snapshot
					</Button>
					{#if selectedTimestamp}
						<Button variant="outline" size="sm" onclick={viewCurrent}>
							View current
						</Button>
					{/if}
				{/if}
			</div>

			<!-- Content area -->
			<Tabs.Root bind:value={activeTab} class="px-2">
				<Tabs.List>
					<Tabs.Trigger value="view">Historical View</Tabs.Trigger>
					<Tabs.Trigger value="compare" disabled={!comparisonResult}>
						Comparison
					</Tabs.Trigger>
				</Tabs.List>

				<Tabs.Content value="view" class="mt-4">
					{#if loading}
						<div class="flex items-center justify-center py-12">
							<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
						</div>
					{:else if historicalData && selectedTimestamp}
						<HistoricalRecordView
							data={historicalData}
							{fields}
							timestamp={selectedTimestamp}
							{currentData}
						/>
					{:else}
						<div class="text-center py-12 text-muted-foreground">
							<Clock class="h-12 w-12 mx-auto mb-4 opacity-50" />
							<p>Select a point on the timeline to view the historical state</p>
							<p class="text-sm mt-2">
								Click on a marker or drag the slider to any date
							</p>
						</div>
					{/if}
				</Tabs.Content>

				<Tabs.Content value="compare" class="mt-4">
					{#if comparisonResult}
						<RecordDiff comparison={comparisonResult} />
					{:else}
						<div class="text-center py-12 text-muted-foreground">
							<GitCompare class="h-12 w-12 mx-auto mb-4 opacity-50" />
							<p>Click "Compare dates" and select two points to compare</p>
						</div>
					{/if}
				</Tabs.Content>
			</Tabs.Root>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={handleClose}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

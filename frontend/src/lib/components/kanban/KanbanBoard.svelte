<script lang="ts">
	import { onMount } from 'svelte';
	import type { KanbanColumn as KanbanColumnData, Stage, Pipeline } from '$lib/api/pipelines';
	import { getKanbanData, moveRecord } from '$lib/api/pipelines';
	import KanbanColumnComponent from './KanbanColumn.svelte';
	import KanbanCard from './KanbanCard.svelte';
	import { Spinner } from '$lib/components/ui/spinner';
	import { toast } from 'svelte-sonner';
	import { cn } from '$lib/utils';

	interface Props {
		pipelineId: number;
		valueField?: string;
		titleField?: string;
		subtitleField?: string;
		filters?: Record<string, string>;
		search?: string;
		onRecordClick?: (record: KanbanColumnData['records'][0]) => void;
		class?: string;
	}

	let {
		pipelineId,
		valueField = 'value',
		titleField = 'name',
		subtitleField,
		filters,
		search,
		onRecordClick,
		class: className
	}: Props = $props();

	let loading = $state(true);
	let pipeline = $state<Pipeline | null>(null);
	let columns = $state<KanbanColumnData[]>([]);
	let totals = $state({ totalRecords: 0, totalValue: 0, weightedValue: 0 });
	let draggedRecord = $state<KanbanColumnData['records'][0] | null>(null);
	let draggedFromStageId = $state<number | null>(null);

	async function loadData() {
		try {
			loading = true;
			const data = await getKanbanData(pipelineId, {
				filters,
				search,
				value_field: valueField
			});
			pipeline = data.pipeline;
			columns = data.columns;
			totals = data.totals;
		} catch (error: any) {
			console.error('Failed to load kanban data:', error);
			toast.error('Failed to load pipeline data');
		} finally {
			loading = false;
		}
	}

	$effect(() => {
		// Re-fetch when filters or search change
		if (pipelineId) {
			loadData();
		}
	});

	function handleDragStart(record: KanbanColumnData['records'][0], stageId: number) {
		draggedRecord = record;
		draggedFromStageId = stageId;
	}

	function handleDragEnd() {
		draggedRecord = null;
		draggedFromStageId = null;
	}

	async function handleDrop(toStageId: number) {
		if (!draggedRecord || draggedFromStageId === toStageId) {
			handleDragEnd();
			return;
		}

		const record = draggedRecord;
		const fromStageId = draggedFromStageId;

		// Optimistically update UI
		const fromColumn = columns.find((c) => c.stage.id === fromStageId);
		const toColumn = columns.find((c) => c.stage.id === toStageId);

		if (fromColumn && toColumn) {
			// Remove from source
			fromColumn.records = fromColumn.records.filter((r) => r.id !== record.id);
			fromColumn.count--;

			// Add to destination
			toColumn.records = [...toColumn.records, record];
			toColumn.count++;

			// Trigger reactivity
			columns = [...columns];
		}

		handleDragEnd();

		try {
			await moveRecord(pipelineId, record.id, toStageId);
			toast.success('Record moved successfully');
		} catch (error: any) {
			console.error('Failed to move record:', error);
			toast.error('Failed to move record');
			// Revert on error
			await loadData();
		}
	}

	export function refresh() {
		loadData();
	}
</script>

<div class={cn('flex h-full flex-col', className)}>
	<!-- Pipeline Header -->
	{#if pipeline}
		<div class="mb-4 flex items-center justify-between">
			<div>
				<h2 class="text-lg font-semibold">{pipeline.name}</h2>
				<p class="text-sm text-muted-foreground">
					{totals.totalRecords} records | Total: ${totals.totalValue.toLocaleString()} | Weighted: ${totals.weightedValue.toLocaleString()}
				</p>
			</div>
		</div>
	{/if}

	<!-- Kanban Board -->
	{#if loading}
		<div class="flex flex-1 items-center justify-center">
			<Spinner class="h-8 w-8" />
		</div>
	{:else}
		<div class="flex flex-1 gap-4 overflow-x-auto pb-4">
			{#each columns as column (column.stage.id)}
				<KanbanColumnComponent
					stage={column.stage}
					count={column.count}
					totalValue={column.totalValue}
					weightedValue={column.weightedValue}
					isDragOver={draggedRecord !== null && draggedFromStageId !== column.stage.id}
					onDrop={() => handleDrop(column.stage.id)}
				>
					{#each column.records as record (record.id)}
						<KanbanCard
							{record}
							{titleField}
							{subtitleField}
							{valueField}
							isDragging={draggedRecord?.id === record.id}
							onDragStart={() => handleDragStart(record, column.stage.id)}
							onDragEnd={handleDragEnd}
							onClick={() => onRecordClick?.(record)}
						/>
					{/each}
				</KanbanColumnComponent>
			{/each}
		</div>
	{/if}
</div>

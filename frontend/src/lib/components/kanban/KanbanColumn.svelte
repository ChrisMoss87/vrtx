<script lang="ts">
	import type { Stage } from '$lib/api/pipelines';
	import type { Snippet } from 'svelte';
	import { cn } from '$lib/utils';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Badge } from '$lib/components/ui/badge';
	import { droppable } from '$lib/utils/dnd.svelte';

	interface Props {
		stage: Stage;
		count: number;
		totalValue?: number;
		weightedValue?: number;
		isDragOver?: boolean;
		onDrop?: (data: any) => void;
		children: Snippet;
		class?: string;
	}

	let {
		stage,
		count,
		totalValue = 0,
		weightedValue = 0,
		isDragOver = false,
		onDrop,
		children,
		class: className
	}: Props = $props();

	let isOver = $state(false);

	function handleDropEvent(item: { data: any }) {
		isOver = false;
		onDrop?.(item.data);
	}
</script>

<div
	class={cn(
		'kanban-column flex max-w-[300px] min-w-[300px] flex-col rounded-lg border bg-muted/30 transition-all duration-200',
		isOver && isDragOver && 'border-primary bg-primary/5 ring-2 ring-primary/20',
		className
	)}
	use:droppable={{
		accepts: ['kanban-board'],
		onDragEnter: () => (isOver = true),
		onDragLeave: () => (isOver = false),
		onDrop: handleDropEvent
	}}
	role="list"
>
	<!-- Column Header -->
	<div class="flex items-center justify-between border-b p-3">
		<div class="flex items-center gap-2">
			<div class="h-3 w-3 rounded-full" style="background-color: {stage.color}"></div>
			<span class="font-medium">{stage.name}</span>
			<Badge variant="secondary" class="ml-1">{count}</Badge>
		</div>
		{#if stage.probability > 0}
			<span class="text-xs text-muted-foreground">{stage.probability}%</span>
		{/if}
	</div>

	<!-- Column Stats -->
	{#if totalValue > 0}
		<div class="border-b px-3 py-2 text-xs">
			<div class="flex justify-between">
				<span class="text-muted-foreground">Total</span>
				<span class="font-medium">${totalValue.toLocaleString()}</span>
			</div>
			{#if stage.probability > 0}
				<div class="flex justify-between">
					<span class="text-muted-foreground">Weighted</span>
					<span class="text-muted-foreground">${weightedValue.toLocaleString()}</span>
				</div>
			{/if}
		</div>
	{/if}

	<!-- Cards Container -->
	<ScrollArea class="flex-1">
		<div class="flex flex-col gap-2 p-2">
			{@render children()}
		</div>
	</ScrollArea>

	<!-- Won/Lost Stage Indicator -->
	{#if stage.is_won_stage || stage.is_lost_stage}
		<div
			class={cn(
				'border-t px-3 py-1.5 text-center text-xs font-medium',
				stage.is_won_stage && 'bg-green-500/10 text-green-600',
				stage.is_lost_stage && 'bg-red-500/10 text-red-600'
			)}
		>
			{stage.is_won_stage ? 'Won Stage' : 'Lost Stage'}
		</div>
	{/if}
</div>

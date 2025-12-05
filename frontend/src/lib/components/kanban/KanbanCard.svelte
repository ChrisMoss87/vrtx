<script lang="ts">
	import type { KanbanColumn } from '$lib/api/pipelines';
	import { cn } from '$lib/utils';
	import { draggable } from '$lib/utils/dnd.svelte';

	interface Props {
		record: KanbanColumn['records'][0];
		titleField?: string;
		subtitleField?: string;
		valueField?: string;
		isDragging?: boolean;
		onDragStart?: () => void;
		onDragEnd?: () => void;
		onClick?: () => void;
		class?: string;
	}

	let {
		record,
		titleField = 'name',
		subtitleField,
		valueField = 'value',
		isDragging = false,
		onDragStart,
		onDragEnd,
		onClick,
		class: className
	}: Props = $props();

	function getFieldValue(field: string | undefined): string {
		if (!field) return '';
		const value = record.data[field];
		if (value === null || value === undefined) return '';
		return String(value);
	}

	function formatValue(field: string | undefined): string {
		if (!field) return '';
		const value = record.data[field];
		if (value === null || value === undefined) return '';
		if (typeof value === 'number') {
			return `$${value.toLocaleString()}`;
		}
		return String(value);
	}

	function handleClick() {
		onClick?.();
	}

	function handleKeyDown(e: KeyboardEvent) {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			onClick?.();
		}
	}

</script>

<!-- svelte-ignore a11y_no_noninteractive_tabindex -->
<!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
<article
	class={cn(
		'kanban-card cursor-grab rounded-md border bg-card p-3 shadow-sm transition-all duration-200 hover:shadow-md active:cursor-grabbing',
		isDragging && 'scale-[0.98] opacity-50 shadow-lg',
		onClick && 'cursor-pointer',
		className
	)}
	use:draggable={{
		data: { recordId: record.id, record },
		id: `kanban-card-${record.id}`,
		sourceId: 'kanban-board',
		onDragStart: () => onDragStart?.(),
		onDragEnd: () => onDragEnd?.()
	}}
	onclick={handleClick}
	onkeydown={handleKeyDown}
	tabindex="0"
>
	<!-- Title -->
	<div class="mb-1 font-medium">
		{getFieldValue(titleField) || `Record #${record.id}`}
	</div>

	<!-- Subtitle -->
	{#if subtitleField && getFieldValue(subtitleField)}
		<div class="mb-2 text-sm text-muted-foreground">
			{getFieldValue(subtitleField)}
		</div>
	{/if}

	<!-- Value -->
	{#if valueField && record.data[valueField]}
		<div class="text-sm font-semibold text-primary">
			{formatValue(valueField)}
		</div>
	{/if}

	<!-- Additional fields preview -->
	<div class="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground">
		{#each Object.entries(record.data).slice(0, 3) as [key, value]}
			{#if key !== titleField && key !== subtitleField && key !== valueField && value}
				<span class="rounded bg-muted px-1.5 py-0.5">
					{key}: {typeof value === 'object' ? JSON.stringify(value) : value}
				</span>
			{/if}
		{/each}
	</div>
</article>

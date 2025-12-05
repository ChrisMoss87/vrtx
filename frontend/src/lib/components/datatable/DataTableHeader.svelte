<script lang="ts">
	import { getContext } from 'svelte';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { ArrowUp, ArrowDown, ChevronsUpDown, GripVertical } from 'lucide-svelte';
	import type { ColumnDef, TableContext, FilterConfig } from './types';
	import * as Table from '$lib/components/ui/table/index.ts';
	import ColumnFilter from './filters/ColumnFilter.svelte';

	interface Props {
		columns: ColumnDef[];
		enableSelection?: boolean;
		enableSorting?: boolean;
		enableColumnFilters?: boolean;
		enableColumnResize?: boolean;
		enableColumnReorder?: boolean;
		hasGrouping?: boolean;
	}

	let {
		columns,
		enableSelection = true,
		enableSorting = true,
		enableColumnFilters = true,
		enableColumnResize = false,
		enableColumnReorder = false,
		hasGrouping = false
	}: Props = $props();

	const table = getContext<TableContext>('table');

	// Resize state
	let resizingColumn = $state<string | null>(null);
	let resizeStartX = $state(0);
	let resizeStartWidth = $state(0);

	// Drag reorder state
	let draggedColumn = $state<string | null>(null);
	let dragOverColumn = $state<string | null>(null);

	// Check if all rows are selected
	let allSelected = $derived(
		table.state.data.length > 0 && table.state.data.every((row) => table.state.rowSelection[row.id])
	);

	// Check if some rows are selected
	let someSelected = $derived(
		table.state.data.some((row) => table.state.rowSelection[row.id]) && !allSelected
	);

	// Get sort info for column
	function getSortInfo(columnId: string) {
		const sortIndex = table.state.sorting.findIndex((s) => s.field === columnId);

		if (sortIndex === -1) {
			return { isSorted: false, direction: null, priority: null };
		}

		return {
			isSorted: true,
			direction: table.state.sorting[sortIndex].direction,
			priority: table.state.sorting.length > 1 ? sortIndex + 1 : null
		};
	}

	// Handle column header click
	function handleHeaderClick(column: ColumnDef, event: MouseEvent) {
		if (!enableSorting || !column.sortable) return;

		table.updateSort(column.id, event.shiftKey);
	}

	// Get current filter for a column
	function getColumnFilter(columnId: string): FilterConfig | undefined {
		return table.state.filters.find((f) => f.field === columnId);
	}

	// Handle filter apply
	function handleFilterApply(columnId: string, filter: FilterConfig | null) {
		if (filter) {
			table.updateFilter(filter);
		} else {
			table.removeFilter(columnId);
		}
	}

	// Get column width from state or default
	function getColumnWidth(column: ColumnDef): number {
		return table.state.columnWidths[column.id] || column.width || 150;
	}

	// ===== Column Resizing =====
	function handleResizeStart(columnId: string, event: MouseEvent) {
		event.preventDefault();
		event.stopPropagation();

		resizingColumn = columnId;
		resizeStartX = event.clientX;
		resizeStartWidth = getColumnWidth(columns.find((c) => c.id === columnId)!);

		document.addEventListener('mousemove', handleResizeMove);
		document.addEventListener('mouseup', handleResizeEnd);
		document.body.style.cursor = 'col-resize';
		document.body.style.userSelect = 'none';
	}

	function handleResizeMove(event: MouseEvent) {
		if (!resizingColumn) return;

		const column = columns.find((c) => c.id === resizingColumn);
		if (!column) return;

		const diff = event.clientX - resizeStartX;
		const minWidth = column.minWidth || 50;
		const maxWidth = column.maxWidth || 500;
		const newWidth = Math.min(maxWidth, Math.max(minWidth, resizeStartWidth + diff));

		table.resizeColumn(resizingColumn, newWidth);
	}

	function handleResizeEnd() {
		resizingColumn = null;
		document.removeEventListener('mousemove', handleResizeMove);
		document.removeEventListener('mouseup', handleResizeEnd);
		document.body.style.cursor = '';
		document.body.style.userSelect = '';
	}

	// ===== Column Reordering =====
	function handleDragStart(columnId: string, event: DragEvent) {
		if (!enableColumnReorder) return;

		draggedColumn = columnId;
		event.dataTransfer!.effectAllowed = 'move';
		event.dataTransfer!.setData('text/plain', columnId);

		// Add drag image
		const target = event.target as HTMLElement;
		if (target) {
			event.dataTransfer!.setDragImage(target, 0, 0);
		}
	}

	function handleDragOver(columnId: string, event: DragEvent) {
		if (!enableColumnReorder || !draggedColumn || draggedColumn === columnId) return;

		event.preventDefault();
		event.dataTransfer!.dropEffect = 'move';
		dragOverColumn = columnId;
	}

	function handleDragLeave() {
		dragOverColumn = null;
	}

	function handleDrop(columnId: string, event: DragEvent) {
		if (!enableColumnReorder || !draggedColumn || draggedColumn === columnId) return;

		event.preventDefault();

		// Get current column order
		const currentOrder = table.state.columnOrder.length > 0
			? [...table.state.columnOrder]
			: columns.map((c) => c.id);

		// Find indices
		const draggedIndex = currentOrder.indexOf(draggedColumn);
		const targetIndex = currentOrder.indexOf(columnId);

		if (draggedIndex !== -1 && targetIndex !== -1) {
			// Remove dragged column and insert at target position
			currentOrder.splice(draggedIndex, 1);
			currentOrder.splice(targetIndex, 0, draggedColumn);

			table.setColumnOrder(currentOrder);
		}

		draggedColumn = null;
		dragOverColumn = null;
	}

	function handleDragEnd() {
		draggedColumn = null;
		dragOverColumn = null;
	}
</script>

<Table.Header>
	<Table.Row>
		<!-- Grouping expand/collapse column -->
		{#if hasGrouping}
			<Table.Head class="w-[40px]">
				<!-- Empty header for expand/collapse column -->
			</Table.Head>
		{/if}

		<!-- Selection checkbox -->
		{#if enableSelection}
			<Table.Head class="w-[50px]">
				<div class="flex items-center justify-center">
					<Checkbox
						checked={allSelected}
						indeterminate={someSelected}
						onCheckedChange={() => table.toggleAllRows()}
						aria-label="Select all rows"
					/>
				</div>
			</Table.Head>
		{/if}

		<!-- Column headers -->
		{#each columns as column (column.id)}
			{@const sortInfo = getSortInfo(column.id)}
			{@const columnWidth = getColumnWidth(column)}
			{@const isDragging = draggedColumn === column.id}
			{@const isDragOver = dragOverColumn === column.id}
			<Table.Head
				class="relative select-none {isDragging ? 'opacity-50' : ''} {isDragOver ? 'bg-accent' : ''}"
				style={enableColumnResize ? `width: ${columnWidth}px; min-width: ${column.minWidth || 50}px; max-width: ${column.maxWidth || 500}px;` : ''}
				aria-sort={sortInfo.isSorted
					? sortInfo.direction === 'asc'
						? 'ascending'
						: 'descending'
					: column.sortable
						? 'none'
						: undefined}
				draggable={enableColumnReorder}
				ondragstart={(e) => handleDragStart(column.id, e)}
				ondragover={(e) => handleDragOver(column.id, e)}
				ondragleave={handleDragLeave}
				ondrop={(e) => handleDrop(column.id, e)}
				ondragend={handleDragEnd}
			>
				<div class="flex w-full items-center justify-between gap-1">
					<!-- Drag handle for reordering -->
					{#if enableColumnReorder}
						<div class="flex-shrink-0 cursor-grab opacity-0 transition-opacity hover:opacity-100 group-hover:opacity-50">
							<GripVertical class="h-3 w-3 text-muted-foreground" />
						</div>
					{/if}

					<button
						type="button"
						class="flex flex-1 items-center gap-2 hover:text-foreground {column.sortable && enableSorting
							? 'cursor-pointer'
							: 'cursor-default'}"
						onclick={(e) => handleHeaderClick(column, e)}
						disabled={!column.sortable || !enableSorting}
						aria-label={column.sortable && enableSorting
							? sortInfo.isSorted
								? `Sort by ${column.header}, currently sorted ${sortInfo.direction === 'asc' ? 'ascending' : 'descending'}`
								: `Sort by ${column.header}`
							: column.header}
					>
						<span class="truncate">{column.header}</span>

						{#if column.sortable && enableSorting}
							<div class="flex h-4 w-4 flex-shrink-0 items-center justify-center">
								{#if sortInfo.isSorted}
									{#if sortInfo.direction === 'asc'}
										<ArrowUp class="h-3.5 w-3.5" />
									{:else}
										<ArrowDown class="h-3.5 w-3.5" />
									{/if}
									{#if sortInfo.priority !== null}
										<span class="ml-1 text-xs">{sortInfo.priority}</span>
									{/if}
								{:else}
									<ChevronsUpDown class="h-3.5 w-3.5 opacity-50" />
								{/if}
							</div>
						{/if}
					</button>

					<!-- Column Filter -->
					{#if enableColumnFilters}
						<ColumnFilter
							{column}
							currentFilter={getColumnFilter(column.id)}
							onApply={(filter) => handleFilterApply(column.id, filter)}
						/>
					{/if}
				</div>

				<!-- Resize handle -->
				{#if enableColumnResize}
					<div
						class="absolute right-0 top-0 h-full w-1 cursor-col-resize bg-transparent hover:bg-primary/50 {resizingColumn === column.id ? 'bg-primary' : ''}"
						onmousedown={(e) => handleResizeStart(column.id, e)}
						role="separator"
						aria-label="Resize column {column.header}"
						tabindex={0}
						onkeydown={(e) => {
							// Allow keyboard resize with arrow keys
							if (e.key === 'ArrowLeft') {
								table.resizeColumn(column.id, Math.max((column.minWidth || 50), columnWidth - 10));
							} else if (e.key === 'ArrowRight') {
								table.resizeColumn(column.id, Math.min((column.maxWidth || 500), columnWidth + 10));
							}
						}}
					></div>
				{/if}
			</Table.Head>
		{/each}
	</Table.Row>
</Table.Header>

<script lang="ts">
	import { getContext } from 'svelte';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { ArrowUp, ArrowDown, ChevronsUpDown } from 'lucide-svelte';
	import type { ColumnDef, TableContext, FilterConfig } from './types';
	import * as Table from '$lib/components/ui/table/index.ts';
	import ColumnFilter from './filters/ColumnFilter.svelte';

	interface Props {
		columns: ColumnDef[];
		enableSelection?: boolean;
		enableSorting?: boolean;
		enableColumnFilters?: boolean;
		enableColumnResize?: boolean;
		hasGrouping?: boolean;
	}

	let {
		columns,
		enableSelection = true,
		enableSorting = true,
		enableColumnFilters = true,
		enableColumnResize = false,
		hasGrouping = false
	}: Props = $props();

	const table = getContext<TableContext>('table');

	// Resize state
	let resizingColumn = $state<string | null>(null);
	let resizeStartX = $state(0);
	let resizeStartWidth = $state(0);

	// Check if all rows are selected
	let allSelected = $derived(
		table.state.data.length > 0 &&
			table.state.data.every((row) => {
				const rowId = row.id as string | number;
				return table.state.rowSelection[rowId];
			})
	);

	// Check if some rows are selected
	let someSelected = $derived(
		table.state.data.some((row) => {
			const rowId = row.id as string | number;
			return table.state.rowSelection[rowId];
		}) && !allSelected
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

	// Get column pin state
	function getColumnPinState(columnId: string): 'left' | 'right' | false {
		return table.state.columnPinning[columnId] || false;
	}

	// Calculate sticky offset for pinned columns
	function getStickyOffset(column: ColumnDef, position: 'left' | 'right'): number {
		let offset = 0;

		// Add selection column width if pinning left
		if (position === 'left' && enableSelection) {
			offset += 50;
		}

		// Add grouping column width if pinning left
		if (position === 'left' && hasGrouping) {
			offset += 40;
		}

		// Add widths of columns pinned before this one
		for (const col of columns) {
			if (col.id === column.id) break;
			const pinState = getColumnPinState(col.id);
			if (pinState === position) {
				offset += getColumnWidth(col);
			}
		}

		return offset;
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
			{@const pinState = getColumnPinState(column.id)}
			{@const stickyOffset = pinState ? getStickyOffset(column, pinState) : 0}
			<Table.Head
				class="relative select-none {pinState ? 'sticky z-20 bg-background shadow-sm' : ''}"
				style="{enableColumnResize
					? `width: ${columnWidth}px; min-width: ${column.minWidth || 50}px; max-width: ${column.maxWidth || 500}px;`
					: ''}{pinState === 'left' ? `left: ${stickyOffset}px;` : ''}{pinState === 'right' ? `right: ${stickyOffset}px;` : ''}"
				aria-sort={sortInfo.isSorted
					? sortInfo.direction === 'asc'
						? 'ascending'
						: 'descending'
					: column.sortable
						? 'none'
						: undefined}
			>
				<div class="flex w-full items-center justify-between gap-1">
					<button
						type="button"
						class="flex flex-1 items-center gap-1 hover:text-foreground min-w-0 {column.sortable &&
						enableSorting
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
							<div class="flex-shrink-0">
								{#if sortInfo.isSorted}
									{#if sortInfo.direction === 'asc'}
										<ArrowUp class="h-3.5 w-3.5" />
									{:else}
										<ArrowDown class="h-3.5 w-3.5" />
									{/if}
									{#if sortInfo.priority !== null}
										<span class="ml-0.5 text-xs text-muted-foreground">{sortInfo.priority}</span>
									{/if}
								{:else}
									<ChevronsUpDown class="h-3.5 w-3.5 opacity-30" />
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
					<!-- svelte-ignore a11y_no_noninteractive_tabindex -->
					<!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
					<div
						class="absolute right-0 top-0 bottom-0 w-[5px] cursor-col-resize group/resize flex items-center justify-center transition-all
							{resizingColumn === column.id
								? 'bg-primary'
								: 'hover:bg-primary/30'}"
						onmousedown={(e) => handleResizeStart(column.id, e)}
						role="separator"
						aria-label="Resize column {column.header}"
						tabindex={0}
						onkeydown={(e) => {
							if (e.key === 'ArrowLeft') {
								table.resizeColumn(column.id, Math.max(column.minWidth || 50, columnWidth - 10));
							} else if (e.key === 'ArrowRight') {
								table.resizeColumn(column.id, Math.min(column.maxWidth || 500, columnWidth + 10));
							}
						}}
					>
						<!-- Visible resize grip indicator -->
						<div class="h-4 w-[3px] rounded-full bg-border group-hover/resize:bg-primary/60 transition-colors
							{resizingColumn === column.id ? 'bg-primary-foreground' : ''}"></div>
					</div>
				{/if}
			</Table.Head>
		{/each}
	</Table.Row>
</Table.Header>

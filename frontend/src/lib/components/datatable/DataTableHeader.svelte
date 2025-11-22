<script lang="ts">
	import { getContext } from 'svelte';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { ArrowUp, ArrowDown, ChevronsUpDown } from 'lucide-svelte';
	import type { ColumnDef, TableContext } from './types';

	interface Props {
		columns: ColumnDef[];
		enableSelection?: boolean;
		enableSorting?: boolean;
	}

	let {
		columns,
		enableSelection = true,
		enableSorting = true
	}: Props = $props();

	const table = getContext<TableContext>('table');

	// Check if all rows are selected
	let allSelected = $derived(
		table.state.data.length > 0 &&
			table.state.data.every((row) => table.state.rowSelection[row.id])
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
</script>

<thead class="[&_tr]:border-b">
	<tr class="border-b transition-colors hover:bg-muted/50">
		<!-- Selection checkbox -->
		{#if enableSelection}
			<th class="h-12 w-12 px-4">
				<div class="flex items-center justify-center">
					<Checkbox
						checked={allSelected}
						indeterminate={someSelected}
						onCheckedChange={() => table.toggleAllRows()}
						aria-label="Select all rows"
					/>
				</div>
			</th>
		{/if}

		<!-- Column headers -->
		{#each columns as column (column.id)}
			{@const sortInfo = getSortInfo(column.id)}
			<th
				class="h-12 px-4 text-left align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0"
				style:width={table.state.columnWidths[column.id]
					? `${table.state.columnWidths[column.id]}px`
					: undefined}
			>
				<button
					class="flex items-center gap-2 hover:text-foreground {column.sortable && enableSorting
						? 'cursor-pointer'
						: 'cursor-default'}"
					onclick={(e) => handleHeaderClick(column, e)}
					disabled={!column.sortable || !enableSorting}
					aria-label={column.sortable && enableSorting
						? `Sort by ${column.header}`
						: column.header}
				>
					<span>{column.header}</span>

					{#if column.sortable && enableSorting}
						<div class="flex h-4 w-4 items-center justify-center">
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
			</th>
		{/each}
	</tr>
</thead>

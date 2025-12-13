<script lang="ts">
	import { getContext } from 'svelte';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Button } from '$lib/components/ui/button';
	import { Loader2, FileX2, SearchX, Plus, FilterX, RefreshCw, AlertCircle } from 'lucide-svelte';
	import { getNestedValue, formatCellValue } from './utils';
	import EditableCell from './EditableCell.svelte';
	import type { ColumnDef, TableContext } from './types';
	import * as Table from '$lib/components/ui/table';

	interface Props {
		columns: ColumnDef[];
		data: any[];
		loading?: boolean;
		error?: string | null;
		enableSelection?: boolean;
		enableInlineEdit?: boolean;
		enableColumnResize?: boolean;
		moduleApiName: string;
		onRowClick?: (row: any) => void;
		onCellUpdate?: (recordId: string, field: string, value: any) => Promise<void>;
		onCreateNew?: () => void;
	}

	let {
		columns,
		data,
		loading = false,
		error = null,
		enableSelection = true,
		enableInlineEdit = true,
		enableColumnResize = false,
		moduleApiName,
		onRowClick,
		onCellUpdate,
		onCreateNew
	}: Props = $props();

	// Get column width from table state
	function getColumnWidth(column: ColumnDef): number {
		return table.state.columnWidths[column.id] || column.width || 150;
	}

	const table = getContext<TableContext>('table');

	// Determine empty state type
	let hasFilters = $derived(table.state.filters.length > 0);
	let hasSearch = $derived(table.state.globalFilter?.length > 0);
	let hasFiltersOrSearch = $derived(hasFilters || hasSearch);

	function handleRowClick(row: any) {
		if (onRowClick) {
			onRowClick(row);
		}
	}

	function handleClearFilters() {
		table.clearFilters();
		if (hasSearch) {
			table.updateGlobalFilter('');
		}
	}

	function handleRetry() {
		table.refresh();
	}

	// Check if column is editable
	function isColumnEditable(column: ColumnDef): boolean {
		// Don't allow editing if custom cell renderer is used
		if (column.cell) return false;

		// Allow editing for these types
		const editableTypes = [
			'text',
			'email',
			'phone',
			'url',
			'number',
			'decimal',
			'date',
			'datetime'
		];
		return editableTypes.includes(column.type || 'text');
	}
</script>

<Table.Body>
	{#if loading}
		<!-- Loading state -->
		<Table.Row>
			<Table.Cell
				colspan={enableSelection ? columns.length + 1 : columns.length}
				class="h-32 text-center"
			>
				<div class="flex flex-col items-center justify-center gap-3 text-muted-foreground">
					<Loader2 class="h-8 w-8 animate-spin text-primary/60" />
					<span class="text-sm">Loading records...</span>
				</div>
			</Table.Cell>
		</Table.Row>
	{:else if error}
		<!-- Error state -->
		<Table.Row>
			<Table.Cell
				colspan={enableSelection ? columns.length + 1 : columns.length}
				class="h-48 text-center"
			>
				<div class="flex flex-col items-center justify-center gap-4">
					<div class="flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10">
						<AlertCircle class="h-8 w-8 text-destructive" />
					</div>
					<div class="space-y-1">
						<p class="font-medium text-destructive">Error loading data</p>
						<p class="max-w-md text-sm text-muted-foreground">{error}</p>
					</div>
					<Button variant="outline" size="sm" onclick={handleRetry}>
						<RefreshCw class="mr-2 h-4 w-4" />
						Try again
					</Button>
				</div>
			</Table.Cell>
		</Table.Row>
	{:else if data.length === 0}
		<!-- Empty state - context aware -->
		<Table.Row>
			<Table.Cell
				colspan={enableSelection ? columns.length + 1 : columns.length}
				class="py-16 text-center"
			>
				<div class="flex flex-col items-center justify-center gap-4">
					{#if hasFiltersOrSearch}
						<!-- No results due to filters/search -->
						<div class="flex h-16 w-16 items-center justify-center rounded-full bg-sky-50 dark:bg-sky-950/30">
							<SearchX class="h-8 w-8 text-sky-500 dark:text-sky-400" />
						</div>
						<div class="space-y-2">
							<p class="text-lg font-semibold text-slate-900 dark:text-slate-100">No matching records</p>
							<p class="max-w-md text-sm text-slate-500 dark:text-slate-400">
								{#if hasSearch && hasFilters}
									No records match your search and filters.
								{:else if hasSearch}
									No records match "{table.state.globalFilter}".
								{:else}
									No records match your current filters.
								{/if}
							</p>
						</div>
						<Button variant="outline" size="default" onclick={handleClearFilters} class="mt-2">
							<FilterX class="mr-2 h-4 w-4" />
							Clear {hasSearch && hasFilters ? 'all' : hasSearch ? 'search' : 'filters'}
						</Button>
					{:else}
						<!-- No records exist yet -->
						<div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
							<FileX2 class="h-8 w-8 text-slate-400 dark:text-slate-500" />
						</div>
						<div class="space-y-2">
							<p class="text-lg font-semibold text-slate-900 dark:text-slate-100">No records yet</p>
							<p class="max-w-md text-sm text-slate-500 dark:text-slate-400">
								Get started by creating your first record in this module.
							</p>
						</div>
						{#if onCreateNew}
							<Button size="default" onclick={onCreateNew} class="mt-2">
								<Plus class="mr-2 h-4 w-4" />
								Create first record
							</Button>
						{/if}
					{/if}
				</div>
			</Table.Cell>
		</Table.Row>
	{:else}
		<!-- Data rows -->
		{#each data as row, index (row.id || index)}
			{@const isSelected = table.state.rowSelection[row.id] || false}
			<Table.Row
				class="{isSelected ? 'bg-muted/50' : ''} {onRowClick ? 'cursor-pointer' : ''}"
				onclick={() => handleRowClick(row)}
			>
				<!-- Selection checkbox -->
				{#if enableSelection}
					<Table.Cell class="w-[50px]">
						<div class="flex items-center justify-center">
							<Checkbox
								checked={isSelected}
								onCheckedChange={() => table.toggleRowSelection(row.id)}
								aria-label="Select row"
								onclick={(e) => e.stopPropagation()}
							/>
						</div>
					</Table.Cell>
				{/if}

				<!-- Data cells -->
				{#each columns as column (column.id)}
					{@const value = getNestedValue(row, column.accessorKey)}
					{@const formatted = column.format
						? column.format(value, row)
						: formatCellValue(value, column.type)}
					{@const cellClass = column.cellClass ? column.cellClass(value, row) : ''}
					{@const editable = enableInlineEdit && isColumnEditable(column)}
					{@const columnWidth = getColumnWidth(column)}

					<Table.Cell
						class={cellClass}
						style={enableColumnResize ? `width: ${columnWidth}px; min-width: ${column.minWidth || 50}px; max-width: ${column.maxWidth || 500}px;` : ''}
					>
						{#if column.cell}
							<!-- Custom cell component -->
							<svelte:component this={column.cell} {value} {row} {column} {index} />
						{:else if editable}
							<!-- Editable cell with inline editing -->
							<EditableCell {value} {row} {column} {moduleApiName} onUpdate={onCellUpdate} />
						{:else}
							<!-- Default cell rendering (non-editable) -->
							<div class="flex items-center">
								{#if column.type === 'boolean'}
									<div
										class="rounded-full px-2 py-0.5 text-xs font-medium {value
											? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
											: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'}"
									>
										{formatted}
									</div>
								{:else if column.type === 'email'}
									<a
										href="mailto:{value}"
										class="text-primary hover:underline"
										onclick={(e) => e.stopPropagation()}
									>
										{formatted}
									</a>
								{:else if column.type === 'url'}
									<a
										href={value}
										target="_blank"
										rel="noopener noreferrer"
										class="text-primary hover:underline"
										onclick={(e) => e.stopPropagation()}
									>
										{formatted}
									</a>
								{:else if column.type === 'phone'}
									<a
										href="tel:{value}"
										class="text-primary hover:underline"
										onclick={(e) => e.stopPropagation()}
									>
										{formatted}
									</a>
								{:else}
									<span class="truncate">{formatted}</span>
								{/if}
							</div>
						{/if}
					</Table.Cell>
				{/each}
			</Table.Row>
		{/each}
	{/if}
</Table.Body>

<script lang="ts">
	import { getContext, onMount } from 'svelte';
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
		rowHeight?: number;
		overscan?: number;
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
		rowHeight = 48,
		overscan = 5,
		onRowClick,
		onCellUpdate,
		onCreateNew
	}: Props = $props();

	const table = getContext<TableContext>('table');

	// Virtual scrolling state
	let containerRef: HTMLDivElement | undefined = $state();
	let scrollTop = $state(0);
	let containerHeight = $state(400);

	// Calculate visible rows
	const totalHeight = $derived(data.length * rowHeight);
	const startIndex = $derived(Math.max(0, Math.floor(scrollTop / rowHeight) - overscan));
	const endIndex = $derived(
		Math.min(data.length, Math.ceil((scrollTop + containerHeight) / rowHeight) + overscan)
	);
	const visibleData = $derived(data.slice(startIndex, endIndex));
	const offsetY = $derived(startIndex * rowHeight);

	// Determine empty state type
	let hasFilters = $derived(table.state.filters.length > 0);
	let hasSearch = $derived(table.state.globalFilter?.length > 0);
	let hasFiltersOrSearch = $derived(hasFilters || hasSearch);

	// Get column width from table state
	function getColumnWidth(column: ColumnDef): number {
		return table.state.columnWidths[column.id] || column.width || 150;
	}

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

	function handleScroll(event: Event) {
		const target = event.target as HTMLDivElement;
		scrollTop = target.scrollTop;
	}

	// Check if column is editable
	function isColumnEditable(column: ColumnDef): boolean {
		if (column.cell) return false;
		const editableTypes = [
			'text',
			'email',
			'phone',
			'url',
			'number',
			'decimal',
			'date',
			'datetime',
			'select',
			'radio',
			'multiselect',
			'boolean',
			'toggle',
			'checkbox'
		];
		return editableTypes.includes(column.type || 'text');
	}

	onMount(() => {
		if (containerRef) {
			const resizeObserver = new ResizeObserver((entries) => {
				for (const entry of entries) {
					containerHeight = entry.contentRect.height;
				}
			});
			resizeObserver.observe(containerRef);
			return () => resizeObserver.disconnect();
		}
	});
</script>

{#if loading}
	<!-- Loading state -->
	<div class="flex h-64 items-center justify-center">
		<div class="flex flex-col items-center justify-center gap-3 text-muted-foreground">
			<Loader2 class="h-8 w-8 animate-spin text-primary/60" />
			<span class="text-sm">Loading records...</span>
		</div>
	</div>
{:else if error}
	<!-- Error state -->
	<div class="flex h-64 items-center justify-center">
		<div class="flex flex-col items-center justify-center gap-4">
			<div class="flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10">
				<AlertCircle class="h-8 w-8 text-destructive" />
			</div>
			<div class="space-y-1 text-center">
				<p class="font-medium text-destructive">Error loading data</p>
				<p class="max-w-md text-sm text-muted-foreground">{error}</p>
			</div>
			<Button variant="outline" size="sm" onclick={handleRetry}>
				<RefreshCw class="mr-2 h-4 w-4" />
				Try again
			</Button>
		</div>
	</div>
{:else if data.length === 0}
	<!-- Empty state -->
	<div class="flex h-64 items-center justify-center">
		<div class="flex flex-col items-center justify-center gap-4">
			{#if hasFiltersOrSearch}
				<div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
					<SearchX class="h-8 w-8 text-muted-foreground" />
				</div>
				<div class="space-y-1 text-center">
					<p class="font-medium">No matching records</p>
					<p class="max-w-md text-sm text-muted-foreground">
						{#if hasSearch && hasFilters}
							No records match your search and filters.
						{:else if hasSearch}
							No records match "{table.state.globalFilter}".
						{:else}
							No records match your current filters.
						{/if}
					</p>
				</div>
				<Button variant="outline" size="sm" onclick={handleClearFilters}>
					<FilterX class="mr-2 h-4 w-4" />
					Clear {hasSearch && hasFilters ? 'all' : hasSearch ? 'search' : 'filters'}
				</Button>
			{:else}
				<div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
					<FileX2 class="h-8 w-8 text-muted-foreground" />
				</div>
				<div class="space-y-1 text-center">
					<p class="font-medium">No records yet</p>
					<p class="max-w-md text-sm text-muted-foreground">
						Get started by creating your first record.
					</p>
				</div>
				{#if onCreateNew}
					<Button size="sm" onclick={onCreateNew}>
						<Plus class="mr-2 h-4 w-4" />
						Create first record
					</Button>
				{/if}
			{/if}
		</div>
	</div>
{:else}
	<!-- Virtual scrolling container -->
	<div
		bind:this={containerRef}
		class="max-h-[600px] overflow-auto"
		onscroll={handleScroll}
		role="rowgroup"
	>
		<!-- Spacer for total height -->
		<div style="height: {totalHeight}px; position: relative;">
			<!-- Visible rows container -->
			<div style="position: absolute; top: {offsetY}px; left: 0; right: 0;">
				<Table.Body>
					{#each visibleData as row, virtualIndex (row.id || startIndex + virtualIndex)}
						{@const actualIndex = startIndex + virtualIndex}
						{@const isSelected = table.state.rowSelection[row.id] || false}
						<Table.Row
							class="{isSelected ? 'bg-muted/50' : ''} {onRowClick ? 'cursor-pointer' : ''}"
							style="height: {rowHeight}px;"
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
									style={enableColumnResize
										? `width: ${columnWidth}px; min-width: ${column.minWidth || 50}px; max-width: ${column.maxWidth || 500}px;`
										: ''}
								>
									{#if column.cell}
										<svelte:component this={column.cell} {value} {row} {column} index={actualIndex} />
									{:else if editable}
										<EditableCell {value} {row} {column} {moduleApiName} onUpdate={onCellUpdate} />
									{:else}
										<div class="flex items-center truncate">
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
													class="truncate text-primary hover:underline"
													onclick={(e) => e.stopPropagation()}
												>
													{formatted}
												</a>
											{:else if column.type === 'url'}
												<a
													href={value}
													target="_blank"
													rel="noopener noreferrer"
													class="truncate text-primary hover:underline"
													onclick={(e) => e.stopPropagation()}
												>
													{formatted}
												</a>
											{:else if column.type === 'phone'}
												<a
													href="tel:{value}"
													class="truncate text-primary hover:underline"
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
				</Table.Body>
			</div>
		</div>
	</div>

	<!-- Row count info -->
	<div class="px-2 py-1 text-xs text-muted-foreground">
		Showing rows {startIndex + 1} - {Math.min(endIndex, data.length)} of {data.length}
	</div>
{/if}

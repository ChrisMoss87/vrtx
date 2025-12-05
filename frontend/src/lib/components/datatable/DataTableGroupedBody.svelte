<script lang="ts">
	import { getContext } from 'svelte';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Button } from '$lib/components/ui/button';
	import {
		Loader2,
		FileX2,
		SearchX,
		Plus,
		FilterX,
		RefreshCw,
		AlertCircle,
		ChevronRight,
		ChevronDown
	} from 'lucide-svelte';
	import { getNestedValue, formatCellValue } from './utils';
	import EditableCell from './EditableCell.svelte';
	import type { ColumnDef, TableContext } from './types';
	import * as Table from '$lib/components/ui/table';

	interface Props {
		columns: ColumnDef[];
		data: any[];
		groupByField: string;
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
		groupByField,
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

	const table = getContext<TableContext>('table');

	// Group data by field
	interface GroupedData {
		key: string;
		label: string;
		rows: any[];
		count: number;
		expanded: boolean;
	}

	let expandedGroups = $state<Set<string>>(new Set());

	// Group the data
	const groupedData = $derived.by(() => {
		const groups = new Map<string, any[]>();

		// Find the column for the group field to get display options
		const groupColumn = columns.find((c) => c.accessorKey === groupByField || c.id === groupByField);

		data.forEach((row) => {
			const value = getNestedValue(row, groupByField);
			const key = value !== null && value !== undefined ? String(value) : '(empty)';

			if (!groups.has(key)) {
				groups.set(key, []);
			}
			groups.get(key)!.push(row);
		});

		// Convert to array and sort
		const result: GroupedData[] = [];
		groups.forEach((rows, key) => {
			// Get label from options if available
			let label = key;
			if (groupColumn?.options || groupColumn?.filterOptions) {
				const options = groupColumn.options || groupColumn.filterOptions || [];
				const option = options.find((o) => String(o.value) === key);
				if (option) {
					label = option.label;
				}
			}

			result.push({
				key,
				label,
				rows,
				count: rows.length,
				expanded: expandedGroups.has(key)
			});
		});

		// Sort groups alphabetically
		result.sort((a, b) => a.label.localeCompare(b.label));

		return result;
	});

	// Determine empty state type
	let hasFilters = $derived(table.state.filters.length > 0);
	let hasSearch = $derived(table.state.globalFilter?.length > 0);
	let hasFiltersOrSearch = $derived(hasFilters || hasSearch);

	// Get column width from table state
	function getColumnWidth(column: ColumnDef): number {
		return table.state.columnWidths[column.id] || column.width || 150;
	}

	function toggleGroup(key: string) {
		const newSet = new Set(expandedGroups);
		if (newSet.has(key)) {
			newSet.delete(key);
		} else {
			newSet.add(key);
		}
		expandedGroups = newSet;
	}

	function expandAll() {
		expandedGroups = new Set(groupedData.map((g) => g.key));
	}

	function collapseAll() {
		expandedGroups = new Set();
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

	// Check if all rows in a group are selected
	function isGroupSelected(group: GroupedData): boolean {
		return group.rows.every((row) => table.state.rowSelection[row.id]);
	}

	// Check if some rows in a group are selected
	function isGroupPartiallySelected(group: GroupedData): boolean {
		const selectedCount = group.rows.filter((row) => table.state.rowSelection[row.id]).length;
		return selectedCount > 0 && selectedCount < group.rows.length;
	}

	// Toggle all rows in a group
	function toggleGroupSelection(group: GroupedData) {
		const allSelected = isGroupSelected(group);
		group.rows.forEach((row) => {
			if (allSelected) {
				// Deselect all
				if (table.state.rowSelection[row.id]) {
					table.toggleRowSelection(row.id);
				}
			} else {
				// Select all
				if (!table.state.rowSelection[row.id]) {
					table.toggleRowSelection(row.id);
				}
			}
		});
	}
</script>

{#if loading}
	<!-- Loading state -->
	<Table.Body>
		<Table.Row>
			<Table.Cell
				colspan={enableSelection ? columns.length + 2 : columns.length + 1}
				class="h-32 text-center"
			>
				<div class="flex flex-col items-center justify-center gap-3 text-muted-foreground">
					<Loader2 class="h-8 w-8 animate-spin text-primary/60" />
					<span class="text-sm">Loading records...</span>
				</div>
			</Table.Cell>
		</Table.Row>
	</Table.Body>
{:else if error}
	<!-- Error state -->
	<Table.Body>
		<Table.Row>
			<Table.Cell
				colspan={enableSelection ? columns.length + 2 : columns.length + 1}
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
	</Table.Body>
{:else if data.length === 0}
	<!-- Empty state -->
	<Table.Body>
		<Table.Row>
			<Table.Cell
				colspan={enableSelection ? columns.length + 2 : columns.length + 1}
				class="h-48 text-center"
			>
				<div class="flex flex-col items-center justify-center gap-4">
					{#if hasFiltersOrSearch}
						<div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
							<SearchX class="h-8 w-8 text-muted-foreground" />
						</div>
						<div class="space-y-1">
							<p class="font-medium">No matching records</p>
							<p class="max-w-md text-sm text-muted-foreground">
								No records match your current filters.
							</p>
						</div>
						<Button variant="outline" size="sm" onclick={handleClearFilters}>
							<FilterX class="mr-2 h-4 w-4" />
							Clear filters
						</Button>
					{:else}
						<div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
							<FileX2 class="h-8 w-8 text-muted-foreground" />
						</div>
						<div class="space-y-1">
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
			</Table.Cell>
		</Table.Row>
	</Table.Body>
{:else}
	<!-- Expand/Collapse controls -->
	<div class="flex items-center justify-end gap-2 px-4 py-2 text-xs">
		<button type="button" class="text-primary hover:underline" onclick={expandAll}>
			Expand all
		</button>
		<span class="text-muted-foreground">|</span>
		<button type="button" class="text-primary hover:underline" onclick={collapseAll}>
			Collapse all
		</button>
		<span class="ml-2 text-muted-foreground">{groupedData.length} groups</span>
	</div>

	<!-- Grouped rows -->
	<Table.Body>
		{#each groupedData as group (group.key)}
			<!-- Group header row -->
			<Table.Row class="bg-muted/50 hover:bg-muted/70">
				<!-- Expand/collapse toggle -->
				<Table.Cell class="w-[40px]">
					<button
						type="button"
						onclick={() => toggleGroup(group.key)}
						class="flex h-6 w-6 items-center justify-center rounded hover:bg-accent"
						aria-label={group.expanded ? 'Collapse group' : 'Expand group'}
					>
						{#if group.expanded}
							<ChevronDown class="h-4 w-4" />
						{:else}
							<ChevronRight class="h-4 w-4" />
						{/if}
					</button>
				</Table.Cell>

				<!-- Group selection checkbox -->
				{#if enableSelection}
					<Table.Cell class="w-[50px]">
						<div class="flex items-center justify-center">
							<Checkbox
								checked={isGroupSelected(group)}
								indeterminate={isGroupPartiallySelected(group)}
								onCheckedChange={() => toggleGroupSelection(group)}
								aria-label="Select all in group"
							/>
						</div>
					</Table.Cell>
				{/if}

				<!-- Group info spanning remaining columns -->
				<Table.Cell colspan={columns.length} class="font-medium">
					<div class="flex items-center gap-2">
						<span>{group.label}</span>
						<span class="text-sm text-muted-foreground">({group.count} records)</span>
					</div>
				</Table.Cell>
			</Table.Row>

			<!-- Group rows (when expanded) -->
			{#if group.expanded}
				{#each group.rows as row, index (row.id || index)}
					{@const isSelected = table.state.rowSelection[row.id] || false}
					<Table.Row
						class="{isSelected ? 'bg-muted/30' : ''} {onRowClick ? 'cursor-pointer' : ''}"
						onclick={() => handleRowClick(row)}
					>
						<!-- Spacer for expand/collapse column -->
						<Table.Cell class="w-[40px]"></Table.Cell>

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
									<svelte:component this={column.cell} {value} {row} {column} {index} />
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
			{/if}
		{/each}
	</Table.Body>
{/if}

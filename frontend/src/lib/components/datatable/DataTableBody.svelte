<script lang="ts">
	import { getContext } from 'svelte';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Loader2 } from 'lucide-svelte';
	import { getNestedValue, formatCellValue } from './utils';
	import EditableCell from './EditableCell.svelte';
	import type { ColumnDef, TableContext } from './types';

	interface Props {
		columns: ColumnDef[];
		data: any[];
		loading?: boolean;
		error?: string | null;
		enableSelection?: boolean;
		enableInlineEdit?: boolean;
		moduleApiName: string;
		onRowClick?: (row: any) => void;
		onCellUpdate?: (recordId: string, field: string, value: any) => Promise<void>;
	}

	let {
		columns,
		data,
		loading = false,
		error = null,
		enableSelection = true,
		enableInlineEdit = true,
		moduleApiName,
		onRowClick,
		onCellUpdate
	}: Props = $props();

	const table = getContext<TableContext>('table');

	function handleRowClick(row: any) {
		if (onRowClick) {
			onRowClick(row);
		}
	}

	// Check if column is editable
	function isColumnEditable(column: ColumnDef): boolean {
		// Don't allow editing if custom cell renderer is used
		if (column.cell) return false;

		// Allow editing for these types
		const editableTypes = ['text', 'email', 'phone', 'url', 'number', 'decimal', 'date', 'datetime'];
		return editableTypes.includes(column.type || 'text');
	}
</script>

<tbody class="[&_tr:last-child]:border-0">
	{#if loading}
		<!-- Loading state -->
		<tr>
			<td colspan={enableSelection ? columns.length + 1 : columns.length} class="h-24 text-center">
				<div class="flex items-center justify-center gap-2 text-muted-foreground">
					<Loader2 class="h-4 w-4 animate-spin" />
					<span>Loading...</span>
				</div>
			</td>
		</tr>
	{:else if error}
		<!-- Error state -->
		<tr>
			<td colspan={enableSelection ? columns.length + 1 : columns.length} class="h-24 text-center">
				<div class="text-destructive">
					<p class="font-medium">Error loading data</p>
					<p class="text-sm text-muted-foreground">{error}</p>
				</div>
			</td>
		</tr>
	{:else if data.length === 0}
		<!-- Empty state -->
		<tr>
			<td colspan={enableSelection ? columns.length + 1 : columns.length} class="h-24 text-center">
				<div class="text-muted-foreground">
					<p class="font-medium">No results found</p>
					<p class="text-sm">Try adjusting your filters or search term</p>
				</div>
			</td>
		</tr>
	{:else}
		<!-- Data rows -->
		{#each data as row, index (row.id || index)}
			{@const isSelected = table.state.rowSelection[row.id] || false}
			<tr
				class="border-b transition-colors {isSelected ? 'bg-muted/50' : 'hover:bg-muted/50'} {onRowClick ? 'cursor-pointer' : ''}"
				onclick={() => handleRowClick(row)}
			>
				<!-- Selection checkbox -->
				{#if enableSelection}
					<td class="px-4">
						<div class="flex items-center justify-center">
							<Checkbox
								checked={isSelected}
								onCheckedChange={() => table.toggleRowSelection(row.id)}
								aria-label="Select row"
								onclick={(e) => e.stopPropagation()}
							/>
						</div>
					</td>
				{/if}

				<!-- Data cells -->
				{#each columns as column (column.id)}
					{@const value = getNestedValue(row, column.accessorKey)}
					{@const formatted = column.format
						? column.format(value, row)
						: formatCellValue(value, column.type)}
					{@const cellClass = column.cellClass ? column.cellClass(value, row) : ''}
					{@const editable = enableInlineEdit && isColumnEditable(column)}

					<td class="px-4 py-3 align-middle {cellClass}">
						{#if column.cell}
							<!-- Custom cell component -->
							<svelte:component this={column.cell} {value} {row} {column} {index} />
						{:else if editable}
							<!-- Editable cell with inline editing -->
							<EditableCell
								{value}
								{row}
								{column}
								{moduleApiName}
								onUpdate={onCellUpdate}
							/>
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
					</td>
				{/each}
			</tr>
		{/each}
	{/if}
</tbody>

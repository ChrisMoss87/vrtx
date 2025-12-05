<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as ResponsiveDialog from '$lib/components/ui/responsive-dialog';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Plus, X, Trash2 } from 'lucide-svelte';
	import type { ColumnDef, TableContext, FilterConfig, FilterOperator } from './types';
	import { TextFilter, NumberFilter, DateFilter, SelectFilter } from './filters';

	interface Props {
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
	}

	let { open = $bindable(false), onOpenChange }: Props = $props();

	const table = getContext<TableContext>('table');

	// Get filterable columns
	const filterableColumns = $derived(
		table.columns.filter((col) => col.filterable !== false && col.type !== 'actions')
	);

	// Track pending filters (filters being built before applying)
	let pendingFilters = $state<FilterConfig[]>([...table.state.filters]);

	// Sync pending filters when dialog opens
	$effect(() => {
		if (open) {
			pendingFilters = [...table.state.filters];
		}
	});

	// Determine filter type from column type
	function getFilterType(column: ColumnDef): 'text' | 'number' | 'date' | 'select' {
		if (
			column.type === 'number' ||
			column.type === 'decimal' ||
			column.type === 'currency' ||
			column.type === 'percent'
		) {
			return 'number';
		}
		if (column.type === 'date' || column.type === 'datetime') {
			return 'date';
		}
		if (column.type === 'select' || column.type === 'radio' || column.type === 'multiselect') {
			return 'select';
		}
		return 'text';
	}

	// Add a new blank filter
	function addFilter() {
		const firstColumn = filterableColumns[0];
		if (!firstColumn) return;

		pendingFilters = [
			...pendingFilters,
			{
				field: firstColumn.id,
				operator: 'contains' as FilterOperator,
				value: ''
			}
		];
	}

	// Remove a filter by index
	function removeFilter(index: number) {
		pendingFilters = pendingFilters.filter((_, i) => i !== index);
	}

	// Update a filter
	function updateFilter(index: number, filter: Partial<FilterConfig>) {
		pendingFilters = pendingFilters.map((f, i) => (i === index ? { ...f, ...filter } : f));
	}

	// Apply all pending filters to the table
	function applyFilters() {
		// Clear existing filters
		table.clearFilters();

		// Apply each pending filter that has a value
		pendingFilters.forEach((filter) => {
			// Check if value is valid (not empty, null, or undefined)
			const hasValue = filter.value !== '' && filter.value !== null && filter.value !== undefined;

			// For operators that don't require a value (is_empty, is_not_empty, etc.)
			const noValueRequired = ['is_empty', 'is_not_empty', 'is_null', 'is_not_null'].includes(
				filter.operator
			);

			if (hasValue || noValueRequired) {
				table.updateFilter(filter);
			}
		});

		// Close the dialog
		open = false;
		onOpenChange?.(false);
	}

	// Clear all filters
	function clearAllFilters() {
		pendingFilters = [];
	}

	// Get column by ID
	function getColumn(columnId: string): ColumnDef | undefined {
		return table.columns.find((col) => col.id === columnId);
	}
</script>

<ResponsiveDialog.Root bind:open {onOpenChange}>
	<ResponsiveDialog.Content class="sm:max-w-2xl">
		<ResponsiveDialog.Header>
			<ResponsiveDialog.Title>Filters</ResponsiveDialog.Title>
			<ResponsiveDialog.Description>
				Add multiple filters to refine your results. All filters are applied with AND logic.
			</ResponsiveDialog.Description>
		</ResponsiveDialog.Header>

		<div class="flex flex-col gap-4 py-4">
			<!-- Filter List -->
			<ScrollArea class="max-h-[60vh] pr-4">
				<div class="space-y-3">
					{#if pendingFilters.length === 0}
						<div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
							<p class="text-sm text-muted-foreground">No filters applied</p>
							<Button variant="outline" size="sm" onclick={addFilter}>
								<Plus class="mr-2 h-4 w-4" />
								Add filter
							</Button>
						</div>
					{:else}
						{#each pendingFilters as filter, index (index)}
							{@const column = getColumn(filter.field)}

							<div class="space-y-3 rounded-lg border p-3">
								<!-- Filter Header -->
								<div class="flex items-center justify-between gap-2">
									<div class="flex min-w-0 flex-1 items-center gap-2">
										<Badge variant="secondary" class="flex-shrink-0 text-xs">
											{index + 1}
										</Badge>
										<span class="truncate text-sm font-medium"
											>{column?.header || filter.field}</span
										>
									</div>
									<Button
										variant="ghost"
										size="icon"
										class="h-6 w-6 flex-shrink-0"
										onclick={() => removeFilter(index)}
										aria-label="Remove filter"
									>
										<X class="h-3 w-3" />
									</Button>
								</div>

								<!-- Column Selection -->
								<div class="space-y-2">
									<label for="column-{index}" class="text-xs font-medium text-muted-foreground"
										>Column</label
									>
									<Select.Root
										type="single"
										value={filter.field}
										onValueChange={(val) => {
											if (val) {
												updateFilter(index, {
													field: val,
													operator: 'contains',
													value: ''
												});
											}
										}}
									>
										<Select.Trigger class="w-full" id="column-{index}">
											<span>{column?.header || filter.field || 'Select column'}</span>
										</Select.Trigger>
										<Select.Content>
											{#each filterableColumns as col}
												<Select.Item value={col.id}>{col.header}</Select.Item>
											{/each}
										</Select.Content>
									</Select.Root>
								</div>

								<!-- Filter Component Based on Type -->
								{#if column}
									{@const filterType = getFilterType(column)}
									<div class="rounded-md border bg-muted/50 p-3">
										{#if filterType === 'text'}
											<TextFilter
												field={filter.field}
												initialValue={filter}
												onApply={(newFilter) => {
													if (newFilter) {
														updateFilter(index, newFilter);
													}
												}}
												onClose={() => {}}
											/>
										{:else if filterType === 'number'}
											<NumberFilter
												field={filter.field}
												initialValue={filter}
												onApply={(newFilter) => {
													if (newFilter) {
														updateFilter(index, newFilter);
													}
												}}
												onClose={() => {}}
											/>
										{:else if filterType === 'date'}
											<DateFilter
												field={filter.field}
												initialValue={filter}
												onApply={(newFilter) => {
													if (newFilter) {
														updateFilter(index, {
															field: filter.field,
															operator: newFilter.operator as any,
															value: newFilter.value
														});
													}
												}}
												onClose={() => {}}
											/>
										{:else if filterType === 'select'}
											<SelectFilter
												field={filter.field}
												options={column.filterOptions || []}
												initialValue={filter}
												onApply={(newFilter) => {
													if (newFilter) {
														updateFilter(index, newFilter);
													}
												}}
												onClose={() => {}}
											/>
										{/if}
									</div>
								{/if}
							</div>
						{/each}
					{/if}
				</div>
			</ScrollArea>

			<!-- Add Filter Button (when filters exist) -->
			{#if pendingFilters.length > 0}
				<Button variant="outline" size="sm" onclick={addFilter} class="w-full">
					<Plus class="mr-2 h-4 w-4" />
					Add another filter
				</Button>
			{/if}
		</div>

		<!-- Footer Actions -->
		<ResponsiveDialog.Footer class="flex-col gap-2 sm:flex-row">
			<Button
				variant="ghost"
				onclick={() => {
					open = false;
					onOpenChange?.(false);
				}}
				class="order-2 sm:order-1"
			>
				Cancel
			</Button>
			<div class="order-1 flex gap-2 sm:order-2">
				<Button
					variant="outline"
					onclick={clearAllFilters}
					disabled={pendingFilters.length === 0}
					class="flex-shrink-0"
					aria-label="Clear all filters"
				>
					<Trash2 class="h-4 w-4" />
				</Button>
				<Button onclick={applyFilters} class="flex-1" disabled={pendingFilters.length === 0}>
					Apply {pendingFilters.length > 0
						? `${pendingFilters.length} `
						: ''}filter{pendingFilters.length === 1 ? '' : 's'}
				</Button>
			</div>
		</ResponsiveDialog.Footer>
	</ResponsiveDialog.Content>
</ResponsiveDialog.Root>

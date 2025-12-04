<script lang="ts">
	import { getContext } from 'svelte';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import * as Popover from '$lib/components/ui/popover';
	import { X, Edit } from 'lucide-svelte';
	import type { TableContext, FilterConfig } from './types';

	// Import filter components
	import TextFilter from './filters/TextFilter.svelte';
	import NumberFilter from './filters/NumberFilter.svelte';
	import DateRangeFilter from './filters/DateRangeFilter.svelte';
	import SelectFilter from './filters/SelectFilter.svelte';

	const table = getContext<TableContext>('table');

	// Track which filter chip is being edited
	let editingField = $state<string | null>(null);

	// Format filter value for display
	function formatFilterValue(filter: FilterConfig): string {
		const operators: Record<string, string> = {
			equals: '=',
			not_equals: '≠',
			contains: 'contains',
			starts_with: 'starts with',
			ends_with: 'ends with',
			greater_than: '>',
			less_than: '<',
			greater_or_equal: '≥',
			less_or_equal: '≤',
			between: 'between',
			in: 'in',
			is_empty: 'is empty',
			is_not_empty: 'is not empty',
			today: 'today',
			yesterday: 'yesterday',
			last_7_days: 'last 7 days',
			last_30_days: 'last 30 days',
			this_month: 'this month',
			last_month: 'last month',
			before: 'before',
			after: 'after'
		};

		const operator = operators[filter.operator] || filter.operator;

		if (filter.operator === 'is_empty' || filter.operator === 'is_not_empty') {
			return operator;
		}

		if (filter.operator === 'between' && Array.isArray(filter.value)) {
			return `${operator} ${filter.value[0]} and ${filter.value[1]}`;
		}

		if (filter.operator === 'in' && Array.isArray(filter.value)) {
			return `${operator} (${filter.value.length})`;
		}

		if (
			filter.operator === 'today' ||
			filter.operator === 'yesterday' ||
			filter.operator === 'last_7_days' ||
			filter.operator === 'last_30_days' ||
			filter.operator === 'this_month' ||
			filter.operator === 'last_month'
		) {
			return operator;
		}

		return `${operator} ${filter.value}`;
	}

	// Get column header by ID
	function getColumnHeader(columnId: string): string {
		const column = table.columns.find((c) => c.id === columnId);
		return column?.header || columnId;
	}

	function removeFilter(field: string) {
		table.removeFilter(field);
	}

	function clearAllFilters() {
		table.clearFilters();
	}

	// Get filter component based on column type
	function getFilterComponent(columnId: string) {
		const column = table.columns.find((c) => c.id === columnId);
		if (!column) return TextFilter;

		switch (column.type) {
			case 'number':
			case 'decimal':
			case 'currency':
			case 'percent':
				return NumberFilter;
			case 'date':
			case 'datetime':
				return DateRangeFilter;
			case 'select':
			case 'multiselect':
			case 'radio':
				return SelectFilter;
			default:
				return TextFilter;
		}
	}

	function getFilterOptions(columnId: string) {
		const column = table.columns.find((c) => c.id === columnId);
		return column?.filterOptions || [];
	}

	function handleEditFilter(filter: FilterConfig | null, field: string) {
		if (filter) {
			table.updateFilter(filter);
		} else {
			table.removeFilter(field);
		}
		editingField = null;
	}
</script>

{#if table.state.filters.length > 0}
	<div class="flex flex-wrap items-center gap-2">
		<span class="text-sm text-muted-foreground">Filters:</span>

		{#each table.state.filters as filter (filter.field)}
			{@const FilterComponent = getFilterComponent(filter.field)}
			{@const filterOptions = getFilterOptions(filter.field)}

			<Popover.Root
				open={editingField === filter.field}
				onOpenChange={(open) => {
					if (!open) editingField = null;
				}}
			>
				<Popover.Trigger>
					{#snippet child({ props })}
						<Badge
							{...props}
							variant="secondary"
							class="cursor-pointer gap-1.5 pr-1 pl-2 transition-colors hover:bg-secondary/80"
							onclick={() => (editingField = filter.field)}
						>
							<span class="font-normal">
								{getColumnHeader(filter.field)}: {formatFilterValue(filter)}
							</span>
							<div class="flex items-center gap-0.5">
								<Button
									variant="ghost"
									size="icon"
									class="h-4 w-4 p-0 hover:bg-transparent"
									onclick={(e: MouseEvent) => {
										e.stopPropagation();
										editingField = filter.field;
									}}
									aria-label="Edit filter"
								>
									<Edit class="h-2.5 w-2.5" />
								</Button>
								<Button
									variant="ghost"
									size="icon"
									class="h-4 w-4 p-0 hover:bg-transparent"
									onclick={(e: MouseEvent) => {
										e.stopPropagation();
										removeFilter(filter.field);
									}}
									aria-label="Remove filter"
								>
									<X class="h-3 w-3" />
								</Button>
							</div>
						</Badge>
					{/snippet}
				</Popover.Trigger>
				<Popover.Content class="w-80 p-0" align="start">
					<div class="border-b px-3 py-2">
						<h4 class="text-sm font-medium">Edit filter: {getColumnHeader(filter.field)}</h4>
					</div>
					<div class="p-3">
						{#if FilterComponent === TextFilter}
							<TextFilter
								field={filter.field}
								initialValue={filter}
								onApply={(f) => handleEditFilter(f, filter.field)}
								onClose={() => (editingField = null)}
							/>
						{:else if FilterComponent === NumberFilter}
							<NumberFilter
								field={filter.field}
								initialValue={filter}
								onApply={(f) => handleEditFilter(f, filter.field)}
								onClose={() => (editingField = null)}
							/>
						{:else if FilterComponent === DateRangeFilter}
							<DateRangeFilter
								field={filter.field}
								initialValue={filter}
								onApply={(f) => handleEditFilter(f, filter.field)}
								onClose={() => (editingField = null)}
							/>
						{:else if FilterComponent === SelectFilter}
							<SelectFilter
								field={filter.field}
								options={filterOptions}
								initialValue={filter}
								onApply={(f) => handleEditFilter(f, filter.field)}
								onClose={() => (editingField = null)}
							/>
						{/if}
					</div>
				</Popover.Content>
			</Popover.Root>
		{/each}

		<Button variant="ghost" size="sm" onclick={clearAllFilters} class="h-6 text-xs">
			Clear all
		</Button>
	</div>
{/if}

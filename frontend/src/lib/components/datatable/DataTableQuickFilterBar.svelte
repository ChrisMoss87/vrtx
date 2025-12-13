<script lang="ts">
	import { getContext } from 'svelte';
	import { Input } from '$lib/components/ui/input';
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import * as Popover from '$lib/components/ui/popover';
	import { Calendar } from '$lib/components/ui/calendar';
	import { Badge } from '$lib/components/ui/badge';
	import { X, Calendar as CalendarIcon, ChevronDown, Filter } from 'lucide-svelte';
	import type { ColumnDef, TableContext, FilterConfig } from './types';
	import { cn } from '$lib/utils';
	import { CalendarDate, getLocalTimeZone, today, parseDate } from '@internationalized/date';

	interface Props {
		quickFilterColumns?: string[]; // Column IDs to show in quick bar
		showAdvancedToggle?: boolean;
		onAdvancedClick?: () => void;
	}

	let { quickFilterColumns = [], showAdvancedToggle = true, onAdvancedClick }: Props = $props();

	const table = getContext<TableContext>('table');

	// Auto-detect quick filter columns if not provided (top 5 filterable columns)
	const quickColumns = $derived(
		quickFilterColumns.length > 0
			? table.columns.filter((col) => quickFilterColumns.includes(col.id))
			: table.columns.filter((col) => col.filterable !== false).slice(0, 5)
	);

	// Track filter values for each quick column
	let filterValues = $state<Record<string, any>>({});

	// Track which columns have active filters
	const activeFilters = $derived(
		table.state.filters.reduce(
			(acc, filter) => {
				acc[filter.field] = filter;
				return acc;
			},
			{} as Record<string, FilterConfig>
		)
	);

	// Initialize filter values from active filters
	$effect(() => {
		quickColumns.forEach((col) => {
			const activeFilter = activeFilters[col.id];
			if (activeFilter) {
				filterValues[col.id] = activeFilter.value;
			}
		});
	});

	// Apply filter with debounce
	let debounceTimers: Record<string, ReturnType<typeof setTimeout>> = {};

	function applyFilter(columnId: string, value: any, operator: string = 'auto') {
		// Clear existing debounce timer
		if (debounceTimers[columnId]) {
			clearTimeout(debounceTimers[columnId]);
		}

		// Debounce the filter application
		debounceTimers[columnId] = setTimeout(() => {
			const column = quickColumns.find((col) => col.id === columnId);
			if (!column) return;

			// Auto-detect operator based on column type
			let selectedOperator = operator;
			if (operator === 'auto') {
				selectedOperator = getDefaultOperator(column.type, value);
			}

			// Clear filter if value is empty
			if (value === '' || value === null || value === undefined) {
				table.removeFilter(columnId);
				return;
			}

			// Apply the filter
			table.updateFilter({
				field: columnId,
				operator: selectedOperator as any,
				value: value
			});
		}, 300);
	}

	function getDefaultOperator(columnType: string, value: any): string {
		switch (columnType) {
			case 'text':
			case 'email':
			case 'phone':
			case 'url':
				return 'contains';
			case 'number':
			case 'decimal':
			case 'currency':
			case 'percent':
				return 'equals';
			case 'date':
			case 'datetime':
				return Array.isArray(value) || value?.from ? 'between' : 'equals';
			case 'select':
			case 'multiselect':
			case 'boolean':
				return Array.isArray(value) ? 'in' : 'equals';
			default:
				return 'equals';
		}
	}

	function clearFilter(columnId: string) {
		filterValues[columnId] = '';
		table.removeFilter(columnId);
	}

	function clearAllFilters() {
		filterValues = {};
		table.clearFilters();
	}

	// Count active filters
	const activeFilterCount = $derived(table.state.filters.length);
</script>

<div class="flex flex-col gap-3 rounded-lg border bg-card p-4">
	<!-- Quick Filter Bar Header -->
	<div class="flex items-center justify-between">
		<div class="flex items-center gap-2">
			<Filter class="h-4 w-4 text-muted-foreground" />
			<span class="text-sm font-medium">Quick Filters</span>
			{#if activeFilterCount > 0}
				<Badge variant="secondary" class="h-5 px-1.5 text-xs">
					{activeFilterCount}
				</Badge>
			{/if}
		</div>
		<div class="flex items-center gap-2">
			{#if activeFilterCount > 0}
				<Button variant="ghost" size="sm" onclick={clearAllFilters}>
					<X class="mr-1 h-3 w-3" />
					Clear all
				</Button>
			{/if}
			{#if showAdvancedToggle}
				<Button variant="outline" size="sm" onclick={onAdvancedClick}>
					<Filter class="mr-1 h-3 w-3" />
					Advanced
				</Button>
			{/if}
		</div>
	</div>

	<!-- Quick Filter Inputs -->
	<div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
		{#each quickColumns as column (column.id)}
			{@const hasFilter = !!activeFilters[column.id]}
			<div class="flex flex-col gap-1.5">
				<label for="filter-{column.id}" class="text-xs font-medium text-muted-foreground">
					{column.header}
					{#if hasFilter}
						<span class="text-primary">*</span>
					{/if}
				</label>

				{#if column.type === 'text' || column.type === 'email' || column.type === 'phone' || column.type === 'url'}
					<!-- Text Input -->
					<div class="relative">
						<Input
							id="filter-{column.id}"
							type="text"
							placeholder="Search {column.header.toLowerCase()}..."
							bind:value={filterValues[column.id]}
							oninput={() => applyFilter(column.id, filterValues[column.id])}
							class={cn('pr-8', hasFilter && 'border-primary')}
						/>
						{#if hasFilter}
							<button
								type="button"
								onclick={() => clearFilter(column.id)}
								class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
							>
								<X class="h-3 w-3" />
							</button>
						{/if}
					</div>
				{:else if column.type === 'number' || column.type === 'decimal' || column.type === 'currency' || column.type === 'percent'}
					<!-- Number Input -->
					<div class="relative">
						<Input
							id="filter-{column.id}"
							type="number"
							placeholder="Filter by {column.header.toLowerCase()}..."
							bind:value={filterValues[column.id]}
							oninput={() => applyFilter(column.id, filterValues[column.id])}
							class={cn('pr-8', hasFilter && 'border-primary')}
						/>
						{#if hasFilter}
							<button
								type="button"
								onclick={() => clearFilter(column.id)}
								class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
							>
								<X class="h-3 w-3" />
							</button>
						{/if}
					</div>
				{:else if column.type === 'select' || column.type === 'multiselect' || column.type === 'radio'}
					<!-- Select Dropdown -->
					<Select.Root
						type="single"
						value={filterValues[column.id] || undefined}
						onValueChange={(value) => {
							if (value === '__clear__') {
								filterValues[column.id] = '';
								clearFilter(column.id);
							} else if (value) {
								filterValues[column.id] = value;
								applyFilter(column.id, value);
							}
						}}
					>
						<Select.Trigger class={cn('w-full', hasFilter && 'border-primary')}>
							{(column.filterOptions || column.options)?.find((opt) => opt.value === filterValues[column.id])?.label || `All ${column.header.toLowerCase()}`}
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="__clear__">
								<span class="text-muted-foreground">All {column.header.toLowerCase()}</span>
							</Select.Item>
							{#each column.filterOptions || column.options || [] as option}
								<Select.Item value={String(option.value)}>
									{option.label}
									{#if option.count !== undefined}
										<span class="ml-2 text-xs text-muted-foreground">({option.count})</span>
									{/if}
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				{:else if column.type === 'date' || column.type === 'datetime'}
					<!-- Date Picker -->
					<Popover.Root>
						<Popover.Trigger>
							{#snippet child({ props })}
								<Button
									{...props}
									variant="outline"
									class={cn(
										'w-full justify-start text-left font-normal',
										!filterValues[column.id] && 'text-muted-foreground',
										hasFilter && 'border-primary'
									)}
								>
									<CalendarIcon class="mr-2 h-4 w-4" />
									{#if filterValues[column.id]}
										{filterValues[column.id].toString()}
									{:else}
										Pick date
									{/if}
								</Button>
							{/snippet}
						</Popover.Trigger>
						<Popover.Content class="w-auto p-0" align="start">
							<Calendar
								type="single"
								value={filterValues[column.id]}
								onValueChange={(date: any) => {
									if (date) {
										filterValues[column.id] = date;
										applyFilter(column.id, date.toString());
									}
								}}
							/>
							{#if hasFilter}
								<div class="border-t p-2">
									<Button
										variant="ghost"
										size="sm"
										class="w-full"
										onclick={() => clearFilter(column.id)}
									>
										<X class="mr-1 h-3 w-3" />
										Clear
									</Button>
								</div>
							{/if}
						</Popover.Content>
					</Popover.Root>
				{:else if column.type === 'boolean'}
					<!-- Boolean Toggle -->
					<Select.Root
						type="single"
						value={filterValues[column.id] !== undefined ? String(filterValues[column.id]) : undefined}
						onValueChange={(value) => {
							if (value === '__clear__') {
								filterValues[column.id] = undefined;
								clearFilter(column.id);
							} else if (value) {
								filterValues[column.id] = value === 'true';
								applyFilter(column.id, value === 'true');
							}
						}}
					>
						<Select.Trigger class={cn('w-full', hasFilter && 'border-primary')}>
							{filterValues[column.id] !== undefined ? (filterValues[column.id] ? 'Yes' : 'No') : 'All'}
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="__clear__">
								<span class="text-muted-foreground">All</span>
							</Select.Item>
							<Select.Item value="true">Yes</Select.Item>
							<Select.Item value="false">No</Select.Item>
						</Select.Content>
					</Select.Root>
				{/if}
			</div>
		{/each}
	</div>
</div>

<script lang="ts">
	import { getContext, onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import * as Popover from '$lib/components/ui/popover';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Calendar } from '$lib/components/ui/calendar';
	import { Filter, X, Plus, Clock, Bookmark } from 'lucide-svelte';
	import type { ColumnDef, TableContext, FilterConfig } from './types';
	import { cn } from '$lib/utils';

	interface Props {
		moduleApiName: string;
	}

	let { moduleApiName }: Props = $props();

	const table = getContext<TableContext>('table');

	let open = $state(false);
	let activeTab = $state('quick');

	// Get filterable columns (top 6 for quick filters)
	const filterableColumns = $derived(table.columns.filter((col) => col.filterable !== false));
	const quickColumns = $derived(filterableColumns.slice(0, 6));

	// Track filter values - initialize from current table state
	let filterValues = $state<Record<string, any>>({});
	let initialized = $state(false);

	// Active filters map
	const activeFilters = $derived(
		table.state.filters.reduce(
			(acc, filter) => {
				acc[filter.field] = filter;
				return acc;
			},
			{} as Record<string, FilterConfig>
		)
	);

	// Initialize filter values from table state on mount
	onMount(() => {
		const newValues: Record<string, any> = {};
		table.state.filters.forEach((filter) => {
			newValues[filter.field] = filter.value;
		});
		filterValues = newValues;
		initialized = true;
	});

	// Sync filter values when popover opens (to pick up external changes)
	$effect(() => {
		if (open && initialized) {
			// When popover opens, sync values from table state
			const newValues: Record<string, any> = {};
			table.state.filters.forEach((filter) => {
				newValues[filter.field] = filter.value;
			});
			filterValues = newValues;
		}
	});

	const filterCount = $derived(table.state.filters.length);

	// Debounced filter application
	let debounceTimers: Record<string, ReturnType<typeof setTimeout>> = {};

	function applyFilter(columnId: string, value: any) {
		if (debounceTimers[columnId]) {
			clearTimeout(debounceTimers[columnId]);
		}

		debounceTimers[columnId] = setTimeout(() => {
			const column = filterableColumns.find((col) => col.id === columnId);
			if (!column) return;

			if (value === '' || value === null || value === undefined) {
				table.removeFilter(columnId);
				delete filterValues[columnId];
				return;
			}

			const operator = getDefaultOperator(column.type);
			table.updateFilter({
				field: columnId,
				operator: operator as any,
				value: value
			});
		}, 300);
	}

	function getDefaultOperator(columnType: string): string {
		switch (columnType) {
			case 'text':
			case 'email':
			case 'phone':
			case 'url':
				return 'contains';
			case 'select':
			case 'multiselect':
			case 'boolean':
				return 'equals';
			default:
				return 'equals';
		}
	}

	function clearFilter(columnId: string) {
		delete filterValues[columnId];
		table.removeFilter(columnId);
	}

	function clearAllFilters() {
		filterValues = {};
		table.clearFilters();
	}

	// Get column display name
	function getColumnName(columnId: string): string {
		const col = table.columns.find((c) => c.id === columnId);
		return col?.header || columnId;
	}

	// Format filter value for display
	function formatFilterValue(filter: FilterConfig): string {
		if (filter.value === null || filter.value === undefined) return '';
		if (typeof filter.value === 'boolean') return filter.value ? 'Yes' : 'No';
		if (Array.isArray(filter.value)) return filter.value.join(', ');
		return String(filter.value);
	}
</script>

<Popover.Root bind:open>
	<Popover.Trigger>
		<Button
			variant={filterCount > 0 ? 'default' : 'outline'}
			size="sm"
			class="relative"
			aria-label={filterCount > 0 ? `${filterCount} filters active` : 'Add filters'}
		>
			<Filter class="mr-2 h-4 w-4" />
			Filters
			{#if filterCount > 0}
				<Badge
					variant="secondary"
					class="ml-2 h-5 rounded-full bg-background px-1.5 text-xs text-foreground"
				>
					{filterCount}
				</Badge>
			{/if}
		</Button>
	</Popover.Trigger>

	<Popover.Content class="w-96 p-0" align="start">
		<div class="flex flex-col">
			<!-- Header -->
			<div class="flex items-center justify-between border-b px-4 py-3">
				<h4 class="font-semibold">Filters</h4>
				{#if filterCount > 0}
					<Button variant="ghost" size="sm" onclick={clearAllFilters} class="h-7 text-xs">
						Clear all
					</Button>
				{/if}
			</div>

			<!-- Active Filters (if any) -->
			{#if filterCount > 0}
				<div class="border-b px-4 py-3">
					<div class="mb-2 text-xs font-medium text-muted-foreground">Active filters</div>
					<div class="flex flex-wrap gap-1.5">
						{#each table.state.filters as filter}
							<Badge variant="secondary" class="pr-1">
								<span class="font-medium">{getColumnName(filter.field)}:</span>
								<span class="ml-1 max-w-[100px] truncate">{formatFilterValue(filter)}</span>
								<button
									type="button"
									onclick={() => clearFilter(filter.field)}
									class="ml-1 rounded-full p-0.5 hover:bg-muted"
									aria-label="Remove filter"
								>
									<X class="h-3 w-3" />
								</button>
							</Badge>
						{/each}
					</div>
				</div>
			{/if}

			<!-- Quick Filters -->
			<div class="max-h-80 overflow-y-auto px-4 py-3">
				<div class="mb-3 text-xs font-medium text-muted-foreground">Add filter</div>
				<div class="space-y-3">
					{#each quickColumns as column (column.id)}
						{@const hasFilter = !!activeFilters[column.id]}
						<div class="space-y-1.5">
							<label for="filter-{column.id}" class="flex items-center gap-1 text-sm font-medium">
								{column.header}
								{#if hasFilter}
									<span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
								{/if}
							</label>

							{#if column.type === 'text' || column.type === 'email' || column.type === 'phone' || column.type === 'url'}
								<div class="relative">
									<Input
										id="filter-{column.id}"
										type="text"
										placeholder="Contains..."
										value={filterValues[column.id] || ''}
										oninput={(e) => {
											filterValues[column.id] = e.currentTarget.value;
											applyFilter(column.id, e.currentTarget.value);
										}}
										class={cn('h-8 pr-8 text-sm', hasFilter && 'border-primary')}
									/>
									{#if hasFilter}
										<button
											type="button"
											onclick={() => clearFilter(column.id)}
											class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
											aria-label="Clear filter"
										>
											<X class="h-3 w-3" />
										</button>
									{/if}
								</div>
							{:else if column.type === 'number' || column.type === 'decimal' || column.type === 'currency'}
								<div class="relative">
									<Input
										id="filter-{column.id}"
										type="number"
										placeholder="Equals..."
										value={filterValues[column.id] || ''}
										oninput={(e) => {
											filterValues[column.id] = e.currentTarget.value;
											applyFilter(column.id, e.currentTarget.value);
										}}
										class={cn('h-8 pr-8 text-sm', hasFilter && 'border-primary')}
									/>
									{#if hasFilter}
										<button
											type="button"
											onclick={() => clearFilter(column.id)}
											class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
											aria-label="Clear filter"
										>
											<X class="h-3 w-3" />
										</button>
									{/if}
								</div>
							{:else if column.type === 'select' || column.type === 'multiselect'}
								<Select.Root
									type="single"
									value={filterValues[column.id] || ''}
									onValueChange={(val) => {
										if (val) {
											filterValues[column.id] = val;
											applyFilter(column.id, val);
										} else {
											clearFilter(column.id);
										}
									}}
								>
									<Select.Trigger class={cn('h-8 text-sm', hasFilter && 'border-primary')}>
										<span>
											{(column.filterOptions || [])?.find(
												(opt) => opt.value === filterValues[column.id]
											)?.label || 'Any'}
										</span>
									</Select.Trigger>
									<Select.Content>
										<Select.Item value="">
											<span class="text-muted-foreground">Any</span>
										</Select.Item>
										{#each column.filterOptions || [] as option}
											<Select.Item value={option.value}>
												{option.label}
												{#if option.count !== undefined}
													<span class="ml-auto text-xs text-muted-foreground">
														({option.count})
													</span>
												{/if}
											</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							{:else if column.type === 'boolean'}
								<Select.Root
									type="single"
									value={filterValues[column.id] !== undefined
										? String(filterValues[column.id])
										: ''}
									onValueChange={(val) => {
										if (val !== undefined && val !== '') {
											const boolValue = val === 'true';
											filterValues[column.id] = boolValue;
											applyFilter(column.id, boolValue);
										} else {
											clearFilter(column.id);
										}
									}}
								>
									<Select.Trigger class={cn('h-8 text-sm', hasFilter && 'border-primary')}>
										<span>
											{filterValues[column.id] !== undefined
												? filterValues[column.id]
													? 'Yes'
													: 'No'
												: 'Any'}
										</span>
									</Select.Trigger>
									<Select.Content>
										<Select.Item value="">
											<span class="text-muted-foreground">Any</span>
										</Select.Item>
										<Select.Item value="true">Yes</Select.Item>
										<Select.Item value="false">No</Select.Item>
									</Select.Content>
								</Select.Root>
							{:else}
								<Input
									id="filter-{column.id}"
									type="text"
									placeholder="Filter..."
									value={filterValues[column.id] || ''}
									oninput={(e) => {
										filterValues[column.id] = e.currentTarget.value;
										applyFilter(column.id, e.currentTarget.value);
									}}
									class={cn('h-8 text-sm', hasFilter && 'border-primary')}
								/>
							{/if}
						</div>
					{/each}

					{#if filterableColumns.length > 6}
						<p class="pt-2 text-center text-xs text-muted-foreground">
							{filterableColumns.length - 6} more columns available in advanced filters
						</p>
					{/if}
				</div>
			</div>
		</div>
	</Popover.Content>
</Popover.Root>

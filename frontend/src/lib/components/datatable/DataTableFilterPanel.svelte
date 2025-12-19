<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { X, RotateCcw } from 'lucide-svelte';
	import type { ColumnDef, TableContext, FilterConfig } from './types';
	import { cn } from '$lib/utils';
	import { slide } from 'svelte/transition';

	interface Props {
		open?: boolean;
		onClose?: () => void;
	}

	let { open = false, onClose }: Props = $props();

	const table = getContext<TableContext>('table');

	// Get all filterable columns
	const filterableColumns = $derived(table.columns.filter((col) => col.filterable !== false));

	// Track pending filter values (not applied yet)
	let pendingFilters = $state<Record<string, any>>({});

	// Sync pending filters when panel opens
	$effect(() => {
		if (open) {
			// Initialize pending filters from current table state
			const newPending: Record<string, any> = {};
			table.state.filters.forEach((filter) => {
				newPending[filter.field] = {
					operator: filter.operator,
					value: filter.value
				};
			});
			pendingFilters = newPending;
		}
	});

	// Get default operator for a column type
	function getDefaultOperator(columnType: string): string {
		switch (columnType) {
			case 'text':
			case 'email':
			case 'phone':
			case 'url':
				return 'contains';
			case 'select':
			case 'multiselect':
				return 'in';
			case 'boolean':
				return 'equals';
			case 'number':
			case 'decimal':
			case 'currency':
				return 'equals';
			case 'date':
			case 'datetime':
				return 'last_30_days';
			default:
				return 'equals';
		}
	}

	// Update a pending filter value
	function updatePendingFilter(columnId: string, value: any, operator?: string) {
		const column = filterableColumns.find((col) => col.id === columnId);
		if (!column) return;

		const currentOperator = operator || pendingFilters[columnId]?.operator || getDefaultOperator(column.type || 'text');

		// Handle empty values - remove from pending
		if (value === '' || value === null || value === undefined || (Array.isArray(value) && value.length === 0)) {
			const { [columnId]: _, ...rest } = pendingFilters;
			pendingFilters = rest;
			return;
		}

		pendingFilters = {
			...pendingFilters,
			[columnId]: {
				operator: currentOperator,
				value: value
			}
		};
	}

	// Clear a single pending filter
	function clearPendingFilter(columnId: string) {
		const { [columnId]: _, ...rest } = pendingFilters;
		pendingFilters = rest;
	}

	// Clear all pending filters
	function clearAllPending() {
		pendingFilters = {};
	}

	// Apply all pending filters
	function applyFilters() {
		// Build all filters at once
		const filters = Object.entries(pendingFilters)
			.filter(([_, filterData]) => filterData && filterData.value !== undefined && filterData.value !== '')
			.map(([field, filterData]) => ({
				field,
				operator: filterData.operator,
				value: filterData.value
			}));

		// Set all filters in one call (triggers single API request)
		table.setFilters(filters);

		onClose?.();
	}

	// Reset to current applied filters
	function resetFilters() {
		const newPending: Record<string, any> = {};
		table.state.filters.forEach((filter) => {
			newPending[filter.field] = {
				operator: filter.operator,
				value: filter.value
			};
		});
		pendingFilters = newPending;
	}

	// Count pending changes
	const pendingCount = $derived(Object.keys(pendingFilters).length);
	const appliedCount = $derived(table.state.filters.length);
	const hasChanges = $derived(() => {
		const pendingKeys = Object.keys(pendingFilters);
		const appliedKeys = table.state.filters.map(f => f.field);

		if (pendingKeys.length !== appliedKeys.length) return true;

		for (const key of pendingKeys) {
			const applied = table.state.filters.find(f => f.field === key);
			if (!applied) return true;
			const pending = pendingFilters[key];
			if (pending.operator !== applied.operator) return true;
			if (JSON.stringify(pending.value) !== JSON.stringify(applied.value)) return true;
		}
		return false;
	});

	// Date preset options
	const datePresets = [
		{ value: 'today', label: 'Today' },
		{ value: 'yesterday', label: 'Yesterday' },
		{ value: 'last_7_days', label: 'Last 7 days' },
		{ value: 'last_30_days', label: 'Last 30 days' },
		{ value: 'this_month', label: 'This month' },
		{ value: 'last_month', label: 'Last month' },
		{ value: 'is_empty', label: 'Is empty' },
		{ value: 'is_not_empty', label: 'Has value' }
	];
</script>

{#if open}
	<div
		class="rounded-lg border bg-card shadow-sm"
		transition:slide={{ duration: 200 }}
	>
		<!-- Header -->
		<div class="flex items-center justify-between border-b px-4 py-3">
			<div class="flex items-center gap-2">
				<h4 class="font-semibold">Filters</h4>
				{#if pendingCount > 0}
					<span class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
						{pendingCount}
					</span>
				{/if}
			</div>
			<div class="flex items-center gap-2">
				{#if pendingCount > 0}
					<Button variant="ghost" size="sm" onclick={clearAllPending} class="h-7 text-xs">
						Clear all
					</Button>
				{/if}
				<Button variant="ghost" size="icon" onclick={onClose} class="h-7 w-7">
					<X class="h-4 w-4" />
				</Button>
			</div>
		</div>

		<!-- Filter grid -->
		<div class="p-4">
			<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
				{#each filterableColumns as column (column.id)}
					{@const pendingFilter = pendingFilters[column.id]}
					{@const hasValue = pendingFilter && pendingFilter.value !== undefined && pendingFilter.value !== '' && (!Array.isArray(pendingFilter.value) || pendingFilter.value.length > 0)}

					<div class="space-y-1.5">
						<label
							for="filter-{column.id}"
							class="flex items-center gap-1.5 text-sm font-medium"
						>
							{column.header}
							{#if hasValue}
								<button
									type="button"
									onclick={() => clearPendingFilter(column.id)}
									class="rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
									aria-label="Clear filter"
								>
									<X class="h-3 w-3" />
								</button>
							{/if}
						</label>

						{#if column.type === 'text' || column.type === 'email' || column.type === 'phone' || column.type === 'url' || column.type === 'textarea'}
							<Input
								id="filter-{column.id}"
								type="text"
								placeholder="Contains..."
								value={pendingFilter?.value || ''}
								oninput={(e) => updatePendingFilter(column.id, e.currentTarget.value, 'contains')}
								class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}
							/>

						{:else if column.type === 'number' || column.type === 'decimal' || column.type === 'currency' || column.type === 'percent'}
							<Input
								id="filter-{column.id}"
								type="number"
								placeholder="Equals..."
								value={pendingFilter?.value || ''}
								oninput={(e) => updatePendingFilter(column.id, e.currentTarget.value ? parseFloat(e.currentTarget.value) : '', 'equals')}
								class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}
							/>

						{:else if column.type === 'select' || column.type === 'multiselect'}
							{@const options = column.filterOptions || column.options || []}
							{@const selectedValues: string[] = Array.isArray(pendingFilter?.value) ? (pendingFilter.value as (string | number | boolean)[]).map(v => String(v)) : (pendingFilter?.value != null && pendingFilter.value !== '' ? [String(pendingFilter.value)] : [])}
							{@const selectedLabels = options.filter((opt) => selectedValues.includes(String(opt.value))).map((opt) => opt.label)}

							<Select.Root
								type="multiple"
								value={selectedValues}
								onValueChange={(vals) => updatePendingFilter(column.id, vals && vals.length > 0 ? vals : '', 'in')}
							>
								<Select.Trigger class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}>
									<span class="truncate">
										{#if selectedLabels.length === 0}
											Any
										{:else if selectedLabels.length === 1}
											{selectedLabels[0]}
										{:else}
											{selectedLabels.length} selected
										{/if}
									</span>
								</Select.Trigger>
								<Select.Content>
									{#each options as option (option.value)}
										<Select.Item value={String(option.value)}>
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
								value={pendingFilter?.value !== undefined ? String(pendingFilter.value) : ''}
								onValueChange={(val) => {
									if (val !== undefined && val !== '') {
										updatePendingFilter(column.id, val === 'true', 'equals');
									} else {
										clearPendingFilter(column.id);
									}
								}}
							>
								<Select.Trigger class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}>
									<span>
										{pendingFilter?.value !== undefined
											? pendingFilter.value ? 'Yes' : 'No'
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

						{:else if column.type === 'date' || column.type === 'datetime'}
							<Select.Root
								type="single"
								value={pendingFilter?.operator || ''}
								onValueChange={(val) => {
									if (val) {
										updatePendingFilter(column.id, '', val);
									} else {
										clearPendingFilter(column.id);
									}
								}}
							>
								<Select.Trigger class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}>
									<span>
										{#if pendingFilter?.operator}
											{datePresets.find(p => p.value === pendingFilter.operator)?.label || 'Any'}
										{:else}
											Any
										{/if}
									</span>
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="">
										<span class="text-muted-foreground">Any</span>
									</Select.Item>
									{#each datePresets as preset}
										<Select.Item value={preset.value}>{preset.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>

						{:else if column.type === 'lookup' || column.type === 'user'}
							{@const options = column.filterOptions || []}
							{@const selectedValues: string[] = Array.isArray(pendingFilter?.value) ? (pendingFilter.value as (string | number | boolean)[]).map(v => String(v)) : (pendingFilter?.value != null && pendingFilter.value !== '' ? [String(pendingFilter.value)] : [])}
							{@const selectedLabels = options.filter((opt) => selectedValues.includes(String(opt.value))).map((opt) => opt.label)}

							<Select.Root
								type="multiple"
								value={selectedValues}
								onValueChange={(vals) => updatePendingFilter(column.id, vals && vals.length > 0 ? vals : '', 'in')}
							>
								<Select.Trigger class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}>
									<span class="truncate">
										{#if selectedLabels.length === 0}
											Any
										{:else if selectedLabels.length === 1}
											{selectedLabels[0]}
										{:else}
											{selectedLabels.length} selected
										{/if}
									</span>
								</Select.Trigger>
								<Select.Content>
									{#each options as option (option.value)}
										<Select.Item value={String(option.value)}>{option.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>

						{:else}
							<!-- Default text input for unknown types -->
							<Input
								id="filter-{column.id}"
								type="text"
								placeholder="Filter..."
								value={pendingFilter?.value || ''}
								oninput={(e) => updatePendingFilter(column.id, e.currentTarget.value)}
								class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}
							/>
						{/if}
					</div>
				{/each}
			</div>
		</div>

		<!-- Footer with Apply/Reset buttons -->
		<div class="flex items-center justify-between border-t bg-muted/30 px-4 py-3">
			<div class="text-sm text-muted-foreground">
				{#if pendingCount > 0}
					{pendingCount} filter{pendingCount === 1 ? '' : 's'} selected
				{:else}
					No filters selected
				{/if}
			</div>
			<div class="flex items-center gap-2">
				{#if hasChanges()}
					<Button variant="outline" size="sm" onclick={resetFilters}>
						<RotateCcw class="mr-1.5 h-3.5 w-3.5" />
						Reset
					</Button>
				{/if}
				<Button size="sm" onclick={applyFilters}>
					Apply Filters
				</Button>
			</div>
		</div>
	</div>
{/if}

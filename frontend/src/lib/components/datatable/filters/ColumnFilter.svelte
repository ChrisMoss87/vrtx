<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Popover from '$lib/components/ui/popover';
	import { Filter, X } from 'lucide-svelte';
	import type { ColumnDef, FilterConfig, FilterOption } from '../types';
	import { cn } from '$lib/utils';
	import TextFilter from './TextFilter.svelte';
	import NumberFilter from './NumberFilter.svelte';
	import DateFilter from './DateFilter.svelte';
	import SelectFilter from './SelectFilter.svelte';
	import BooleanFilter from './BooleanFilter.svelte';

	interface Props {
		column: ColumnDef;
		currentFilter?: FilterConfig;
		onApply: (filter: FilterConfig | null) => void;
	}

	let { column, currentFilter, onApply }: Props = $props();

	let open = $state(false);

	const hasActiveFilter = $derived(currentFilter !== undefined);

	function handleApply(filter: FilterConfig | null) {
		onApply(filter);
		open = false;
	}

	function handleClose() {
		open = false;
	}

	function handleClear(e: MouseEvent) {
		e.stopPropagation();
		onApply(null);
	}

	// Get filter options from column definition
	function getFilterOptions(): FilterOption[] {
		// First check filterOptions, then options, then meta.field.options
		if (column.filterOptions && column.filterOptions.length > 0) {
			return column.filterOptions;
		}
		if (column.options && column.options.length > 0) {
			return column.options;
		}
		// Check if options are in the meta.field object (from module fields)
		const fieldMeta = column.meta?.field;
		if (fieldMeta && typeof fieldMeta === 'object' && 'options' in fieldMeta && Array.isArray(fieldMeta.options)) {
			return fieldMeta.options.map((opt: { label?: string; value: string | number | boolean }) => ({
				label: String(opt.label ?? opt.value),
				value: opt.value
			}));
		}
		return [];
	}

	// Determine which filter component to use based on column type
	// Note: This is a $derived value to ensure reactivity when column.type changes
	const filterType = $derived.by(() => {
		const colType = column.type;
		switch (colType) {
			case 'text':
			case 'textarea':
			case 'email':
			case 'phone':
			case 'url':
				return 'text';

			case 'number':
			case 'decimal':
			case 'currency':
			case 'percent':
				return 'number';

			case 'date':
			case 'datetime':
			case 'time':
				return 'date';

			case 'select':
			case 'multiselect':
			case 'radio':
			case 'tags':
				return 'select';

			case 'boolean':
			case 'checkbox':
			case 'toggle':
				return 'boolean';

			case 'lookup':
				// Lookups should use select filter if options are available
				const options = getFilterOptions();
				return options.length > 0 ? 'select' : 'text';

			case 'actions':
				return 'none';

			default:
				return 'text';
		}
	});

	const filterOptions = $derived(getFilterOptions());

</script>

{#if column.filterable !== false && filterType !== 'none'}
	<Popover.Root bind:open>
		<Popover.Trigger>
			{#snippet child({ props })}
				<button
					{...props}
					type="button"
					class={cn(
						'flex h-6 w-6 items-center justify-center rounded-sm p-0 transition-colors',
						hasActiveFilter
							? 'text-primary hover:text-primary'
							: 'text-muted-foreground hover:text-foreground'
					)}
					aria-label="Filter {column.header}"
				>
					{#if hasActiveFilter}
						<div class="relative">
							<Filter class="h-3.5 w-3.5 fill-current" />
							<span
								role="button"
								tabindex={0}
								class="absolute -right-1 -top-1 flex h-3 w-3 cursor-pointer items-center justify-center rounded-full bg-destructive text-destructive-foreground hover:bg-destructive/80"
								onclick={handleClear}
								onkeydown={(e) => {
									if (e.key === 'Enter' || e.key === ' ') handleClear(e as unknown as MouseEvent);
								}}
								aria-label="Clear filter"
							>
								<X class="h-2 w-2" />
							</span>
						</div>
					{:else}
						<Filter class="h-3.5 w-3.5" />
					{/if}
				</button>
			{/snippet}
		</Popover.Trigger>
		<Popover.Content class="w-auto p-0" align="start">
			<div class="border-b px-3 py-2">
				<p class="text-sm font-medium">Filter: {column.header}</p>
				<p class="text-xs text-muted-foreground capitalize">{column.type} field</p>
			</div>

			{#if filterType === 'text'}
				<TextFilter
					field={column.id}
					initialValue={currentFilter}
					onApply={handleApply}
					onClose={handleClose}
				/>
			{:else if filterType === 'number'}
				<NumberFilter
					field={column.id}
					initialValue={currentFilter}
					onApply={handleApply}
					onClose={handleClose}
				/>
			{:else if filterType === 'date'}
				<DateFilter
					field={column.id}
					value={currentFilter ? { operator: currentFilter.operator, value: currentFilter.value as string | string[] } : undefined}
					onApply={(filter) => {
						if (filter) {
							handleApply({
								field: column.id,
								operator: filter.operator as FilterConfig['operator'],
								value: filter.value
							});
						} else {
							handleApply(null);
						}
					}}
					onClose={handleClose}
				/>
			{:else if filterType === 'select'}
				<SelectFilter
					field={column.id}
					options={filterOptions}
					initialValue={currentFilter}
					onApply={handleApply}
					onClose={handleClose}
				/>
			{:else if filterType === 'boolean'}
				<BooleanFilter
					field={column.id}
					initialValue={currentFilter}
					onApply={handleApply}
					onClose={handleClose}
				/>
			{/if}
		</Popover.Content>
	</Popover.Root>
{/if}

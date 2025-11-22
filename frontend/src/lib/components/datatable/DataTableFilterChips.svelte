<script lang="ts">
	import { getContext } from 'svelte';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import { X } from 'lucide-svelte';
	import type { TableContext, FilterConfig } from './types';

	const table = getContext<TableContext>('table');

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

		if (filter.operator === 'today' || filter.operator === 'yesterday' ||
		    filter.operator === 'last_7_days' || filter.operator === 'last_30_days' ||
		    filter.operator === 'this_month' || filter.operator === 'last_month') {
			return operator;
		}

		return `${operator} ${filter.value}`;
	}

	// Get column header by ID
	function getColumnHeader(columnId: string): string {
		const column = table.columns.find(c => c.id === columnId);
		return column?.header || columnId;
	}

	function removeFilter(field: string) {
		table.removeFilter(field);
	}

	function clearAllFilters() {
		table.clearFilters();
	}
</script>

{#if table.state.filters.length > 0}
	<div class="flex flex-wrap items-center gap-2">
		<span class="text-sm text-muted-foreground">Filters:</span>

		{#each table.state.filters as filter (filter.field)}
			<Badge variant="secondary" class="gap-1 pl-2 pr-1">
				<span class="font-normal">
					{getColumnHeader(filter.field)}: {formatFilterValue(filter)}
				</span>
				<Button
					variant="ghost"
					size="icon"
					class="h-4 w-4 p-0 hover:bg-transparent"
					onclick={() => removeFilter(filter.field)}
				>
					<X class="h-3 w-3" />
				</Button>
			</Badge>
		{/each}

		<Button
			variant="ghost"
			size="sm"
			onclick={clearAllFilters}
			class="h-6 text-xs"
		>
			Clear all
		</Button>
	</div>
{/if}

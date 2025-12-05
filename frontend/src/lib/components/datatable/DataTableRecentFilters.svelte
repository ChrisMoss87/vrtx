<script lang="ts">
	import { getContext, onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Clock, Trash2 } from 'lucide-svelte';
	import type { TableContext, FilterConfig } from './types';

	interface RecentFilterEntry {
		id: string;
		filters: FilterConfig[];
		timestamp: number;
		label: string;
	}

	interface Props {
		moduleApiName: string;
		maxRecent?: number;
	}

	let { moduleApiName, maxRecent = 10 }: Props = $props();

	const table = getContext<TableContext>('table');

	let recentFilters = $state<RecentFilterEntry[]>([]);

	// Load recent filters from localStorage on mount
	onMount(() => {
		loadRecentFilters();
	});

	function loadRecentFilters() {
		try {
			const stored = localStorage.getItem(`recent-filters-${moduleApiName}`);
			if (stored) {
				recentFilters = JSON.parse(stored);
			}
		} catch (error) {
			console.error('Failed to load recent filters:', error);
			recentFilters = [];
		}
	}

	function saveRecentFilters() {
		try {
			localStorage.setItem(`recent-filters-${moduleApiName}`, JSON.stringify(recentFilters));
		} catch (error) {
			console.error('Failed to save recent filters:', error);
		}
	}

	// Watch for filter changes and add to recent
	let previousFiltersString = '';
	$effect(() => {
		const currentFiltersString = JSON.stringify(table.state.filters);

		// Only add if filters changed and are not empty
		if (currentFiltersString !== previousFiltersString && table.state.filters.length > 0) {
			addToRecent(table.state.filters);
			previousFiltersString = currentFiltersString;
		}
	});

	function addToRecent(filters: FilterConfig[]) {
		const label = generateFilterLabel(filters);
		const id = generateFilterId(filters);

		// Check if this exact filter combination already exists
		const existingIndex = recentFilters.findIndex((entry) => entry.id === id);

		if (existingIndex !== -1) {
			// Update timestamp of existing entry
			recentFilters[existingIndex].timestamp = Date.now();
			// Move to front
			const [existing] = recentFilters.splice(existingIndex, 1);
			recentFilters = [existing, ...recentFilters];
		} else {
			// Add new entry
			const newEntry: RecentFilterEntry = {
				id,
				filters: [...filters],
				timestamp: Date.now(),
				label
			};

			recentFilters = [newEntry, ...recentFilters].slice(0, maxRecent);
		}

		saveRecentFilters();
	}

	function generateFilterId(filters: FilterConfig[]): string {
		// Create a deterministic ID from filter combination
		return filters
			.map((f) => `${f.field}:${f.operator}:${JSON.stringify(f.value)}`)
			.sort()
			.join('|');
	}

	function generateFilterLabel(filters: FilterConfig[]): string {
		if (filters.length === 1) {
			const filter = filters[0];
			const column = table.columns.find((col) => col.id === filter.field);
			const columnName = column?.header || filter.field;
			return `${columnName} ${formatOperator(filter.operator)} ${formatValue(filter.value)}`;
		} else {
			return `${filters.length} filters applied`;
		}
	}

	function formatOperator(operator: string): string {
		const operatorLabels: Record<string, string> = {
			equals: '=',
			not_equals: '≠',
			contains: 'contains',
			not_contains: 'does not contain',
			starts_with: 'starts with',
			ends_with: 'ends with',
			greater_than: '>',
			greater_than_or_equal: '≥',
			less_than: '<',
			less_than_or_equal: '≤',
			between: 'between',
			in: 'in',
			not_in: 'not in',
			is_empty: 'is empty',
			is_not_empty: 'is not empty'
		};
		return operatorLabels[operator] || operator;
	}

	function formatValue(value: any): string {
		if (value === null || value === undefined) return '';
		if (typeof value === 'boolean') return value ? 'Yes' : 'No';
		if (Array.isArray(value)) return value.join(', ');
		if (typeof value === 'object') return JSON.stringify(value);
		return String(value);
	}

	function applyRecentFilter(entry: RecentFilterEntry) {
		// Clear existing filters
		table.clearFilters();

		// Apply filters from recent entry
		entry.filters.forEach((filter) => {
			table.updateFilter(filter);
		});
	}

	function removeRecentFilter(id: string, event: Event) {
		event.stopPropagation();
		recentFilters = recentFilters.filter((entry) => entry.id !== id);
		saveRecentFilters();
	}

	function clearAllRecent() {
		recentFilters = [];
		saveRecentFilters();
	}

	function getRelativeTime(timestamp: number): string {
		const now = Date.now();
		const diff = now - timestamp;

		const minutes = Math.floor(diff / 60000);
		const hours = Math.floor(diff / 3600000);
		const days = Math.floor(diff / 86400000);

		if (minutes < 1) return 'Just now';
		if (minutes < 60) return `${minutes}m ago`;
		if (hours < 24) return `${hours}h ago`;
		if (days < 7) return `${days}d ago`;
		return new Date(timestamp).toLocaleDateString();
	}
</script>

{#if recentFilters.length > 0}
	<DropdownMenu.Root>
		<DropdownMenu.Trigger>
			{#snippet child({ props })}
				<Button {...props} variant="outline" size="sm">
					<Clock class="mr-1 h-3 w-3" />
					Recent
					<Badge variant="secondary" class="ml-1 h-4 px-1 text-xs">
						{recentFilters.length}
					</Badge>
				</Button>
			{/snippet}
		</DropdownMenu.Trigger>
		<DropdownMenu.Content class="w-96" align="start">
			<div class="flex items-center justify-between border-b px-2 py-1.5">
				<DropdownMenu.Label class="p-0">Recent Filters</DropdownMenu.Label>
				{#if recentFilters.length > 0}
					<Button variant="ghost" size="sm" class="h-6 text-xs" onclick={clearAllRecent}>
						Clear all
					</Button>
				{/if}
			</div>
			{#each recentFilters as entry (entry.id)}
				<DropdownMenu.Item
					onclick={() => applyRecentFilter(entry)}
					class="flex items-start justify-between gap-2 py-2"
				>
					<div class="min-w-0 flex-1">
						<div class="truncate font-medium">{entry.label}</div>
						<div class="mt-1 flex items-center gap-2">
							<Badge variant="secondary" class="h-4 px-1 text-xs">
								{entry.filters.length} filter{entry.filters.length === 1 ? '' : 's'}
							</Badge>
							<span class="text-xs text-muted-foreground">
								{getRelativeTime(entry.timestamp)}
							</span>
						</div>
						<!-- Show filter details if more than one -->
						{#if entry.filters.length > 1}
							<div class="mt-1 space-y-0.5">
								{#each entry.filters.slice(0, 3) as filter}
									{@const column = table.columns.find((col) => col.id === filter.field)}
									<div class="truncate text-xs text-muted-foreground">
										• {column?.header || filter.field}: {formatOperator(filter.operator)}
										{formatValue(filter.value)}
									</div>
								{/each}
								{#if entry.filters.length > 3}
									<div class="text-xs text-muted-foreground italic">
										+{entry.filters.length - 3} more...
									</div>
								{/if}
							</div>
						{/if}
					</div>
					<Button
						variant="ghost"
						size="sm"
						class="h-6 w-6 flex-shrink-0 p-0"
						onclick={(e) => removeRecentFilter(entry.id, e)}
						aria-label="Remove from recent"
					>
						<Trash2 class="h-3 w-3" />
					</Button>
				</DropdownMenu.Item>
			{/each}
		</DropdownMenu.Content>
	</DropdownMenu.Root>
{/if}

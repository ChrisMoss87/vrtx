<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import * as Popover from '$lib/components/ui/popover';
	import { Badge } from '$lib/components/ui/badge';
	import { Calendar, User, Filter, X, Save, Clock } from 'lucide-svelte';
	import {
		setGlobalDateRange,
		setSelectedOwner,
		clearGlobalFilters,
		useGlobalFilters,
		getGlobalFilters
	} from '$lib/stores/dashboardFilterContext.svelte';
	import type { DateRangeConfig, FilterConfig } from '$lib/types/filters';

	interface FilterPreset {
		id: string;
		name: string;
		filters: FilterConfig[];
		dateRange?: DateRangeConfig;
	}

	interface Props {
		users?: { id: number; name: string }[];
		onFiltersChange?: () => void;
	}

	let { users = [], onFiltersChange }: Props = $props();

	const filterState = useGlobalFilters();

	let selectedDateRange = $state<string>('all_time');
	let showSavePreset = $state(false);
	let presetName = $state('');

	// Saved presets from localStorage
	let savedPresets = $state<FilterPreset[]>([]);

	// Load presets on mount
	$effect(() => {
		const stored = localStorage.getItem('dashboard_filter_presets');
		if (stored) {
			try {
				savedPresets = JSON.parse(stored);
			} catch {
				savedPresets = [];
			}
		}
	});

	const dateRangeOptions = [
		{ value: 'all_time', label: 'All Time' },
		{ value: 'today', label: 'Today' },
		{ value: 'yesterday', label: 'Yesterday' },
		{ value: 'this_week', label: 'This Week' },
		{ value: 'last_week', label: 'Last Week' },
		{ value: 'this_month', label: 'This Month' },
		{ value: 'last_month', label: 'Last Month' },
		{ value: 'this_quarter', label: 'This Quarter' },
		{ value: 'last_quarter', label: 'Last Quarter' },
		{ value: 'this_year', label: 'This Year' },
		{ value: 'last_year', label: 'Last Year' }
	];

	function handleDateRangeChange(value: string | undefined) {
		if (!value) return;

		selectedDateRange = value;

		if (value === 'all_time') {
			setGlobalDateRange(null);
		} else {
			setGlobalDateRange({ type: value as DateRangeConfig['type'] });
		}

		onFiltersChange?.();
	}

	function handleOwnerChange(value: string | undefined) {
		if (!value) return;

		if (value === 'all') {
			setSelectedOwner(null);
		} else {
			setSelectedOwner(parseInt(value, 10));
		}

		onFiltersChange?.();
	}

	function handleClearAll() {
		selectedDateRange = 'all_time';
		clearGlobalFilters();
		onFiltersChange?.();
	}

	function savePreset() {
		if (!presetName.trim()) return;

		const preset: FilterPreset = {
			id: crypto.randomUUID(),
			name: presetName.trim(),
			filters: getGlobalFilters(),
			dateRange: filterState.dateRange || undefined
		};

		savedPresets = [...savedPresets, preset];
		localStorage.setItem('dashboard_filter_presets', JSON.stringify(savedPresets));

		presetName = '';
		showSavePreset = false;
	}

	function loadPreset(preset: FilterPreset) {
		if (preset.dateRange) {
			setGlobalDateRange(preset.dateRange);
			selectedDateRange = preset.dateRange.type || 'all_time';
		} else {
			setGlobalDateRange(null);
			selectedDateRange = 'all_time';
		}

		// Note: Additional filters would need to be applied here
		onFiltersChange?.();
	}

	function deletePreset(presetId: string) {
		savedPresets = savedPresets.filter((p) => p.id !== presetId);
		localStorage.setItem('dashboard_filter_presets', JSON.stringify(savedPresets));
	}

	const activeFilterCount = $derived(
		(filterState.filters?.length || 0) + (filterState.dateRange ? 1 : 0)
	);
</script>

<div class="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/30 px-4 py-3">
	<!-- Date Range Filter -->
	<div class="flex items-center gap-2">
		<Calendar class="h-4 w-4 text-muted-foreground" />
		<Select.Root
			type="single"
			value={selectedDateRange}
			onValueChange={handleDateRangeChange}
		>
			<Select.Trigger class="h-8 w-36">
				<span class="truncate">
					{dateRangeOptions.find((o) => o.value === selectedDateRange)?.label || 'Select...'}
				</span>
			</Select.Trigger>
			<Select.Content>
				{#each dateRangeOptions as option}
					<Select.Item value={option.value}>{option.label}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Owner Filter -->
	{#if users.length > 0}
		<div class="flex items-center gap-2">
			<User class="h-4 w-4 text-muted-foreground" />
			<Select.Root
				type="single"
				value={filterState.selectedOwnerId?.toString() || 'all'}
				onValueChange={handleOwnerChange}
			>
				<Select.Trigger class="h-8 w-40">
					<span class="truncate">
						{filterState.selectedOwnerId
							? users.find((u) => u.id === filterState.selectedOwnerId)?.name || 'Selected'
							: 'All Owners'}
					</span>
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="all">All Owners</Select.Item>
					{#each users as user}
						<Select.Item value={user.id.toString()}>{user.name}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>
	{/if}

	<!-- Saved Presets -->
	{#if savedPresets.length > 0}
		<Popover.Root>
			<Popover.Trigger>
				{#snippet child({ props })}
					<Button variant="outline" size="sm" class="h-8 gap-1" {...props}>
						<Clock class="h-3.5 w-3.5" />
						Presets
					</Button>
				{/snippet}
			</Popover.Trigger>
			<Popover.Content class="w-56 p-2">
				<div class="space-y-1">
					{#each savedPresets as preset}
						<div class="flex items-center justify-between rounded px-2 py-1 hover:bg-muted">
							<button
								type="button"
								class="flex-1 text-left text-sm"
								onclick={() => loadPreset(preset)}
							>
								{preset.name}
							</button>
							<Button
								variant="ghost"
								size="sm"
								class="h-6 w-6 p-0"
								onclick={() => deletePreset(preset.id)}
							>
								<X class="h-3 w-3" />
							</Button>
						</div>
					{/each}
				</div>
			</Popover.Content>
		</Popover.Root>
	{/if}

	<!-- Active Filters Indicator -->
	{#if activeFilterCount > 0}
		<Badge variant="secondary" class="gap-1">
			<Filter class="h-3 w-3" />
			{activeFilterCount} active
		</Badge>
	{/if}

	<!-- Spacer -->
	<div class="flex-1"></div>

	<!-- Actions -->
	{#if filterState.hasActiveFilters}
		<Popover.Root bind:open={showSavePreset}>
			<Popover.Trigger>
				{#snippet child({ props })}
					<Button variant="outline" size="sm" class="h-8 gap-1" {...props}>
						<Save class="h-3.5 w-3.5" />
						Save
					</Button>
				{/snippet}
			</Popover.Trigger>
			<Popover.Content class="w-64 p-3">
				<div class="space-y-2">
					<label class="text-sm font-medium">Preset Name</label>
					<input
						type="text"
						bind:value={presetName}
						class="w-full rounded border px-2 py-1 text-sm"
						placeholder="My Filter Preset"
					/>
					<Button size="sm" class="w-full" onclick={savePreset}>
						Save Preset
					</Button>
				</div>
			</Popover.Content>
		</Popover.Root>

		<Button
			variant="ghost"
			size="sm"
			class="h-8 gap-1 text-muted-foreground"
			onclick={handleClearAll}
		>
			<X class="h-3.5 w-3.5" />
			Clear All
		</Button>
	{/if}
</div>

<script lang="ts">
	import { onMount } from 'svelte';
	import type { ForecastSummary, ForecastDeal, PeriodType, ForecastCategory } from '$lib/api/forecasts';
	import * as forecastApi from '$lib/api/forecasts';
	import { modulesApi, type Module } from '$lib/api/modules';
	import { ForecastSummaryCards, ForecastDealsTable } from '$lib/components/forecast';
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Card from '$lib/components/ui/card';
	import { PluginGate } from '$lib/components/billing';
	import { toast } from 'svelte-sonner';
	import TrendingUp from 'lucide-svelte/icons/trending-up';
	import Calendar from 'lucide-svelte/icons/calendar';
	import Settings from 'lucide-svelte/icons/settings';
	import RefreshCw from 'lucide-svelte/icons/refresh-cw';

	// State
	let modules = $state<Module[]>([]);
	let selectedModuleApiName = $state<string | null>(null);
	let periodType = $state<PeriodType>('month');
	let summary = $state<ForecastSummary | null>(null);
	let deals = $state<ForecastDeal[]>([]);
	let loading = $state(true);
	let activeTab = $state<'all' | ForecastCategory>('all');

	// Period options
	const periodOptions = [
		{ value: 'week', label: 'This Week' },
		{ value: 'month', label: 'This Month' },
		{ value: 'quarter', label: 'This Quarter' },
		{ value: 'year', label: 'This Year' }
	];

	// Load initial data
	async function loadModules() {
		try {
			// Get modules that could have deals/opportunities (have currency fields)
			modules = await modulesApi.getAll();
			// Default to Deals module if it exists
			const dealsModule = modules.find((m) => m.api_name === 'Deals');
			if (dealsModule) {
				selectedModuleApiName = dealsModule.api_name;
			} else if (modules.length > 0) {
				selectedModuleApiName = modules[0].api_name;
			}
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Failed to load modules');
		}
	}

	async function loadForecastData() {
		if (!selectedModuleApiName) return;

		loading = true;
		try {
			const [summaryData, dealsData] = await Promise.all([
				forecastApi.getForecastSummary({
					module_api_name: selectedModuleApiName,
					period_type: periodType
				}),
				forecastApi.getForecastDeals({
					module_api_name: selectedModuleApiName,
					period_type: periodType
				})
			]);

			summary = summaryData;
			deals = dealsData;
		} catch (error) {
			console.error('Failed to load forecast data:', error);
			toast.error('Failed to load forecast data');
		} finally {
			loading = false;
		}
	}

	function handleModuleChange(value: string) {
		selectedModuleApiName = value;
	}

	function handlePeriodChange(value: string) {
		periodType = value as PeriodType;
	}

	function handleDealUpdate(updatedDeal: ForecastDeal) {
		deals = deals.map((d) => (d.id === updatedDeal.id ? updatedDeal : d));
		// Reload summary to reflect changes
		loadForecastData();
	}

	// Reload when module or period changes
	$effect(() => {
		if (selectedModuleApiName && periodType) {
			loadForecastData();
		}
	});

	onMount(() => {
		loadModules();
	});

	// Get selected module name
	const selectedModule = $derived(modules.find((m) => m.api_name === selectedModuleApiName));

	// Get period label
	const periodLabel = $derived(periodOptions.find((p) => p.value === periodType)?.label || 'This Month');

	// Filter deals by tab
	const filteredDeals = $derived(
		activeTab === 'all' ? deals : deals.filter((d) => d.forecast_category === activeTab)
	);

	// Tab counts
	const tabCounts = $derived({
		all: deals.length,
		commit: deals.filter((d) => d.forecast_category === 'commit').length,
		best_case: deals.filter((d) => d.forecast_category === 'best_case').length,
		pipeline: deals.filter((d) => d.forecast_category === 'pipeline' || !d.forecast_category).length,
		omitted: deals.filter((d) => d.forecast_category === 'omitted').length
	});
</script>

<svelte:head>
	<title>Forecasts | VRTX</title>
</svelte:head>

<PluginGate
	plugin="sales-forecasting"
	title="Sales Forecasting"
	description="Track revenue projections, manage quotas, and analyze pipeline health with advanced forecasting tools."
>
<div class="container mx-auto py-6 space-y-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold tracking-tight">Sales Forecast</h1>
			<p class="text-muted-foreground">
				Track revenue projections and quota attainment
			</p>
		</div>
		<div class="flex items-center gap-2">
			<Button variant="outline" href="/forecasts/quotas">
				<Settings class="mr-2 h-4 w-4" />
				Manage Quotas
			</Button>
		</div>
	</div>

	<!-- Filters -->
	<div class="flex items-center gap-4">
		<div class="flex items-center gap-2">
			<span class="text-sm text-muted-foreground">Module:</span>
			<Select.Root
				type="single"
				value={selectedModuleApiName || ''}
				onValueChange={handleModuleChange}
			>
				<Select.Trigger class="w-[200px]">
					{selectedModule?.name || 'Select module'}
				</Select.Trigger>
				<Select.Content>
					{#each modules as mod}
						<Select.Item value={mod.api_name}>
							{mod.name}
						</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>

		<div class="flex items-center gap-2">
			<Calendar class="h-4 w-4 text-muted-foreground" />
			<Select.Root
				type="single"
				value={periodType}
				onValueChange={handlePeriodChange}
			>
				<Select.Trigger class="w-[150px]">
					{periodLabel}
				</Select.Trigger>
				<Select.Content>
					{#each periodOptions as option}
						<Select.Item value={option.value}>
							{option.label}
						</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>

		<Button variant="ghost" size="icon" onclick={() => loadForecastData()} disabled={loading}>
			<RefreshCw class="h-4 w-4 {loading ? 'animate-spin' : ''}" />
		</Button>
	</div>

	{#if loading && !summary}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading forecast data...</div>
		</div>
	{:else if !selectedModuleApiName}
		<Card.Root>
			<Card.Content class="py-12 text-center">
				<TrendingUp class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
				<h3 class="text-lg font-semibold">No Module Selected</h3>
				<p class="text-muted-foreground">
					Select a module to view forecasts
				</p>
			</Card.Content>
		</Card.Root>
	{:else if modules.length === 0}
		<Card.Root>
			<Card.Content class="py-12 text-center">
				<TrendingUp class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
				<h3 class="text-lg font-semibold">No Modules Found</h3>
				<p class="text-muted-foreground">
					Create a module first to start forecasting
				</p>
			</Card.Content>
		</Card.Root>
	{:else if summary}
		<!-- Summary Cards -->
		<ForecastSummaryCards {summary} />

		<!-- Deals Table -->
		<Card.Root>
			<Card.Header>
				<Card.Title>Deals</Card.Title>
				<Card.Description>
					Manage forecast categories for your deals
				</Card.Description>
			</Card.Header>
			<Card.Content>
				<Tabs.Root bind:value={activeTab}>
					<Tabs.List class="mb-4">
						<Tabs.Trigger value="all">
							All ({tabCounts.all})
						</Tabs.Trigger>
						<Tabs.Trigger value="commit">
							Commit ({tabCounts.commit})
						</Tabs.Trigger>
						<Tabs.Trigger value="best_case">
							Best Case ({tabCounts.best_case})
						</Tabs.Trigger>
						<Tabs.Trigger value="pipeline">
							Pipeline ({tabCounts.pipeline})
						</Tabs.Trigger>
						<Tabs.Trigger value="omitted">
							Omitted ({tabCounts.omitted})
						</Tabs.Trigger>
					</Tabs.List>

					<ForecastDealsTable
						deals={filteredDeals}
						showCategory={activeTab === 'all'}
						onDealUpdate={handleDealUpdate}
					/>
				</Tabs.Root>
			</Card.Content>
		</Card.Root>
	{/if}
</div>
</PluginGate>

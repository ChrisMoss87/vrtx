<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { Input } from '$lib/components/ui/input';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Dialog from '$lib/components/ui/dialog';
	import {
		getScenario,
		deleteScenario,
		duplicateScenario,
		getScenarioDeals,
		updateScenarioDeal,
		commitDeal,
		resetDeal,
		getGapAnalysis as fetchGapAnalysis,
		type Scenario,
		type ScenarioDeal,
		type GapAnalysis
	} from '$lib/api/scenarios';
	import {
		ScenarioSummary,
		GapAnalysis as GapAnalysisPanel,
		DealEditorPanel,
		ScenarioKanban
	} from '$lib/components/scenario-planner';
	import { toast } from 'svelte-sonner';
	import {
		ArrowLeft,
		Target,
		TrendingUp,
		TrendingDown,
		DollarSign,
		BarChart3,
		Settings,
		Copy,
		Trash2,
		Share2,
		Edit,
		CheckCircle,
		XCircle,
		RefreshCw
	} from 'lucide-svelte';

	const scenarioId = $derived(parseInt($page.params.id ?? '0'));

	// State
	let scenario = $state<Scenario | null>(null);
	let deals = $state<ScenarioDeal[]>([]);
	let gapAnalysis = $state<GapAnalysis | null>(null);
	let activeTab = $state('overview');
	let selectedDeal = $state<ScenarioDeal | null>(null);
	let showDealEditor = $state(false);
	let showDeleteConfirm = $state(false);
	let showGapAnalysis = $state(false);

	// Loading states
	let loading = $state(true);
	let loadingDeals = $state(true);
	let loadingGap = $state(true);
	let saving = $state(false);

	async function loadScenario() {
		loading = true;
		try {
			scenario = await getScenario(scenarioId);
		} catch (e) {
			toast.error('Failed to load scenario');
			goto('/forecasts/scenarios');
		} finally {
			loading = false;
		}
	}

	async function loadDeals() {
		loadingDeals = true;
		try {
			deals = await getScenarioDeals(scenarioId);
		} catch (e) {
			toast.error('Failed to load deals');
		} finally {
			loadingDeals = false;
		}
	}

	async function loadGapAnalysis() {
		loadingGap = true;
		try {
			if (scenario?.target_amount && scenario.period_start && scenario.period_end) {
				gapAnalysis = await fetchGapAnalysis(scenario.target_amount, scenario.period_start, scenario.period_end);
			} else {
				gapAnalysis = null;
			}
		} catch (e) {
			// Gap analysis may not be available
			gapAnalysis = null;
		} finally {
			loadingGap = false;
		}
	}

	async function handleDuplicate() {
		saving = true;
		try {
			const newScenario = await duplicateScenario(scenarioId);
			toast.success('Scenario duplicated');
			goto(`/forecasts/scenarios/${newScenario.id}`);
		} catch (e) {
			toast.error('Failed to duplicate scenario');
		} finally {
			saving = false;
		}
	}

	async function handleDelete() {
		saving = true;
		try {
			await deleteScenario(scenarioId);
			toast.success('Scenario deleted');
			goto('/forecasts/scenarios');
		} catch (e) {
			toast.error('Failed to delete scenario');
		} finally {
			saving = false;
			showDeleteConfirm = false;
		}
	}

	async function handleDealUpdate(deal: ScenarioDeal, updates: Partial<ScenarioDeal>) {
		try {
			// Extract only the fields that can be updated via API
			const apiUpdates: {
				amount?: number;
				probability?: number;
				close_date?: string;
				stage_id?: number;
				is_committed?: boolean;
				is_excluded?: boolean;
				notes?: string;
			} = {};
			if (updates.amount !== undefined) apiUpdates.amount = updates.amount;
			if (updates.probability !== undefined) apiUpdates.probability = updates.probability;
			if (updates.close_date !== undefined && updates.close_date !== null) apiUpdates.close_date = updates.close_date;
			if (updates.stage_id !== undefined && updates.stage_id !== null) apiUpdates.stage_id = updates.stage_id;
			if (updates.is_committed !== undefined) apiUpdates.is_committed = updates.is_committed;
			if (updates.is_excluded !== undefined) apiUpdates.is_excluded = updates.is_excluded;
			if (updates.notes !== undefined && updates.notes !== null) apiUpdates.notes = updates.notes;

			await updateScenarioDeal(scenarioId, deal.id, apiUpdates);
			toast.success('Deal updated');
			await loadDeals();
			await loadScenario();
		} catch (e) {
			toast.error('Failed to update deal');
		}
	}

	async function handleCommitDeal(deal: ScenarioDeal) {
		try {
			await commitDeal(scenarioId, deal.id);
			toast.success('Changes committed to actual deal');
			await loadDeals();
		} catch (e) {
			toast.error('Failed to commit changes');
		}
	}

	async function handleResetDeal(deal: ScenarioDeal) {
		try {
			await resetDeal(scenarioId, deal.id);
			toast.success('Deal reset to original values');
			await loadDeals();
			await loadScenario();
		} catch (e) {
			toast.error('Failed to reset deal');
		}
	}

	function formatCurrency(amount: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(amount);
	}

	function getScenarioTypeColor(type: string): string {
		switch (type) {
			case 'best_case':
				return 'bg-green-500/10 text-green-700 border-green-500/20';
			case 'worst_case':
				return 'bg-red-500/10 text-red-700 border-red-500/20';
			case 'target_hit':
				return 'bg-blue-500/10 text-blue-700 border-blue-500/20';
			case 'current':
				return 'bg-gray-500/10 text-gray-700 border-gray-500/20';
			default:
				return 'bg-purple-500/10 text-purple-700 border-purple-500/20';
		}
	}

	function getScenarioTypeLabel(type: string): string {
		switch (type) {
			case 'best_case':
				return 'Best Case';
			case 'worst_case':
				return 'Worst Case';
			case 'target_hit':
				return 'Target Hit';
			case 'current':
				return 'Current';
			default:
				return 'Custom';
		}
	}

	$effect(() => {
		loadScenario();
		loadDeals();
		loadGapAnalysis();
	});
</script>

<svelte:head>
	<title>{scenario?.name ?? 'Scenario'} | Scenarios | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<!-- Header -->
	<div class="flex items-center gap-4">
		<Button variant="ghost" size="icon" onclick={() => goto('/forecasts/scenarios')}>
			<ArrowLeft class="h-5 w-5" />
		</Button>
		<div class="flex-1">
			{#if loading}
				<Skeleton class="h-8 w-64 mb-2" />
				<Skeleton class="h-4 w-48" />
			{:else if scenario}
				<div class="flex items-center gap-3">
					<h1 class="text-2xl font-bold">{scenario.name}</h1>
					<Badge class={getScenarioTypeColor(scenario.scenario_type)}>
						{getScenarioTypeLabel(scenario.scenario_type)}
					</Badge>
					{#if scenario.is_baseline}
						<Badge variant="outline">Baseline</Badge>
					{/if}
					{#if scenario.is_shared}
						<Badge variant="secondary">Shared</Badge>
					{/if}
				</div>
				{#if scenario.description}
					<p class="text-muted-foreground">{scenario.description}</p>
				{/if}
			{/if}
		</div>
		<div class="flex items-center gap-2">
			<Button variant="outline" onclick={handleDuplicate} disabled={saving}>
				<Copy class="mr-2 h-4 w-4" />
				Duplicate
			</Button>
			<Button variant="outline" class="text-destructive" onclick={() => (showDeleteConfirm = true)}>
				<Trash2 class="mr-2 h-4 w-4" />
				Delete
			</Button>
		</div>
	</div>

	<!-- Summary Cards -->
	{#if scenario}
		<div class="grid grid-cols-2 md:grid-cols-5 gap-4">
			<Card>
				<CardContent class="pt-4">
					<div class="flex items-center gap-2">
						<DollarSign class="h-5 w-5 text-green-500" />
						<div>
							<div class="text-2xl font-bold">{formatCurrency(scenario.total_weighted)}</div>
							<div class="text-sm text-muted-foreground">Weighted Total</div>
						</div>
					</div>
				</CardContent>
			</Card>
			<Card>
				<CardContent class="pt-4">
					<div class="flex items-center gap-2">
						<BarChart3 class="h-5 w-5 text-blue-500" />
						<div>
							<div class="text-2xl font-bold">{formatCurrency(scenario.total_unweighted)}</div>
							<div class="text-sm text-muted-foreground">Unweighted Total</div>
						</div>
					</div>
				</CardContent>
			</Card>
			<Card>
				<CardContent class="pt-4">
					<div class="flex items-center gap-2">
						<Target class="h-5 w-5 text-purple-500" />
						<div>
							<div class="text-2xl font-bold">{scenario.deal_count}</div>
							<div class="text-sm text-muted-foreground">Deals</div>
						</div>
					</div>
				</CardContent>
			</Card>
			{#if scenario.target_amount}
				<Card>
					<CardContent class="pt-4">
						<div class="flex items-center gap-2">
							<Target class="h-5 w-5 text-orange-500" />
							<div>
								<div class="text-2xl font-bold">{formatCurrency(scenario.target_amount)}</div>
								<div class="text-sm text-muted-foreground">Target</div>
							</div>
						</div>
					</CardContent>
				</Card>
				<Card>
					<CardContent class="pt-4">
						{@const gap = scenario.target_amount - scenario.total_weighted}
						<div class="flex items-center gap-2">
							{#if gap > 0}
								<TrendingDown class="h-5 w-5 text-red-500" />
							{:else}
								<TrendingUp class="h-5 w-5 text-green-500" />
							{/if}
							<div>
								<div class="text-2xl font-bold {gap > 0 ? 'text-red-600' : 'text-green-600'}">
									{formatCurrency(Math.abs(gap))}
								</div>
								<div class="text-sm text-muted-foreground">
									{gap > 0 ? 'Gap to Target' : 'Over Target'}
								</div>
							</div>
						</div>
					</CardContent>
				</Card>
			{/if}
		</div>
	{/if}

	<Tabs.Root bind:value={activeTab}>
		<Tabs.List>
			<Tabs.Trigger value="overview">Overview</Tabs.Trigger>
			<Tabs.Trigger value="deals">Deals ({deals.length})</Tabs.Trigger>
			<Tabs.Trigger value="kanban">Kanban</Tabs.Trigger>
			{#if gapAnalysis}
				<Tabs.Trigger value="gap">Gap Analysis</Tabs.Trigger>
			{/if}
		</Tabs.List>

		<Tabs.Content value="overview" class="mt-4">
			{#if scenario?.metrics}
				<ScenarioSummary metrics={scenario.metrics} targetAmount={scenario.target_amount} />
			{:else}
				<Card>
					<CardContent class="py-12 text-center text-muted-foreground">
						<BarChart3 class="h-12 w-12 mx-auto mb-4 opacity-50" />
						<p>No metrics available</p>
					</CardContent>
				</Card>
			{/if}
		</Tabs.Content>

		<Tabs.Content value="deals" class="mt-4">
			<Card>
				<CardContent class="p-0">
					{#if loadingDeals}
						<div class="p-6 space-y-4">
							{#each Array(5) as _}
								<Skeleton class="h-16 w-full" />
							{/each}
						</div>
					{:else if deals.length === 0}
						<div class="p-12 text-center text-muted-foreground">
							<Target class="h-12 w-12 mx-auto mb-4 opacity-50" />
							<p>No deals in this scenario</p>
						</div>
					{:else}
						<div class="divide-y">
							{#each deals as deal}
								<div class="flex items-center gap-4 p-4 hover:bg-muted/50">
									<div class="flex-1 min-w-0">
										<div class="flex items-center gap-2">
											<p class="font-medium truncate">{deal.name}</p>
											{#if deal.has_changes}
												<Badge variant="outline" class="text-xs">Modified</Badge>
											{/if}
											{#if deal.is_committed}
												<Badge variant="default" class="text-xs">Committed</Badge>
											{/if}
											{#if deal.is_excluded}
												<Badge variant="secondary" class="text-xs">Excluded</Badge>
											{/if}
										</div>
										<p class="text-sm text-muted-foreground">
											{deal.stage_name ?? 'No Stage'} &bull; {deal.probability}% probability
										</p>
									</div>
									<div class="text-right">
										<p class="font-medium">{formatCurrency(deal.amount)}</p>
										<p class="text-sm text-muted-foreground">
											Weighted: {formatCurrency(deal.weighted_amount)}
										</p>
									</div>
									<div class="flex items-center gap-1">
										<Button
											variant="ghost"
											size="sm"
											onclick={() => {
												selectedDeal = deal;
												showDealEditor = true;
											}}
										>
											<Edit class="h-4 w-4" />
										</Button>
										{#if deal.has_changes && !deal.is_committed}
											<Button
												variant="ghost"
												size="sm"
												class="text-green-600"
												onclick={() => handleCommitDeal(deal)}
											>
												<CheckCircle class="h-4 w-4" />
											</Button>
											<Button
												variant="ghost"
												size="sm"
												class="text-orange-600"
												onclick={() => handleResetDeal(deal)}
											>
												<RefreshCw class="h-4 w-4" />
											</Button>
										{/if}
									</div>
								</div>
							{/each}
						</div>
					{/if}
				</CardContent>
			</Card>
		</Tabs.Content>

		<Tabs.Content value="kanban" class="mt-4">
			{#if scenario?.metrics?.by_stage}
				<ScenarioKanban
					{deals}
					stageBreakdown={scenario.metrics.by_stage}
					onDealSelect={(deal) => {
						selectedDeal = deal;
						showDealEditor = true;
					}}
					onDealMove={(deal, newStageId) => handleDealUpdate(deal, { stage_id: newStageId })}
				/>
			{/if}
		</Tabs.Content>

		<Tabs.Content value="gap" class="mt-4">
			{#if scenario?.target_amount}
				<Card>
					<CardHeader>
						<CardTitle>Gap Analysis</CardTitle>
						<CardDescription>
							Analysis of the gap between current pipeline and target
						</CardDescription>
					</CardHeader>
					<CardContent>
						<Button variant="outline" onclick={() => (showGapAnalysis = true)}>
							<Target class="mr-2 h-4 w-4" />
							Open Gap Analysis
						</Button>
					</CardContent>
				</Card>
			{/if}
		</Tabs.Content>
	</Tabs.Root>
</div>

<!-- Deal Editor Dialog -->
<Dialog.Root bind:open={showDealEditor}>
	<Dialog.Content class="max-w-lg">
		{#if selectedDeal}
			<DealEditorPanel
				deal={selectedDeal}
				onUpdate={(updates) => {
					handleDealUpdate(selectedDeal!, updates);
					showDealEditor = false;
				}}
				onClose={() => (showDealEditor = false)}
			/>
		{/if}
	</Dialog.Content>
</Dialog.Root>

<!-- Delete Confirmation -->
<Dialog.Root bind:open={showDeleteConfirm}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Delete Scenario</Dialog.Title>
			<Dialog.Description>
				Are you sure you want to delete "{scenario?.name}"? This action cannot be undone.
			</Dialog.Description>
		</Dialog.Header>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showDeleteConfirm = false)}>Cancel</Button>
			<Button variant="destructive" onclick={handleDelete} disabled={saving}>
				{saving ? 'Deleting...' : 'Delete'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Gap Analysis Panel -->
{#if showGapAnalysis && scenario?.target_amount && scenario.period_start && scenario.period_end}
	<GapAnalysisPanel
		target={scenario.target_amount}
		periodStart={scenario.period_start}
		periodEnd={scenario.period_end}
		onClose={() => (showGapAnalysis = false)}
	/>
{/if}

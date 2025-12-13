<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import { Input } from '$lib/components/ui/input';
	import { Plus, Copy, GitCompare, Save, Target, TrendingUp, AlertTriangle, CheckCircle } from 'lucide-svelte';
	import {
		getScenarios,
		getScenario,
		createScenario,
		duplicateScenario,
		updateScenarioDeal,
		type Scenario,
		type ScenarioDeal
	} from '$lib/api/scenarios';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import ScenarioKanban from './ScenarioKanban.svelte';
	import ScenarioSummary from './ScenarioSummary.svelte';
	import DealEditorPanel from './DealEditorPanel.svelte';
	import ScenarioComparison from './ScenarioComparison.svelte';
	import GapAnalysis from './GapAnalysis.svelte';

	export let initialScenarioId: number | null = null;

	let scenarios: Scenario[] = [];
	let currentScenario: Scenario | null = null;
	let selectedDeal: ScenarioDeal | null = null;
	let loading = true;
	let showComparison = false;
	let showGapAnalysis = false;

	// Period selection
	let periodStart = getQuarterStart();
	let periodEnd = getQuarterEnd();

	function getQuarterStart(): string {
		const now = new Date();
		const quarter = Math.floor(now.getMonth() / 3);
		return new Date(now.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
	}

	function getQuarterEnd(): string {
		const now = new Date();
		const quarter = Math.floor(now.getMonth() / 3);
		return new Date(now.getFullYear(), (quarter + 1) * 3, 0).toISOString().split('T')[0];
	}

	onMount(async () => {
		await loadScenarios();

		if (initialScenarioId) {
			await loadScenario(initialScenarioId);
		} else if (scenarios.length > 0) {
			await loadScenario(scenarios[0].id);
		}

		loading = false;
	});

	async function loadScenarios() {
		const { data, error } = await tryCatch(
			getScenarios({ period_start: periodStart, period_end: periodEnd })
		);

		if (error) {
			toast.error('Failed to load scenarios');
			return;
		}

		scenarios = data ?? [];
	}

	async function loadScenario(id: number) {
		loading = true;
		const { data, error } = await tryCatch(getScenario(id));
		loading = false;

		if (error) {
			toast.error('Failed to load scenario');
			return;
		}

		currentScenario = data;
	}

	async function handleCreateScenario() {
		const name = prompt('Enter scenario name:', `Custom Scenario - ${new Date().toLocaleDateString()}`);
		if (!name) return;

		const { data, error } = await tryCatch(
			createScenario({
				name,
				period_start: periodStart,
				period_end: periodEnd,
				scenario_type: 'custom'
			})
		);

		if (error) {
			toast.error('Failed to create scenario');
			return;
		}

		toast.success('Scenario created');
		await loadScenarios();
		if (data) {
			await loadScenario(data.id);
		}
	}

	async function handleDuplicateScenario() {
		if (!currentScenario) return;

		const { data, error } = await tryCatch(duplicateScenario(currentScenario.id));

		if (error) {
			toast.error('Failed to duplicate scenario');
			return;
		}

		toast.success('Scenario duplicated');
		await loadScenarios();
		if (data) {
			await loadScenario(data.id);
		}
	}

	async function handleDealUpdate(deal: ScenarioDeal, changes: Partial<ScenarioDeal>) {
		if (!currentScenario) return;

		const { data, error } = await tryCatch(
			updateScenarioDeal(currentScenario.id, deal.deal_record_id, changes as Parameters<typeof updateScenarioDeal>[2])
		);

		if (error) {
			toast.error('Failed to update deal');
			return;
		}

		// Update local state
		if (currentScenario.deals) {
			const index = currentScenario.deals.findIndex((d) => d.id === deal.id);
			if (index >= 0 && data) {
				currentScenario.deals[index] = { ...currentScenario.deals[index], ...data.deal };
			}
		}

		// Update scenario totals
		if (data && currentScenario.metrics) {
			currentScenario.metrics.total_weighted = data.scenario_totals.total_weighted;
			currentScenario.metrics.total_unweighted = data.scenario_totals.total_unweighted;
			currentScenario.metrics.gap_amount = data.scenario_totals.gap_amount;
			currentScenario.metrics.progress_percent = data.scenario_totals.progress_percent;
		}

		currentScenario = currentScenario; // Trigger reactivity
	}

	function handleDealSelect(deal: ScenarioDeal) {
		selectedDeal = deal;
	}

	function handleDealMove(deal: ScenarioDeal, newStageId: number) {
		handleDealUpdate(deal, { stage_id: newStageId });
	}

	function formatCurrency(value: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(value);
	}

	$: gapAmount = currentScenario?.metrics?.gap_amount ?? 0;
	$: progressPercent = currentScenario?.metrics?.progress_percent ?? 0;
	$: isOnTrack = gapAmount <= 0;
</script>

<div class="flex h-full flex-col">
	<!-- Header -->
	<div class="flex items-center justify-between border-b bg-background px-4 py-3">
		<div class="flex items-center gap-4">
			<h1 class="text-lg font-semibold flex items-center gap-2">
				<Target class="h-5 w-5" />
				Scenario Planner
			</h1>

			<Select.Root
				type="single"
				value={currentScenario?.id.toString() ?? ''}
				onValueChange={(val) => val && loadScenario(parseInt(val))}
			>
				<Select.Trigger class="w-[220px]">
					<span>{currentScenario?.name ?? 'Select scenario'}</span>
				</Select.Trigger>
				<Select.Content>
					{#each scenarios as scenario}
						<Select.Item value={scenario.id.toString()}>
							{scenario.name}
						</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>

		<div class="flex items-center gap-2">
			<Button variant="outline" size="sm" onclick={handleCreateScenario}>
				<Plus class="mr-1 h-4 w-4" />
				New
			</Button>
			<Button variant="outline" size="sm" onclick={handleDuplicateScenario} disabled={!currentScenario}>
				<Copy class="mr-1 h-4 w-4" />
				Duplicate
			</Button>
			<Button variant="outline" size="sm" onclick={() => (showComparison = true)}>
				<GitCompare class="mr-1 h-4 w-4" />
				Compare
			</Button>
		</div>
	</div>

	{#if loading}
		<div class="flex flex-1 items-center justify-center">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
		</div>
	{:else if currentScenario}
		<!-- Summary Bar -->
		<div class="border-b bg-muted/30 px-4 py-3">
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-6">
					{#if currentScenario.target_amount}
						<div class="text-sm">
							<span class="text-muted-foreground">Target:</span>
							<span class="ml-1 font-semibold">{formatCurrency(currentScenario.target_amount)}</span>
						</div>
					{/if}
					<div class="text-sm">
						<span class="text-muted-foreground">Weighted:</span>
						<span class="ml-1 font-semibold">{formatCurrency(currentScenario.metrics?.total_weighted ?? 0)}</span>
					</div>
					<div class="text-sm">
						<span class="text-muted-foreground">Committed:</span>
						<span class="ml-1 font-semibold">{formatCurrency(currentScenario.metrics?.committed_total ?? 0)}</span>
					</div>
				</div>

				{#if currentScenario.target_amount}
					<div class="flex items-center gap-3">
						<div class="flex items-center gap-2">
							{#if isOnTrack}
								<CheckCircle class="h-4 w-4 text-green-500" />
								<span class="text-sm font-medium text-green-600">On Track</span>
							{:else}
								<AlertTriangle class="h-4 w-4 text-amber-500" />
								<span class="text-sm font-medium text-amber-600">
									Gap: {formatCurrency(gapAmount)}
								</span>
							{/if}
						</div>
						<div class="w-48">
							<div class="h-2 rounded-full bg-muted">
								<div
									class="h-2 rounded-full transition-all {isOnTrack ? 'bg-green-500' : 'bg-amber-500'}"
									style="width: {Math.min(100, progressPercent)}%"
								></div>
							</div>
							<div class="mt-1 text-xs text-muted-foreground text-right">
								{progressPercent.toFixed(0)}% of target
							</div>
						</div>
						<Button variant="ghost" size="sm" onclick={() => (showGapAnalysis = true)}>
							<TrendingUp class="mr-1 h-4 w-4" />
							Gap Analysis
						</Button>
					</div>
				{/if}
			</div>
		</div>

		<!-- Main Content -->
		<div class="flex flex-1 overflow-hidden">
			<!-- Kanban View -->
			<div class="flex-1 overflow-auto p-4">
				<ScenarioKanban
					deals={currentScenario.deals ?? []}
					stageBreakdown={currentScenario.metrics?.by_stage ?? []}
					onDealSelect={handleDealSelect}
					onDealMove={handleDealMove}
				/>
			</div>

			<!-- Side Panel -->
			{#if selectedDeal}
				<div class="w-80 border-l bg-background">
					<DealEditorPanel
						deal={selectedDeal}
						onUpdate={(changes) => handleDealUpdate(selectedDeal!, changes)}
						onClose={() => (selectedDeal = null)}
					/>
				</div>
			{:else if currentScenario.metrics}
				<div class="w-80 border-l bg-background overflow-y-auto">
					<ScenarioSummary
						metrics={currentScenario.metrics}
						targetAmount={currentScenario.target_amount}
					/>
				</div>
			{/if}
		</div>
	{:else}
		<div class="flex flex-1 flex-col items-center justify-center text-muted-foreground">
			<Target class="mb-4 h-12 w-12 opacity-50" />
			<p>No scenario selected</p>
			<Button variant="outline" class="mt-4" onclick={handleCreateScenario}>
				Create your first scenario
			</Button>
		</div>
	{/if}
</div>

<!-- Modals -->
{#if showComparison}
	<ScenarioComparison
		{scenarios}
		onClose={() => (showComparison = false)}
	/>
{/if}

{#if showGapAnalysis && currentScenario?.target_amount}
	<GapAnalysis
		target={currentScenario.target_amount}
		{periodStart}
		{periodEnd}
		onClose={() => (showGapAnalysis = false)}
	/>
{/if}

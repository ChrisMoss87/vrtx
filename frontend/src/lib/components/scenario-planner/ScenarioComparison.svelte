<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { X, TrendingUp, TrendingDown, Minus } from 'lucide-svelte';
	import { compareScenarios, type Scenario, type ScenarioComparison as ComparisonData } from '$lib/api/scenarios';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	export let scenarios: Scenario[];
	export let onClose: () => void;

	let selectedIds: number[] = [];
	let comparison: ComparisonData[] = [];
	let loading = false;

	// Pre-select first 2 scenarios
	onMount(() => {
		selectedIds = scenarios.slice(0, 2).map((s) => s.id);
		if (selectedIds.length >= 2) {
			loadComparison();
		}
	});

	async function loadComparison() {
		if (selectedIds.length < 2) {
			toast.error('Select at least 2 scenarios to compare');
			return;
		}

		loading = true;
		const { data, error } = await tryCatch(compareScenarios(selectedIds));
		loading = false;

		if (error) {
			toast.error('Failed to load comparison');
			return;
		}

		comparison = data ?? [];
	}

	function toggleScenario(id: number) {
		if (selectedIds.includes(id)) {
			selectedIds = selectedIds.filter((i) => i !== id);
		} else {
			selectedIds = [...selectedIds, id];
		}
	}

	function formatCurrency(value: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(value);
	}

	function formatDelta(value: number): string {
		const formatted = formatCurrency(Math.abs(value));
		return value >= 0 ? `+${formatted}` : `-${formatted}`;
	}

	function getDeltaIcon(value: number) {
		if (value > 0) return TrendingUp;
		if (value < 0) return TrendingDown;
		return Minus;
	}

	function getDeltaColor(value: number): string {
		if (value > 0) return 'text-green-600';
		if (value < 0) return 'text-red-600';
		return 'text-muted-foreground';
	}
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
	class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
	onclick={onClose}
	onkeydown={(e) => e.key === 'Escape' && onClose()}
>
	<!-- svelte-ignore a11y_click_events_have_key_events -->
	<div
		class="w-full max-w-4xl max-h-[90vh] overflow-hidden rounded-lg bg-background shadow-xl"
		onclick={(e) => e.stopPropagation()}
	>
		<!-- Header -->
		<div class="flex items-center justify-between border-b p-4">
			<h2 class="text-lg font-semibold">Compare Scenarios</h2>
			<Button variant="ghost" size="icon" onclick={onClose}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		<!-- Scenario Selection -->
		<div class="border-b p-4">
			<div class="flex flex-wrap gap-4">
				{#each scenarios as scenario}
					<label class="flex items-center gap-2 cursor-pointer">
						<Checkbox
							checked={selectedIds.includes(scenario.id)}
							onCheckedChange={() => toggleScenario(scenario.id)}
						/>
						<span class="text-sm">{scenario.name}</span>
					</label>
				{/each}
			</div>
			<Button
				class="mt-3"
				size="sm"
				onclick={loadComparison}
				disabled={selectedIds.length < 2 || loading}
			>
				{loading ? 'Loading...' : 'Compare Selected'}
			</Button>
		</div>

		<!-- Comparison Table -->
		{#if comparison.length > 0}
			<div class="overflow-auto p-4">
				<table class="w-full text-sm">
					<thead>
						<tr class="border-b">
							<th class="text-left py-2 px-3 font-medium">Metric</th>
							{#each comparison as comp}
								<th class="text-right py-2 px-3 font-medium">
									{comp.scenario_name}
									{#if comp.scenario_type !== 'custom'}
										<span class="text-xs text-muted-foreground block capitalize">
											({comp.scenario_type.replace('_', ' ')})
										</span>
									{/if}
								</th>
							{/each}
						</tr>
					</thead>
					<tbody>
						<!-- Weighted Total -->
						<tr class="border-b hover:bg-muted/50">
							<td class="py-3 px-3 font-medium">Weighted Pipeline</td>
							{#each comparison as comp, i}
								<td class="py-3 px-3 text-right">
									<div class="font-semibold">{formatCurrency(comp.metrics.total_weighted)}</div>
									{#if i > 0 && comp.delta}
										<div class="text-xs {getDeltaColor(comp.delta.total_weighted)} flex items-center justify-end gap-1">
											<svelte:component this={getDeltaIcon(comp.delta.total_weighted)} class="h-3 w-3" />
											{formatDelta(comp.delta.total_weighted)}
										</div>
									{/if}
								</td>
							{/each}
						</tr>

						<!-- Unweighted Total -->
						<tr class="border-b hover:bg-muted/50">
							<td class="py-3 px-3 font-medium">Total Pipeline</td>
							{#each comparison as comp, i}
								<td class="py-3 px-3 text-right">
									<div>{formatCurrency(comp.metrics.total_unweighted)}</div>
									{#if i > 0 && comp.delta}
										<div class="text-xs {getDeltaColor(comp.delta.total_unweighted)}">
											{formatDelta(comp.delta.total_unweighted)}
										</div>
									{/if}
								</td>
							{/each}
						</tr>

						<!-- Deal Count -->
						<tr class="border-b hover:bg-muted/50">
							<td class="py-3 px-3 font-medium">Deal Count</td>
							{#each comparison as comp, i}
								<td class="py-3 px-3 text-right">
									<div>{comp.metrics.deal_count}</div>
									{#if i > 0 && comp.delta}
										<div class="text-xs {getDeltaColor(comp.delta.deal_count)}">
											{comp.delta.deal_count >= 0 ? '+' : ''}{comp.delta.deal_count}
										</div>
									{/if}
								</td>
							{/each}
						</tr>

						<!-- Average Probability -->
						<tr class="border-b hover:bg-muted/50">
							<td class="py-3 px-3 font-medium">Avg. Win Rate</td>
							{#each comparison as comp, i}
								<td class="py-3 px-3 text-right">
									<div>{comp.metrics.average_probability.toFixed(0)}%</div>
									{#if i > 0 && comp.delta}
										<div class="text-xs {getDeltaColor(comp.delta.average_probability)}">
											{comp.delta.average_probability >= 0 ? '+' : ''}{comp.delta.average_probability.toFixed(1)}%
										</div>
									{/if}
								</td>
							{/each}
						</tr>

						<!-- Committed -->
						<tr class="border-b hover:bg-muted/50">
							<td class="py-3 px-3 font-medium">Committed</td>
							{#each comparison as comp}
								<td class="py-3 px-3 text-right text-green-600">
									{formatCurrency(comp.metrics.committed_total)}
								</td>
							{/each}
						</tr>

						<!-- Target Progress -->
						{#if comparison.some((c) => c.metrics.target_amount)}
							<tr class="border-b hover:bg-muted/50">
								<td class="py-3 px-3 font-medium">Target Progress</td>
								{#each comparison as comp}
									<td class="py-3 px-3 text-right">
										{#if comp.metrics.target_amount}
											<div class="{comp.metrics.gap_amount <= 0 ? 'text-green-600' : 'text-amber-600'}">
												{comp.metrics.progress_percent.toFixed(0)}%
											</div>
											{#if comp.metrics.gap_amount > 0}
												<div class="text-xs text-muted-foreground">
													Gap: {formatCurrency(comp.metrics.gap_amount)}
												</div>
											{/if}
										{:else}
											<span class="text-muted-foreground">-</span>
										{/if}
									</td>
								{/each}
							</tr>
						{/if}

						<!-- Average Deal Size -->
						<tr class="hover:bg-muted/50">
							<td class="py-3 px-3 font-medium">Avg. Deal Size</td>
							{#each comparison as comp}
								<td class="py-3 px-3 text-right">
									{formatCurrency(comp.metrics.average_deal_size)}
								</td>
							{/each}
						</tr>
					</tbody>
				</table>
			</div>
		{:else if !loading}
			<div class="p-8 text-center text-muted-foreground">
				Select at least 2 scenarios and click Compare to see the comparison
			</div>
		{/if}
	</div>
</div>

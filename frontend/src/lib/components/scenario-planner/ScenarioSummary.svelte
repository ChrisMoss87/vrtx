<script lang="ts">
	import { BarChart3, Target, TrendingUp, Users, DollarSign } from 'lucide-svelte';
	import type { ScenarioMetrics } from '$lib/api/scenarios';

	interface Props {
		metrics: ScenarioMetrics;
		targetAmount?: number | null;
	}

	let {
		metrics,
		targetAmount = null,
	}: Props = $props();

	function formatCurrency(value: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(value);
	}
</script>

<div class="p-4 space-y-6">
	<h3 class="font-semibold flex items-center gap-2">
		<BarChart3 class="h-4 w-4" />
		Scenario Summary
	</h3>

	<!-- Key Metrics -->
	<div class="grid grid-cols-2 gap-3">
		<div class="rounded-lg border bg-muted/30 p-3">
			<div class="text-xs text-muted-foreground mb-1">Total Pipeline</div>
			<div class="text-lg font-semibold">{formatCurrency(metrics.total_unweighted)}</div>
		</div>
		<div class="rounded-lg border bg-muted/30 p-3">
			<div class="text-xs text-muted-foreground mb-1">Weighted</div>
			<div class="text-lg font-semibold">{formatCurrency(metrics.total_weighted)}</div>
		</div>
		<div class="rounded-lg border bg-muted/30 p-3">
			<div class="text-xs text-muted-foreground mb-1">Committed</div>
			<div class="text-lg font-semibold text-green-600">{formatCurrency(metrics.committed_total)}</div>
		</div>
		<div class="rounded-lg border bg-muted/30 p-3">
			<div class="text-xs text-muted-foreground mb-1">Avg Probability</div>
			<div class="text-lg font-semibold">{metrics.average_probability.toFixed(0)}%</div>
		</div>
	</div>

	<!-- Target Progress -->
	{#if targetAmount}
		<div class="rounded-lg border p-4 space-y-3">
			<div class="flex items-center justify-between">
				<span class="text-sm flex items-center gap-2">
					<Target class="h-4 w-4" />
					Target Progress
				</span>
				<span class="text-sm font-medium">{metrics.progress_percent.toFixed(0)}%</span>
			</div>
			<div class="h-2 rounded-full bg-muted">
				<div
					class="h-2 rounded-full transition-all {metrics.gap_amount <= 0
						? 'bg-green-500'
						: 'bg-amber-500'}"
					style="width: {Math.min(100, metrics.progress_percent)}%"
				></div>
			</div>
			<div class="flex justify-between text-xs text-muted-foreground">
				<span>Target: {formatCurrency(targetAmount)}</span>
				{#if metrics.gap_amount > 0}
					<span class="text-amber-600">Gap: {formatCurrency(metrics.gap_amount)}</span>
				{:else}
					<span class="text-green-600">On track!</span>
				{/if}
			</div>
		</div>
	{/if}

	<!-- Deal Stats -->
	<div class="space-y-3">
		<h4 class="text-sm font-medium flex items-center gap-2">
			<Users class="h-4 w-4" />
			Deal Breakdown
		</h4>
		<div class="space-y-2 text-sm">
			<div class="flex justify-between">
				<span class="text-muted-foreground">Total Deals</span>
				<span>{metrics.deal_count}</span>
			</div>
			<div class="flex justify-between">
				<span class="text-muted-foreground">Committed</span>
				<span class="text-green-600">{metrics.committed_count}</span>
			</div>
			<div class="flex justify-between">
				<span class="text-muted-foreground">Open</span>
				<span>{metrics.open_count}</span>
			</div>
			<div class="flex justify-between">
				<span class="text-muted-foreground">Avg Deal Size</span>
				<span>{formatCurrency(metrics.average_deal_size)}</span>
			</div>
		</div>
	</div>

	<!-- Stage Breakdown -->
	{#if metrics.by_stage.length > 0}
		<div class="space-y-3">
			<h4 class="text-sm font-medium flex items-center gap-2">
				<TrendingUp class="h-4 w-4" />
				By Stage
			</h4>
			<div class="space-y-2">
				{#each metrics.by_stage as stage}
					<div class="rounded-lg border p-2">
						<div class="flex items-center justify-between mb-1">
							<span class="text-sm font-medium">{stage.stage_name}</span>
							<span class="text-xs text-muted-foreground">{stage.deal_count} deals</span>
						</div>
						<div class="flex items-center justify-between text-xs">
							<span class="text-muted-foreground">{formatCurrency(stage.total_amount)}</span>
							<span class="font-medium">{formatCurrency(stage.weighted_amount)} weighted</span>
						</div>
					</div>
				{/each}
			</div>
		</div>
	{/if}

	<!-- Timeline Preview -->
	{#if metrics.timeline.length > 0}
		<div class="space-y-3">
			<h4 class="text-sm font-medium flex items-center gap-2">
				<DollarSign class="h-4 w-4" />
				Weekly Projection
			</h4>
			<div class="space-y-1">
				{#each metrics.timeline.slice(0, 6) as week}
					<div class="flex items-center gap-2 text-xs">
						<span class="w-20 text-muted-foreground truncate">
							{new Date(week.week_start).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
						</span>
						<div class="flex-1 h-2 bg-muted rounded-full overflow-hidden">
							<div
								class="h-2 bg-primary rounded-full"
								style="width: {metrics.total_unweighted > 0
									? (week.cumulative / metrics.total_unweighted) * 100
									: 0}%"
							></div>
						</div>
						<span class="w-16 text-right font-medium">
							{formatCurrency(week.cumulative_weighted)}
						</span>
					</div>
				{/each}
			</div>
		</div>
	{/if}
</div>

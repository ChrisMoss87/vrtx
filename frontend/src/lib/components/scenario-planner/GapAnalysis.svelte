<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { X, Target, TrendingUp, Users, DollarSign, Lightbulb, CheckCircle, AlertTriangle, XCircle } from 'lucide-svelte';
	import { getGapAnalysis, type GapAnalysis as GapAnalysisData } from '$lib/api/scenarios';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	interface Props {
		target: number;
		periodStart: string;
		periodEnd: string;
		onClose: () => void;
	}

	let {
		target,
		periodStart,
		periodEnd,
		onClose,
	}: Props = $props();

	let analysis = $state<GapAnalysisData | null>(null);
	let loading = $state(true);

	onMount(async () => {
		const { data, error } = await tryCatch(getGapAnalysis(target, periodStart, periodEnd));
		loading = false;

		if (error) {
			toast.error('Failed to load gap analysis');
			return;
		}

		analysis = data;
	});

	function formatCurrency(value: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(value);
	}

	function getFeasibilityIcon(feasibility: string) {
		switch (feasibility) {
			case 'high':
				return CheckCircle;
			case 'medium':
				return AlertTriangle;
			case 'low':
				return XCircle;
			default:
				return AlertTriangle;
		}
	}

	function getFeasibilityColor(feasibility: string): string {
		switch (feasibility) {
			case 'high':
				return 'text-green-600 bg-green-50 dark:bg-green-950/30';
			case 'medium':
				return 'text-amber-600 bg-amber-50 dark:bg-amber-950/30';
			case 'low':
				return 'text-red-600 bg-red-50 dark:bg-red-950/30';
			default:
				return 'text-muted-foreground bg-muted';
		}
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
		class="w-full max-w-2xl max-h-[90vh] overflow-hidden rounded-lg bg-background shadow-xl"
		onclick={(e) => e.stopPropagation()}
	>
		<!-- Header -->
		<div class="flex items-center justify-between border-b p-4">
			<h2 class="text-lg font-semibold flex items-center gap-2">
				<Target class="h-5 w-5" />
				Gap Analysis
			</h2>
			<Button variant="ghost" size="icon" onclick={onClose}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		{#if loading}
			<div class="flex items-center justify-center p-12">
				<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
			</div>
		{:else if analysis}
			<div class="overflow-y-auto p-4 space-y-6">
				<!-- Summary -->
				<div class="grid grid-cols-3 gap-4">
					<div class="rounded-lg border p-4 text-center">
						<div class="text-xs text-muted-foreground mb-1">Target</div>
						<div class="text-xl font-bold">{formatCurrency(analysis.target)}</div>
					</div>
					<div class="rounded-lg border p-4 text-center">
						<div class="text-xs text-muted-foreground mb-1">Weighted Pipeline</div>
						<div class="text-xl font-bold">{formatCurrency(analysis.current_weighted)}</div>
					</div>
					<div class="rounded-lg border p-4 text-center {analysis.is_on_track ? 'border-green-200 bg-green-50 dark:bg-green-950/20' : 'border-amber-200 bg-amber-50 dark:bg-amber-950/20'}">
						<div class="text-xs text-muted-foreground mb-1">
							{analysis.is_on_track ? 'Surplus' : 'Gap'}
						</div>
						<div class="text-xl font-bold {analysis.is_on_track ? 'text-green-600' : 'text-amber-600'}">
							{analysis.is_on_track ? '+' : ''}{formatCurrency(analysis.is_on_track ? -analysis.gap : analysis.gap)}
						</div>
					</div>
				</div>

				<!-- Progress Bar -->
				<div class="space-y-2">
					<div class="flex justify-between text-sm">
						<span class="text-muted-foreground">Progress to Target</span>
						<span class="font-medium">{(100 - analysis.gap_percent).toFixed(0)}%</span>
					</div>
					<div class="h-3 rounded-full bg-muted">
						<div
							class="h-3 rounded-full transition-all {analysis.is_on_track ? 'bg-green-500' : 'bg-amber-500'}"
							style="width: {Math.min(100, 100 - analysis.gap_percent)}%"
						></div>
					</div>
				</div>

				<!-- Current State -->
				<div class="grid grid-cols-3 gap-4 text-sm">
					<div class="flex items-center gap-2">
						<Users class="h-4 w-4 text-muted-foreground" />
						<span class="text-muted-foreground">Deals:</span>
						<span class="font-medium">{analysis.deal_count}</span>
					</div>
					<div class="flex items-center gap-2">
						<DollarSign class="h-4 w-4 text-muted-foreground" />
						<span class="text-muted-foreground">Avg Size:</span>
						<span class="font-medium">{formatCurrency(analysis.average_deal_size)}</span>
					</div>
					<div class="flex items-center gap-2">
						<TrendingUp class="h-4 w-4 text-muted-foreground" />
						<span class="text-muted-foreground">Avg Prob:</span>
						<span class="font-medium">{analysis.average_probability.toFixed(0)}%</span>
					</div>
				</div>

				<!-- Recommendations -->
				{#if analysis.recommendations.length > 0 && !analysis.is_on_track}
					<div class="space-y-3">
						<h3 class="font-semibold flex items-center gap-2">
							<Lightbulb class="h-4 w-4" />
							Recommendations to Close the Gap
						</h3>

						{#each analysis.recommendations as rec}
							<div class="rounded-lg border p-4 {getFeasibilityColor(rec.feasibility)}">
								<div class="flex items-start gap-3">
									<svelte:component this={getFeasibilityIcon(rec.feasibility)} class="h-5 w-5 mt-0.5" />
									<div class="flex-1">
										<div class="font-medium">{rec.title}</div>
										<div class="text-sm mt-1 opacity-90">{rec.description}</div>
										<div class="text-xs mt-2 uppercase tracking-wide opacity-75">
											Feasibility: {rec.feasibility}
										</div>
									</div>
								</div>
							</div>
						{/each}
					</div>
				{/if}

				<!-- Top Deals -->
				{#if analysis.top_deals.length > 0}
					<div class="space-y-3">
						<h3 class="font-semibold">Top Deals by Weighted Value</h3>
						<div class="space-y-2">
							{#each analysis.top_deals.slice(0, 5) as deal}
								<div class="flex items-center justify-between rounded-lg border p-3 text-sm">
									<div>
										<div class="font-medium">{deal.name}</div>
										<div class="text-xs text-muted-foreground">
											{deal.stage ?? 'No stage'} • Close: {deal.close_date
												? new Date(deal.close_date).toLocaleDateString()
												: 'TBD'}
										</div>
									</div>
									<div class="text-right">
										<div class="font-semibold">{formatCurrency(deal.weighted)}</div>
										<div class="text-xs text-muted-foreground">
											{formatCurrency(deal.amount)} @ {deal.probability}%
										</div>
									</div>
								</div>
							{/each}
						</div>
					</div>
				{/if}
			</div>
		{/if}
	</div>
</div>

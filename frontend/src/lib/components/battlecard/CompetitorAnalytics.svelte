<script lang="ts">
	import { onMount } from 'svelte';
	import { TrendingUp, TrendingDown, Target, DollarSign } from 'lucide-svelte';
	import { getCompetitorAnalytics, type CompetitorAnalytics } from '$lib/api/competitors';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	export let competitorId: number;

	let analytics: CompetitorAnalytics | null = null;
	let loading = true;

	onMount(async () => {
		const { data, error } = await tryCatch(getCompetitorAnalytics(competitorId));
		loading = false;

		if (error) {
			toast.error('Failed to load analytics');
			return;
		}

		analytics = data;
	});

	function formatCurrency(amount: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(amount);
	}

	function getWinRateColor(rate: number | null): string {
		if (rate === null) return 'bg-muted';
		if (rate >= 60) return 'bg-green-500';
		if (rate >= 40) return 'bg-amber-500';
		return 'bg-red-500';
	}
</script>

{#if loading}
	<div class="flex items-center justify-center py-12">
		<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
	</div>
{:else if analytics}
	<div class="space-y-6">
		<!-- Summary Cards -->
		<div class="grid gap-4 md:grid-cols-4">
			<div class="rounded-lg border p-4">
				<div class="text-sm text-muted-foreground">Win Rate</div>
				<div class="flex items-center gap-2 mt-1">
					<span class="text-2xl font-bold">
						{analytics.summary.win_rate !== null ? `${analytics.summary.win_rate}%` : '-'}
					</span>
					{#if analytics.summary.win_rate !== null}
						{#if analytics.summary.win_rate >= 50}
							<TrendingUp class="h-5 w-5 text-green-500" />
						{:else}
							<TrendingDown class="h-5 w-5 text-red-500" />
						{/if}
					{/if}
				</div>
				<div class="text-xs text-muted-foreground mt-1">
					{analytics.summary.won} won / {analytics.summary.lost} lost
				</div>
			</div>

			<div class="rounded-lg border p-4">
				<div class="text-sm text-muted-foreground">Total Deals</div>
				<div class="text-2xl font-bold mt-1">{analytics.summary.total_deals}</div>
				<div class="text-xs text-muted-foreground mt-1">competitive encounters</div>
			</div>

			<div class="rounded-lg border p-4">
				<div class="text-sm text-muted-foreground flex items-center gap-1">
					<DollarSign class="h-3 w-3" />
					Revenue Won
				</div>
				<div class="text-2xl font-bold mt-1 text-green-600 dark:text-green-400">
					{formatCurrency(analytics.summary.won_amount)}
				</div>
			</div>

			<div class="rounded-lg border p-4">
				<div class="text-sm text-muted-foreground flex items-center gap-1">
					<DollarSign class="h-3 w-3" />
					Revenue Lost
				</div>
				<div class="text-2xl font-bold mt-1 text-red-600 dark:text-red-400">
					{formatCurrency(analytics.summary.lost_amount)}
				</div>
			</div>
		</div>

		<!-- Win Rate by Deal Size -->
		<div class="rounded-lg border p-4">
			<h3 class="font-semibold mb-4">Win Rate by Deal Size</h3>
			<div class="space-y-3">
				{#each analytics.by_deal_size as bracket}
					<div class="flex items-center gap-4">
						<div class="w-24 text-sm">{bracket.label}</div>
						<div class="flex-1">
							<div class="h-4 bg-muted rounded-full overflow-hidden">
								<div
									class="h-full {getWinRateColor(bracket.win_rate)} transition-all"
									style="width: {bracket.win_rate ?? 0}%"
								></div>
							</div>
						</div>
						<div class="w-24 text-sm text-right">
							{bracket.win_rate !== null ? `${bracket.win_rate}%` : '-'}
							<span class="text-muted-foreground">({bracket.won}/{bracket.total})</span>
						</div>
					</div>
				{/each}
			</div>
		</div>

		<!-- Top Objection Handlers -->
		{#if analytics.top_objections.length > 0}
			<div class="rounded-lg border p-4">
				<h3 class="font-semibold mb-4 flex items-center gap-2">
					<Target class="h-4 w-4" />
					Most Effective Counters
				</h3>
				<div class="space-y-2">
					{#each analytics.top_objections as obj, index}
						<div class="flex items-center gap-3">
							<span class="w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-medium flex items-center justify-center">
								{index + 1}
							</span>
							<div class="flex-1 text-sm truncate">"{obj.objection}"</div>
							<div class="text-sm text-right">
								<span class="font-medium {obj.effectiveness && obj.effectiveness >= 60 ? 'text-green-600 dark:text-green-400' : ''}">
									{obj.effectiveness !== null ? `${obj.effectiveness}%` : '-'}
								</span>
								<span class="text-muted-foreground ml-1">({obj.uses} uses)</span>
							</div>
						</div>
					{/each}
				</div>
			</div>
		{/if}

		<!-- Monthly Trend -->
		{#if analytics.monthly_trend.length > 0}
			<div class="rounded-lg border p-4">
				<h3 class="font-semibold mb-4">6-Month Trend</h3>
				<div class="flex items-end gap-2 h-32">
					{#each analytics.monthly_trend as month}
						<div class="flex-1 flex flex-col items-center">
							<div class="w-full flex flex-col gap-0.5" style="height: 100px">
								{#if month.total > 0}
									<div
										class="w-full bg-green-500 rounded-t"
										style="height: {(month.won / Math.max(...analytics.monthly_trend.map(m => m.total))) * 100}%"
									></div>
									<div
										class="w-full bg-red-500 rounded-b"
										style="height: {(month.lost / Math.max(...analytics.monthly_trend.map(m => m.total))) * 100}%"
									></div>
								{:else}
									<div class="w-full bg-muted rounded" style="height: 2px"></div>
								{/if}
							</div>
							<div class="text-xs text-muted-foreground mt-2">{month.month.split(' ')[0]}</div>
						</div>
					{/each}
				</div>
				<div class="flex justify-center gap-4 mt-4 text-xs">
					<div class="flex items-center gap-1">
						<div class="w-3 h-3 bg-green-500 rounded"></div>
						<span>Won</span>
					</div>
					<div class="flex items-center gap-1">
						<div class="w-3 h-3 bg-red-500 rounded"></div>
						<span>Lost</span>
					</div>
				</div>
			</div>
		{/if}
	</div>
{/if}

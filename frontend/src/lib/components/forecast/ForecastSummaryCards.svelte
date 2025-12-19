<script lang="ts">
	import type { ForecastSummary } from '$lib/api/forecasts';
	import { formatCurrency } from '$lib/api/forecasts';
	import { Card, CardContent, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Progress } from '$lib/components/ui/progress';
	import { cn } from '$lib/utils';
	import { CheckCircle, TrendingUp, BarChart3, Target } from 'lucide-svelte';

	interface Props {
		summary: ForecastSummary;
		currency?: string;
	}

	let { summary, currency = 'USD' }: Props = $props();

	const quotaAmount = $derived(summary.quota?.amount ?? 0);

	function getAttainmentPercentage(amount: number): number {
		if (quotaAmount <= 0) return 0;
		return Math.min(100, Math.round((amount / quotaAmount) * 100));
	}

	const cards = $derived([
		{
			title: 'Commit',
			amount: summary.commit.amount,
			count: summary.commit.count,
			icon: CheckCircle,
			color: 'text-green-600',
			bgColor: 'bg-green-50 dark:bg-green-950',
			description: 'Deals you expect to close this period'
		},
		{
			title: 'Best Case',
			amount: summary.best_case.amount,
			count: summary.best_case.count,
			icon: TrendingUp,
			color: 'text-blue-600',
			bgColor: 'bg-blue-50 dark:bg-blue-950',
			description: 'Likely to close deals'
		},
		{
			title: 'Pipeline',
			amount: summary.pipeline.amount,
			count: summary.pipeline.count,
			icon: BarChart3,
			color: 'text-gray-600',
			bgColor: 'bg-gray-50 dark:bg-gray-950',
			description: 'All open deals in progress'
		},
		{
			title: 'Weighted',
			amount: summary.weighted.amount,
			count: summary.weighted.count,
			icon: Target,
			color: 'text-purple-600',
			bgColor: 'bg-purple-50 dark:bg-purple-950',
			description: 'Probability-weighted forecast'
		}
	]);
</script>

<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
	{#each cards as card}
		<Card>
			<CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
				<CardTitle class="text-sm font-medium">{card.title}</CardTitle>
				<div class={cn('rounded-full p-2', card.bgColor)}>
					<svelte:component this={card.icon} class={cn('h-4 w-4', card.color)} />
				</div>
			</CardHeader>
			<CardContent>
				<div class="text-2xl font-bold">{formatCurrency(card.amount, currency)}</div>
				<p class="text-xs text-muted-foreground">
					{card.count} deal{card.count !== 1 ? 's' : ''}
				</p>
				{#if quotaAmount > 0}
					<div class="mt-3">
						<div class="flex justify-between text-xs mb-1">
							<span class="text-muted-foreground">of {formatCurrency(quotaAmount, currency)} quota</span>
							<span class="font-medium">{getAttainmentPercentage(card.amount)}%</span>
						</div>
						<Progress value={getAttainmentPercentage(card.amount)} class="h-1.5" />
					</div>
				{/if}
			</CardContent>
		</Card>
	{/each}
</div>

{#if summary.quota}
	<Card class="mt-4">
		<CardContent class="pt-6">
			<div class="flex items-center justify-between">
				<div>
					<p class="text-sm font-medium">Quota Attainment</p>
					<p class="text-2xl font-bold">{summary.quota.attainment}%</p>
				</div>
				<div class="text-right">
					<p class="text-sm text-muted-foreground">Closed Won</p>
					<p class="text-xl font-semibold text-green-600">
						{formatCurrency(summary.closed_won.amount, currency)}
					</p>
				</div>
				<div class="text-right">
					<p class="text-sm text-muted-foreground">Remaining</p>
					<p class="text-xl font-semibold">
						{formatCurrency(summary.quota.remaining, currency)}
					</p>
				</div>
			</div>
			<Progress value={summary.quota.attainment} class="mt-4 h-2" />
		</CardContent>
	</Card>
{/if}

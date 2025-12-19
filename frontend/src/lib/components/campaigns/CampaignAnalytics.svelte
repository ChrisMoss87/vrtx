<script lang="ts">
	import type { Campaign, CampaignAnalytics, CampaignMetric, TopLink } from '$lib/api/campaigns';
	import { getCampaignAnalytics, getCampaignMetrics } from '$lib/api/campaigns';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Progress } from '$lib/components/ui/progress';
	import { Spinner } from '$lib/components/ui/spinner';
	import {
		Send,
		Mail,
		MousePointer,
		Ban,
		TrendingUp,
		DollarSign,
		Link,
		BarChart3
	} from 'lucide-svelte';

	interface Props {
		campaign: Campaign;
	}

	let { campaign }: Props = $props();

	let loading = $state(true);
	let analytics = $state<CampaignAnalytics | null>(null);
	let topLinks = $state<TopLink[]>([]);
	let metrics = $state<CampaignMetric[]>([]);

	async function loadAnalytics() {
		loading = true;
		try {
			const [analyticsData, metricsData] = await Promise.all([
				getCampaignAnalytics(campaign.id),
				getCampaignMetrics(campaign.id)
			]);
			analytics = analyticsData.analytics;
			topLinks = analyticsData.top_links;
			metrics = metricsData;
		} catch (error) {
			console.error('Failed to load analytics:', error);
		} finally {
			loading = false;
		}
	}

	function formatNumber(num: number): string {
		if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
		if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
		return num.toString();
	}

	function formatCurrency(value: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(value);
	}

	function formatPercent(value: number): string {
		return value.toFixed(1) + '%';
	}

	$effect(() => {
		loadAnalytics();
	});
</script>

{#if loading}
	<div class="flex items-center justify-center py-12">
		<Spinner class="h-8 w-8" />
	</div>
{:else if analytics}
	<div class="space-y-6">
		<!-- KPI Cards -->
		<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Total Sends</p>
							<p class="text-2xl font-bold">{formatNumber(analytics.total_sends)}</p>
						</div>
						<div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
							<Send class="h-5 w-5 text-blue-600 dark:text-blue-400" />
						</div>
					</div>
					<p class="mt-2 text-xs text-muted-foreground">
						{formatNumber(analytics.delivered)} delivered
					</p>
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Open Rate</p>
							<p class="text-2xl font-bold">{formatPercent(analytics.open_rate)}</p>
						</div>
						<div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
							<Mail class="h-5 w-5 text-green-600 dark:text-green-400" />
						</div>
					</div>
					<Progress value={analytics.open_rate} class="mt-2" />
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Click Rate</p>
							<p class="text-2xl font-bold">{formatPercent(analytics.click_rate)}</p>
						</div>
						<div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
							<MousePointer class="h-5 w-5 text-purple-600 dark:text-purple-400" />
						</div>
					</div>
					<Progress value={analytics.click_rate} class="mt-2" />
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Bounce Rate</p>
							<p class="text-2xl font-bold">{formatPercent(analytics.bounce_rate)}</p>
						</div>
						<div class="rounded-full bg-red-100 p-3 dark:bg-red-900">
							<Ban class="h-5 w-5 text-red-600 dark:text-red-400" />
						</div>
					</div>
					<Progress value={analytics.bounce_rate} class="mt-2" />
				</Card.Content>
			</Card.Root>
		</div>

		<!-- Conversion & Revenue -->
		<div class="grid gap-4 sm:grid-cols-2">
			<Card.Root>
				<Card.Header>
					<Card.Title class="flex items-center gap-2">
						<TrendingUp class="h-5 w-5" />
						Conversions
					</Card.Title>
				</Card.Header>
				<Card.Content>
					<div class="flex items-baseline gap-2">
						<span class="text-3xl font-bold">{analytics.conversions}</span>
						<Badge variant="secondary">{formatPercent(analytics.conversion_rate)} rate</Badge>
					</div>
					<p class="text-sm text-muted-foreground mt-2">
						From {formatNumber(analytics.clicked)} clicks
					</p>
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Header>
					<Card.Title class="flex items-center gap-2">
						<DollarSign class="h-5 w-5" />
						Revenue
					</Card.Title>
				</Card.Header>
				<Card.Content>
					<div class="flex items-baseline gap-2">
						<span class="text-3xl font-bold">{formatCurrency(analytics.revenue)}</span>
					</div>
					{#if analytics.conversions > 0}
						<p class="text-sm text-muted-foreground mt-2">
							{formatCurrency(analytics.revenue / analytics.conversions)} per conversion
						</p>
					{/if}
				</Card.Content>
			</Card.Root>
		</div>

		<!-- Top Links -->
		{#if topLinks.length > 0}
			<Card.Root>
				<Card.Header>
					<Card.Title class="flex items-center gap-2">
						<Link class="h-5 w-5" />
						Top Clicked Links
					</Card.Title>
				</Card.Header>
				<Card.Content>
					<div class="space-y-3">
						{#each topLinks.slice(0, 5) as link, index}
							<div class="flex items-center justify-between">
								<div class="flex items-center gap-3 min-w-0">
									<Badge variant="outline" class="shrink-0">{index + 1}</Badge>
									<div class="min-w-0">
										{#if link.link_name}
											<p class="font-medium truncate">{link.link_name}</p>
										{/if}
										<p class="text-sm text-muted-foreground truncate">{link.url}</p>
									</div>
								</div>
								<Badge>{link.click_count} clicks</Badge>
							</div>
						{/each}
					</div>
				</Card.Content>
			</Card.Root>
		{/if}

		<!-- Metrics Over Time -->
		{#if metrics.length > 0}
			<Card.Root>
				<Card.Header>
					<Card.Title class="flex items-center gap-2">
						<BarChart3 class="h-5 w-5" />
						Daily Metrics
					</Card.Title>
				</Card.Header>
				<Card.Content>
					<div class="overflow-x-auto">
						<table class="w-full text-sm">
							<thead>
								<tr class="border-b">
									<th class="pb-2 text-left font-medium">Date</th>
									<th class="pb-2 text-right font-medium">Sends</th>
									<th class="pb-2 text-right font-medium">Opens</th>
									<th class="pb-2 text-right font-medium">Clicks</th>
									<th class="pb-2 text-right font-medium">Bounces</th>
									<th class="pb-2 text-right font-medium">Conversions</th>
								</tr>
							</thead>
							<tbody>
								{#each metrics.slice(-10) as metric}
									<tr class="border-b">
										<td class="py-2">{new Date(metric.date).toLocaleDateString()}</td>
										<td class="py-2 text-right">{metric.sends}</td>
										<td class="py-2 text-right">{metric.unique_opens}</td>
										<td class="py-2 text-right">{metric.unique_clicks}</td>
										<td class="py-2 text-right">{metric.bounces}</td>
										<td class="py-2 text-right">{metric.conversions}</td>
									</tr>
								{/each}
							</tbody>
						</table>
					</div>
				</Card.Content>
			</Card.Root>
		{/if}
	</div>
{:else}
	<div class="rounded-lg border border-dashed p-8 text-center">
		<BarChart3 class="mx-auto h-12 w-12 text-muted-foreground" />
		<h3 class="mt-4 text-lg font-medium">No Analytics Data</h3>
		<p class="text-sm text-muted-foreground mt-1">
			Analytics will appear here once the campaign has been started.
		</p>
	</div>
{/if}

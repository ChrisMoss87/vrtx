<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Eye, Users, FileText, TrendingDown, Clock, ArrowUpRight } from 'lucide-svelte';
	import type { PageAnalytics } from '$lib/api/landing-pages';

	interface Props {
		analytics: PageAnalytics | null;
		loading?: boolean;
		onDateRangeChange?: (startDate: string, endDate: string) => void;
	}

	let { analytics, loading = false, onDateRangeChange }: Props = $props();

	let dateRange = $state('7d');

	const dateRangeOptions = [
		{ value: '7d', label: 'Last 7 days' },
		{ value: '30d', label: 'Last 30 days' },
		{ value: '90d', label: 'Last 90 days' }
	];

	function handleDateRangeChange(value: string) {
		dateRange = value;
		const endDate = new Date().toISOString().split('T')[0];
		const days = parseInt(value);
		const startDate = new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
		onDateRangeChange?.(startDate, endDate);
	}

	function formatNumber(num: number): string {
		if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
		if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
		return num.toString();
	}

	function formatPercentage(num: number): string {
		return (num * 100).toFixed(1) + '%';
	}
</script>

<div class="space-y-6">
	<!-- Date range selector -->
	<div class="flex items-center justify-between">
		<h3 class="text-lg font-semibold">Analytics</h3>
		<Select.Root
			type="single"
			value={dateRange}
			onValueChange={(v) => v && handleDateRangeChange(v)}
		>
			<Select.Trigger class="w-40">
				{dateRangeOptions.find((o) => o.value === dateRange)?.label}
			</Select.Trigger>
			<Select.Content>
				{#each dateRangeOptions as option}
					<Select.Item value={option.value}>{option.label}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
	</div>

	{#if loading}
		<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
			{#each Array(4) as _}
				<Card.Root>
					<Card.Content class="pt-6">
						<div class="bg-muted h-4 w-24 animate-pulse rounded"></div>
						<div class="bg-muted mt-2 h-8 w-16 animate-pulse rounded"></div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{:else if analytics}
		<!-- Summary Cards -->
		<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center gap-2">
						<Eye class="text-muted-foreground h-4 w-4" />
						<span class="text-muted-foreground text-sm">Total Views</span>
					</div>
					<div class="mt-2 text-2xl font-bold">{formatNumber(analytics.totals.views)}</div>
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center gap-2">
						<Users class="text-muted-foreground h-4 w-4" />
						<span class="text-muted-foreground text-sm">Unique Visitors</span>
					</div>
					<div class="mt-2 text-2xl font-bold">
						{formatNumber(analytics.totals.unique_visitors)}
					</div>
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center gap-2">
						<FileText class="text-muted-foreground h-4 w-4" />
						<span class="text-muted-foreground text-sm">Conversions</span>
					</div>
					<div class="mt-2 flex items-baseline gap-2">
						<span class="text-2xl font-bold">
							{formatNumber(analytics.totals.form_submissions)}
						</span>
						<span class="text-sm text-green-600">
							{formatPercentage(analytics.totals.conversion_rate)}
						</span>
					</div>
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center gap-2">
						<TrendingDown class="text-muted-foreground h-4 w-4" />
						<span class="text-muted-foreground text-sm">Bounce Rate</span>
					</div>
					<div class="mt-2 text-2xl font-bold">
						{formatPercentage(analytics.totals.bounce_rate)}
					</div>
				</Card.Content>
			</Card.Root>
		</div>

		<!-- Daily Chart -->
		<Card.Root>
			<Card.Header>
				<Card.Title>Daily Traffic</Card.Title>
			</Card.Header>
			<Card.Content>
				{#if analytics.daily.length > 0}
					<div class="h-64">
						<div class="flex h-full items-end gap-1">
							{#each analytics.daily as day}
								{@const maxViews = Math.max(...analytics.daily.map((d) => d.views), 1)}
								<div class="group relative flex-1">
									<div
										class="bg-primary/80 hover:bg-primary w-full rounded-t transition-colors"
										style="height: {(day.views / maxViews) * 100}%"
									></div>
									<div
										class="invisible absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded bg-black px-2 py-1 text-xs text-white group-hover:visible"
									>
										<div>{new Date(day.date).toLocaleDateString()}</div>
										<div>{day.views} views</div>
										<div>{day.form_submissions} conversions</div>
									</div>
								</div>
							{/each}
						</div>
					</div>
				{:else}
					<div class="text-muted-foreground flex h-64 items-center justify-center">
						No data available
					</div>
				{/if}
			</Card.Content>
		</Card.Root>

		<!-- Breakdowns -->
		<div class="grid gap-4 md:grid-cols-3">
			<!-- Referrer Breakdown -->
			<Card.Root>
				<Card.Header>
					<Card.Title class="text-base">Top Referrers</Card.Title>
				</Card.Header>
				<Card.Content>
					{#if Object.keys(analytics.referrer_breakdown).length > 0}
						<div class="space-y-2">
							{#each Object.entries(analytics.referrer_breakdown)
								.sort((a, b) => b[1] - a[1])
								.slice(0, 5) as [referrer, count]}
								<div class="flex items-center justify-between text-sm">
									<span class="truncate">{referrer || 'Direct'}</span>
									<span class="text-muted-foreground">{count}</span>
								</div>
							{/each}
						</div>
					{:else}
						<p class="text-muted-foreground text-sm">No referrer data</p>
					{/if}
				</Card.Content>
			</Card.Root>

			<!-- Device Breakdown -->
			<Card.Root>
				<Card.Header>
					<Card.Title class="text-base">Devices</Card.Title>
				</Card.Header>
				<Card.Content>
					{#if Object.keys(analytics.device_breakdown).length > 0}
						<div class="space-y-2">
							{#each Object.entries(analytics.device_breakdown).sort((a, b) => b[1] - a[1]) as [device, count]}
								{@const total = Object.values(analytics.device_breakdown).reduce(
									(a, b) => a + b,
									0
								)}
								<div class="flex items-center justify-between text-sm">
									<span class="capitalize">{device}</span>
									<span class="text-muted-foreground">
										{((count / total) * 100).toFixed(0)}%
									</span>
								</div>
							{/each}
						</div>
					{:else}
						<p class="text-muted-foreground text-sm">No device data</p>
					{/if}
				</Card.Content>
			</Card.Root>

			<!-- Location Breakdown -->
			<Card.Root>
				<Card.Header>
					<Card.Title class="text-base">Top Locations</Card.Title>
				</Card.Header>
				<Card.Content>
					{#if Object.keys(analytics.location_breakdown).length > 0}
						<div class="space-y-2">
							{#each Object.entries(analytics.location_breakdown)
								.sort((a, b) => b[1] - a[1])
								.slice(0, 5) as [location, count]}
								<div class="flex items-center justify-between text-sm">
									<span>{location || 'Unknown'}</span>
									<span class="text-muted-foreground">{count}</span>
								</div>
							{/each}
						</div>
					{:else}
						<p class="text-muted-foreground text-sm">No location data</p>
					{/if}
				</Card.Content>
			</Card.Root>
		</div>
	{:else}
		<Card.Root>
			<Card.Content class="py-12 text-center">
				<p class="text-muted-foreground">No analytics data available</p>
			</Card.Content>
		</Card.Root>
	{/if}
</div>

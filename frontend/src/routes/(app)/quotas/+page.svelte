<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Avatar from '$lib/components/ui/avatar';
	import {
		Target,
		Trophy,
		TrendingUp,
		TrendingDown,
		Clock,
		Users,
		DollarSign,
		Award,
		ChevronRight,
		Plus,
		RefreshCw,
		BarChart3,
		Zap
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getMyProgress,
		getLeaderboard,
		getMyPosition,
		getCurrentPeriod,
		type QuotaProgress,
		type LeaderboardEntry,
		type QuotaPeriod,
		type MetricType
	} from '$lib/api/quotas';

	let loading = $state(true);
	let myProgress = $state<QuotaProgress[]>([]);
	let leaderboard = $state<{ period: any; entries: LeaderboardEntry[] } | null>(null);
	let myPosition = $state<any>(null);
	let currentPeriod = $state<QuotaPeriod | null>(null);
	let selectedMetric = $state<MetricType>('revenue');
	let activeTab = $state('my-progress');

	const metricIcons: Record<MetricType, typeof DollarSign> = {
		revenue: DollarSign,
		deals: Target,
		leads: Users,
		calls: Zap,
		meetings: Clock,
		activities: BarChart3,
		custom: Target
	};

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [progressData, leaderboardData, positionData, periodData] = await Promise.all([
				getMyProgress(),
				getLeaderboard({ metric_type: selectedMetric, limit: 10 }),
				getMyPosition({ metric_type: selectedMetric }),
				getCurrentPeriod()
			]);
			myProgress = progressData;
			leaderboard = leaderboardData;
			myPosition = positionData;
			currentPeriod = periodData;
		} catch (error) {
			console.error('Failed to load quota data:', error);
			toast.error('Failed to load quota data');
		} finally {
			loading = false;
		}
	}

	async function handleMetricChange() {
		try {
			const [leaderboardData, positionData] = await Promise.all([
				getLeaderboard({ metric_type: selectedMetric, limit: 10 }),
				getMyPosition({ metric_type: selectedMetric })
			]);
			leaderboard = leaderboardData;
			myPosition = positionData;
		} catch (error) {
			console.error('Failed to load leaderboard:', error);
		}
	}

	function formatCurrency(amount: number, currency: string = 'USD'): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency,
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(amount);
	}

	function formatNumber(num: number): string {
		if (num >= 1000000) {
			return (num / 1000000).toFixed(1) + 'M';
		}
		if (num >= 1000) {
			return (num / 1000).toFixed(1) + 'K';
		}
		return num.toLocaleString();
	}

	function getAttainmentColor(percent: number): string {
		if (percent >= 100) return 'text-emerald-600';
		if (percent >= 75) return 'text-blue-600';
		if (percent >= 50) return 'text-amber-600';
		return 'text-red-600';
	}

	function getAttainmentBg(percent: number): string {
		if (percent >= 100) return 'bg-emerald-500';
		if (percent >= 75) return 'bg-blue-500';
		if (percent >= 50) return 'bg-amber-500';
		return 'bg-red-500';
	}
</script>

<svelte:head>
	<title>Quotas & Goals | VRTX CRM</title>
</svelte:head>

<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white">
	<div class="container mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
		<!-- Header -->
		<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
			<div>
				<h1 class="text-3xl font-bold tracking-tight text-slate-900">Quotas & Goals</h1>
				<p class="mt-1 text-slate-500">Track your performance and compete with your team</p>
			</div>
			<div class="flex gap-3">
				<Button variant="outline" onclick={() => goto('/goals')}>
					<Target class="mr-2 h-4 w-4" />
					My Goals
				</Button>
				<Button onclick={() => goto('/quotas/leaderboard')}>
					<Trophy class="mr-2 h-4 w-4" />
					Full Leaderboard
				</Button>
			</div>
		</div>

		<!-- Period Info -->
		{#if currentPeriod}
			<Card.Root class="mb-6 border-0 bg-gradient-to-r from-blue-600 to-violet-600 text-white shadow-lg">
				<Card.Content class="p-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-blue-100">Current Period</p>
							<h2 class="text-2xl font-bold">{currentPeriod.name}</h2>
							<p class="mt-1 text-sm text-blue-100">
								{currentPeriod.days_remaining} days remaining
							</p>
						</div>
						<div class="text-right">
							<div class="text-4xl font-bold">{currentPeriod.progress_percent}%</div>
							<p class="text-sm text-blue-100">of period elapsed</p>
						</div>
					</div>
					<div class="mt-4 h-2 w-full rounded-full bg-white/20">
						<div
							class="h-full rounded-full bg-white transition-all"
							style="width: {currentPeriod.progress_percent}%"
						></div>
					</div>
				</Card.Content>
			</Card.Root>
		{/if}

		<!-- Tabs -->
		<Tabs.Root bind:value={activeTab} class="space-y-6">
			<Tabs.List class="h-12 bg-white p-1 shadow-sm">
				<Tabs.Trigger value="my-progress" class="px-6">
					<Target class="mr-2 h-4 w-4" />
					My Progress
				</Tabs.Trigger>
				<Tabs.Trigger value="leaderboard" class="px-6">
					<Trophy class="mr-2 h-4 w-4" />
					Leaderboard
				</Tabs.Trigger>
				<Tabs.Trigger value="team" class="px-6">
					<Users class="mr-2 h-4 w-4" />
					Team Overview
				</Tabs.Trigger>
			</Tabs.List>

			<!-- My Progress Tab -->
			<Tabs.Content value="my-progress">
				{#if loading}
					<div class="flex items-center justify-center py-16">
						<div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-blue-600"></div>
					</div>
				{:else if myProgress.length === 0}
					<Card.Root class="border-0 bg-white shadow-sm">
						<Card.Content class="flex flex-col items-center justify-center py-16">
							<div class="rounded-full bg-slate-100 p-4">
								<Target class="h-8 w-8 text-slate-400" />
							</div>
							<h3 class="mt-4 text-lg font-medium text-slate-900">No quotas assigned</h3>
							<p class="mt-1 text-sm text-slate-500">Contact your manager to set up quotas for this period</p>
						</Card.Content>
					</Card.Root>
				{:else}
					<div class="grid gap-6 md:grid-cols-2">
						{#each myProgress as quota}
							{@const Icon = metricIcons[quota.metric_type] || Target}
							<Card.Root class="border-0 bg-white shadow-sm transition-shadow hover:shadow-md">
								<Card.Content class="p-6">
									<div class="flex items-start justify-between">
										<div class="flex items-center gap-3">
											<div class="rounded-lg bg-blue-100 p-2">
												<Icon class="h-5 w-5 text-blue-600" />
											</div>
											<div>
												<h3 class="font-semibold text-slate-900">{quota.metric_label}</h3>
												<p class="text-sm text-slate-500">{quota.period.name}</p>
											</div>
										</div>
										{#if quota.is_achieved}
											<Badge class="bg-emerald-100 text-emerald-700">
												<Award class="mr-1 h-3 w-3" />
												Achieved
											</Badge>
										{/if}
									</div>

									<div class="mt-6">
										<div class="flex items-end justify-between">
											<div>
												<span class="text-3xl font-bold {getAttainmentColor(quota.attainment_percent)}">
													{quota.metric_type === 'revenue'
														? formatCurrency(quota.current_value, quota.currency)
														: formatNumber(quota.current_value)}
												</span>
												<span class="text-slate-400"> / {quota.metric_type === 'revenue'
													? formatCurrency(quota.target_value, quota.currency)
													: formatNumber(quota.target_value)}</span>
											</div>
											<div class="text-right">
												<span class="text-2xl font-bold {getAttainmentColor(quota.attainment_percent)}">
													{quota.attainment_percent}%
												</span>
											</div>
										</div>

										<div class="mt-3 h-3 w-full rounded-full bg-slate-100">
											<div
												class="h-full rounded-full transition-all {getAttainmentBg(quota.attainment_percent)}"
												style="width: {Math.min(100, quota.attainment_percent)}%"
											></div>
										</div>
									</div>

									<div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4 text-sm">
										<div class="flex items-center gap-4">
											{#if quota.gap_to_target > 0}
												<span class="text-slate-500">
													<span class="font-medium text-slate-700">
														{quota.metric_type === 'revenue'
															? formatCurrency(quota.gap_to_target, quota.currency)
															: formatNumber(quota.gap_to_target)}
													</span> to go
												</span>
											{/if}
											{#if quota.pace_required}
												<span class="text-slate-500">
													<span class="font-medium text-slate-700">
														{quota.metric_type === 'revenue'
															? formatCurrency(quota.pace_required, quota.currency)
															: formatNumber(quota.pace_required)}
													</span>/day needed
												</span>
											{/if}
										</div>
										<span class="flex items-center text-slate-400">
											<Clock class="mr-1 h-3.5 w-3.5" />
											{quota.period.days_remaining}d left
										</span>
									</div>
								</Card.Content>
							</Card.Root>
						{/each}
					</div>
				{/if}
			</Tabs.Content>

			<!-- Leaderboard Tab -->
			<Tabs.Content value="leaderboard">
				<div class="mb-4 flex items-center justify-between">
					<Select.Root type="single" bind:value={selectedMetric} onValueChange={() => handleMetricChange()}>
						<Select.Trigger class="w-48">
							{selectedMetric === 'revenue' ? 'Revenue' :
							 selectedMetric === 'deals' ? 'Closed Deals' :
							 selectedMetric === 'leads' ? 'New Leads' :
							 selectedMetric.charAt(0).toUpperCase() + selectedMetric.slice(1)}
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="revenue">Revenue</Select.Item>
							<Select.Item value="deals">Closed Deals</Select.Item>
							<Select.Item value="leads">New Leads</Select.Item>
							<Select.Item value="activities">Activities</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>

				{#if myPosition}
					<Card.Root class="mb-6 border-0 bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-lg">
						<Card.Content class="p-6">
							<div class="flex items-center justify-between">
								<div>
									<p class="text-sm font-medium text-amber-100">Your Position</p>
									<div class="mt-1 flex items-baseline gap-2">
										<span class="text-4xl font-bold">{myPosition.rank_badge || `#${myPosition.rank}`}</span>
										<span class="text-amber-100">of {myPosition.total}</span>
									</div>
									<p class="mt-1 text-sm text-amber-100">Top {myPosition.percentile}% of team</p>
								</div>
								<div class="text-right">
									<p class="text-3xl font-bold">{myPosition.attainment_percent}%</p>
									<p class="text-sm text-amber-100">attainment</p>
									{#if myPosition.trend > 0}
										<p class="mt-1 flex items-center justify-end text-sm text-emerald-200">
											<TrendingUp class="mr-1 h-4 w-4" />
											+{myPosition.trend}% this week
										</p>
									{:else if myPosition.trend < 0}
										<p class="mt-1 flex items-center justify-end text-sm text-red-200">
											<TrendingDown class="mr-1 h-4 w-4" />
											{myPosition.trend}% this week
										</p>
									{/if}
								</div>
							</div>
						</Card.Content>
					</Card.Root>
				{/if}

				{#if leaderboard?.entries?.length}
					<Card.Root class="border-0 bg-white shadow-sm">
						<Card.Header class="border-b border-slate-100">
							<Card.Title class="flex items-center gap-2">
								<Trophy class="h-5 w-5 text-amber-500" />
								{leaderboard.period.name} Leaderboard
							</Card.Title>
						</Card.Header>
						<Card.Content class="p-0">
							<div class="divide-y divide-slate-100">
								{#each leaderboard.entries as entry, i}
									<div class="flex items-center gap-4 p-4 transition-colors hover:bg-slate-50">
										<div class="w-12 text-center">
											{#if entry.rank_badge}
												<span class="text-2xl">{entry.rank_badge}</span>
											{:else}
												<span class="text-lg font-semibold text-slate-400">#{entry.rank}</span>
											{/if}
										</div>
										<Avatar.Root class="h-10 w-10">
											<Avatar.Fallback class="bg-slate-200 text-slate-600">
												{entry.user.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
											</Avatar.Fallback>
										</Avatar.Root>
										<div class="flex-1">
											<p class="font-medium text-slate-900">{entry.user.name}</p>
											<p class="text-sm text-slate-500">
												{selectedMetric === 'revenue'
													? formatCurrency(entry.value)
													: formatNumber(entry.value)} / {selectedMetric === 'revenue'
													? formatCurrency(entry.target)
													: formatNumber(entry.target)}
											</p>
										</div>
										<div class="text-right">
											<p class="text-lg font-bold {getAttainmentColor(entry.attainment_percent)}">
												{entry.attainment_percent}%
											</p>
											{#if entry.trend > 0}
												<p class="flex items-center justify-end text-xs text-emerald-600">
													<TrendingUp class="mr-0.5 h-3 w-3" />
													+{entry.trend}%
												</p>
											{:else if entry.trend < 0}
												<p class="flex items-center justify-end text-xs text-red-600">
													<TrendingDown class="mr-0.5 h-3 w-3" />
													{entry.trend}%
												</p>
											{/if}
										</div>
									</div>
								{/each}
							</div>
						</Card.Content>
					</Card.Root>
				{:else}
					<Card.Root class="border-0 bg-white shadow-sm">
						<Card.Content class="flex flex-col items-center justify-center py-16">
							<Trophy class="h-12 w-12 text-slate-300" />
							<h3 class="mt-4 text-lg font-medium text-slate-900">No leaderboard data</h3>
							<p class="text-sm text-slate-500">Quotas need to be set up first</p>
						</Card.Content>
					</Card.Root>
				{/if}
			</Tabs.Content>

			<!-- Team Tab -->
			<Tabs.Content value="team">
				<Card.Root class="border-0 bg-white shadow-sm">
					<Card.Content class="flex flex-col items-center justify-center py-16">
						<Users class="h-12 w-12 text-slate-300" />
						<h3 class="mt-4 text-lg font-medium text-slate-900">Team Overview</h3>
						<p class="text-sm text-slate-500">Coming soon - View your entire team's progress</p>
						<Button class="mt-4" variant="outline" onclick={() => goto('/quotas/leaderboard')}>
							View Leaderboard
							<ChevronRight class="ml-2 h-4 w-4" />
						</Button>
					</Card.Content>
				</Card.Root>
			</Tabs.Content>
		</Tabs.Root>
	</div>
</div>

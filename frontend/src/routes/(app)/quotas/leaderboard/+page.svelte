<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as Avatar from '$lib/components/ui/avatar';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import {
		Trophy,
		Medal,
		ArrowLeft,
		TrendingUp,
		TrendingDown,
		Crown,
		Target,
		DollarSign,
		Users,
		Clock,
		BarChart3,
		Zap,
		RefreshCw
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getLeaderboard,
		getQuotaPeriods,
		refreshLeaderboard,
		type LeaderboardEntry,
		type QuotaPeriod,
		type MetricType
	} from '$lib/api/quotas';

	let loading = $state(true);
	let refreshing = $state(false);
	let periods = $state<QuotaPeriod[]>([]);
	let selectedPeriodId = $state<number | null>(null);
	let selectedMetric = $state<MetricType>('revenue');
	let leaderboardData = $state<{
		period: { id: number; name: string; days_remaining: number };
		metric_type: MetricType;
		entries: LeaderboardEntry[];
	} | null>(null);

	const metricOptions: { value: MetricType; label: string; icon: typeof DollarSign }[] = [
		{ value: 'revenue', label: 'Revenue', icon: DollarSign },
		{ value: 'deals', label: 'Closed Deals', icon: Target },
		{ value: 'leads', label: 'New Leads', icon: Users },
		{ value: 'calls', label: 'Calls Made', icon: Zap },
		{ value: 'meetings', label: 'Meetings', icon: Clock },
		{ value: 'activities', label: 'Activities', icon: BarChart3 }
	];

	onMount(async () => {
		await loadPeriods();
		await loadLeaderboard();
	});

	async function loadPeriods() {
		try {
			const response = await getQuotaPeriods({ active: true, per_page: 50 });
			periods = response.data || [];
			if (periods.length > 0 && !selectedPeriodId) {
				const current = periods.find((p) => p.is_current);
				selectedPeriodId = current?.id || periods[0].id;
			}
		} catch (error) {
			console.error('Failed to load periods:', error);
		}
	}

	async function loadLeaderboard() {
		loading = true;
		try {
			const data = await getLeaderboard({
				period_id: selectedPeriodId || undefined,
				metric_type: selectedMetric,
				limit: 50
			});
			leaderboardData = data;
		} catch (error) {
			console.error('Failed to load leaderboard:', error);
			toast.error('Failed to load leaderboard');
		} finally {
			loading = false;
		}
	}

	async function handleRefresh() {
		refreshing = true;
		try {
			await refreshLeaderboard(selectedPeriodId || undefined, selectedMetric);
			await loadLeaderboard();
			toast.success('Leaderboard refreshed');
		} catch (error) {
			console.error('Failed to refresh:', error);
			toast.error('Failed to refresh leaderboard');
		} finally {
			refreshing = false;
		}
	}

	function formatValue(value: number, metric: MetricType): string {
		if (metric === 'revenue') {
			return new Intl.NumberFormat('en-US', {
				style: 'currency',
				currency: 'USD',
				minimumFractionDigits: 0,
				maximumFractionDigits: 0
			}).format(value);
		}
		if (value >= 1000000) {
			return (value / 1000000).toFixed(1) + 'M';
		}
		if (value >= 1000) {
			return (value / 1000).toFixed(1) + 'K';
		}
		return value.toLocaleString();
	}

	function getAttainmentColor(percent: number): string {
		if (percent >= 100) return 'text-emerald-600';
		if (percent >= 75) return 'text-blue-600';
		if (percent >= 50) return 'text-amber-600';
		return 'text-red-600';
	}

	function getRankDisplay(entry: LeaderboardEntry): { icon: typeof Crown | null; class: string } {
		if (entry.rank === 1) return { icon: Crown, class: 'text-amber-500' };
		if (entry.rank === 2) return { icon: Medal, class: 'text-slate-400' };
		if (entry.rank === 3) return { icon: Medal, class: 'text-amber-700' };
		return { icon: null, class: 'text-slate-500' };
	}
</script>

<svelte:head>
	<title>Leaderboard | VRTX CRM</title>
</svelte:head>

<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white">
	<div class="container mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
		<!-- Header -->
		<div class="mb-8">
			<Button variant="ghost" class="mb-4" onclick={() => goto('/quotas')}>
				<ArrowLeft class="mr-2 h-4 w-4" />
				Back to Quotas
			</Button>
			<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
				<div>
					<h1 class="flex items-center gap-3 text-3xl font-bold tracking-tight text-slate-900">
						<Trophy class="h-8 w-8 text-amber-500" />
						Leaderboard
					</h1>
					<p class="mt-1 text-slate-500">See how you rank against your teammates</p>
				</div>
				<Button variant="outline" onclick={handleRefresh} disabled={refreshing}>
					<RefreshCw class="mr-2 h-4 w-4 {refreshing ? 'animate-spin' : ''}" />
					Refresh
				</Button>
			</div>
		</div>

		<!-- Filters -->
		<Card.Root class="mb-6 border-0 bg-white shadow-sm">
			<Card.Content class="flex flex-wrap gap-4 p-4">
				<div class="flex-1 min-w-[200px]">
					<span class="mb-1.5 block text-sm font-medium text-slate-700">Period</span>
					<Select.Root
						type="single"
						value={selectedPeriodId?.toString()}
						onValueChange={(v) => {
							if (v) {
								selectedPeriodId = parseInt(v);
								loadLeaderboard();
							}
						}}
					>
						<Select.Trigger class="w-full">
							{periods.find((p) => p.id === selectedPeriodId)?.name || 'Select period'}
						</Select.Trigger>
						<Select.Content>
							{#each periods as period}
								<Select.Item value={period.id.toString()}>
									{period.name}
									{#if period.is_current}
										<Badge variant="secondary" class="ml-2">Current</Badge>
									{/if}
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="flex-1 min-w-[200px]">
					<span class="mb-1.5 block text-sm font-medium text-slate-700">Metric</span>
					<Select.Root
						type="single"
						value={selectedMetric}
						onValueChange={(v) => {
							if (v) {
								selectedMetric = v as MetricType;
								loadLeaderboard();
							}
						}}
					>
						<Select.Trigger class="w-full">
							{metricOptions.find((m) => m.value === selectedMetric)?.label || selectedMetric}
						</Select.Trigger>
						<Select.Content>
							{#each metricOptions as option}
								<Select.Item value={option.value}>{option.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</Card.Content>
		</Card.Root>

		<!-- Period Info -->
		{#if leaderboardData?.period}
			<div class="mb-6 flex items-center justify-between rounded-lg bg-blue-50 px-4 py-3">
				<span class="font-medium text-blue-900">{leaderboardData.period.name}</span>
				<span class="text-sm text-blue-700">
					{leaderboardData.period.days_remaining} days remaining
				</span>
			</div>
		{/if}

		<!-- Leaderboard -->
		{#if loading}
			<div class="flex items-center justify-center py-16">
				<div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-blue-600"></div>
			</div>
		{:else if !leaderboardData?.entries?.length}
			<Card.Root class="border-0 bg-white shadow-sm">
				<Card.Content class="flex flex-col items-center justify-center py-16">
					<Trophy class="h-16 w-16 text-slate-200" />
					<h3 class="mt-4 text-xl font-medium text-slate-900">No rankings yet</h3>
					<p class="mt-1 text-slate-500">Quotas need to be assigned to show the leaderboard</p>
				</Card.Content>
			</Card.Root>
		{:else}
			<!-- Top 3 Podium -->
			{#if leaderboardData.entries.length >= 3}
				<div class="mb-8 grid grid-cols-3 gap-4">
					<!-- 2nd Place -->
					<div class="pt-8">
						<Card.Root class="relative border-0 bg-gradient-to-b from-slate-100 to-white shadow-sm">
							<div class="absolute -top-4 left-1/2 -translate-x-1/2">
								<div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-400 text-white shadow-lg">
									<Medal class="h-5 w-5" />
								</div>
							</div>
							<Card.Content class="pt-10 pb-6 text-center">
								<Avatar.Root class="mx-auto h-16 w-16">
									<Avatar.Fallback class="bg-slate-200 text-lg text-slate-600">
										{leaderboardData.entries[1].user.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
									</Avatar.Fallback>
								</Avatar.Root>
								<h3 class="mt-3 font-semibold text-slate-900">{leaderboardData.entries[1].user.name}</h3>
								<p class="text-2xl font-bold text-slate-600">
									{formatValue(leaderboardData.entries[1].value, selectedMetric)}
								</p>
								<p class="text-sm text-slate-500">{leaderboardData.entries[1].attainment_percent}% attainment</p>
							</Card.Content>
						</Card.Root>
					</div>

					<!-- 1st Place -->
					<div>
						<Card.Root class="relative border-0 bg-gradient-to-b from-amber-100 to-white shadow-lg">
							<div class="absolute -top-4 left-1/2 -translate-x-1/2">
								<div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-500 text-white shadow-lg">
									<Crown class="h-6 w-6" />
								</div>
							</div>
							<Card.Content class="pt-12 pb-6 text-center">
								<Avatar.Root class="mx-auto h-20 w-20 ring-4 ring-amber-200">
									<Avatar.Fallback class="bg-amber-100 text-xl text-amber-700">
										{leaderboardData.entries[0].user.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
									</Avatar.Fallback>
								</Avatar.Root>
								<h3 class="mt-3 text-lg font-bold text-slate-900">{leaderboardData.entries[0].user.name}</h3>
								<p class="text-3xl font-bold text-amber-600">
									{formatValue(leaderboardData.entries[0].value, selectedMetric)}
								</p>
								<p class="text-sm text-slate-500">{leaderboardData.entries[0].attainment_percent}% attainment</p>
							</Card.Content>
						</Card.Root>
					</div>

					<!-- 3rd Place -->
					<div class="pt-12">
						<Card.Root class="relative border-0 bg-gradient-to-b from-amber-50 to-white shadow-sm">
							<div class="absolute -top-4 left-1/2 -translate-x-1/2">
								<div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-700 text-white shadow-lg">
									<Medal class="h-5 w-5" />
								</div>
							</div>
							<Card.Content class="pt-10 pb-6 text-center">
								<Avatar.Root class="mx-auto h-14 w-14">
									<Avatar.Fallback class="bg-amber-100 text-amber-700">
										{leaderboardData.entries[2].user.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
									</Avatar.Fallback>
								</Avatar.Root>
								<h3 class="mt-3 font-semibold text-slate-900">{leaderboardData.entries[2].user.name}</h3>
								<p class="text-xl font-bold text-amber-700">
									{formatValue(leaderboardData.entries[2].value, selectedMetric)}
								</p>
								<p class="text-sm text-slate-500">{leaderboardData.entries[2].attainment_percent}% attainment</p>
							</Card.Content>
						</Card.Root>
					</div>
				</div>
			{/if}

			<!-- Full Rankings List -->
			<Card.Root class="border-0 bg-white shadow-sm">
				<Card.Header class="border-b border-slate-100">
					<Card.Title>Full Rankings</Card.Title>
				</Card.Header>
				<Card.Content class="p-0">
					<div class="divide-y divide-slate-100">
						{#each leaderboardData.entries as entry, i}
							{@const rankDisplay = getRankDisplay(entry)}
							<div class="flex items-center gap-4 p-4 transition-colors hover:bg-slate-50">
								<div class="w-16 text-center">
									{#if entry.rank_badge}
										<span class="text-2xl">{entry.rank_badge}</span>
									{:else if rankDisplay.icon}
										<rankDisplay.icon class="mx-auto h-6 w-6 {rankDisplay.class}" />
									{:else}
										<span class="text-lg font-semibold text-slate-400">#{entry.rank}</span>
									{/if}
								</div>

								<Avatar.Root class="h-12 w-12">
									<Avatar.Fallback class="bg-slate-200 text-slate-600">
										{entry.user.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
									</Avatar.Fallback>
								</Avatar.Root>

								<div class="flex-1">
									<p class="font-semibold text-slate-900">{entry.user.name}</p>
									<div class="flex items-center gap-2 text-sm text-slate-500">
										<span>{formatValue(entry.value, selectedMetric)}</span>
										<span class="text-slate-300">•</span>
										<span>Target: {formatValue(entry.target, selectedMetric)}</span>
									</div>
								</div>

								<div class="text-right">
									<p class="text-xl font-bold {getAttainmentColor(entry.attainment_percent)}">
										{entry.attainment_percent}%
									</p>
									{#if entry.trend !== 0}
										<p class="flex items-center justify-end text-sm {entry.trend > 0 ? 'text-emerald-600' : 'text-red-600'}">
											{#if entry.trend > 0}
												<TrendingUp class="mr-1 h-4 w-4" />
												+{entry.trend}%
											{:else}
												<TrendingDown class="mr-1 h-4 w-4" />
												{entry.trend}%
											{/if}
										</p>
									{/if}
								</div>

								{#if entry.gap !== 0}
									<div class="w-32 text-right text-sm">
										{#if entry.gap > 0}
											<span class="text-red-600">
												{formatValue(entry.gap, selectedMetric)} to go
											</span>
										{:else}
											<span class="text-emerald-600">
												+{formatValue(Math.abs(entry.gap), selectedMetric)} over
											</span>
										{/if}
									</div>
								{/if}
							</div>
						{/each}
					</div>
				</Card.Content>
			</Card.Root>
		{/if}
	</div>
</div>

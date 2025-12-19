<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Progress } from '$lib/components/ui/progress';
	import { Badge } from '$lib/components/ui/badge';
	import { TrendingUp, TrendingDown, Target, DollarSign, PieChart } from 'lucide-svelte';
	import { forecastsApi, type ForecastSummary, type PeriodType } from '$lib/api/forecasts';
	import { onMount } from 'svelte';

	interface Props {
		title?: string;
		config?: {
			module_api_name?: string;
			period_type?: PeriodType;
			user_id?: number;
			show_breakdown?: boolean;
			show_quota?: boolean;
		};
		data?: ForecastSummary | null;
		loading?: boolean;
	}

	let { title = 'Sales Forecast', config = {}, data = null, loading = false }: Props = $props();

	let forecastData = $state<ForecastSummary | null>(data);
	let isLoading = $state(loading);
	let error = $state<string | null>(null);

	const moduleApiName = config.module_api_name ?? 'deals';
	const periodType = config.period_type ?? 'quarter';
	const showBreakdown = config.show_breakdown ?? true;
	const showQuota = config.show_quota ?? true;

	onMount(async () => {
		if (!forecastData) {
			await loadForecast();
		}
	});

	async function loadForecast() {
		isLoading = true;
		error = null;
		try {
			forecastData = await forecastsApi.getSummary({
				module_api_name: moduleApiName,
				period_type: periodType,
				user_id: config.user_id
			});
		} catch (e) {
			error = 'Failed to load forecast';
			console.error('Forecast load error:', e);
		} finally {
			isLoading = false;
		}
	}

	function formatCurrency(value: number): string {
		if (value >= 1000000) {
			return '$' + (value / 1000000).toFixed(1) + 'M';
		} else if (value >= 1000) {
			return '$' + (value / 1000).toFixed(0) + 'K';
		}
		return '$' + value.toLocaleString();
	}

	function formatPeriod(period: ForecastSummary['period']): string {
		const start = new Date(period.start);
		const end = new Date(period.end);

		switch (period.type) {
			case 'quarter':
				const q = Math.ceil((start.getMonth() + 1) / 3);
				return `Q${q} ${start.getFullYear()}`;
			case 'month':
				return start.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
			case 'year':
				return start.getFullYear().toString();
			default:
				return `${start.toLocaleDateString()} - ${end.toLocaleDateString()}`;
		}
	}

	const quotaProgress = $derived(() => {
		if (!forecastData?.quota) return 0;
		return Math.min(100, Math.round(forecastData.quota.attainment));
	});

	const totalForecast = $derived(() => {
		if (!forecastData) return 0;
		return forecastData.closed_won.amount + forecastData.commit.amount;
	});

	const totalPipeline = $derived(() => {
		if (!forecastData) return 0;
		return (
			forecastData.commit.amount + forecastData.best_case.amount + forecastData.pipeline.amount
		);
	});
</script>

<Card.Root class="h-full">
	<Card.Header class="pb-2">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-2">
				<PieChart class="text-muted-foreground h-4 w-4" />
				<Card.Title class="text-sm font-medium">{title}</Card.Title>
			</div>
			{#if forecastData}
				<Badge variant="outline" class="text-xs">
					{formatPeriod(forecastData.period)}
				</Badge>
			{/if}
		</div>
	</Card.Header>
	<Card.Content class="space-y-4">
		{#if isLoading}
			<div class="flex animate-pulse flex-col items-center py-6">
				<div class="bg-muted h-12 w-32 rounded"></div>
				<div class="bg-muted mt-3 h-4 w-24 rounded"></div>
			</div>
		{:else if error}
			<div class="text-muted-foreground py-6 text-center text-sm">{error}</div>
		{:else if forecastData}
			<!-- Main Forecast Number -->
			<div class="text-center">
				<div class="text-3xl font-bold">{formatCurrency(totalForecast())}</div>
				<div class="text-muted-foreground text-xs">Forecast (Closed + Commit)</div>
			</div>

			<!-- Quota Progress -->
			{#if showQuota && forecastData.quota}
				<div class="space-y-2">
					<div class="flex items-center justify-between text-sm">
						<span class="text-muted-foreground flex items-center gap-1">
							<Target class="h-3 w-3" />
							Quota Progress
						</span>
						<span class="font-medium">{quotaProgress()}%</span>
					</div>
					<Progress value={quotaProgress()} class="h-2" />
					<div class="text-muted-foreground flex justify-between text-xs">
						<span>{formatCurrency(forecastData.closed_won.amount)} closed</span>
						<span>{formatCurrency(forecastData.quota.amount)} target</span>
					</div>
				</div>
			{/if}

			<!-- Category Breakdown -->
			{#if showBreakdown}
				<div class="space-y-2 border-t pt-3">
					<!-- Closed Won -->
					<div class="flex items-center justify-between text-sm">
						<div class="flex items-center gap-2">
							<div class="h-2 w-2 rounded-full bg-green-500"></div>
							<span>Closed Won</span>
							<Badge variant="secondary" class="text-xs">{forecastData.closed_won.count}</Badge>
						</div>
						<span class="font-medium text-green-600">
							{formatCurrency(forecastData.closed_won.amount)}
						</span>
					</div>

					<!-- Commit -->
					<div class="flex items-center justify-between text-sm">
						<div class="flex items-center gap-2">
							<div class="h-2 w-2 rounded-full bg-blue-500"></div>
							<span>Commit</span>
							<Badge variant="secondary" class="text-xs">{forecastData.commit.count}</Badge>
						</div>
						<span class="font-medium text-blue-600">
							{formatCurrency(forecastData.commit.amount)}
						</span>
					</div>

					<!-- Best Case -->
					<div class="flex items-center justify-between text-sm">
						<div class="flex items-center gap-2">
							<div class="h-2 w-2 rounded-full bg-yellow-500"></div>
							<span>Best Case</span>
							<Badge variant="secondary" class="text-xs">{forecastData.best_case.count}</Badge>
						</div>
						<span class="font-medium text-yellow-600">
							{formatCurrency(forecastData.best_case.amount)}
						</span>
					</div>

					<!-- Pipeline -->
					<div class="flex items-center justify-between text-sm">
						<div class="flex items-center gap-2">
							<div class="bg-muted h-2 w-2 rounded-full"></div>
							<span>Pipeline</span>
							<Badge variant="secondary" class="text-xs">{forecastData.pipeline.count}</Badge>
						</div>
						<span class="text-muted-foreground font-medium">
							{formatCurrency(forecastData.pipeline.amount)}
						</span>
					</div>
				</div>

				<!-- Weighted Pipeline -->
				<div class="bg-muted/50 flex items-center justify-between rounded-lg p-2 text-sm">
					<span class="text-muted-foreground">Weighted Pipeline</span>
					<span class="font-medium">{formatCurrency(forecastData.weighted.amount)}</span>
				</div>
			{/if}
		{:else}
			<div class="text-muted-foreground py-6 text-center text-sm">No forecast data available</div>
		{/if}
	</Card.Content>
</Card.Root>

<script lang="ts">
	import type { CohortResult } from '$lib/api/reports';

	interface Props {
		data: CohortResult;
		title?: string;
		showValues?: boolean;
		colorScale?: 'blue' | 'green' | 'purple' | 'orange';
	}

	let { data, title = 'Cohort Analysis', showValues = true, colorScale = 'blue' }: Props = $props();

	// Format date for display
	function formatDate(dateStr: string, interval: string): string {
		const date = new Date(dateStr);
		switch (interval) {
			case 'day':
				return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
			case 'week':
				return `W${getWeekNumber(date)}`;
			case 'month':
				return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
			case 'quarter':
				return `Q${Math.floor(date.getMonth() / 3) + 1} ${date.getFullYear().toString().slice(-2)}`;
			case 'year':
				return date.getFullYear().toString();
			default:
				return dateStr;
		}
	}

	function getWeekNumber(date: Date): number {
		const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
		const dayNum = d.getUTCDay() || 7;
		d.setUTCDate(d.getUTCDate() + 4 - dayNum);
		const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
		return Math.ceil(((d.getTime() - yearStart.getTime()) / 86400000 + 1) / 7);
	}

	// Calculate max value for color scaling
	let maxValue = $derived.by(() => {
		let max = 0;
		for (const cohort of Object.values(data.data)) {
			for (const value of Object.values(cohort)) {
				if (value > max) max = value;
			}
		}
		return max || 1;
	});

	// Get color intensity based on value
	function getColorIntensity(value: number): string {
		const intensity = Math.min(value / maxValue, 1);
		const colors = {
			blue: `rgba(59, 130, 246, ${0.1 + intensity * 0.8})`,
			green: `rgba(34, 197, 94, ${0.1 + intensity * 0.8})`,
			purple: `rgba(168, 85, 247, ${0.1 + intensity * 0.8})`,
			orange: `rgba(249, 115, 22, ${0.1 + intensity * 0.8})`
		};
		return colors[colorScale];
	}

	// Get text color based on intensity
	function getTextColor(value: number): string {
		const intensity = value / maxValue;
		return intensity > 0.5 ? 'text-white' : 'text-foreground';
	}

	// Format value for display
	function formatValue(value: number): string {
		if (value >= 1000000) {
			return (value / 1000000).toFixed(1) + 'M';
		}
		if (value >= 1000) {
			return (value / 1000).toFixed(1) + 'K';
		}
		return value.toString();
	}

	// Calculate period index (for retention-style display)
	function getPeriodIndex(cohortDate: string, periodDate: string): number {
		const cohort = new Date(cohortDate);
		const period = new Date(periodDate);
		const diffTime = period.getTime() - cohort.getTime();
		const diffDays = diffTime / (1000 * 60 * 60 * 24);

		switch (data.config.period_interval) {
			case 'day':
				return Math.floor(diffDays);
			case 'week':
				return Math.floor(diffDays / 7);
			case 'month':
				return (
					(period.getFullYear() - cohort.getFullYear()) * 12 +
					period.getMonth() -
					cohort.getMonth()
				);
			case 'quarter':
				return Math.floor(
					((period.getFullYear() - cohort.getFullYear()) * 12 +
						period.getMonth() -
						cohort.getMonth()) /
						3
				);
			case 'year':
				return period.getFullYear() - cohort.getFullYear();
			default:
				return 0;
		}
	}
</script>

<div class="space-y-4">
	{#if title}
		<h3 class="text-lg font-semibold">{title}</h3>
	{/if}

	{#if data.cohorts.length === 0}
		<div class="flex h-40 items-center justify-center rounded-lg border border-dashed">
			<p class="text-muted-foreground">No cohort data available</p>
		</div>
	{:else}
		<div class="overflow-x-auto">
			<table class="w-full border-collapse text-sm">
				<thead>
					<tr>
						<th class="border bg-muted px-3 py-2 text-left font-medium">Cohort</th>
						{#each data.periods as period}
							<th class="border bg-muted px-3 py-2 text-center font-medium">
								{formatDate(period, data.config.period_interval)}
							</th>
						{/each}
					</tr>
				</thead>
				<tbody>
					{#each data.cohorts as cohort}
						{@const cohortData = data.data[cohort] || {}}
						<tr>
							<td class="border bg-muted/50 px-3 py-2 font-medium">
								{formatDate(cohort, data.config.cohort_interval)}
							</td>
							{#each data.periods as period}
								{@const value = cohortData[period] || 0}
								{@const periodIdx = getPeriodIndex(cohort, period)}
								<td
									class="border px-3 py-2 text-center transition-colors {getTextColor(value)}"
									style="background-color: {periodIdx >= 0 ? getColorIntensity(value) : 'transparent'}"
									title="{formatDate(cohort, data.config.cohort_interval)} → {formatDate(period, data.config.period_interval)}: {value}"
								>
									{#if periodIdx >= 0 && showValues}
										{formatValue(value)}
									{:else if periodIdx < 0}
										<span class="text-muted-foreground">-</span>
									{/if}
								</td>
							{/each}
						</tr>
					{/each}
				</tbody>
			</table>
		</div>

		<!-- Legend -->
		<div class="flex items-center justify-between text-xs text-muted-foreground">
			<div class="flex items-center gap-2">
				<span>Low</span>
				<div class="flex">
					{#each [0.2, 0.4, 0.6, 0.8, 1] as intensity}
						<div
							class="h-4 w-6"
							style="background-color: {getColorIntensity(intensity * maxValue)}"
						></div>
					{/each}
				</div>
				<span>High</span>
			</div>
			<div>
				<span class="capitalize">{data.config.metric_aggregation}</span> by
				<span class="capitalize">{data.config.cohort_interval}</span> cohort
			</div>
		</div>
	{/if}
</div>

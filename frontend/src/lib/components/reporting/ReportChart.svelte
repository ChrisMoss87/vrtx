<script lang="ts">
	import { onMount } from 'svelte';
	import * as Card from '$lib/components/ui/card';
	import { BarChart2, LineChart, PieChart, TrendingUp } from 'lucide-svelte';
	import type { ChartType } from '$lib/api/reports';

	interface Props {
		data: Record<string, any>[];
		chartType: ChartType;
		title?: string;
		height?: number;
	}

	let { data, chartType, title, height = 300 }: Props = $props();

	// Get chart dimensions
	let chartContainer: HTMLDivElement;
	let chartWidth = $state(0);

	onMount(() => {
		if (chartContainer) {
			chartWidth = chartContainer.clientWidth;
		}
	});

	// Process data for chart display
	const chartData = $derived(() => {
		if (!data || data.length === 0) return { labels: [], values: [] };

		const keys = Object.keys(data[0]);
		const labelKey = keys[0]; // First column as labels
		const valueKey = keys.find((k) => typeof data[0][k] === 'number') || keys[1]; // First numeric column as values

		return {
			labels: data.map((d) => String(d[labelKey] || '')),
			values: data.map((d) => Number(d[valueKey]) || 0),
			labelKey,
			valueKey
		};
	});

	// Calculate max value for scaling
	const maxValue = $derived(() => {
		const values = chartData().values;
		return Math.max(...values, 1);
	});

	// Line chart constants
	const lineChartPadding = 40;
	const lineChartHeight = $derived(height - lineChartPadding * 2);
	const lineChartWidth = $derived((chartWidth || 400) - lineChartPadding * 2);

	// Calculate line chart points
	const lineChartPoints = $derived(() => {
		const values = chartData().values;
		return values
			.map((v, i) => {
				const x = lineChartPadding + (i / (values.length - 1 || 1)) * lineChartWidth;
				const y = lineChartPadding + lineChartHeight - (v / maxValue()) * lineChartHeight;
				return `${x},${y}`;
			})
			.join(' ');
	});

	// Colors for charts
	const colors = [
		'hsl(var(--primary))',
		'hsl(var(--chart-2, 160 60% 45%))',
		'hsl(var(--chart-3, 30 80% 55%))',
		'hsl(var(--chart-4, 280 65% 60%))',
		'hsl(var(--chart-5, 340 75% 55%))',
		'hsl(200 70% 50%)',
		'hsl(120 60% 45%)',
		'hsl(45 90% 50%)'
	];

	// Get icon for chart type
	function getChartIcon(type: ChartType) {
		switch (type) {
			case 'bar':
				return BarChart2;
			case 'line':
			case 'area':
				return LineChart;
			case 'pie':
			case 'doughnut':
				return PieChart;
			default:
				return TrendingUp;
		}
	}

	// Format number for display
	function formatNumber(value: number): string {
		if (value >= 1000000) {
			return (value / 1000000).toFixed(1) + 'M';
		} else if (value >= 1000) {
			return (value / 1000).toFixed(1) + 'K';
		}
		return value.toLocaleString();
	}

	// Calculate pie chart segments
	function getPieSegments() {
		const { values, labels } = chartData();
		const total = values.reduce((a, b) => a + b, 0);
		let currentAngle = -90; // Start from top

		return values.map((value, i) => {
			const percentage = total > 0 ? (value / total) * 100 : 0;
			const angle = (percentage / 100) * 360;
			const startAngle = currentAngle;
			const endAngle = currentAngle + angle;
			currentAngle = endAngle;

			// Calculate SVG arc path
			const largeArc = angle > 180 ? 1 : 0;
			const startRad = (startAngle * Math.PI) / 180;
			const endRad = (endAngle * Math.PI) / 180;
			const radius = 80;
			const centerX = 100;
			const centerY = 100;

			const x1 = centerX + radius * Math.cos(startRad);
			const y1 = centerY + radius * Math.sin(startRad);
			const x2 = centerX + radius * Math.cos(endRad);
			const y2 = centerY + radius * Math.sin(endRad);

			return {
				label: labels[i],
				value,
				percentage,
				color: colors[i % colors.length],
				path: `M ${centerX} ${centerY} L ${x1} ${y1} A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2} Z`
			};
		});
	}
</script>

<div bind:this={chartContainer} class="w-full" style="height: {height}px;">
	{#if !data || data.length === 0}
		<div class="flex h-full items-center justify-center text-muted-foreground">
			No data to display
		</div>
	{:else if chartType === 'bar'}
		<!-- Bar Chart -->
		<div class="flex h-full flex-col">
			<div class="flex flex-1 items-end gap-2 pb-6">
				{#each chartData().values as value, i}
					{@const heightPercent = (value / maxValue()) * 100}
					<div class="group relative flex flex-1 flex-col items-center">
						<div
							class="w-full max-w-12 rounded-t-sm bg-primary transition-all hover:opacity-80"
							style="height: {heightPercent}%;"
						></div>
						<span
							class="mt-2 max-w-full truncate text-xs text-muted-foreground"
							title={chartData().labels[i]}
						>
							{chartData().labels[i]}
						</span>
						<!-- Tooltip -->
						<div
							class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 rounded bg-popover px-2 py-1 text-xs opacity-0 shadow-md transition-opacity group-hover:opacity-100"
						>
							{formatNumber(value)}
						</div>
					</div>
				{/each}
			</div>
		</div>
	{:else if chartType === 'line' || chartType === 'area'}
		<!-- Line/Area Chart -->
		<svg viewBox="0 0 {chartWidth || 400} {height}" class="h-full w-full">
			<!-- Grid lines -->
			{#each [0, 0.25, 0.5, 0.75, 1] as tick}
				<line
					x1={lineChartPadding}
					y1={lineChartPadding + lineChartHeight * tick}
					x2={lineChartPadding + lineChartWidth}
					y2={lineChartPadding + lineChartHeight * tick}
					stroke="currentColor"
					stroke-opacity="0.1"
				/>
			{/each}

			<!-- Area fill -->
			{#if chartType === 'area'}
				<polygon
					points="{lineChartPadding},{lineChartPadding + lineChartHeight} {lineChartPoints()} {lineChartPadding + lineChartWidth},{lineChartPadding + lineChartHeight}"
					fill="hsl(var(--primary))"
					fill-opacity="0.2"
				/>
			{/if}

			<!-- Line -->
			<polyline
				points={lineChartPoints()}
				fill="none"
				stroke="hsl(var(--primary))"
				stroke-width="2"
				stroke-linecap="round"
				stroke-linejoin="round"
			/>

			<!-- Data points -->
			{#each chartData().values as value, i}
				{@const x = lineChartPadding + (i / (chartData().values.length - 1 || 1)) * lineChartWidth}
				{@const y = lineChartPadding + lineChartHeight - (value / maxValue()) * lineChartHeight}
				<circle cx={x} cy={y} r="4" fill="hsl(var(--primary))" />
			{/each}
		</svg>
	{:else if chartType === 'pie' || chartType === 'doughnut'}
		<!-- Pie/Doughnut Chart -->
		<div class="flex h-full items-center justify-center gap-8">
			<svg viewBox="0 0 200 200" class="h-48 w-48">
				{#each getPieSegments() as segment}
					<path d={segment.path} fill={segment.color} class="hover:opacity-80" />
				{/each}
				{#if chartType === 'doughnut'}
					<circle cx="100" cy="100" r="50" fill="hsl(var(--background))" />
				{/if}
			</svg>
			<!-- Legend -->
			<div class="flex flex-col gap-2">
				{#each getPieSegments() as segment}
					<div class="flex items-center gap-2">
						<div class="h-3 w-3 rounded-sm" style="background-color: {segment.color};"></div>
						<span class="text-sm">{segment.label}</span>
						<span class="text-sm text-muted-foreground">({segment.percentage.toFixed(1)}%)</span>
					</div>
				{/each}
			</div>
		</div>
	{:else if chartType === 'kpi'}
		<!-- KPI Card -->
		{@const value = chartData().values[0] || 0}
		<div class="flex h-full flex-col items-center justify-center">
			<div class="text-5xl font-bold">{formatNumber(value)}</div>
			{#if chartData().labels[0]}
				<div class="mt-2 text-muted-foreground">{chartData().labels[0]}</div>
			{/if}
		</div>
	{:else}
		<!-- Fallback: simple table-like display -->
		<div class="space-y-2">
			{#each data.slice(0, 10) as row, i}
				<div class="flex items-center justify-between rounded border p-2">
					<span class="text-sm">{chartData().labels[i]}</span>
					<span class="font-medium">{formatNumber(chartData().values[i])}</span>
				</div>
			{/each}
			{#if data.length > 10}
				<p class="text-center text-sm text-muted-foreground">
					+{data.length - 10} more items
				</p>
			{/if}
		</div>
	{/if}
</div>

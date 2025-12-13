<script lang="ts">
	import { onMount } from 'svelte';
	import * as Card from '$lib/components/ui/card';
	import { BarChart2 } from 'lucide-svelte';

	interface Props {
		title: string;
		data: {
			labels?: string[];
			datasets?: Array<{
				label: string;
				data: number[];
				backgroundColor?: string | string[];
			}>;
			chart_type?: 'bar' | 'line' | 'pie' | 'doughnut' | 'area';
		} | null;
		loading?: boolean;
		chartType?: 'bar' | 'line' | 'pie' | 'doughnut' | 'area';
	}

	let { title, data, loading = false, chartType = 'bar' }: Props = $props();

	let canvasRef = $state<HTMLCanvasElement | null>(null);
	let chart = $state<any>(null);

	// Color palette for charts
	const colors = [
		'rgba(59, 130, 246, 0.8)', // blue
		'rgba(16, 185, 129, 0.8)', // green
		'rgba(245, 158, 11, 0.8)', // amber
		'rgba(239, 68, 68, 0.8)', // red
		'rgba(139, 92, 246, 0.8)', // purple
		'rgba(236, 72, 153, 0.8)', // pink
		'rgba(6, 182, 212, 0.8)', // cyan
		'rgba(249, 115, 22, 0.8)' // orange
	];

	const borderColors = colors.map((c) => c.replace('0.8', '1'));

	onMount(() => {
		async function initChart() {
			if (!canvasRef || !data?.labels || !data?.datasets) return;

			try {
				const { Chart, registerables } = await import('chart.js');
				Chart.register(...registerables);

				const type = data.chart_type || chartType;

				chart = new Chart(canvasRef, {
					type: type === 'area' ? 'line' : type,
					data: {
						labels: data.labels,
						datasets: data.datasets.map((ds, i) => ({
							...ds,
							backgroundColor:
								ds.backgroundColor ||
								(type === 'pie' || type === 'doughnut' ? colors : colors[i % colors.length]),
							borderColor:
								type === 'line' || type === 'area' ? borderColors[i % borderColors.length] : undefined,
							borderWidth: type === 'pie' || type === 'doughnut' ? 1 : undefined,
							fill: type === 'area',
							tension: type === 'line' || type === 'area' ? 0.3 : undefined
						}))
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: {
								display: type === 'pie' || type === 'doughnut',
								position: 'bottom',
								labels: {
									boxWidth: 12,
									padding: 8,
									font: { size: 11 }
								}
							}
						},
						scales:
							type !== 'pie' && type !== 'doughnut'
								? {
										y: {
											beginAtZero: true,
											ticks: { font: { size: 10 } }
										},
										x: {
											ticks: { font: { size: 10 }, maxRotation: 45 }
										}
									}
								: undefined
					}
				});
			} catch (error) {
				console.error('Failed to load chart.js:', error);
			}
		}

		initChart();

		return () => {
			if (chart) {
				chart.destroy();
			}
		};
	});

	// Update chart when data changes
	$effect(() => {
		if (chart && data?.labels && data?.datasets) {
			chart.data.labels = data.labels;
			chart.data.datasets = data.datasets.map((ds, i) => ({
				...ds,
				backgroundColor: ds.backgroundColor || colors[i % colors.length],
				borderColor: borderColors[i % borderColors.length]
			}));
			chart.update();
		}
	});
</script>

<Card.Root class="h-full">
	<Card.Header class="pb-2">
		<div class="flex items-center gap-2">
			<BarChart2 class="h-4 w-4 text-muted-foreground" />
			<Card.Title class="text-sm font-medium">{title}</Card.Title>
		</div>
	</Card.Header>
	<Card.Content class="flex-1">
		{#if loading}
			<div class="flex min-h-[200px] animate-pulse items-center justify-center">
				<div class="h-full w-full rounded bg-muted"></div>
			</div>
		{:else if !data?.labels || !data?.datasets}
			<div class="flex min-h-[200px] flex-col items-center justify-center text-muted-foreground">
				<BarChart2 class="mb-2 h-8 w-8" />
				<p class="text-sm">No chart data available</p>
			</div>
		{:else}
			<div class="relative min-h-[200px] h-full">
				<canvas bind:this={canvasRef}></canvas>
			</div>
		{/if}
	</Card.Content>
</Card.Root>

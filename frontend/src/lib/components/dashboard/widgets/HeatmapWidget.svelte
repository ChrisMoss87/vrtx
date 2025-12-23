<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Grid3x3, ExternalLink } from 'lucide-svelte';
	import type { WidgetConfig } from '$lib/api/dashboards';
	import {
		navigateToRecords,
		buildFiltersFromKpiConfig
	} from '$lib/stores/dashboardNavigation.svelte';
	import type { FilterConfig } from '$lib/types/filters';

	interface HeatmapCell {
		x: string | number;
		y: string | number;
		value: number;
	}

	interface Props {
		title: string;
		data: {
			cells: HeatmapCell[];
			x_labels: string[];
			y_labels: string[];
			min_value?: number;
			max_value?: number;
			value_label?: string;
			module_api_name?: string;
			x_field?: string;
			y_field?: string;
		} | null;
		config?: WidgetConfig;
		loading?: boolean;
	}

	let { title, data, config, loading = false }: Props = $props();

	// Check if click-through is enabled
	const isClickable = $derived(
		config?.module_id && config?.click_enabled !== false && data?.module_api_name
	);

	function handleCellClick(xLabel: string, yLabel: string) {
		if (!isClickable || !data?.module_api_name) return;

		const filters: FilterConfig[] = buildFiltersFromKpiConfig(config || {});

		// Add filters for the clicked cell
		const xField = data.x_field || config?.x_field || 'x';
		const yField = data.y_field || config?.y_field || 'y';

		filters.push({ field: xField, operator: 'equals', value: xLabel });
		filters.push({ field: yField, operator: 'equals', value: yLabel });

		navigateToRecords(data.module_api_name, filters);
	}

	const minValue = $derived(() => {
		if (!data?.cells || data.cells.length === 0) return 0;
		return data.min_value ?? Math.min(...data.cells.map((c) => c.value));
	});

	const maxValue = $derived(() => {
		if (!data?.cells || data.cells.length === 0) return 1;
		return data.max_value ?? Math.max(...data.cells.map((c) => c.value));
	});

	function getCellValue(xLabel: string, yLabel: string): number {
		if (!data?.cells) return 0;
		const cell = data.cells.find((c) => String(c.x) === xLabel && String(c.y) === yLabel);
		return cell?.value ?? 0;
	}

	function getIntensity(value: number): number {
		const min = minValue();
		const max = maxValue();
		if (max === min) return 0.5;
		return (value - min) / (max - min);
	}

	function getCellColor(value: number): string {
		const intensity = getIntensity(value);
		// Use a blue color scale
		if (intensity === 0) return 'bg-muted';
		if (intensity < 0.2) return 'bg-blue-100 dark:bg-blue-950';
		if (intensity < 0.4) return 'bg-blue-200 dark:bg-blue-900';
		if (intensity < 0.6) return 'bg-blue-300 dark:bg-blue-800';
		if (intensity < 0.8) return 'bg-blue-400 dark:bg-blue-700';
		return 'bg-blue-500 dark:bg-blue-600';
	}

	function formatValue(value: number): string {
		if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
		if (value >= 1000) return (value / 1000).toFixed(1) + 'K';
		return value.toLocaleString();
	}
</script>

<Card.Root class="flex h-full flex-col group">
	<Card.Header class="pb-2">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-2">
				<Grid3x3 class="h-4 w-4 text-muted-foreground" />
				<Card.Title class="text-sm font-medium">{title}</Card.Title>
			</div>
			{#if isClickable}
				<ExternalLink class="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
			{/if}
		</div>
	</Card.Header>
	<Card.Content class="flex flex-1 flex-col overflow-auto">
		{#if loading}
			<div class="flex flex-1 items-center justify-center">
				<div class="h-32 w-full animate-pulse rounded bg-muted"></div>
			</div>
		{:else if data?.x_labels && data?.y_labels}
			<div class="flex flex-1 flex-col">
				<!-- Heatmap grid -->
				<div class="flex-1 overflow-auto">
					<table class="w-full border-collapse">
						<thead>
							<tr>
								<th class="p-1 text-xs font-normal text-muted-foreground"></th>
								{#each data.x_labels as xLabel}
									<th
										class="p-1 text-center text-xs font-normal text-muted-foreground"
										title={xLabel}
									>
										{xLabel.length > 3 ? xLabel.slice(0, 3) : xLabel}
									</th>
								{/each}
							</tr>
						</thead>
						<tbody>
							{#each data.y_labels as yLabel}
								<tr>
									<td
										class="whitespace-nowrap p-1 text-right text-xs text-muted-foreground"
										title={yLabel}
									>
										{yLabel.length > 8 ? yLabel.slice(0, 8) + '…' : yLabel}
									</td>
									{#each data.x_labels as xLabel}
										{@const value = getCellValue(xLabel, yLabel)}
										<td class="p-0.5">
											<button
												type="button"
												class="cell-group relative flex h-6 min-w-6 items-center justify-center rounded text-xs transition-all hover:scale-110 hover:shadow-md {getCellColor(value)} {isClickable ? 'cursor-pointer' : ''}"
												title="{yLabel} × {xLabel}: {formatValue(value)}"
												onclick={() => handleCellClick(xLabel, yLabel)}
												disabled={!isClickable}
											>
												<span class="text-[10px] font-medium text-white opacity-0 hover:opacity-100 dark:text-white">
													{value > 0 ? formatValue(value) : ''}
												</span>
											</button>
										</td>
									{/each}
								</tr>
							{/each}
						</tbody>
					</table>
				</div>

				<!-- Legend -->
				<div class="mt-3 flex items-center justify-between border-t pt-2">
					<span class="text-xs text-muted-foreground">
						{data.value_label || 'Value'}
					</span>
					<div class="flex items-center gap-1">
						<span class="text-xs text-muted-foreground">{formatValue(minValue())}</span>
						<div class="flex h-3 gap-0.5">
							<div class="w-4 rounded-sm bg-muted"></div>
							<div class="w-4 rounded-sm bg-blue-100 dark:bg-blue-950"></div>
							<div class="w-4 rounded-sm bg-blue-200 dark:bg-blue-900"></div>
							<div class="w-4 rounded-sm bg-blue-300 dark:bg-blue-800"></div>
							<div class="w-4 rounded-sm bg-blue-400 dark:bg-blue-700"></div>
							<div class="w-4 rounded-sm bg-blue-500 dark:bg-blue-600"></div>
						</div>
						<span class="text-xs text-muted-foreground">{formatValue(maxValue())}</span>
					</div>
				</div>
			</div>
		{:else}
			<div class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
				No heatmap data available
			</div>
		{/if}
	</Card.Content>
</Card.Root>

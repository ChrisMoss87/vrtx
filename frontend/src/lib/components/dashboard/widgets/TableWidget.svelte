<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import { Table as TableIcon, TrendingUp, TrendingDown } from 'lucide-svelte';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import {
		presets,
		applyConditionalFormat,
		formatStyleToInline,
		type FormatCondition
	} from './conditionalFormatting';

	type ColumnFormatting = {
		type: 'traffic_light' | 'reverse_traffic_light' | 'progress' | 'trend' | 'status' | 'custom';
		thresholds?: [number, number];
		conditions?: FormatCondition[];
	};

	interface Props {
		title: string;
		data: {
			columns?: string[];
			data?: Record<string, any>[];
			total_count?: number;
			column_formatting?: Record<string, ColumnFormatting>;
		} | null;
		loading?: boolean;
		maxRows?: number;
	}

	let { title, data, loading = false, maxRows = 10 }: Props = $props();

	const columns = $derived(() => {
		if (data?.columns && data.columns.length > 0) {
			return data.columns;
		}
		// Infer columns from data
		if (data?.data && data.data.length > 0) {
			return Object.keys(data.data[0]);
		}
		return [];
	});

	const rows = $derived(() => {
		if (!data?.data) return [];
		return data.data.slice(0, maxRows);
	});

	function formatCellValue(value: any): string {
		if (value === null || value === undefined) return '-';
		if (typeof value === 'number') {
			if (Number.isInteger(value)) {
				return value.toLocaleString();
			}
			return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
		}
		if (typeof value === 'boolean') {
			return value ? 'Yes' : 'No';
		}
		return String(value);
	}

	function formatColumnHeader(col: string): string {
		return col
			.replace(/_/g, ' ')
			.replace(/([A-Z])/g, ' $1')
			.replace(/^./, (str) => str.toUpperCase())
			.trim();
	}

	function getCellFormatting(col: string, value: any): { style: string; icon?: 'up' | 'down' } {
		const formatting = data?.column_formatting?.[col];
		if (!formatting) return { style: '' };

		let formatStyle;

		switch (formatting.type) {
			case 'traffic_light':
				if (typeof value === 'number') {
					formatStyle = presets.trafficLight(value, formatting.thresholds);
				}
				break;
			case 'reverse_traffic_light':
				if (typeof value === 'number') {
					formatStyle = presets.reverseTrafficLight(value, formatting.thresholds);
				}
				break;
			case 'progress':
				if (typeof value === 'number') {
					formatStyle = presets.progress(value);
				}
				break;
			case 'trend':
				if (typeof value === 'number') {
					formatStyle = presets.trend(value);
				}
				break;
			case 'status':
				if (typeof value === 'string') {
					formatStyle = presets.status(value);
				}
				break;
			case 'custom':
				if (formatting.conditions) {
					formatStyle = applyConditionalFormat(value, formatting.conditions);
				}
				break;
		}

		if (!formatStyle) return { style: '' };

		return {
			style: formatStyleToInline(formatStyle),
			icon: formatStyle.icon as 'up' | 'down' | undefined
		};
	}
</script>

<Card.Root class="h-full">
	<Card.Header class="pb-2">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-2">
				<TableIcon class="h-4 w-4 text-muted-foreground" />
				<Card.Title class="text-sm font-medium">{title}</Card.Title>
			</div>
			{#if data?.total_count && data.total_count > maxRows}
				<span class="text-xs text-muted-foreground">
					Showing {maxRows} of {data.total_count}
				</span>
			{/if}
		</div>
	</Card.Header>
	<Card.Content class="p-0">
		{#if loading}
			<div class="animate-pulse p-4">
				<div class="space-y-2">
					{#each [1, 2, 3, 4, 5] as _}
						<div class="h-8 rounded bg-muted"></div>
					{/each}
				</div>
			</div>
		{:else if rows().length === 0}
			<div class="flex flex-col items-center justify-center py-8 text-muted-foreground">
				<TableIcon class="mb-2 h-8 w-8" />
				<p class="text-sm">No data available</p>
			</div>
		{:else}
			<ScrollArea class="max-h-[300px]">
				<Table.Root>
					<Table.Header>
						<Table.Row>
							{#each columns() as col}
								<Table.Head class="text-xs">{formatColumnHeader(col)}</Table.Head>
							{/each}
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each rows() as row}
							<Table.Row>
								{#each columns() as col}
									{@const cellFormat = getCellFormatting(col, row[col])}
									<Table.Cell class="text-sm">
										<span
											class="inline-flex items-center gap-1 rounded px-1"
											style={cellFormat.style}
										>
											{#if cellFormat.icon === 'up'}
												<TrendingUp class="h-3 w-3" />
											{:else if cellFormat.icon === 'down'}
												<TrendingDown class="h-3 w-3" />
											{/if}
											{formatCellValue(row[col])}
										</span>
									</Table.Cell>
								{/each}
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
			</ScrollArea>
		{/if}
	</Card.Content>
</Card.Root>

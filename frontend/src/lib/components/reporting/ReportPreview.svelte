<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import { Spinner } from '$lib/components/ui/spinner';
	import { RefreshCw, Download, FileWarning } from 'lucide-svelte';
	import type { ReportResult, ReportType, ChartType } from '$lib/api/reports';
	import ReportChart from './ReportChart.svelte';

	interface Props {
		result: ReportResult | null;
		reportType: ReportType;
		chartType: ChartType | null;
		loading?: boolean;
		onRefresh?: () => void;
	}

	let { result, reportType, chartType, loading = false, onRefresh }: Props = $props();

	// Get column headers from data
	const columns = $derived(() => {
		if (!result?.data || result.data.length === 0) return [];
		return Object.keys(result.data[0]);
	});

	// Format cell value for display
	function formatValue(value: any): string {
		if (value === null || value === undefined) return '—';
		if (typeof value === 'boolean') return value ? 'Yes' : 'No';
		if (typeof value === 'number') {
			// Check if it looks like currency
			if (Math.abs(value) >= 1000) {
				return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
			}
			return value.toString();
		}
		if (value instanceof Date || (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value))) {
			try {
				return new Date(value).toLocaleDateString();
			} catch {
				return String(value);
			}
		}
		return String(value);
	}
</script>

<Card.Root class="h-full">
	<Card.Header class="flex flex-row items-center justify-between">
		<div>
			<Card.Title class="text-base">Preview Results</Card.Title>
			<Card.Description>
				{#if result}
					{result.total} {result.total === 1 ? 'result' : 'results'} found
				{:else}
					Run a preview to see your report data
				{/if}
			</Card.Description>
		</div>
		{#if onRefresh}
			<Button variant="outline" size="sm" onclick={onRefresh} disabled={loading}>
				<RefreshCw class="mr-2 h-4 w-4 {loading ? 'animate-spin' : ''}" />
				Refresh
			</Button>
		{/if}
	</Card.Header>
	<Card.Content>
		{#if loading}
			<div class="flex items-center justify-center py-12">
				<Spinner class="h-8 w-8" />
			</div>
		{:else if !result}
			<div class="flex flex-col items-center justify-center py-12 text-center">
				<FileWarning class="h-12 w-12 text-muted-foreground mb-4" />
				<p class="text-muted-foreground">No preview available</p>
				<p class="text-sm text-muted-foreground mt-1">
					Click "Preview" to see your report results
				</p>
			</div>
		{:else if result.data.length === 0}
			<div class="flex flex-col items-center justify-center py-12 text-center">
				<FileWarning class="h-12 w-12 text-muted-foreground mb-4" />
				<p class="text-muted-foreground">No data found</p>
				<p class="text-sm text-muted-foreground mt-1">
					Try adjusting your filters or date range
				</p>
			</div>
		{:else if reportType === 'chart' && chartType}
			<!-- Chart View -->
			<ReportChart data={result.data} {chartType} />
		{:else if reportType === 'matrix' && result.rows && result.columns}
			<!-- Matrix View -->
			<div class="overflow-auto">
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head class="font-medium"></Table.Head>
							{#each result.columns as col}
								<Table.Head class="text-center">{col}</Table.Head>
							{/each}
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each result.rows as row}
							<Table.Row>
								<Table.Cell class="font-medium">{row}</Table.Cell>
								{#each result.columns as col}
									<Table.Cell class="text-center">
										{formatValue((result.data as any)[row]?.[col])}
									</Table.Cell>
								{/each}
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
			</div>
		{:else}
			<!-- Table View (default) -->
			<div class="overflow-auto max-h-[500px]">
				<Table.Root>
					<Table.Header>
						<Table.Row>
							{#each columns() as col}
								<Table.Head class="whitespace-nowrap">{col}</Table.Head>
							{/each}
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each result.data.slice(0, 100) as row}
							<Table.Row>
								{#each columns() as col}
									<Table.Cell class="max-w-xs truncate">
										{formatValue(row[col])}
									</Table.Cell>
								{/each}
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
			</div>
			{#if result.data.length > 100}
				<p class="mt-4 text-center text-sm text-muted-foreground">
					Showing first 100 of {result.total} results
				</p>
			{/if}
		{/if}
	</Card.Content>
</Card.Root>

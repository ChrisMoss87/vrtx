<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import { Table as TableIcon } from 'lucide-svelte';
	import { ScrollArea } from '$lib/components/ui/scroll-area';

	interface Props {
		title: string;
		data: {
			columns?: string[];
			data?: Record<string, any>[];
			total_count?: number;
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
									<Table.Cell class="text-sm">
										{formatCellValue(row[col])}
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

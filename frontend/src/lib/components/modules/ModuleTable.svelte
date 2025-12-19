<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import {
		Table,
		TableBody,
		TableCell,
		TableHead,
		TableHeader,
		TableRow
	} from '$lib/components/ui/table';
	import type { Module, PaginatedRecords, Field } from '$lib/types/modules';
	import { ArrowDown, ArrowUp, ArrowUpDown, Eye } from 'lucide-svelte';

	interface Props {
		module: Module;
		records: PaginatedRecords;
		sortBy?: string;
		sortDirection?: 'asc' | 'desc';
		onSort: (field: string) => void;
		onPageChange: (page: number) => void;
		onViewRecord: (recordId: number) => void;
	}

	let {
		module,
		records,
		sortBy,
		sortDirection = 'asc',
		onSort,
		onPageChange,
		onViewRecord
	}: Props = $props();

	// Get the first 5 fields from the first block to display as columns
	const displayFields = $derived(module.blocks?.[0]?.fields?.slice(0, 5) ?? []);

	function formatValue(value: any, field: Field): string {
		if (value === null || value === undefined) return '—';

		switch (field.type) {
			case 'date':
				return new Date(value).toLocaleDateString();
			case 'datetime':
				return new Date(value).toLocaleString();
			case 'currency':
				return new Intl.NumberFormat('en-US', {
					style: 'currency',
					currency: 'USD'
				}).format(value);
			case 'percent':
				return `${value}%`;
			case 'checkbox':
			case 'toggle':
				return value ? 'Yes' : 'No';
			case 'multiselect':
				return Array.isArray(value) ? value.join(', ') : value;
			case 'select':
			case 'radio':
				// Find the label from field options
				const option = field.options?.find((opt) => opt.value === value);
				return option?.label ?? value;
			default:
				return String(value);
		}
	}

	function getSortIcon(field: string) {
		if (sortBy !== field) return ArrowUpDown;
		return sortDirection === 'asc' ? ArrowUp : ArrowDown;
	}
</script>

<div class="rounded-md border">
	<Table>
		<TableHeader>
			<TableRow>
				{#each displayFields as field (field.id)}
					<TableHead>
						<button
							type="button"
							class="flex items-center gap-2 font-medium hover:text-foreground"
							onclick={() => onSort(field.api_name)}
						>
							{field.label}
							<svelte:component this={getSortIcon(field.api_name)} class="h-4 w-4" />
						</button>
					</TableHead>
				{/each}
				<TableHead class="w-[100px]">Actions</TableHead>
			</TableRow>
		</TableHeader>
		<TableBody>
			{#if records.records.length === 0}
				<TableRow>
					<TableCell colspan={displayFields.length + 1} class="h-24 text-center">
						No records found.
					</TableCell>
				</TableRow>
			{:else}
				{#each records.records as record (record.id)}
					<TableRow class="cursor-pointer hover:bg-muted/50">
						{#each displayFields as field (field.id)}
							<TableCell onclick={() => onViewRecord(record.id)} class="font-medium">
								{formatValue(record.data[field.api_name], field)}
							</TableCell>
						{/each}
						<TableCell>
							<Button variant="ghost" size="sm" onclick={() => onViewRecord(record.id)}>
								<Eye class="h-4 w-4" />
								<span class="sr-only">View</span>
							</Button>
						</TableCell>
					</TableRow>
				{/each}
			{/if}
		</TableBody>
	</Table>
</div>

<!-- Pagination -->
{#if records.meta.last_page > 1}
	<div class="flex items-center justify-between">
		<div class="text-sm text-muted-foreground">
			Page {records.meta.current_page} of {records.meta.last_page}
		</div>

		<div class="flex items-center gap-2">
			<Button
				variant="outline"
				size="sm"
				disabled={records.meta.current_page === 1}
				onclick={() => onPageChange(records.meta.current_page - 1)}
			>
				Previous
			</Button>

			<!-- Show page numbers (simplified - show first, current-1, current, current+1, last) -->
			{#if records.meta.current_page > 2}
				<Button variant="outline" size="sm" onclick={() => onPageChange(1)}>1</Button>
				{#if records.meta.current_page > 3}
					<span class="text-muted-foreground">...</span>
				{/if}
			{/if}

			{#if records.meta.current_page > 1}
				<Button
					variant="outline"
					size="sm"
					onclick={() => onPageChange(records.meta.current_page - 1)}
				>
					{records.meta.current_page - 1}
				</Button>
			{/if}

			<Button variant="default" size="sm" disabled>
				{records.meta.current_page}
			</Button>

			{#if records.meta.current_page < records.meta.last_page}
				<Button
					variant="outline"
					size="sm"
					onclick={() => onPageChange(records.meta.current_page + 1)}
				>
					{records.meta.current_page + 1}
				</Button>
			{/if}

			{#if records.meta.current_page < records.meta.last_page - 1}
				{#if records.meta.current_page < records.meta.last_page - 2}
					<span class="text-muted-foreground">...</span>
				{/if}
				<Button variant="outline" size="sm" onclick={() => onPageChange(records.meta.last_page)}>
					{records.meta.last_page}
				</Button>
			{/if}

			<Button
				variant="outline"
				size="sm"
				disabled={records.meta.current_page === records.meta.last_page}
				onclick={() => onPageChange(records.meta.current_page + 1)}
			>
				Next
			</Button>
		</div>
	</div>
{/if}

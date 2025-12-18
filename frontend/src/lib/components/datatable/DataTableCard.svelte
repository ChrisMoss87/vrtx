<script lang="ts">
	import type { ColumnDef, BaseRowData } from './types';
	import { Badge } from '$lib/components/ui/badge';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Button } from '$lib/components/ui/button';
	import { ChevronDown, ChevronUp } from 'lucide-svelte';
	import {
		getNestedValue,
		formatCellValue,
		getMobileVisibleColumns,
		getMobileHiddenColumns
	} from './utils';

	interface Props {
		record: BaseRowData;
		columns: ColumnDef[];
		recordNameField?: string;
		maxVisibleFields?: number;
		selected?: boolean;
		selectionMode?: boolean;
		onSelect?: (recordId: number | string) => void;
		onCardClick?: (record: BaseRowData) => void;
		class?: string;
	}

	let {
		record,
		columns,
		recordNameField,
		maxVisibleFields = 4,
		selected = false,
		selectionMode = false,
		onSelect,
		onCardClick,
		class: className = ''
	}: Props = $props();

	let expanded = $state(false);

	// Get visible and hidden columns
	const visibleColumns = $derived(getMobileVisibleColumns(columns, maxVisibleFields, recordNameField));
	const hiddenColumns = $derived(getMobileHiddenColumns(columns, maxVisibleFields, recordNameField));

	// Get the title field (first priority 1 field or first visible field)
	const titleColumn = $derived(
		visibleColumns.find((col) => {
			const id = col.id.toLowerCase();
			return id === 'name' || id === 'title' || id === recordNameField;
		}) || visibleColumns[0]
	);

	// Get subtitle/status field (priority 2 field like status, stage)
	const subtitleColumn = $derived(
		visibleColumns.find((col) => {
			const id = col.id.toLowerCase();
			return (
				id === 'status' ||
				id === 'stage' ||
				id === 'state' ||
				col.type === 'select'
			);
		})
	);

	// Fields to show in the main body (excluding title and subtitle)
	const bodyColumns = $derived(
		visibleColumns.filter((col) => col !== titleColumn && col !== subtitleColumn)
	);

	function getFieldValue(column: ColumnDef): string {
		const value = getNestedValue(record, column.accessorKey);
		return formatCellValue(value, column.type);
	}

	function getRawValue(column: ColumnDef): unknown {
		return getNestedValue(record, column.accessorKey);
	}

	// Get option label for select fields
	function getOptionLabel(column: ColumnDef, value: unknown): string | null {
		if (!column.options || !value) return null;
		const option = column.options.find((opt) => opt.value === value);
		return option?.label || null;
	}

	// Get option color for select fields
	function getOptionColor(column: ColumnDef, value: unknown): string | null {
		if (!column.options || !value) return null;
		const option = column.options.find((opt) => opt.value === value);
		return (option as any)?.color || null;
	}

	function handleCardClick(e: MouseEvent) {
		// Don't trigger if clicking on checkbox or expand button
		if ((e.target as HTMLElement).closest('[data-no-card-click]')) {
			return;
		}
		onCardClick?.(record);
	}

	function handleSelect(checked: boolean) {
		onSelect?.(record.id);
	}

	function toggleExpand(e: MouseEvent) {
		e.stopPropagation();
		expanded = !expanded;
	}
</script>

<div
	role="button"
	tabindex="0"
	class="relative rounded-lg border bg-card p-4 shadow-sm transition-all hover:shadow-md active:scale-[0.99] {selected ? 'border-primary ring-2 ring-primary/20' : 'border-border'} {className}"
	onclick={handleCardClick}
	onkeydown={(e) => e.key === 'Enter' && handleCardClick(e as unknown as MouseEvent)}
>
	<!-- Selection checkbox (when in selection mode) -->
	{#if selectionMode}
		<div class="absolute top-3 right-3" data-no-card-click>
			<Checkbox
				checked={selected}
				onCheckedChange={handleSelect}
				class="h-5 w-5"
			/>
		</div>
	{/if}

	<!-- Card Header: Title and Status -->
	<div class="mb-3 {selectionMode ? 'pr-8' : ''}">
		{#if titleColumn}
			{@const titleValue = getFieldValue(titleColumn)}
			<h3 class="text-base font-semibold text-foreground line-clamp-2">
				{titleValue || 'Untitled'}
			</h3>
		{/if}

		{#if subtitleColumn}
			{@const subtitleValue = getRawValue(subtitleColumn)}
			{@const optionLabel = getOptionLabel(subtitleColumn, subtitleValue)}
			{@const optionColor = getOptionColor(subtitleColumn, subtitleValue)}
			{#if subtitleValue}
				<div class="mt-1.5">
					<Badge
						variant="secondary"
						class="text-xs"
						style={optionColor ? `background-color: ${optionColor}20; color: ${optionColor}; border-color: ${optionColor}40;` : ''}
					>
						{optionLabel || formatCellValue(subtitleValue, subtitleColumn.type)}
					</Badge>
				</div>
			{/if}
		{/if}
	</div>

	<!-- Card Body: Key Fields -->
	{#if bodyColumns.length > 0}
		<div class="space-y-2 border-t border-border/50 pt-3">
			{#each bodyColumns as column (column.id)}
				{@const value = getFieldValue(column)}
				{#if value}
					<div class="flex items-start justify-between gap-2 text-sm">
						<span class="text-muted-foreground shrink-0">{column.header}</span>
						<span class="text-foreground text-right truncate">
							{#if column.type === 'currency'}
								<span class="font-medium">{value}</span>
							{:else if column.type === 'email'}
								<a
									href="mailto:{value}"
									class="text-primary hover:underline"
									onclick={(e) => e.stopPropagation()}
									data-no-card-click
								>
									{value}
								</a>
							{:else if column.type === 'phone'}
								<a
									href="tel:{value}"
									class="text-primary hover:underline"
									onclick={(e) => e.stopPropagation()}
									data-no-card-click
								>
									{value}
								</a>
							{:else if column.type === 'url'}
								<a
									href={value}
									target="_blank"
									rel="noopener noreferrer"
									class="text-primary hover:underline"
									onclick={(e) => e.stopPropagation()}
									data-no-card-click
								>
									{value}
								</a>
							{:else}
								{value}
							{/if}
						</span>
					</div>
				{/if}
			{/each}
		</div>
	{/if}

	<!-- Expandable Section -->
	{#if hiddenColumns.length > 0}
		<div class="mt-3 border-t border-border/50 pt-3">
			{#if expanded}
				<div class="space-y-2 mb-3">
					{#each hiddenColumns as column (column.id)}
						{@const value = getFieldValue(column)}
						{#if value}
							<div class="flex items-start justify-between gap-2 text-sm">
								<span class="text-muted-foreground shrink-0">{column.header}</span>
								<span class="text-foreground text-right truncate">
									{#if column.type === 'currency'}
										<span class="font-medium">{value}</span>
									{:else if column.type === 'email'}
										<a
											href="mailto:{value}"
											class="text-primary hover:underline"
											onclick={(e) => e.stopPropagation()}
											data-no-card-click
										>
											{value}
										</a>
									{:else if column.type === 'phone'}
										<a
											href="tel:{value}"
											class="text-primary hover:underline"
											onclick={(e) => e.stopPropagation()}
											data-no-card-click
										>
											{value}
										</a>
									{:else}
										{value}
									{/if}
								</span>
							</div>
						{/if}
					{/each}
				</div>
			{/if}

			<Button
				variant="ghost"
				size="sm"
				class="w-full h-8 text-xs text-muted-foreground hover:text-foreground"
				onclick={toggleExpand}
				data-no-card-click
			>
				{#if expanded}
					<ChevronUp class="h-4 w-4 mr-1" />
					Show Less
				{:else}
					<ChevronDown class="h-4 w-4 mr-1" />
					Show {hiddenColumns.length} More Field{hiddenColumns.length > 1 ? 's' : ''}
				{/if}
			</Button>
		</div>
	{/if}
</div>

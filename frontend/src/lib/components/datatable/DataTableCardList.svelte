<script lang="ts">
	import type { ColumnDef, BaseRowData, PaginationState, RowSelection } from './types';
	import DataTableCard from './DataTableCard.svelte';
	import MobileActionSheet from './MobileActionSheet.svelte';
	import SimplePagination from './SimplePagination.svelte';
	import { Button } from '$lib/components/ui/button';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Badge } from '$lib/components/ui/badge';
	import { CheckSquare, Square, Loader2 } from 'lucide-svelte';
	import { getNestedValue, formatCellValue } from './utils';

	interface Props {
		data: BaseRowData[];
		columns: ColumnDef[];
		pagination: PaginationState;
		rowSelection?: RowSelection;
		loading?: boolean;
		recordNameField?: string;
		maxVisibleFields?: number;
		enableSelection?: boolean;
		enablePagination?: boolean;
		onRowClick?: (record: BaseRowData) => void;
		onSelectionChange?: (selection: RowSelection) => void;
		onPageChange?: (page: number) => void;
		onPageSizeChange?: (size: number) => void;
		onAction?: (action: string, record: BaseRowData) => void;
		class?: string;
	}

	let {
		data,
		columns,
		pagination,
		rowSelection = {},
		loading = false,
		recordNameField,
		maxVisibleFields = 4,
		enableSelection = true,
		enablePagination = true,
		onRowClick,
		onSelectionChange,
		onPageChange,
		onPageSizeChange,
		onAction,
		class: className = ''
	}: Props = $props();

	// Selection mode state
	let selectionMode = $state(false);

	// Action sheet state
	let actionSheetOpen = $state(false);
	let selectedRecord = $state<BaseRowData | null>(null);

	// Calculate selection state
	const selectedCount = $derived(Object.values(rowSelection).filter(Boolean).length);
	const allSelected = $derived(data.length > 0 && selectedCount === data.length);
	const someSelected = $derived(selectedCount > 0 && selectedCount < data.length);

	// Get record title for action sheet
	function getRecordTitle(record: BaseRowData): string {
		if (recordNameField) {
			const value = getNestedValue(record, `data.${recordNameField}`);
			if (value) return formatCellValue(value, 'text');
		}

		// Try common name fields
		const nameFields = ['name', 'title', 'subject', 'label'];
		for (const field of nameFields) {
			const value = getNestedValue(record, `data.${field}`);
			if (value) return formatCellValue(value, 'text');
		}

		return `Record #${record.id}`;
	}

	function handleCardClick(record: BaseRowData) {
		if (selectionMode) {
			toggleRowSelection(record.id);
		} else {
			// Open action sheet
			selectedRecord = record;
			actionSheetOpen = true;
		}
	}

	function toggleRowSelection(recordId: number | string) {
		const newSelection = { ...rowSelection };
		if (newSelection[recordId]) {
			delete newSelection[recordId];
		} else {
			newSelection[recordId] = true;
		}
		onSelectionChange?.(newSelection);
	}

	function toggleAllRows() {
		if (allSelected) {
			// Deselect all
			onSelectionChange?.({});
		} else {
			// Select all
			const newSelection: RowSelection = {};
			data.forEach((row) => {
				newSelection[row.id] = true;
			});
			onSelectionChange?.(newSelection);
		}
	}

	function enterSelectionMode() {
		selectionMode = true;
	}

	function exitSelectionMode() {
		selectionMode = false;
		onSelectionChange?.({});
	}

	function handleAction(action: string, record: BaseRowData) {
		onAction?.(action, record);
	}

	function handleActionSheetOpenChange(open: boolean) {
		actionSheetOpen = open;
		if (!open) {
			selectedRecord = null;
		}
	}
</script>

<div class="flex flex-col h-full {className}">
	<!-- Selection Mode Header -->
	{#if enableSelection && (selectionMode || selectedCount > 0)}
		<div class="sticky top-0 z-10 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 border-b px-4 py-3">
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-3">
					{#if selectionMode}
						<Button
							variant="ghost"
							size="sm"
							class="h-8 px-2"
							onclick={toggleAllRows}
						>
							{#if allSelected}
								<CheckSquare class="h-4 w-4 mr-2" />
							{:else}
								<Square class="h-4 w-4 mr-2" />
							{/if}
							{allSelected ? 'Deselect All' : 'Select All'}
						</Button>
					{/if}
					{#if selectedCount > 0}
						<Badge variant="secondary" class="h-6">
							{selectedCount} selected
						</Badge>
					{/if}
				</div>
				<Button
					variant="ghost"
					size="sm"
					class="h-8"
					onclick={exitSelectionMode}
				>
					Cancel
				</Button>
			</div>
		</div>
	{/if}

	<!-- Card List -->
	<div class="flex-1 overflow-y-auto">
		{#if loading}
			<div class="flex items-center justify-center py-12">
				<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
			</div>
		{:else if data.length === 0}
			<div class="flex flex-col items-center justify-center py-12 text-center">
				<p class="text-lg font-medium text-muted-foreground">No records found</p>
				<p class="text-sm text-muted-foreground mt-1">
					Try adjusting your search or filters
				</p>
			</div>
		{:else}
			<div class="p-4 space-y-3">
				{#each data as record (record.id)}
					<DataTableCard
						{record}
						{columns}
						{recordNameField}
						{maxVisibleFields}
						selected={!!rowSelection[record.id]}
						{selectionMode}
						onSelect={toggleRowSelection}
						onCardClick={handleCardClick}
					/>
				{/each}
			</div>
		{/if}
	</div>

	<!-- Pagination -->
	{#if enablePagination && pagination.total > 0}
		<div class="border-t bg-background px-4 py-3">
			<SimplePagination
				{pagination}
				onPageChange={onPageChange}
				onPageSizeChange={onPageSizeChange}
			/>
		</div>
	{/if}

	<!-- Long Press Hint (when not in selection mode) -->
	{#if enableSelection && !selectionMode && data.length > 0}
		<div class="fixed bottom-20 left-1/2 -translate-x-1/2 z-20">
			<Button
				variant="secondary"
				size="sm"
				class="h-8 shadow-lg"
				onclick={enterSelectionMode}
			>
				<CheckSquare class="h-4 w-4 mr-2" />
				Select Multiple
			</Button>
		</div>
	{/if}

	<!-- Action Sheet -->
	<MobileActionSheet
		bind:open={actionSheetOpen}
		record={selectedRecord}
		recordTitle={selectedRecord ? getRecordTitle(selectedRecord) : 'Record'}
		onOpenChange={handleActionSheetOpenChange}
		onAction={handleAction}
	/>
</div>

<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Search, X, Download, Trash2, Tag, FileSpreadsheet, FileText, Edit } from 'lucide-svelte';
	import { Badge } from '$lib/components/ui/badge';
	import { toast } from 'svelte-sonner';
	import axios from 'axios';
	import type { TableContext } from './types';
	import DataTableColumnToggle from './DataTableColumnToggle.svelte';
	import DataTableFilterChips from './DataTableFilterChips.svelte';
	import DataTableFilters from './DataTableFilters.svelte';
	import DataTableViews from './DataTableViews.svelte';
	import DataTableMassUpdate from './DataTableMassUpdate.svelte';
	import { buildApiRequest, transformFiltersForApi } from './utils';

	interface Props {
		enableSearch?: boolean;
		enableFilters?: boolean;
		enableBulkActions?: boolean;
		enableExport?: boolean;
		enableViews?: boolean;
		enableColumnToggle?: boolean;
		module?: string;
		defaultViewId?: number | null;
		selectedCount?: number;
		hasFilters?: boolean;
	}

	let {
		enableSearch = true,
		enableFilters = true,
		enableBulkActions = true,
		enableExport = true,
		enableViews = true,
		enableColumnToggle = true,
		module = '',
		defaultViewId,
		selectedCount = 0,
		hasFilters = false
	}: Props = $props();

	const table = getContext<TableContext>('table');

	let searchValue = $state(table.state.globalFilter);
	let deleteDialogOpen = $state(false);
	let isDeleting = $state(false);
	let massUpdateOpen = $state(false);

	function handleSearchInput(event: Event) {
		const target = event.target as HTMLInputElement;
		searchValue = target.value;
		table.updateGlobalFilter(target.value);
	}

	function clearSearch() {
		searchValue = '';
		table.updateGlobalFilter('');
	}

	async function handleBulkDelete() {
		if (!module) return;

		isDeleting = true;

		try {
			// Get selected row IDs
			const selectedIds = Object.keys(table.state.rowSelection)
				.filter((id) => table.state.rowSelection[id])
				.map((id) => parseInt(id));

			// Call bulk delete API
			const response = await axios.post(`/api/modules/${module}/records/bulk-delete`, {
				ids: selectedIds
			});

			// Show success message
			toast.success(response.data.message || `Deleted ${selectedIds.length} record(s)`);

			// Clear selection
			table.clearSelection();

			// Refresh table data
			await table.refresh();

			// Close dialog
			deleteDialogOpen = false;
		} catch (error: any) {
			console.error('Bulk delete error:', error);
			toast.error(error.response?.data?.message || 'Failed to delete records');
		} finally {
			isDeleting = false;
		}
	}

	function handleExport(format: 'xlsx' | 'csv') {
		if (!module) return;

		try {
			// Build request params
			const request = buildApiRequest(table.state);

			// Build URL with query params
			const params = new URLSearchParams();
			params.set('format', format);

			if (request.sort) {
				params.set('sort', JSON.stringify(request.sort));
			}

			if (request.filters) {
				// Transform filters to backend format
				const transformedFilters = transformFiltersForApi(request.filters);
				params.set('filters', JSON.stringify(transformedFilters));
			}

			if (request.search) {
				params.set('search', request.search);
			}

			// Get visible columns
			const visibleColumns = Object.entries(table.state.columnVisibility)
				.filter(([_, visible]) => visible)
				.map(([columnId]) => columnId)
				.filter((id) => id !== 'actions'); // Exclude actions column

			if (visibleColumns.length > 0) {
				params.set('columns', visibleColumns.join(','));
			}

			// Trigger download by navigating to export endpoint
			window.location.href = `/api/modules/${module}/records/export?${params.toString()}`;

			toast.success(`Exporting ${format.toUpperCase()} file...`);
		} catch (error: any) {
			console.error('Export error:', error);
			toast.error('Failed to export data');
		}
	}
</script>

<div class="flex flex-col gap-3">
	<!-- Main toolbar row: Search, filters, and actions -->
	<div class="flex flex-wrap items-center gap-2">
		<!-- Search -->
		{#if enableSearch}
			<div class="relative w-full max-w-xs" role="search">
				<Search
					class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground"
					aria-hidden="true"
				/>
				<Input
					type="search"
					placeholder="Search..."
					value={searchValue}
					oninput={handleSearchInput}
					class="h-9 pr-8 pl-8"
					aria-label="Search records"
				/>
				{#if searchValue}
					<button
						type="button"
						onclick={clearSearch}
						class="absolute top-2.5 right-2.5 text-muted-foreground hover:text-foreground"
						aria-label="Clear search"
					>
						<X class="h-4 w-4" />
					</button>
				{/if}
			</div>
		{/if}

		<!-- Filters (unified popover) -->
		{#if enableFilters}
			<DataTableFilters moduleApiName={module} />
		{/if}

		<!-- Views -->
		{#if enableViews}
			<DataTableViews moduleApiName={module} />
		{/if}

		<!-- Spacer -->
		<div class="flex-1"></div>

		<!-- Right side actions -->
		<div class="flex items-center gap-2">
			{#if enableColumnToggle}
				<DataTableColumnToggle />
			{/if}

			{#if enableExport && selectedCount === 0}
				<DropdownMenu.Root>
					<DropdownMenu.Trigger>
						{#snippet child({ props })}
							<Button variant="outline" size="sm" {...props}>
								<Download class="mr-2 h-4 w-4" />
								<span class="hidden sm:inline">Export</span>
							</Button>
						{/snippet}
					</DropdownMenu.Trigger>
					<DropdownMenu.Content align="end">
						<DropdownMenu.Item onclick={() => handleExport('xlsx')}>
							<FileSpreadsheet class="mr-2 h-4 w-4" />
							Export as Excel
						</DropdownMenu.Item>
						<DropdownMenu.Item onclick={() => handleExport('csv')}>
							<FileText class="mr-2 h-4 w-4" />
							Export as CSV
						</DropdownMenu.Item>
					</DropdownMenu.Content>
				</DropdownMenu.Root>
			{/if}
		</div>
	</div>

	<!-- Filter chips (shown below toolbar when filters are active) -->
	{#if table.state.filters.length > 0}
		<DataTableFilterChips />
	{/if}

	<!-- Bulk actions (shown when items selected) -->
	{#if selectedCount > 0 && enableBulkActions}
		<div
			class="flex items-center gap-2 rounded-lg bg-muted/50 p-2"
			role="toolbar"
			aria-label="Bulk actions"
		>
			<span class="text-sm font-medium" aria-live="polite">
				{selectedCount}
				{selectedCount === 1 ? 'record' : 'records'} selected
			</span>

			<div class="flex-1"></div>

			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					{#snippet child({ props })}
						<Button variant="outline" size="sm" {...props}>
							<Download class="mr-2 h-4 w-4" />
							Export selected
						</Button>
					{/snippet}
				</DropdownMenu.Trigger>
				<DropdownMenu.Content align="end">
					<DropdownMenu.Item onclick={() => handleExport('xlsx')}>
						<FileSpreadsheet class="mr-2 h-4 w-4" />
						Export as Excel
					</DropdownMenu.Item>
					<DropdownMenu.Item onclick={() => handleExport('csv')}>
						<FileText class="mr-2 h-4 w-4" />
						Export as CSV
					</DropdownMenu.Item>
				</DropdownMenu.Content>
			</DropdownMenu.Root>

			<Button
				variant="secondary"
				size="sm"
				onclick={() => (massUpdateOpen = true)}
				aria-label="Mass update selected records"
			>
				<Edit class="mr-2 h-4 w-4" aria-hidden="true" />
				Mass Update
			</Button>

			<Button
				variant="destructive"
				size="sm"
				onclick={() => (deleteDialogOpen = true)}
				aria-label="Delete selected records"
			>
				<Trash2 class="mr-2 h-4 w-4" aria-hidden="true" />
				Delete
			</Button>

			<Button
				variant="ghost"
				size="sm"
				onclick={() => table.clearSelection()}
				aria-label="Clear selection"
			>
				<X class="h-4 w-4" />
			</Button>
		</div>
	{/if}
</div>

<!-- Mass Update Dialog -->
{#if module}
	<DataTableMassUpdate moduleApiName={module} bind:open={massUpdateOpen} />
{/if}

{#if module}
	<AlertDialog.Root bind:open={deleteDialogOpen}>
		<AlertDialog.Content>
			<AlertDialog.Header>
				<AlertDialog.Title
					>Delete {selectedCount} record{selectedCount === 1 ? '' : 's'}?</AlertDialog.Title
				>
				<AlertDialog.Description>
					This action cannot be undone. This will permanently delete the selected record{selectedCount ===
					1
						? ''
						: 's'} from the database.
				</AlertDialog.Description>
			</AlertDialog.Header>
			<AlertDialog.Footer>
				<AlertDialog.Cancel disabled={isDeleting}>Cancel</AlertDialog.Cancel>
				<AlertDialog.Action onclick={handleBulkDelete} disabled={isDeleting}>
					{isDeleting ? 'Deleting...' : 'Delete'}
				</AlertDialog.Action>
			</AlertDialog.Footer>
		</AlertDialog.Content>
	</AlertDialog.Root>
{/if}

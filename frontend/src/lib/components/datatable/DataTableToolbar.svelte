<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Search, X, Download, Trash2, Tag, FileSpreadsheet, FileText, Filter } from 'lucide-svelte';
	import { Badge } from '$lib/components/ui/badge';
	import { toast } from 'svelte-sonner';
	import axios from 'axios';
	import type { TableContext } from './types';
	import DataTableViewSwitcher from './DataTableViewSwitcher.svelte';
	import DataTableColumnToggle from './DataTableColumnToggle.svelte';
	import DataTableSaveViewDialog from './DataTableSaveViewDialog.svelte';
	import DataTableFilterChips from './DataTableFilterChips.svelte';
	import DataTableFiltersDrawer from './DataTableFiltersDrawer.svelte';
	import { buildApiRequest } from './utils';

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
	let currentView = $state<any>(null);
	let saveViewDialogOpen = $state(false);
	let deleteDialogOpen = $state(false);
	let isDeleting = $state(false);
	let filtersDrawerOpen = $state(false);

	function handleSearchInput(event: Event) {
		const target = event.target as HTMLInputElement;
		searchValue = target.value;
		table.updateGlobalFilter(target.value);
	}

	function clearSearch() {
		searchValue = '';
		table.updateGlobalFilter('');
	}

	async function handleViewChange(view: any) {
		currentView = view;
		await table.loadView(view);
	}

	function handleSaveView() {
		if (currentView) {
			// Update existing view
			updateCurrentView();
		} else {
			// Open dialog to create new view
			saveViewDialogOpen = true;
		}
	}

	async function updateCurrentView() {
		if (!currentView) return;

		try {
			const response = await fetch(`/api/table-views/${currentView.id}`, {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
				},
				body: JSON.stringify({
					filters: table.state.filters || null,
					sorting: table.state.sorting || null,
					column_visibility: table.state.columnVisibility || null,
					page_size: table.state.pagination.perPage || 50
				})
			});

			if (response.ok) {
				console.log('View updated successfully');
			}
		} catch (error) {
			console.error('Failed to update view:', error);
		}
	}

	function getCurrentTableState() {
		return {
			filters: table.state.filters,
			sorting: table.state.sorting,
			columnVisibility: table.state.columnVisibility,
			pageSize: table.state.pagination.perPage
		};
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
				params.set('filters', JSON.stringify(request.filters));
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

<div class="flex flex-col gap-2">
	<!-- Top row: View switcher and actions -->
	<div class="flex items-center justify-between">
		<div class="flex items-center gap-2">
			{#if enableViews && module}
				<DataTableViewSwitcher
					{module}
					{defaultViewId}
					bind:currentView
					onViewChange={handleViewChange}
					onSaveView={handleSaveView}
					onCreateView={() => (saveViewDialogOpen = true)}
				/>
			{/if}
		</div>

		<div class="flex items-center gap-2">
			{#if enableFilters}
				<Button
					variant="outline"
					size="sm"
					onclick={() => (filtersDrawerOpen = true)}
					class="relative"
				>
					<Filter class="mr-2 h-4 w-4" />
					Filters
					{#if table.state.filters.length > 0}
						<Badge variant="secondary" class="ml-2 rounded-full px-1.5 py-0 text-xs">
							{table.state.filters.length}
						</Badge>
					{/if}
				</Button>
			{/if}

			{#if enableColumnToggle}
				<DataTableColumnToggle />
			{/if}

			{#if enableExport && selectedCount === 0}
				<DropdownMenu.Root>
					<DropdownMenu.Trigger >
						<Button variant="outline" size="sm" >
							<Download class="mr-2 h-4 w-4" />
							Export
						</Button>
					</DropdownMenu.Trigger>
					<DropdownMenu.Content align="end">
						<DropdownMenu.Item onclick={() => handleExport('xlsx')}>
							<FileSpreadsheet class="mr-2 h-4 w-4" />
							Export as Excel (.xlsx)
						</DropdownMenu.Item>
						<DropdownMenu.Item onclick={() => handleExport('csv')}>
							<FileText class="mr-2 h-4 w-4" />
							Export as CSV (.csv)
						</DropdownMenu.Item>
					</DropdownMenu.Content>
				</DropdownMenu.Root>
			{/if}
		</div>
	</div>

	<!-- Filter chips row (if any filters active) -->
	{#if table.state.filters.length > 0}
		<DataTableFilterChips />
	{/if}

	<!-- Bottom row: Search, filters, and bulk actions -->
	<div class="flex items-center justify-between">
		<!-- Left side: Search and filters -->
		<div class="flex flex-1 items-center gap-2">
			{#if enableSearch}
				<div class="relative w-full max-w-sm">
					<Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
					<Input
						type="search"
						placeholder="Search..."
						value={searchValue}
						oninput={handleSearchInput}
						class="pl-8 pr-8"
					/>
					{#if searchValue}
						<button
							onclick={clearSearch}
							class="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground"
						>
							<X class="h-4 w-4" />
						</button>
					{/if}
				</div>
			{/if}

			{#if hasFilters}
				<Button variant="ghost" size="sm" onclick={() => table.clearFilters()}>
					<X class="mr-2 h-4 w-4" />
					Clear filters
				</Button>
			{/if}
		</div>

		<!-- Right side: Bulk actions -->
		<div class="flex items-center gap-2">
			{#if selectedCount > 0 && enableBulkActions}
				<div class="flex items-center gap-2">
					<span class="text-sm text-muted-foreground">
						{selectedCount} selected
					</span>

					<Button variant="outline" size="sm">
						<Tag class="mr-2 h-4 w-4" />
						Add tags
					</Button>

					<DropdownMenu.Root>
						<DropdownMenu.Trigger >
							<Button variant="outline" size="sm" >
								<Download class="mr-2 h-4 w-4" />
								Export
							</Button>
						</DropdownMenu.Trigger>
						<DropdownMenu.Content align="end">
							<DropdownMenu.Item onclick={() => handleExport('xlsx')}>
								<FileSpreadsheet class="mr-2 h-4 w-4" />
								Export as Excel (.xlsx)
							</DropdownMenu.Item>
							<DropdownMenu.Item onclick={() => handleExport('csv')}>
								<FileText class="mr-2 h-4 w-4" />
								Export as CSV (.csv)
							</DropdownMenu.Item>
						</DropdownMenu.Content>
					</DropdownMenu.Root>

					<Button variant="destructive" size="sm" onclick={() => (deleteDialogOpen = true)}>
						<Trash2 class="mr-2 h-4 w-4" />
						Delete
					</Button>

					<Button variant="ghost" size="sm" onclick={() => table.clearSelection()}>
						<X class="h-4 w-4" />
					</Button>
				</div>
			{/if}
		</div>
	</div>
</div>

{#if enableFilters}
	<DataTableFiltersDrawer bind:open={filtersDrawerOpen} />
{/if}

{#if module}
	<DataTableSaveViewDialog
		bind:open={saveViewDialogOpen}
		{module}
		currentState={getCurrentTableState()}
		onSaved={() => {
			// Refresh the view switcher
			currentView = null;
		}}
	/>

	<AlertDialog.Root bind:open={deleteDialogOpen}>
		<AlertDialog.Content>
			<AlertDialog.Header>
				<AlertDialog.Title>Delete {selectedCount} record{selectedCount === 1 ? '' : 's'}?</AlertDialog.Title>
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

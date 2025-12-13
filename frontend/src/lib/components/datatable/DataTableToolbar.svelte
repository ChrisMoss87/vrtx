<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import {
		Search,
		X,
		Download,
		Trash2,
		FileSpreadsheet,
		FileText,
		Edit,
		FileType
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import axios from 'axios';
	import type { TableContext } from './types';
	import DataTableColumnToggle from './DataTableColumnToggle.svelte';
	import DataTableFilterChips from './DataTableFilterChips.svelte';
	import DataTableFilters from './DataTableFilters.svelte';
	import DataTableFilterPanel from './DataTableFilterPanel.svelte';
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
	let filterPanelOpen = $state(false);

	function handleSearchInput(event: Event) {
		const target = event.target as HTMLInputElement;
		searchValue = target.value;
		table.updateGlobalFilter(target.value);
	}

	function clearSearch() {
		searchValue = '';
		table.updateGlobalFilter('');
	}

	function toggleFilterPanel() {
		filterPanelOpen = !filterPanelOpen;
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

	async function handlePdfExport() {
		try {
			// Dynamically import jsPDF and autotable (client-side only)
			const { jsPDF } = await import('jspdf');
			const { default: autoTable } = await import('jspdf-autotable');

			// Get visible columns
			const visibleCols = Object.entries(table.state.columnVisibility)
				.filter(([_, visible]) => visible)
				.map(([columnId]) => columnId)
				.filter((id) => id !== 'actions');

			// Get column definitions for headers
			const columnDefs = table.columns.filter((col) => visibleCols.includes(col.id));
			const headers = columnDefs.map((col) => col.header || col.id);

			// Get data rows
			const rows = table.state.data.map((row: Record<string, any>) => {
				return columnDefs.map((col) => {
					const value = col.accessorKey
						? col.accessorKey.split('.').reduce((obj, key) => obj?.[key], row)
						: row[col.id];

					// Format value based on type
					if (value === null || value === undefined) return '';
					if (col.type === 'boolean') return value ? 'Yes' : 'No';
					if (col.type === 'date' && value)
						return new Date(value).toLocaleDateString();
					if (col.type === 'datetime' && value)
						return new Date(value).toLocaleString();
					if (typeof value === 'object') return JSON.stringify(value);
					return String(value);
				});
			});

			// Create PDF
			const doc = new jsPDF({
				orientation: 'landscape',
				unit: 'mm',
				format: 'a4'
			});

			// Add title
			const title = module
				? module.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())
				: 'Data Export';
			doc.setFontSize(16);
			doc.text(title, 14, 15);

			// Add export date
			doc.setFontSize(10);
			doc.setTextColor(128);
			doc.text(`Exported: ${new Date().toLocaleString()}`, 14, 22);
			doc.setTextColor(0);

			// Add table
			autoTable(doc, {
				head: [headers],
				body: rows,
				startY: 28,
				theme: 'striped',
				headStyles: {
					fillColor: [51, 51, 51],
					textColor: 255,
					fontStyle: 'bold'
				},
				styles: {
					fontSize: 8,
					cellPadding: 2
				},
				columnStyles: columnDefs.reduce(
					(acc, col, index) => {
						// Adjust column widths based on type
						if (col.type === 'boolean') {
							acc[index] = { cellWidth: 15 };
						} else if (col.type === 'date' || col.type === 'datetime') {
							acc[index] = { cellWidth: 25 };
						}
						return acc;
					},
					{} as Record<number, { cellWidth: number }>
				),
				didDrawPage: (data) => {
					// Add page number
					doc.setFontSize(8);
					doc.text(
						`Page ${data.pageNumber}`,
						doc.internal.pageSize.getWidth() - 20,
						doc.internal.pageSize.getHeight() - 10
					);
				}
			});

			// Save PDF
			const filename = `${module || 'export'}_${new Date().toISOString().split('T')[0]}.pdf`;
			doc.save(filename);

			toast.success('PDF exported successfully');
		} catch (error: any) {
			console.error('PDF export error:', error);
			toast.error('Failed to export PDF');
		}
	}
</script>

<div class="flex flex-col gap-4">
	<!-- Main toolbar row: Search, filters, and actions -->
	<div class="flex flex-wrap items-center gap-3">
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

		<!-- Filters button (toggles panel) -->
		{#if enableFilters}
			<DataTableFilters
				moduleApiName={module}
				isOpen={filterPanelOpen}
				onToggle={toggleFilterPanel}
			/>
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
						<DropdownMenu.Separator />
						<DropdownMenu.Item onclick={handlePdfExport}>
							<FileType class="mr-2 h-4 w-4" />
							Export as PDF
						</DropdownMenu.Item>
					</DropdownMenu.Content>
				</DropdownMenu.Root>
			{/if}
		</div>
	</div>

	<!-- Filter panel (expandable area) -->
	{#if enableFilters}
		<DataTableFilterPanel
			open={filterPanelOpen}
			onClose={() => (filterPanelOpen = false)}
		/>
	{/if}

	<!-- Filter chips (shown below toolbar when filters are active) -->
	{#if table.state.filters.length > 0}
		<DataTableFilterChips />
	{/if}

	<!-- Bulk actions (shown when items selected) -->
	{#if selectedCount > 0 && enableBulkActions}
		<div
			class="flex items-center gap-3 rounded-xl bg-sky-50 dark:bg-sky-950/30 border border-sky-100 dark:border-sky-900/50 px-4 py-3"
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
					<DropdownMenu.Separator />
					<DropdownMenu.Item onclick={handlePdfExport}>
						<FileType class="mr-2 h-4 w-4" />
						Export as PDF
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

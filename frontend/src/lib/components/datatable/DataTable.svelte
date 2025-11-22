<script lang="ts">
	import { router } from '@sveltejs/kit';
	import { setContext, onMount } from 'svelte';
	import axios from 'axios';
	import {
		generateColumnsFromModule,
		buildApiRequest,
		parseApiResponse,
		toggleMultiSort,
		serializeTableState,
		debounce
	} from './utils';
	import type {
		ColumnDef,
		TableState,
		TableContext,
		DataTableProps,
		SortConfig,
		FilterConfig
	} from './types';
	import DataTableHeader from './DataTableHeader.svelte';
	import DataTableBody from './DataTableBody.svelte';
	import DataTablePagination from './DataTablePagination.svelte';
	import DataTableToolbar from './DataTableToolbar.svelte';
	import DataTableColumnToggle from './DataTableColumnToggle.svelte';

	interface Props {
		moduleApiName: string;
		module?: any;
		columns?: ColumnDef[];
		initialData?: any[];
		defaultView?: number;
		enableSelection?: boolean;
		enableFilters?: boolean;
		enableSearch?: boolean;
		enableSorting?: boolean;
		enablePagination?: boolean;
		enableViews?: boolean;
		enableExport?: boolean;
		enableBulkActions?: boolean;
		enableColumnReorder?: boolean;
		enableColumnResize?: boolean;
		enableInlineEdit?: boolean;
		class?: string;
		onRowClick?: (row: any) => void;
		onSelectionChange?: (rows: any[]) => void;
		onBulkAction?: (action: string, rows: any[]) => void;
		onCellUpdate?: (recordId: string, field: string, value: any) => Promise<void>;
	}

	let {
		moduleApiName,
		module,
		columns: providedColumns,
		initialData,
		defaultView,
		enableSelection = true,
		enableFilters = true,
		enableSearch = true,
		enableSorting = true,
		enablePagination = true,
		enableViews = true,
		enableExport = true,
		enableBulkActions = true,
		enableColumnReorder = false,
		enableColumnResize = false,
		enableInlineEdit = true,
		class: className = '',
		onRowClick,
		onSelectionChange,
		onBulkAction,
		onCellUpdate
	}: Props = $props();

	// Generate columns from module if not provided
	let columns = $state<ColumnDef[]>(
		providedColumns || (module ? generateColumnsFromModule(module) : [])
	);

	// Initialize table state
	let state = $state<TableState>({
		data: initialData || [],
		loading: false,
		error: null,
		pagination: {
			page: 1,
			perPage: 50,
			total: 0,
			from: 0,
			to: 0,
			lastPage: 1
		},
		sorting: [],
		filters: [],
		globalFilter: '',
		columnVisibility: columns.reduce(
			(acc, col) => {
				acc[col.id] = col.visible !== false;
				return acc;
			},
			{} as Record<string, boolean>
		),
		columnOrder: columns.map((c) => c.id),
		columnWidths: columns.reduce(
			(acc, col) => {
				if (col.width) acc[col.id] = col.width;
				return acc;
			},
			{} as Record<string, number>
		),
		columnPinning: columns.reduce(
			(acc, col) => {
				if (col.pinned) acc[col.id] = col.pinned;
				return acc;
			},
			{} as Record<string, 'left' | 'right' | false>
		),
		rowSelection: {},
		currentView: null
	});

	// Visible columns based on columnVisibility and columnOrder
	let visibleColumns = $derived(
		state.columnOrder
			.filter((id) => state.columnVisibility[id])
			.map((id) => columns.find((c) => c.id === id))
			.filter(Boolean) as ColumnDef[]
	);

	// Selected rows
	let selectedRows = $derived(
		state.data.filter((row) => state.rowSelection[row.id])
	);

	// Selected row count
	let selectedCount = $derived(
		Object.values(state.rowSelection).filter(Boolean).length
	);

	// Table context for child components
	const tableContext: TableContext = {
		get state() {
			return state;
		},
		get columns() {
			return columns;
		},
		updateSort(field: string, shiftKey: boolean = false) {
			if (!enableSorting) return;

			state.sorting = toggleMultiSort(state.sorting, field, shiftKey);
			fetchData();
		},
		updateFilter(filter: FilterConfig) {
			if (!enableFilters) return;

			// Remove existing filter for same field
			state.filters = state.filters.filter((f) => f.field !== filter.field);

			// Add new filter
			state.filters.push(filter);

			// Reset to first page
			state.pagination.page = 1;

			fetchData();
		},
		removeFilter(field: string) {
			state.filters = state.filters.filter((f) => f.field !== field);
			fetchData();
		},
		clearFilters() {
			state.filters = [];
			fetchData();
		},
		updateGlobalFilter: debounce((value: string) => {
			if (!enableSearch) return;

			state.globalFilter = value;
			state.pagination.page = 1;
			fetchData();
		}, 300),
		goToPage(page: number) {
			if (!enablePagination) return;

			state.pagination.page = Math.max(1, Math.min(page, state.pagination.lastPage));
			fetchData();
		},
		setPageSize(size: number) {
			if (!enablePagination) return;

			state.pagination.perPage = size;
			state.pagination.page = 1;
			fetchData();
		},
		toggleRowSelection(rowId: string | number) {
			if (!enableSelection) return;

			state.rowSelection[rowId] = !state.rowSelection[rowId];

			if (onSelectionChange) {
				onSelectionChange(selectedRows);
			}
		},
		toggleAllRows() {
			if (!enableSelection) return;

			const allSelected = state.data.every((row) => state.rowSelection[row.id]);

			if (allSelected) {
				// Deselect all
				state.rowSelection = {};
			} else {
				// Select all visible rows
				state.data.forEach((row) => {
					state.rowSelection[row.id] = true;
				});
			}

			if (onSelectionChange) {
				onSelectionChange(selectedRows);
			}
		},
		clearSelection() {
			state.rowSelection = {};

			if (onSelectionChange) {
				onSelectionChange([]);
			}
		},
		toggleColumnVisibility(columnId: string) {
			state.columnVisibility[columnId] = !state.columnVisibility[columnId];
		},
		resetColumnVisibility() {
			state.columnVisibility = columns.reduce(
				(acc, col) => {
					acc[col.id] = col.visible !== false;
					return acc;
				},
				{} as Record<string, boolean>
			);
		},
		updateColumnVisibility(visibility: Record<string, boolean>) {
			state.columnVisibility = { ...visibility };
		},
		updateSorting(sorting: SortConfig[]) {
			state.sorting = sorting;
			fetchData();
		},
		updatePageSize(size: number) {
			state.pagination.perPage = size;
			state.pagination.page = 1;
			fetchData();
		},
		setColumnOrder(order: string[]) {
			state.columnOrder = order;
		},
		resizeColumn(columnId: string, width: number) {
			if (!enableColumnResize) return;

			state.columnWidths[columnId] = width;
		},
		pinColumn(columnId: string, position: 'left' | 'right' | false) {
			state.columnPinning[columnId] = position;
		},
		async loadView(view: any) {
			if (!view) {
				state.currentView = null;
				return;
			}

			state.currentView = view;

			// Apply view settings to table state
			if (view.filters) {
				state.filters = Array.isArray(view.filters) ? view.filters : [];
			}

			if (view.sorting) {
				state.sorting = Array.isArray(view.sorting) ? view.sorting : [];
			}

			if (view.column_visibility) {
				state.columnVisibility = { ...state.columnVisibility, ...view.column_visibility };
			}

			if (view.column_order && Array.isArray(view.column_order)) {
				state.columnOrder = view.column_order;
			}

			if (view.column_widths) {
				state.columnWidths = { ...state.columnWidths, ...view.column_widths };
			}

			if (view.page_size) {
				state.pagination.perPage = view.page_size;
			}

			// Reset to first page when loading a new view
			state.pagination.page = 1;

			// Fetch data with new view settings
			await fetchData();
		},
		async saveView(view: any) {
			// TODO: Implement view saving
		},
		async deleteView(viewId: number) {
			// TODO: Implement view deletion
		},
		async refresh() {
			await fetchData();
		}
	};

	// Set context for child components
	setContext('table', tableContext);

	// Fetch data from API
	async function fetchData() {
		state.loading = true;
		state.error = null;

		try {
			const request = buildApiRequest(state);

			const response = await axios.get(`/api/modules/${moduleApiName}/records`, {
				params: {
					page: request.page,
					per_page: request.per_page,
					sort: request.sort ? JSON.stringify(request.sort) : undefined,
					filters: request.filters ? JSON.stringify(request.filters) : undefined,
					search: request.search
				}
			});

			const { data, pagination } = parseApiResponse(response.data);

			state.data = data;
			state.pagination = pagination;

			// Update URL with current state
			const params = serializeTableState(state);
			router.visit(`?${params.toString()}`, {
				preserveState: true,
				preserveScroll: true,
				replace: true
			});
		} catch (error: any) {
			state.error = error.message || 'Failed to load data';
			console.error('DataTable fetch error:', error);
		} finally {
			state.loading = false;
		}
	}

	// Load initial data on mount
	onMount(() => {
		if (!initialData) {
			fetchData();
		}
	});

	// Handle row click
	function handleRowClick(row: any) {
		if (onRowClick) {
			onRowClick(row);
		}
	}

	// Handle cell update
	async function handleCellUpdate(recordId: string, field: string, value: any) {
		if (onCellUpdate) {
			await onCellUpdate(recordId, field, value);
		} else {
			// Default implementation: call API endpoint
			const response = await axios.patch(`/api/modules/${moduleApiName}/records/${recordId}`, {
				[field]: value
			});

			// Update local state
			const recordIndex = state.data.findIndex((r) => r.id === recordId);
			if (recordIndex !== -1) {
				state.data[recordIndex][field] = value;
			}
		}
	}
</script>

<div class="space-y-4 {className}">
	<!-- Toolbar (search, filters, bulk actions, column toggle, views) -->
	{#if enableSearch || enableFilters || enableBulkActions || enableExport || enableViews}
		<DataTableToolbar
			{enableSearch}
			{enableFilters}
			{enableBulkActions}
			{enableExport}
			{enableViews}
			enableColumnToggle={true}
			module={moduleApiName}
			defaultViewId={defaultView}
			selectedCount={selectedCount}
			hasFilters={state.filters.length > 0}
		/>
	{/if}

	<!-- Table -->
	<div class="rounded-md border">
		<div class="relative overflow-auto">
			<table class="w-full caption-bottom text-sm">
				<DataTableHeader
					columns={visibleColumns}
					{enableSelection}
					{enableSorting}
				/>
				<DataTableBody
					columns={visibleColumns}
					data={state.data}
					loading={state.loading}
					error={state.error}
					{enableSelection}
					{enableInlineEdit}
					{moduleApiName}
					onRowClick={handleRowClick}
					onCellUpdate={handleCellUpdate}
				/>
			</table>
		</div>
	</div>

	<!-- Pagination -->
	{#if enablePagination}
		<DataTablePagination />
	{/if}
</div>

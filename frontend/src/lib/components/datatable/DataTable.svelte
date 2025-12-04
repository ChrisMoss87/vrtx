<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { setContext, onMount } from 'svelte';
	import axios from 'axios';
	import {
		generateColumnsFromModule,
		buildApiRequest,
		parseApiResponse,
		toggleMultiSort,
		serializeTableState,
		debounce,
		transformFiltersForApi
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
	import * as Table from '$lib/components/ui/table';
	import { getDefaultView } from '$lib/api/views';

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
		onCreateNew?: () => void;
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
		onCellUpdate,
		onCreateNew
	}: Props = $props();

	// Generate columns from module if not provided
	const initialColumns: ColumnDef[] =
		providedColumns || (module ? generateColumnsFromModule(module) : []);
	let columns = $state<ColumnDef[]>(initialColumns);

	// Initialize table state using initial columns to avoid circular reference
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
		columnVisibility: initialColumns.reduce(
			(acc: Record<string, boolean>, col: ColumnDef) => {
				acc[col.id] = col.visible !== false;
				return acc;
			},
			{} as Record<string, boolean>
		),
		columnOrder: initialColumns.map((c: ColumnDef) => c.id),
		columnWidths: initialColumns.reduce(
			(acc: Record<string, number>, col: ColumnDef) => {
				if (col.width) acc[col.id] = col.width;
				return acc;
			},
			{} as Record<string, number>
		),
		columnPinning: initialColumns.reduce(
			(acc: Record<string, 'left' | 'right' | false>, col: ColumnDef) => {
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
			.filter((id: string) => state.columnVisibility[id])
			.map((id: string) => columns.find((c: ColumnDef) => c.id === id))
			.filter(Boolean) as ColumnDef[]
	);

	// Selected rows
	let selectedRows = $derived(state.data.filter((row: any) => state.rowSelection[row.id]));

	// Selected row count
	let selectedCount = $derived(Object.values(state.rowSelection).filter(Boolean).length);

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
			state.filters = state.filters.filter((f: FilterConfig) => f.field !== filter.field);

			// Add new filter
			state.filters.push(filter);

			// Reset to first page
			state.pagination.page = 1;

			fetchData();
		},
		removeFilter(field: string) {
			state.filters = state.filters.filter((f: FilterConfig) => f.field !== field);
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

			const allSelected = state.data.every((row: any) => state.rowSelection[row.id]);

			if (allSelected) {
				// Deselect all
				state.rowSelection = {};
			} else {
				// Select all visible rows
				state.data.forEach((row: any) => {
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
				(acc: Record<string, boolean>, col: ColumnDef) => {
					acc[col.id] = col.visible !== false;
					return acc;
				},
				{} as Record<string, boolean>
			);
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

			// Get auth token from localStorage
			const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;

			const headers: Record<string, string> = {
				'Content-Type': 'application/json',
				Accept: 'application/json'
			};

			if (token) {
				headers['Authorization'] = `Bearer ${token}`;
			}

			// Transform filters to backend format (field-indexed object)
			const transformedFilters = request.filters
				? transformFiltersForApi(request.filters)
				: undefined;

			// Log request for debugging
			console.log('DataTable request:', {
				filters: request.filters,
				transformedFilters,
				sort: request.sort,
				search: request.search
			});

			const response = await axios.get(`/api/v1/records/${moduleApiName}`, {
				params: {
					page: request.page,
					per_page: request.per_page,
					sort: request.sort ? JSON.stringify(request.sort) : undefined,
					filters: transformedFilters ? JSON.stringify(transformedFilters) : undefined,
					search: request.search
				},
				headers
			});

			console.log('API Response:', response.data);

			// Backend returns { records, meta } not { data, meta }
			const apiData = response.data;
			state.data = apiData.records || [];
			state.pagination = {
				page: apiData.meta?.current_page || 1,
				perPage: apiData.meta?.per_page || 50,
				total: apiData.meta?.total || 0,
				from: apiData.meta?.from || 0,
				to: apiData.meta?.to || 0,
				lastPage: apiData.meta?.last_page || 1
			};

			// Update URL with current state (optional, can be enabled later)
			// const params = serializeTableState(state);
			// goto(`?${params.toString()}`, { replaceState: true, keepFocus: true });
		} catch (error: any) {
			state.error = error.response?.data?.message || error.message || 'Failed to load data';
			console.error('DataTable fetch error:', error);
		} finally {
			state.loading = false;
		}
	}

	// Load default view and initial data on mount
	onMount(async () => {
		if (!initialData) {
			// Try to load default view first
			try {
				const { view, module_defaults } = await getDefaultView(moduleApiName);

				if (view) {
					// Apply view settings
					await tableContext.loadView(view);
				} else if (module_defaults) {
					// Apply module defaults
					if (module_defaults.filters) {
						state.filters = module_defaults.filters;
					}
					if (module_defaults.sorting) {
						state.sorting = module_defaults.sorting;
					}
					if (module_defaults.column_visibility) {
						state.columnVisibility = {
							...state.columnVisibility,
							...module_defaults.column_visibility
						};
					}
					if (module_defaults.page_size) {
						state.pagination.perPage = module_defaults.page_size;
					}
				}
			} catch (error) {
				console.warn('Failed to load default view, using table defaults:', error);
			}

			// Fetch data with applied settings
			await fetchData();
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
			const recordIndex = state.data.findIndex((r: { id: string }) => r.id === recordId);
			if (recordIndex !== -1) {
				state.data[recordIndex][field] = value;
			}
		}
	}

	console.log('state', state);
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
			{selectedCount}
			hasFilters={state.filters.length > 0}
		/>
	{/if}

	<!-- Table with horizontal scroll on mobile -->
	<div class="overflow-hidden rounded-md border">
		<div class="overflow-x-auto" role="region" aria-label="Data table" tabindex="0">
			<Table.Root>
				<DataTableHeader columns={visibleColumns} {enableSelection} {enableSorting} />
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
					{onCreateNew}
				/>
			</Table.Root>
		</div>
	</div>

	<!-- Pagination -->
	{#if enablePagination}
		<DataTablePagination />
	{/if}
</div>

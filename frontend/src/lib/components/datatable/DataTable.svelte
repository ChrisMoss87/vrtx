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
	import DataTableVirtualBody from './DataTableVirtualBody.svelte';
	import DataTableGroupedBody from './DataTableGroupedBody.svelte';
	import DataTablePagination from './DataTablePagination.svelte';
	import DataTableToolbar from './DataTableToolbar.svelte';
	import * as Table from '$lib/components/ui/table';
	import {
		getDefaultView,
		createView,
		updateView,
		deleteView,
		type ModuleView,
		type CreateViewRequest
	} from '$lib/api/views';
	import { toast } from 'svelte-sonner';

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
		enableVirtualScroll?: boolean;
		virtualRowHeight?: number;
		enableGrouping?: boolean;
		groupByField?: string;
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
		enableVirtualScroll = false,
		virtualRowHeight = 48,
		enableGrouping = false,
		groupByField,
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
	let columns: ColumnDef[] = $state(initialColumns);

	// Build initial state values from columns
	const initialColumnVisibility = initialColumns.reduce(
		(acc: Record<string, boolean>, col: ColumnDef) => {
			acc[col.id] = col.visible !== false;
			return acc;
		},
		{} as Record<string, boolean>
	);
	const initialColumnOrder = initialColumns.map((c: ColumnDef) => c.id);
	const initialColumnWidths = initialColumns.reduce(
		(acc: Record<string, number>, col: ColumnDef) => {
			if (col.width) acc[col.id] = col.width;
			return acc;
		},
		{} as Record<string, number>
	);
	const initialColumnPinning = initialColumns.reduce(
		(acc: Record<string, 'left' | 'right' | false>, col: ColumnDef) => {
			if (col.pinned) acc[col.id] = col.pinned;
			return acc;
		},
		{} as Record<string, 'left' | 'right' | false>
	);

	// Initialize table state
	let tableState: TableState = $state({
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
		columnVisibility: initialColumnVisibility,
		columnOrder: initialColumnOrder,
		columnWidths: initialColumnWidths,
		columnPinning: initialColumnPinning,
		rowSelection: {},
		currentView: null
	});

	// Visible columns based on columnVisibility and columnOrder
	// Note: columnVisibility[id] === undefined means visible (not explicitly hidden)
	let visibleColumns = $derived(
		tableState.columnOrder
			.filter((id: string) => tableState.columnVisibility[id] !== false)
			.map((id: string) => columns.find((c: ColumnDef) => c.id === id))
			.filter(Boolean) as ColumnDef[]
	);

	// Selected rows
	let selectedRows = $derived(tableState.data.filter((row: any) => tableState.rowSelection[row.id]));

	// Selected row count
	let selectedCount = $derived(Object.values(tableState.rowSelection).filter(Boolean).length);

	// Table context for child components
	const tableContext: TableContext = {
		get state() {
			return tableState;
		},
		get columns() {
			return columns;
		},
		updateSort(field: string, shiftKey: boolean = false) {
			if (!enableSorting) return;

			tableState.sorting = toggleMultiSort(tableState.sorting, field, shiftKey);
			fetchData();
		},
		updateFilter(filter: FilterConfig) {
			if (!enableFilters) return;

			// Remove existing filter for same field
			tableState.filters = tableState.filters.filter((f: FilterConfig) => f.field !== filter.field);

			// Add new filter
			tableState.filters.push(filter);

			// Reset to first page
			tableState.pagination.page = 1;

			fetchData();
		},
		removeFilter(field: string) {
			tableState.filters = tableState.filters.filter((f: FilterConfig) => f.field !== field);
			fetchData();
		},
		clearFilters() {
			tableState.filters = [];
			fetchData();
		},
		setFilters(filters: FilterConfig[]) {
			if (!enableFilters) return;

			tableState.filters = [...filters];
			tableState.pagination.page = 1;
			fetchData();
		},
		updateGlobalFilter: debounce((value: string) => {
			if (!enableSearch) return;

			tableState.globalFilter = value;
			tableState.pagination.page = 1;
			fetchData();
		}, 300),
		goToPage(page: number) {
			if (!enablePagination) return;

			tableState.pagination.page = Math.max(1, Math.min(page, tableState.pagination.lastPage));
			fetchData();
		},
		setPageSize(size: number) {
			if (!enablePagination) return;

			tableState.pagination.perPage = size;
			tableState.pagination.page = 1;
			fetchData();
		},
		toggleRowSelection(rowId: string | number) {
			if (!enableSelection) return;

			tableState.rowSelection[rowId] = !tableState.rowSelection[rowId];

			if (onSelectionChange) {
				onSelectionChange(selectedRows);
			}
		},
		toggleAllRows() {
			if (!enableSelection) return;

			const allSelected = tableState.data.every((row: any) => tableState.rowSelection[row.id]);

			if (allSelected) {
				// Deselect all
				tableState.rowSelection = {};
			} else {
				// Select all visible rows
				tableState.data.forEach((row: any) => {
					tableState.rowSelection[row.id] = true;
				});
			}

			if (onSelectionChange) {
				onSelectionChange(selectedRows);
			}
		},
		clearSelection() {
			tableState.rowSelection = {};

			if (onSelectionChange) {
				onSelectionChange([]);
			}
		},
		toggleColumnVisibility(columnId: string) {
			tableState.columnVisibility[columnId] = !tableState.columnVisibility[columnId];
		},
		resetColumnVisibility() {
			tableState.columnVisibility = columns.reduce(
				(acc: Record<string, boolean>, col: ColumnDef) => {
					acc[col.id] = col.visible !== false;
					return acc;
				},
				{} as Record<string, boolean>
			);
		},
		setColumnOrder(order: string[]) {
			tableState.columnOrder = order;
		},
		resizeColumn(columnId: string, width: number) {
			if (!enableColumnResize) return;

			tableState.columnWidths[columnId] = width;
		},
		pinColumn(columnId: string, position: 'left' | 'right' | false) {
			tableState.columnPinning[columnId] = position;
		},
		async loadView(view: any) {
			if (!view) {
				tableState.currentView = null;
				return;
			}

			tableState.currentView = view;

			// Apply view settings to table state
			if (view.filters) {
				tableState.filters = Array.isArray(view.filters) ? view.filters : [];
			}

			if (view.sorting) {
				tableState.sorting = Array.isArray(view.sorting) ? view.sorting : [];
			}

			if (view.column_visibility) {
				tableState.columnVisibility = { ...tableState.columnVisibility, ...view.column_visibility };
			}

			if (view.column_order && Array.isArray(view.column_order)) {
				// Merge view's column order with any new columns that may have been added
				// View's order takes precedence, then any missing columns are added at the end
				const allColumnIds = columns.map((c: ColumnDef) => c.id);
				const viewOrderSet = new Set(view.column_order);
				const missingColumns = allColumnIds.filter((id: string) => !viewOrderSet.has(id));
				tableState.columnOrder = [...view.column_order, ...missingColumns];
			}

			if (view.column_widths) {
				tableState.columnWidths = { ...tableState.columnWidths, ...view.column_widths };
			}

			if (view.page_size) {
				tableState.pagination.perPage = view.page_size;
			}

			// Reset to first page when loading a new view
			tableState.pagination.page = 1;

			// Fetch data with new view settings
			await fetchData();
		},
		async saveView(viewData: CreateViewRequest): Promise<ModuleView | null> {
			try {
				const view = await createView(moduleApiName, {
					...viewData,
					filters: tableState.filters,
					sorting: tableState.sorting,
					column_visibility: tableState.columnVisibility,
					column_order: tableState.columnOrder,
					column_widths: tableState.columnWidths,
					page_size: tableState.pagination.perPage
				});

				tableState.currentView = view;
				toast.success('View saved', {
					description: `"${view.name}" has been saved successfully.`
				});

				return view;
			} catch (error: any) {
				console.error('Failed to save view:', error);
				toast.error('Failed to save view', {
					description: error.response?.data?.message || error.message || 'An error occurred'
				});
				return null;
			}
		},
		async deleteView(viewId: number): Promise<boolean> {
			try {
				await deleteView(moduleApiName, viewId);

				// Clear current view if it was the deleted one
				if (tableState.currentView?.id === viewId) {
					tableState.currentView = null;
				}

				toast.success('View deleted', {
					description: 'The view has been deleted successfully.'
				});

				return true;
			} catch (error: any) {
				console.error('Failed to delete view:', error);
				toast.error('Failed to delete view', {
					description: error.response?.data?.message || error.message || 'An error occurred'
				});
				return false;
			}
		},
		async updateCurrentView(): Promise<ModuleView | null> {
			if (!tableState.currentView) {
				toast.error('No view selected', {
					description: 'Please select a view to update.'
				});
				return null;
			}

			try {
				const viewId = 'id' in tableState.currentView ? tableState.currentView.id : undefined;
				if (!viewId) {
					toast.error('Invalid view', {
						description: 'The current view cannot be updated.'
					});
					return null;
				}
				const view = await updateView(moduleApiName, viewId, {
					filters: tableState.filters,
					sorting: tableState.sorting,
					column_visibility: tableState.columnVisibility,
					column_order: tableState.columnOrder,
					column_widths: tableState.columnWidths,
					page_size: tableState.pagination.perPage
				});

				tableState.currentView = view;
				toast.success('View updated', {
					description: `"${view.name}" has been updated successfully.`
				});

				return view;
			} catch (error: any) {
				console.error('Failed to update view:', error);
				toast.error('Failed to update view', {
					description: error.response?.data?.message || error.message || 'An error occurred'
				});
				return null;
			}
		},
		async refresh() {
			await fetchData();
		}
	};

	// Set context for child components
	setContext('table', tableContext);

	// Fetch data from API
	async function fetchData() {
		tableState.loading = true;
		tableState.error = null;

		try {
			const request = buildApiRequest(tableState);

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

			// Transform sort from array of objects to object format for Laravel
			// Frontend format: [{ field: 'name', direction: 'asc' }]
			// Backend format: { 'name': 'asc' }
			const transformedSort: Record<string, string> = {};
			if (request.sort && request.sort.length > 0) {
				request.sort.forEach((s) => {
					transformedSort[s.field] = s.direction;
				});
			}

			// Log request for debugging
			console.log('DataTable request:', {
				filters: request.filters,
				transformedFilters,
				sort: request.sort,
				transformedSort,
				search: request.search
			});

			// Build params object - axios will serialize arrays properly
			const params: Record<string, any> = {
				page: request.page,
				per_page: request.per_page
			};

			// Add sort as object (Laravel expects field => direction format)
			if (Object.keys(transformedSort).length > 0) {
				params.sort = transformedSort;
			}

			// Add filters as object (Laravel expects array/object)
			if (transformedFilters && Object.keys(transformedFilters).length > 0) {
				params.filters = transformedFilters;
			}

			// Add search
			if (request.search) {
				params.search = request.search;
			}

			const response = await axios.get(`/api/v1/records/${moduleApiName}`, {
				params,
				headers,
				paramsSerializer: {
					serialize: (params) => {
						// Custom serializer to handle nested objects/arrays for Laravel
						const searchParams = new URLSearchParams();

						for (const [key, value] of Object.entries(params)) {
							if (value === undefined || value === null) continue;

							if (Array.isArray(value)) {
								// Handle arrays like sort[0][field]=name&sort[0][direction]=asc
								value.forEach((item, index) => {
									if (typeof item === 'object') {
										for (const [itemKey, itemValue] of Object.entries(item)) {
											searchParams.append(`${key}[${index}][${itemKey}]`, String(itemValue));
										}
									} else {
										searchParams.append(`${key}[${index}]`, String(item));
									}
								});
							} else if (typeof value === 'object') {
								// Handle objects like filters[name][operator]=contains&filters[name][value]=test
								for (const [objKey, objValue] of Object.entries(value)) {
									if (typeof objValue === 'object' && objValue !== null) {
										for (const [nestedKey, nestedValue] of Object.entries(objValue)) {
											// Handle array values (e.g., filters[status][value][] = ['lead', 'qualified'])
											if (Array.isArray(nestedValue)) {
												nestedValue.forEach((item, idx) => {
													searchParams.append(`${key}[${objKey}][${nestedKey}][${idx}]`, String(item));
												});
											} else {
												searchParams.append(`${key}[${objKey}][${nestedKey}]`, String(nestedValue));
											}
										}
									} else {
										searchParams.append(`${key}[${objKey}]`, String(objValue));
									}
								}
							} else {
								searchParams.append(key, String(value));
							}
						}

						return searchParams.toString();
					}
				}
			});

			console.log('API Response:', response.data);

			// Backend returns { records, meta } not { data, meta }
			const apiData = response.data;
			tableState.data = apiData.records || [];
			tableState.pagination = {
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
			tableState.error = error.response?.data?.message || error.message || 'Failed to load data';
			console.error('DataTable fetch error:', error);
		} finally {
			tableState.loading = false;
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
						tableState.filters = module_defaults.filters;
					}
					if (module_defaults.sorting) {
						tableState.sorting = module_defaults.sorting;
					}
					if (module_defaults.column_visibility) {
						tableState.columnVisibility = {
							...tableState.columnVisibility,
							...module_defaults.column_visibility
						};
					}
					if (module_defaults.page_size) {
						tableState.pagination.perPage = module_defaults.page_size;
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
			const recordIndex = tableState.data.findIndex((r) => r.id === recordId);
			if (recordIndex !== -1) {
				tableState.data[recordIndex][field] = value;
			}
		}
	}

	console.log('tableState', tableState);
</script>

<div class="space-y-6 {className}">
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
			hasFilters={tableState.filters.length > 0}
		/>
	{/if}

	<!-- Table with horizontal scroll on mobile -->
	<div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm transition-shadow duration-300 hover:shadow-md">
		<div class="overflow-x-auto" role="region" aria-label="Data table" tabindex="0">
			{#if enableGrouping && groupByField}
				<!-- Grouped mode -->
				<Table.Root class={enableColumnResize ? 'table-fixed' : ''}>
					<DataTableHeader
						columns={visibleColumns}
						{enableSelection}
						{enableSorting}
						enableColumnFilters={enableFilters}
						{enableColumnResize}
						{enableColumnReorder}
						hasGrouping={true}
					/>
					{#if tableState.data.length > 0 && !tableState.loading && !tableState.error}
						<DataTableGroupedBody
							columns={visibleColumns}
							data={tableState.data}
							{groupByField}
							loading={tableState.loading}
							error={tableState.error}
							{enableSelection}
							{enableInlineEdit}
							{enableColumnResize}
							{moduleApiName}
							onRowClick={handleRowClick}
							onCellUpdate={handleCellUpdate}
							{onCreateNew}
						/>
					{/if}
				</Table.Root>
			{:else if enableVirtualScroll}
				<!-- Virtual scrolling mode for large datasets -->
				<Table.Root class={enableColumnResize ? 'table-fixed' : ''}>
					<DataTableHeader
						columns={visibleColumns}
						{enableSelection}
						{enableSorting}
						enableColumnFilters={enableFilters}
						{enableColumnResize}
						{enableColumnReorder}
					/>
				</Table.Root>
				{#if tableState.data.length > 0 && !tableState.loading && !tableState.error}
					<DataTableVirtualBody
						columns={visibleColumns}
						data={tableState.data}
						loading={tableState.loading}
						error={tableState.error}
						{enableSelection}
						{enableInlineEdit}
						{enableColumnResize}
						{moduleApiName}
						rowHeight={virtualRowHeight}
						onRowClick={handleRowClick}
						onCellUpdate={handleCellUpdate}
						{onCreateNew}
					/>
				{/if}
			{:else}
				<!-- Standard mode -->
				<Table.Root class={enableColumnResize ? 'table-fixed' : ''}>
					<DataTableHeader
						columns={visibleColumns}
						{enableSelection}
						{enableSorting}
						enableColumnFilters={enableFilters}
						{enableColumnResize}
						{enableColumnReorder}
					/>
					{#if tableState.data.length > 0 && !tableState.loading && !tableState.error}
						<DataTableBody
							columns={visibleColumns}
							data={tableState.data}
							loading={false}
							error={null}
							{enableSelection}
							{enableInlineEdit}
							{enableColumnResize}
							{moduleApiName}
							onRowClick={handleRowClick}
							onCellUpdate={handleCellUpdate}
							{onCreateNew}
						/>
					{/if}
				</Table.Root>
			{/if}
		</div>

		<!-- Empty/Loading/Error states - displayed outside the scrollable table area -->
		{#if tableState.loading}
			<div class="flex flex-col items-center justify-center py-16 text-muted-foreground">
				<div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
				<span class="mt-3 text-sm">Loading records...</span>
			</div>
		{:else if tableState.error}
			<div class="flex flex-col items-center justify-center py-16">
				<div class="flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10">
					<svg class="h-8 w-8 text-destructive" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
					</svg>
				</div>
				<p class="mt-4 font-medium text-destructive">Error loading data</p>
				<p class="mt-1 max-w-md text-center text-sm text-muted-foreground">{tableState.error}</p>
				<button
					type="button"
					class="mt-4 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
					onclick={() => tableContext.refresh()}
				>
					<svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
					</svg>
					Try again
				</button>
			</div>
		{:else if tableState.data.length === 0}
			{@const hasFilters = tableState.filters.length > 0}
			{@const hasSearch = tableState.globalFilter?.length > 0}
			<div class="flex flex-col items-center justify-center py-16">
				{#if hasFilters || hasSearch}
					<!-- No results due to filters/search -->
					<div class="flex h-16 w-16 items-center justify-center rounded-full bg-sky-50 dark:bg-sky-950/30">
						<svg class="h-8 w-8 text-sky-500 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
						</svg>
					</div>
					<p class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">No matching records</p>
					<p class="mt-1 max-w-md text-center text-sm text-slate-500 dark:text-slate-400">
						{#if hasSearch && hasFilters}
							No records match your search and filters.
						{:else if hasSearch}
							No records match "{tableState.globalFilter}".
						{:else}
							No records match your current filters.
						{/if}
					</p>
					<button
						type="button"
						class="mt-4 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
						onclick={() => {
							tableContext.clearFilters();
							if (hasSearch) tableContext.updateGlobalFilter('');
						}}
					>
						<svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
						</svg>
						Clear {hasSearch && hasFilters ? 'all' : hasSearch ? 'search' : 'filters'}
					</button>
				{:else}
					<!-- No records exist yet -->
					<div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
						<svg class="h-8 w-8 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
						</svg>
					</div>
					<p class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">No records yet</p>
					<p class="mt-1 max-w-md text-center text-sm text-slate-500 dark:text-slate-400">
						Get started by creating your first record in this module.
					</p>
					{#if onCreateNew}
						<button
							type="button"
							class="mt-4 inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
							onclick={onCreateNew}
						>
							<svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
							</svg>
							Create first record
						</button>
					{/if}
				{/if}
			</div>
		{/if}
	</div>

	<!-- Pagination -->
	{#if enablePagination}
		<DataTablePagination />
	{/if}
</div>

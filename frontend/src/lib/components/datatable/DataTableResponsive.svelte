<script lang="ts">
	import { onMount } from 'svelte';
	import type { Snippet } from 'svelte';
	import type { ColumnDef, BaseRowData, PaginationState, RowSelection, TableState } from './types';
	import DataTableCardList from './DataTableCardList.svelte';
	import { Monitor, Smartphone } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';

	interface Props {
		/** Current table state */
		tableState: TableState;
		/** Column definitions */
		columns: ColumnDef[];
		/** Breakpoint for switching to card view (default: 1024) */
		mobileBreakpoint?: number;
		/** Force a specific view mode */
		forceView?: 'table' | 'cards' | 'auto';
		/** Record name field for card display */
		recordNameField?: string;
		/** Maximum visible fields on cards */
		maxVisibleFields?: number;
		/** Enable row selection */
		enableSelection?: boolean;
		/** Enable pagination */
		enablePagination?: boolean;
		/** Show view toggle button */
		showViewToggle?: boolean;
		/** Row click handler */
		onRowClick?: (record: BaseRowData) => void;
		/** Selection change handler */
		onSelectionChange?: (selection: RowSelection) => void;
		/** Page change handler */
		onPageChange?: (page: number) => void;
		/** Page size change handler */
		onPageSizeChange?: (size: number) => void;
		/** Action handler for card actions */
		onAction?: (action: string, record: BaseRowData) => void;
		/** Table content snippet (rendered when showing table view) */
		tableContent: Snippet;
		/** Custom class */
		class?: string;
	}

	let {
		tableState,
		columns,
		mobileBreakpoint = 1024,
		forceView = 'auto',
		recordNameField,
		maxVisibleFields = 4,
		enableSelection = true,
		enablePagination = true,
		showViewToggle = true,
		onRowClick,
		onSelectionChange,
		onPageChange,
		onPageSizeChange,
		onAction,
		tableContent,
		class: className = ''
	}: Props = $props();

	// Track viewport width
	let windowWidth = $state(typeof window !== 'undefined' ? window.innerWidth : 1200);
	let userPreferredView = $state<'table' | 'cards' | null>(null);

	// Determine current view
	const currentView = $derived.by(() => {
		if (forceView !== 'auto') {
			return forceView;
		}
		if (userPreferredView) {
			return userPreferredView;
		}
		return windowWidth < mobileBreakpoint ? 'cards' : 'table';
	});

	const isMobileViewport = $derived(windowWidth < mobileBreakpoint);

	onMount(() => {
		// Update width on resize
		const handleResize = () => {
			windowWidth = window.innerWidth;
		};

		// Use matchMedia for better performance
		const mediaQuery = window.matchMedia(`(max-width: ${mobileBreakpoint - 1}px)`);

		const handleMediaChange = (e: MediaQueryListEvent | MediaQueryList) => {
			windowWidth = e.matches ? mobileBreakpoint - 1 : mobileBreakpoint;
		};

		// Initial check
		handleMediaChange(mediaQuery);

		// Listen for changes
		mediaQuery.addEventListener('change', handleMediaChange);
		window.addEventListener('resize', handleResize);

		return () => {
			mediaQuery.removeEventListener('change', handleMediaChange);
			window.removeEventListener('resize', handleResize);
		};
	});

	function toggleView() {
		if (currentView === 'table') {
			userPreferredView = 'cards';
		} else {
			userPreferredView = 'table';
		}
	}

	function handleSelectionChange(selection: RowSelection) {
		onSelectionChange?.(selection);
	}

	function handleAction(action: string, record: BaseRowData) {
		if (action === 'view' && onRowClick) {
			onRowClick(record);
		} else {
			onAction?.(action, record);
		}
	}
</script>

<div class="relative h-full {className}">
	<!-- View Toggle (shown when manually switchable) -->
	{#if showViewToggle && forceView === 'auto'}
		<div class="absolute top-2 right-2 z-10 lg:hidden">
			<Button
				variant="outline"
				size="sm"
				class="h-8 px-2 bg-background/80 backdrop-blur"
				onclick={toggleView}
				title={currentView === 'cards' ? 'Switch to table view' : 'Switch to card view'}
			>
				{#if currentView === 'cards'}
					<Monitor class="h-4 w-4" />
				{:else}
					<Smartphone class="h-4 w-4" />
				{/if}
			</Button>
		</div>
	{/if}

	<!-- Content based on view mode -->
	{#if currentView === 'cards'}
		<DataTableCardList
			data={tableState.data}
			{columns}
			pagination={tableState.pagination}
			rowSelection={tableState.rowSelection}
			loading={tableState.loading}
			{recordNameField}
			{maxVisibleFields}
			{enableSelection}
			{enablePagination}
			onRowClick={onRowClick}
			onSelectionChange={handleSelectionChange}
			onPageChange={onPageChange}
			onPageSizeChange={onPageSizeChange}
			onAction={handleAction}
		/>
	{:else}
		{@render tableContent()}
	{/if}
</div>

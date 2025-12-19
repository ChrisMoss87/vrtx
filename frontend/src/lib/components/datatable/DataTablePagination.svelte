<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-svelte';
	import type { TableContext } from './types';

	const table = getContext<TableContext>('table');

	const pageSizeOptions = [10, 25, 50, 100, 200];

	// Calculate page info
	let pageInfo = $derived({
		from: table.state.pagination.from,
		to: table.state.pagination.to,
		total: table.state.pagination.total,
		currentPage: table.state.pagination.page,
		lastPage: table.state.pagination.lastPage,
		hasNextPage: table.state.pagination.page < table.state.pagination.lastPage,
		hasPrevPage: table.state.pagination.page > 1
	});

	let selectedPageSize = $state(String(table.state.pagination.perPage));

	function handlePageSizeChange(value: string) {
		if (value) {
			selectedPageSize = value;
			table.setPageSize(Number(value));
		}
	}
</script>

<div
	class="flex flex-col gap-4 px-2 sm:flex-row sm:items-center sm:justify-between"
	role="navigation"
	aria-label="Pagination"
>
	<!-- Row info and page size selector -->
	<div
		class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground sm:justify-start sm:gap-4"
	>
		<!-- Mobile: compact info -->
		<div class="sm:hidden" aria-live="polite">
			<span class="font-medium">{pageInfo.from}-{pageInfo.to}</span> of
			<span class="font-medium">{pageInfo.total}</span>
		</div>
		<!-- Desktop: full info -->
		<div class="hidden sm:block" aria-live="polite">
			Showing <span class="font-medium">{pageInfo.from}</span> to
			<span class="font-medium">{pageInfo.to}</span> of
			<span class="font-medium">{pageInfo.total}</span> results
		</div>

		<div class="flex items-center gap-2">
			<span class="hidden sm:inline">Rows per page</span>
			<span class="sm:hidden">Per page</span>
			<Select.Root
				type="single"
				value={selectedPageSize}
				onValueChange={(v) => v && handlePageSizeChange(v)}
			>
				<Select.Trigger class="h-8 w-[70px]" aria-label="Select rows per page">
					<span>{selectedPageSize}</span>
				</Select.Trigger>
				<Select.Content>
					{#each pageSizeOptions as size}
						<Select.Item value={String(size)}>{size}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>
	</div>

	<!-- Page navigation -->
	<div class="flex items-center justify-between gap-1 sm:justify-end">
		<!-- Mobile: simple page indicator -->
		<div class="text-sm text-muted-foreground sm:mr-2" aria-current="page">
			<span class="sm:hidden">{pageInfo.currentPage}/{pageInfo.lastPage}</span>
			<span class="hidden sm:inline">Page {pageInfo.currentPage} of {pageInfo.lastPage}</span>
		</div>

		<div class="flex items-center gap-1">
			<!-- First page - hidden on mobile to save space -->
			<Button
				variant="outline"
				size="sm"
				onclick={() => table.goToPage(1)}
				disabled={!pageInfo.hasPrevPage}
				class="hidden h-8 w-8 p-0 sm:flex"
				aria-label="Go to first page"
			>
				<ChevronsLeft class="h-4 w-4" />
			</Button>

			<Button
				variant="outline"
				size="sm"
				onclick={() => table.goToPage(pageInfo.currentPage - 1)}
				disabled={!pageInfo.hasPrevPage}
				class="h-8 w-8 p-0"
				aria-label="Go to previous page"
			>
				<ChevronLeft class="h-4 w-4" />
			</Button>

			<Button
				variant="outline"
				size="sm"
				onclick={() => table.goToPage(pageInfo.currentPage + 1)}
				disabled={!pageInfo.hasNextPage}
				class="h-8 w-8 p-0"
				aria-label="Go to next page"
			>
				<ChevronRight class="h-4 w-4" />
			</Button>

			<!-- Last page - hidden on mobile to save space -->
			<Button
				variant="outline"
				size="sm"
				onclick={() => table.goToPage(pageInfo.lastPage)}
				disabled={!pageInfo.hasNextPage}
				class="hidden h-8 w-8 p-0 sm:flex"
				aria-label="Go to last page"
			>
				<ChevronsRight class="h-4 w-4" />
			</Button>
		</div>
	</div>
</div>

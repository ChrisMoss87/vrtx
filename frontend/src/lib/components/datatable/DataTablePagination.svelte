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

	function handlePageSizeChange(value: string | undefined) {
		if (value) {
			selectedPageSize = value;
			table.setPageSize(Number(value));
		}
	}
</script>

<div class="flex items-center justify-between px-2">
	<!-- Row info and page size selector -->
	<div class="flex items-center gap-4 text-sm text-muted-foreground">
		<div>
			Showing <span class="font-medium">{pageInfo.from}</span> to
			<span class="font-medium">{pageInfo.to}</span> of
			<span class="font-medium">{pageInfo.total}</span> results
		</div>

		<div class="flex items-center gap-2">
			<span>Rows per page</span>
			<Select.Root value={selectedPageSize} onValueChange={handlePageSizeChange}>
				<Select.Trigger class="h-8 w-[70px]">
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
	<div class="flex items-center gap-1">
		<div class="text-sm text-muted-foreground mr-2">
			Page {pageInfo.currentPage} of {pageInfo.lastPage}
		</div>

		<Button
			variant="outline"
			size="sm"
			onclick={() => table.goToPage(1)}
			disabled={!pageInfo.hasPrevPage}
			class="h-8 w-8 p-0"
		>
			<ChevronsLeft class="h-4 w-4" />
			<span class="sr-only">First page</span>
		</Button>

		<Button
			variant="outline"
			size="sm"
			onclick={() => table.goToPage(pageInfo.currentPage - 1)}
			disabled={!pageInfo.hasPrevPage}
			class="h-8 w-8 p-0"
		>
			<ChevronLeft class="h-4 w-4" />
			<span class="sr-only">Previous page</span>
		</Button>

		<Button
			variant="outline"
			size="sm"
			onclick={() => table.goToPage(pageInfo.currentPage + 1)}
			disabled={!pageInfo.hasNextPage}
			class="h-8 w-8 p-0"
		>
			<ChevronRight class="h-4 w-4" />
			<span class="sr-only">Next page</span>
		</Button>

		<Button
			variant="outline"
			size="sm"
			onclick={() => table.goToPage(pageInfo.lastPage)}
			disabled={!pageInfo.hasNextPage}
			class="h-8 w-8 p-0"
		>
			<ChevronsRight class="h-4 w-4" />
			<span class="sr-only">Last page</span>
		</Button>
	</div>
</div>

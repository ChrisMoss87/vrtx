<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-svelte';
	import type { PaginationState } from './types';

	interface Props {
		pagination: PaginationState;
		onPageChange?: (page: number) => void;
		onPageSizeChange?: (size: number) => void;
		class?: string;
	}

	let {
		pagination,
		onPageChange,
		onPageSizeChange,
		class: className = ''
	}: Props = $props();

	const pageSizeOptions = [10, 25, 50, 100];

	// Calculate page info
	const pageInfo = $derived({
		from: pagination.from,
		to: pagination.to,
		total: pagination.total,
		currentPage: pagination.page,
		lastPage: pagination.lastPage,
		hasNextPage: pagination.page < pagination.lastPage,
		hasPrevPage: pagination.page > 1
	});

	let selectedPageSize = $state(String(pagination.perPage));

	function handlePageSizeChange(value: string | undefined) {
		if (value) {
			selectedPageSize = value;
			onPageSizeChange?.(Number(value));
		}
	}

	function goToPage(page: number) {
		onPageChange?.(page);
	}
</script>

<div
	class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between {className}"
	role="navigation"
	aria-label="Pagination"
>
	<!-- Row info and page size selector -->
	<div class="flex items-center justify-between gap-2 text-sm text-muted-foreground sm:justify-start sm:gap-4">
		<!-- Compact info for mobile -->
		<div aria-live="polite">
			<span class="font-medium">{pageInfo.from}-{pageInfo.to}</span>
			<span class="text-muted-foreground/70"> of </span>
			<span class="font-medium">{pageInfo.total}</span>
		</div>

		<div class="flex items-center gap-2">
			<span class="text-xs text-muted-foreground">Per page</span>
			<Select.Root
				type="single"
				value={selectedPageSize}
				onValueChange={handlePageSizeChange}
			>
				<Select.Trigger class="h-8 w-[60px]" aria-label="Select rows per page">
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
		<!-- Page indicator -->
		<div class="text-sm text-muted-foreground sm:mr-2" aria-current="page">
			Page {pageInfo.currentPage} of {pageInfo.lastPage}
		</div>

		<div class="flex items-center gap-1">
			<!-- First page - hidden on small screens -->
			<Button
				variant="outline"
				size="sm"
				onclick={() => goToPage(1)}
				disabled={!pageInfo.hasPrevPage}
				class="hidden h-8 w-8 p-0 sm:flex"
				aria-label="Go to first page"
			>
				<ChevronsLeft class="h-4 w-4" />
			</Button>

			<Button
				variant="outline"
				size="sm"
				onclick={() => goToPage(pageInfo.currentPage - 1)}
				disabled={!pageInfo.hasPrevPage}
				class="h-8 w-8 p-0"
				aria-label="Go to previous page"
			>
				<ChevronLeft class="h-4 w-4" />
			</Button>

			<Button
				variant="outline"
				size="sm"
				onclick={() => goToPage(pageInfo.currentPage + 1)}
				disabled={!pageInfo.hasNextPage}
				class="h-8 w-8 p-0"
				aria-label="Go to next page"
			>
				<ChevronRight class="h-4 w-4" />
			</Button>

			<!-- Last page - hidden on small screens -->
			<Button
				variant="outline"
				size="sm"
				onclick={() => goToPage(pageInfo.lastPage)}
				disabled={!pageInfo.hasNextPage}
				class="hidden h-8 w-8 p-0 sm:flex"
				aria-label="Go to last page"
			>
				<ChevronsRight class="h-4 w-4" />
			</Button>
		</div>
	</div>
</div>

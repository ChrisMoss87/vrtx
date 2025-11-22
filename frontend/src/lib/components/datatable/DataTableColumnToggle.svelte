<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Settings2 } from 'lucide-svelte';
	import type { TableContext } from './types';

	const table = getContext<TableContext>('table');

	// Get visible column count
	let visibleCount = $derived(
		Object.values(table.state.columnVisibility).filter(Boolean).length
	);
</script>

<DropdownMenu.Root>
	<DropdownMenu.Trigger asChild>
		{#snippet child({ props })}
			<Button {...props} variant="outline" size="sm" class="ml-auto">
				<Settings2 class="mr-2 h-4 w-4" />
				Columns ({visibleCount})
			</Button>
		{/snippet}
	</DropdownMenu.Trigger>
	<DropdownMenu.Content align="end" class="w-[200px]">
		<DropdownMenu.Label>Toggle columns</DropdownMenu.Label>
		<DropdownMenu.Separator />
		<div class="space-y-1 p-1">
			{#each table.columns as column (column.id)}
				{#if column.id !== 'select'}
					<div class="flex items-center space-x-2 rounded-sm px-2 py-1.5 hover:bg-accent">
						<Checkbox
							id="toggle-{column.id}"
							checked={table.state.columnVisibility[column.id] !== false}
							onCheckedChange={() => table.toggleColumnVisibility(column.id)}
						/>
						<label
							for="toggle-{column.id}"
							class="flex-1 text-sm font-normal cursor-pointer select-none"
						>
							{column.header}
						</label>
					</div>
				{/if}
			{/each}
		</div>
		<DropdownMenu.Separator />
		<DropdownMenu.Item onclick={() => table.resetColumnVisibility()}>
			Reset to default
		</DropdownMenu.Item>
	</DropdownMenu.Content>
</DropdownMenu.Root>

<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Settings2, GripVertical, PinOff, ArrowLeftToLine, ArrowRightToLine } from 'lucide-svelte';
	import type { TableContext, ColumnDef } from './types';
	import { flip } from 'svelte/animate';
	import { dndzone, SHADOW_ITEM_MARKER_PROPERTY_NAME, TRIGGERS } from 'svelte-dnd-action';

	const table = getContext<TableContext>('table');

	// Get visible column count
	let visibleCount = $derived(
		table.columns.filter(
			(col) => col.id !== 'select' && table.state.columnVisibility[col.id] !== false
		).length
	);

	// Get ordered columns for display
	function getOrderedColumns(): ColumnDef[] {
		const order = table.state.columnOrder;
		const cols = table.columns.filter((col) => col.id !== 'select');

		if (order.length === 0) {
			return cols;
		}

		return [...cols].sort((a, b) => {
			const aIndex = order.indexOf(a.id);
			const bIndex = order.indexOf(b.id);
			if (aIndex === -1 && bIndex === -1) return 0;
			if (aIndex === -1) return 1;
			if (bIndex === -1) return -1;
			return aIndex - bIndex;
		});
	}

	// DND items - must have id property
	let items = $state<Array<{ id: string; column: ColumnDef; [SHADOW_ITEM_MARKER_PROPERTY_NAME]?: boolean }>>([]);

	// Track if we're currently dragging to prevent $effect from overwriting drag state
	let isDragging = $state(false);

	// Initialize items only when not dragging
	$effect(() => {
		if (isDragging) return;

		const ordered = getOrderedColumns();
		// Only update if column order actually changed (compare ids)
		const currentIds = items.map((i) => i.id).join(',');
		const newIds = ordered.map((c) => c.id).join(',');
		if (currentIds !== newIds) {
			items = ordered.map((col) => ({ id: col.id, column: col }));
		}
	});

	const flipDurationMs = 200;

	function handleConsider(e: CustomEvent<{ items: Array<{ id: string; column: ColumnDef }>, info: { trigger: string } }>) {
		const { trigger } = e.detail.info;
		console.log('[DND Consider]', {
			trigger,
			itemCount: e.detail.items.length,
			items: e.detail.items.map(i => ({
				id: i.id,
				hasColumn: !!i.column,
				isShadow: !!(i as any)[SHADOW_ITEM_MARKER_PROPERTY_NAME]
			}))
		});
		if (trigger === TRIGGERS.DRAG_STARTED) {
			isDragging = true;
		}
		items = e.detail.items;
	}

	function handleFinalize(e: CustomEvent<{ items: Array<{ id: string; column: ColumnDef }>, info: { trigger: string } }>) {
		const { trigger } = e.detail.info;
		console.log('[DND Finalize]', {
			trigger,
			itemCount: e.detail.items.length,
			items: e.detail.items.map(i => ({
				id: i.id,
				hasColumn: !!i.column,
				isShadow: !!(i as any)[SHADOW_ITEM_MARKER_PROPERTY_NAME]
			}))
		});
		isDragging = false;
		items = e.detail.items;
		// Update column order
		const newOrder = items.map((item) => item.id);
		table.setColumnOrder(newOrder);
	}

	function handleToggle(columnId: string) {
		table.toggleColumnVisibility(columnId);
	}

	function handlePin(columnId: string, position: 'left' | 'right' | false) {
		table.pinColumn(columnId, position);
	}

	function getColumnPinState(columnId: string): 'left' | 'right' | false {
		return table.state.columnPinning[columnId] || false;
	}
</script>

<DropdownMenu.Root>
	<DropdownMenu.Trigger>
		{#snippet child({ props })}
			<Button {...props} variant="outline" size="sm" class="ml-auto">
				<Settings2 class="mr-2 h-4 w-4" />
				Columns ({visibleCount})
			</Button>
		{/snippet}
	</DropdownMenu.Trigger>
	<DropdownMenu.Content align="end" class="w-[250px]">
		<DropdownMenu.Label class="flex items-center justify-between">
			<span>Toggle columns ({table.columns.length - 1})</span>
			<span class="text-xs text-muted-foreground font-normal">Drag to reorder</span>
		</DropdownMenu.Label>
		<DropdownMenu.Separator />
		<section
			class="max-h-[350px] overflow-y-auto p-1 space-y-0.5"
			use:dndzone={{
				items,
				flipDurationMs,
				dropTargetStyle: {},
				dragDisabled: false,
				morphDisabled: false,
				type: 'column-toggle'
			}}
			onconsider={handleConsider}
			onfinalize={handleFinalize}
		>
			{#each items as item (item.id)}
				{@const isShadow = item[SHADOW_ITEM_MARKER_PROPERTY_NAME]}
				{@const pinState = getColumnPinState(item.id)}
				<div
					class="flex items-center gap-1 rounded-sm px-2 py-1.5 hover:bg-accent group bg-background transition-all duration-150 {isShadow ? 'opacity-40 border-2 border-dashed border-primary bg-primary/5' : ''} {pinState ? 'bg-primary/5' : ''}"
					animate:flip={{ duration: flipDurationMs }}
				>
					<div class="cursor-grab active:cursor-grabbing touch-none">
						<GripVertical class="h-4 w-4 text-muted-foreground opacity-50 group-hover:opacity-100 flex-shrink-0" />
					</div>
					<button
						type="button"
						class="flex items-center gap-2 flex-1 min-w-0"
						onclick={() => handleToggle(item.id)}
						disabled={isShadow}
					>
						<Checkbox
							checked={table.state.columnVisibility[item.id] !== false}
							tabindex={-1}
						/>
						<span class="text-sm font-normal select-none truncate">
							{item.column?.header ?? item.id}
						</span>
					</button>

					<!-- Pin controls -->
					<div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
						{#if pinState === 'left'}
							<button
								type="button"
								class="p-1 rounded hover:bg-destructive/10 text-primary"
								title="Unpin column"
								onclick={() => handlePin(item.id, false)}
							>
								<PinOff class="h-3 w-3" />
							</button>
						{:else}
							<button
								type="button"
								class="p-1 rounded hover:bg-accent-foreground/10 text-muted-foreground hover:text-foreground"
								title="Pin to left"
								onclick={() => handlePin(item.id, 'left')}
							>
								<ArrowLeftToLine class="h-3 w-3" />
							</button>
						{/if}

						{#if pinState === 'right'}
							<button
								type="button"
								class="p-1 rounded hover:bg-destructive/10 text-primary"
								title="Unpin column"
								onclick={() => handlePin(item.id, false)}
							>
								<PinOff class="h-3 w-3" />
							</button>
						{:else}
							<button
								type="button"
								class="p-1 rounded hover:bg-accent-foreground/10 text-muted-foreground hover:text-foreground"
								title="Pin to right"
								onclick={() => handlePin(item.id, 'right')}
							>
								<ArrowRightToLine class="h-3 w-3" />
							</button>
						{/if}
					</div>
				</div>
			{/each}
		</section>
		<DropdownMenu.Separator />
		<DropdownMenu.Item onclick={() => table.resetColumnVisibility()}>
			Reset to default
		</DropdownMenu.Item>
	</DropdownMenu.Content>
</DropdownMenu.Root>

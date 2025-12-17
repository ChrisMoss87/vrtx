<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import type { GridStack, GridStackNode } from 'gridstack';
	import 'gridstack/dist/gridstack.min.css';
	import 'gridstack/dist/gridstack-extra.min.css';
	import type { DashboardWidget, GridPosition } from '$lib/api/dashboards';

	interface Props {
		widgets: DashboardWidget[];
		editMode?: boolean;
		onLayoutChange?: (widgets: { id: number; x: number; y: number; w: number; h: number }[]) => void;
		onWidgetEdit?: (widget: DashboardWidget) => void;
		onWidgetDelete?: (widget: DashboardWidget) => void;
		children: import('svelte').Snippet<[DashboardWidget, any]>;
	}

	let {
		widgets,
		editMode = false,
		onLayoutChange,
		onWidgetEdit,
		onWidgetDelete,
		children
	}: Props = $props();

	let gridRef: HTMLDivElement | null = $state(null);
	let grid: GridStack | null = $state(null);
	let widgetData: Record<number, any> = $state({});

	// Import GridStack dynamically (client-side only)
	let GridStackModule: typeof import('gridstack') | null = $state(null);

	onMount(async () => {
		// Dynamically import gridstack on client side
		GridStackModule = await import('gridstack');

		if (gridRef && GridStackModule) {
			initGrid();
		}
	});

	onDestroy(() => {
		if (grid) {
			grid.destroy(false);
			grid = null;
		}
	});

	function initGrid() {
		if (!gridRef || !GridStackModule) return;

		grid = GridStackModule.GridStack.init(
			{
				column: 12,
				cellHeight: 80,
				margin: 8,
				float: true,
				disableDrag: !editMode,
				disableResize: !editMode,
				animate: true,
				removable: false
			},
			gridRef
		);

		// Listen for changes
		grid.on('change', (_event: Event, items: GridStackNode[]) => {
			if (onLayoutChange && items) {
				const positions = items.map((item) => ({
					id: Number(item.id),
					x: item.x ?? 0,
					y: item.y ?? 0,
					w: item.w ?? 1,
					h: item.h ?? 1
				}));
				onLayoutChange(positions);
			}
		});
	}

	// Update grid when editMode changes
	$effect(() => {
		if (grid) {
			grid.enableMove(editMode);
			grid.enableResize(editMode);
		}
	});

	// Update grid items when widgets change
	$effect(() => {
		if (grid && widgets) {
			// Remove items that no longer exist
			const existingIds = new Set(widgets.map((w) => String(w.id)));
			const gridItems = grid.getGridItems();
			gridItems.forEach((el) => {
				const id = el.getAttribute('gs-id');
				if (id && !existingIds.has(id)) {
					grid?.removeWidget(el, false);
				}
			});
		}
	});

	function getGridItemOptions(widget: DashboardWidget): GridStackNode {
		const pos = widget.grid_position;
		return {
			id: String(widget.id),
			x: pos.x,
			y: pos.y,
			w: pos.w,
			h: pos.h,
			minW: pos.minW,
			minH: pos.minH,
			maxW: pos.maxW,
			maxH: pos.maxH
		};
	}
</script>

<div class="dashboard-grid-container">
	<div bind:this={gridRef} class="grid-stack">
		{#each widgets as widget (widget.id)}
			{@const opts = getGridItemOptions(widget)}
			<!-- svelte-ignore attribute_quoted -->
			<div
				class="grid-stack-item"
				{...{
					'gs-id': opts.id,
					'gs-x': opts.x,
					'gs-y': opts.y,
					'gs-w': opts.w,
					'gs-h': opts.h,
					'gs-min-w': opts.minW,
					'gs-min-h': opts.minH,
					'gs-max-w': opts.maxW,
					'gs-max-h': opts.maxH
				}}
			>
				<div class="grid-stack-item-content">
					{@render children(widget, widgetData[widget.id])}

					{#if editMode}
						<div class="widget-edit-overlay">
							<button
								type="button"
								class="edit-btn"
								onclick={() => onWidgetEdit?.(widget)}
								title="Edit widget"
							>
								<svg
									xmlns="http://www.w3.org/2000/svg"
									width="16"
									height="16"
									viewBox="0 0 24 24"
									fill="none"
									stroke="currentColor"
									stroke-width="2"
									stroke-linecap="round"
									stroke-linejoin="round"
								>
									<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
									<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
								</svg>
							</button>
							<button
								type="button"
								class="delete-btn"
								onclick={() => onWidgetDelete?.(widget)}
								title="Delete widget"
							>
								<svg
									xmlns="http://www.w3.org/2000/svg"
									width="16"
									height="16"
									viewBox="0 0 24 24"
									fill="none"
									stroke="currentColor"
									stroke-width="2"
									stroke-linecap="round"
									stroke-linejoin="round"
								>
									<polyline points="3 6 5 6 21 6" />
									<path
										d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
									/>
								</svg>
							</button>
						</div>
					{/if}
				</div>
			</div>
		{/each}
	</div>
</div>

<style>
	.dashboard-grid-container {
		width: 100%;
		min-height: 400px;
	}

	:global(.grid-stack) {
		background: transparent;
	}

	:global(.grid-stack-item-content) {
		background: hsl(var(--card));
		border: 1px solid hsl(var(--border));
		border-radius: var(--radius);
		overflow: hidden;
		position: relative;
	}

	:global(.grid-stack-item.ui-draggable-dragging .grid-stack-item-content) {
		box-shadow: 0 8px 32px -4px rgba(0, 0, 0, 0.15);
	}

	:global(.grid-stack-placeholder > .placeholder-content) {
		background: hsl(var(--primary) / 0.1);
		border: 2px dashed hsl(var(--primary));
		border-radius: var(--radius);
	}

	.widget-edit-overlay {
		position: absolute;
		top: 8px;
		right: 8px;
		display: flex;
		gap: 4px;
		z-index: 10;
		opacity: 0;
		transition: opacity 0.2s;
	}

	:global(.grid-stack-item:hover) .widget-edit-overlay {
		opacity: 1;
	}

	.edit-btn,
	.delete-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 28px;
		height: 28px;
		border: none;
		border-radius: var(--radius);
		cursor: pointer;
		transition: all 0.2s;
	}

	.edit-btn {
		background: hsl(var(--secondary));
		color: hsl(var(--secondary-foreground));
	}

	.edit-btn:hover {
		background: hsl(var(--accent));
	}

	.delete-btn {
		background: hsl(var(--destructive));
		color: hsl(var(--destructive-foreground));
	}

	.delete-btn:hover {
		background: hsl(var(--destructive) / 0.8);
	}
</style>

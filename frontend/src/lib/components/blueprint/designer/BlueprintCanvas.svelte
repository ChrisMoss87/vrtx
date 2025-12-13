<script lang="ts">
	import { onMount, createEventDispatcher } from 'svelte';
	import type { BlueprintState, BlueprintTransition } from '$lib/api/blueprints';
	import StateNode from './StateNode.svelte';
	import TransitionArrow from './TransitionArrow.svelte';

	interface Props {
		states: BlueprintState[];
		transitions: BlueprintTransition[];
		selectedStateId?: number | null;
		selectedTransitionId?: number | null;
		readonly?: boolean;
	}

	let {
		states = [],
		transitions = [],
		selectedStateId = null,
		selectedTransitionId = null,
		readonly = false
	}: Props = $props();

	const dispatch = createEventDispatcher<{
		selectState: { state: BlueprintState };
		selectTransition: { transition: BlueprintTransition };
		moveState: { stateId: number; x: number; y: number };
		createTransition: { fromStateId: number; toStateId: number };
		clearSelection: void;
	}>();

	// Canvas state
	let canvasRef: SVGSVGElement;
	let viewBox = $state({ x: 0, y: 0, width: 1200, height: 800 });
	let isPanning = $state(false);
	let panStart = { x: 0, y: 0 };
	let scale = $state(1);

	// Drag state for moving nodes
	let isDraggingNode = $state(false);
	let draggedStateId = $state<number | null>(null);
	let dragStartPos = $state({ x: 0, y: 0 });
	let dragStartNodePos = $state({ x: 0, y: 0 });

	// Drag state for creating new transitions
	let isDraggingConnection = $state(false);
	let connectionStart = $state<{ stateId: number; x: number; y: number } | null>(null);
	let connectionEnd = $state<{ x: number; y: number } | null>(null);

	// Grid settings
	const gridSize = 20;
	const snapToGrid = (value: number) => Math.round(value / gridSize) * gridSize;

	// Convert screen coordinates to SVG coordinates
	function screenToSVG(clientX: number, clientY: number): { x: number; y: number } {
		const rect = canvasRef.getBoundingClientRect();
		return {
			x: (clientX - rect.left) / scale + viewBox.x,
			y: (clientY - rect.top) / scale + viewBox.y
		};
	}

	// Get state position with defaults
	function getStatePosition(state: BlueprintState) {
		return {
			x: state.position_x ?? 100 + (states.indexOf(state) % 4) * 200,
			y: state.position_y ?? 100 + Math.floor(states.indexOf(state) / 4) * 150
		};
	}

	// Node dimensions
	const nodeWidth = 160;
	const nodeHeight = 60;

	// Derived: compute all transition paths reactively based on current state positions
	const transitionPaths = $derived(
		transitions.map(transition => ({
			transition,
			path: computeTransitionPath(transition)
		}))
	);

	// Calculate arrow path between states
	function computeTransitionPath(transition: BlueprintTransition) {
		// Always look up states from the reactive states array to get current positions
		const fromState = states.find((s) => s.id === transition.from_state_id);
		const toState = states.find((s) => s.id === transition.to_state_id);

		if (!toState) return '';

		const toPos = getStatePosition(toState);

		// If no from state (initial transition), draw from the left edge
		if (!fromState) {
			const startX = toPos.x - 50;
			const startY = toPos.y + nodeHeight / 2;
			const endX = toPos.x;
			const endY = toPos.y + nodeHeight / 2;
			return `M ${startX} ${startY} L ${endX} ${endY}`;
		}

		const fromPos = getStatePosition(fromState);

		// Calculate edge points based on relative positions
		const fromCenterX = fromPos.x + nodeWidth / 2;
		const fromCenterY = fromPos.y + nodeHeight / 2;
		const toCenterX = toPos.x + nodeWidth / 2;
		const toCenterY = toPos.y + nodeHeight / 2;

		// Determine which edges to connect based on relative positions
		let startX: number, startY: number, endX: number, endY: number;

		const dx = toCenterX - fromCenterX;
		const dy = toCenterY - fromCenterY;

		// If target is mostly to the right, connect right edge to left edge
		if (Math.abs(dx) > Math.abs(dy)) {
			if (dx > 0) {
				// Target is to the right
				startX = fromPos.x + nodeWidth;
				startY = fromPos.y + nodeHeight / 2;
				endX = toPos.x;
				endY = toPos.y + nodeHeight / 2;
			} else {
				// Target is to the left
				startX = fromPos.x;
				startY = fromPos.y + nodeHeight / 2;
				endX = toPos.x + nodeWidth;
				endY = toPos.y + nodeHeight / 2;
			}
		} else {
			if (dy > 0) {
				// Target is below
				startX = fromPos.x + nodeWidth / 2;
				startY = fromPos.y + nodeHeight;
				endX = toPos.x + nodeWidth / 2;
				endY = toPos.y;
			} else {
				// Target is above
				startX = fromPos.x + nodeWidth / 2;
				startY = fromPos.y;
				endX = toPos.x + nodeWidth / 2;
				endY = toPos.y + nodeHeight;
			}
		}

		// Calculate control points for a smooth bezier curve
		const controlDist = Math.min(Math.abs(endX - startX), Math.abs(endY - startY), 100) * 0.5 + 30;

		let cx1: number, cy1: number, cx2: number, cy2: number;

		if (Math.abs(dx) > Math.abs(dy)) {
			// Horizontal-ish connection
			cx1 = startX + (dx > 0 ? controlDist : -controlDist);
			cy1 = startY;
			cx2 = endX + (dx > 0 ? -controlDist : controlDist);
			cy2 = endY;
		} else {
			// Vertical-ish connection
			cx1 = startX;
			cy1 = startY + (dy > 0 ? controlDist : -controlDist);
			cx2 = endX;
			cy2 = endY + (dy > 0 ? -controlDist : controlDist);
		}

		return `M ${startX} ${startY} C ${cx1} ${cy1}, ${cx2} ${cy2}, ${endX} ${endY}`;
	}

	// Mouse handlers for panning
	function handleMouseDown(e: MouseEvent) {
		if (e.button === 1 || (e.button === 0 && e.shiftKey)) {
			isPanning = true;
			panStart = { x: e.clientX, y: e.clientY };
			e.preventDefault();
		} else if (e.target === canvasRef || (e.target as Element)?.classList.contains('canvas-bg')) {
			dispatch('clearSelection');
		}
	}

	function handleMouseMove(e: MouseEvent) {
		if (isPanning) {
			const dx = (e.clientX - panStart.x) / scale;
			const dy = (e.clientY - panStart.y) / scale;
			viewBox = {
				...viewBox,
				x: viewBox.x - dx,
				y: viewBox.y - dy
			};
			panStart = { x: e.clientX, y: e.clientY };
		} else if (isDraggingNode && draggedStateId !== null) {
			// Calculate new position based on mouse movement
			const currentPos = screenToSVG(e.clientX, e.clientY);
			const dx = currentPos.x - dragStartPos.x;
			const dy = currentPos.y - dragStartPos.y;
			const newX = snapToGrid(dragStartNodePos.x + dx);
			const newY = snapToGrid(dragStartNodePos.y + dy);
			dispatch('moveState', { stateId: draggedStateId, x: newX, y: newY });
		} else if (isDraggingConnection && connectionStart) {
			connectionEnd = screenToSVG(e.clientX, e.clientY);
		}
	}

	function handleMouseUp(e: MouseEvent) {
		// Handle node drag end
		if (isDraggingNode) {
			isDraggingNode = false;
			draggedStateId = null;
		}

		isPanning = false;

		if (isDraggingConnection && connectionStart) {
			// Check if we're over a state node
			const mousePos = screenToSVG(e.clientX, e.clientY);

			for (const state of states) {
				if (state.id === connectionStart.stateId) continue;
				const pos = getStatePosition(state);
				if (mousePos.x >= pos.x && mousePos.x <= pos.x + 160 && mousePos.y >= pos.y && mousePos.y <= pos.y + 60) {
					dispatch('createTransition', {
						fromStateId: connectionStart.stateId,
						toStateId: state.id
					});
					break;
				}
			}
		}

		isDraggingConnection = false;
		connectionStart = null;
		connectionEnd = null;
	}

	// Wheel handler for zooming
	function handleWheel(e: WheelEvent) {
		e.preventDefault();
		const delta = e.deltaY > 0 ? 0.9 : 1.1;
		const newScale = Math.min(Math.max(scale * delta, 0.25), 2);

		// Zoom towards mouse position
		const rect = canvasRef.getBoundingClientRect();
		const mouseX = e.clientX - rect.left;
		const mouseY = e.clientY - rect.top;

		const wx = viewBox.x + mouseX / scale;
		const wy = viewBox.y + mouseY / scale;

		scale = newScale;

		viewBox = {
			...viewBox,
			x: wx - mouseX / scale,
			y: wy - mouseY / scale
		};
	}

	// Handle state selection
	function handleStateClick(state: BlueprintState) {
		dispatch('selectState', { state });
	}

	// Handle state drag start - called from StateNode
	function handleStateDragStart(state: BlueprintState, e: MouseEvent) {
		if (readonly) return;
		isDraggingNode = true;
		draggedStateId = state.id;
		dragStartPos = screenToSVG(e.clientX, e.clientY);
		const pos = getStatePosition(state);
		dragStartNodePos = { x: pos.x, y: pos.y };
	}

	// Handle connection drag start
	function handleConnectionDragStart(stateId: number, x: number, y: number) {
		if (readonly) return;
		isDraggingConnection = true;
		connectionStart = { stateId, x, y };
	}

	// Handle transition click
	function handleTransitionClick(transition: BlueprintTransition) {
		dispatch('selectTransition', { transition });
	}
</script>

<div class="blueprint-canvas-container relative h-full w-full overflow-hidden rounded-lg border bg-muted/30">
	<!-- Toolbar -->
	<div class="absolute left-2 top-2 z-10 flex gap-2">
		<button
			class="rounded bg-background px-2 py-1 text-xs shadow hover:bg-muted"
			onclick={() => (scale = Math.min(scale * 1.2, 2))}
		>
			+
		</button>
		<button
			class="rounded bg-background px-2 py-1 text-xs shadow hover:bg-muted"
			onclick={() => (scale = Math.max(scale * 0.8, 0.25))}
		>
			-
		</button>
		<button
			class="rounded bg-background px-2 py-1 text-xs shadow hover:bg-muted"
			onclick={() => {
				scale = 1;
				viewBox = { x: 0, y: 0, width: 1200, height: 800 };
			}}
		>
			Reset
		</button>
		<span class="rounded bg-background px-2 py-1 text-xs shadow">{Math.round(scale * 100)}%</span>
	</div>

	<!-- SVG Canvas -->
	<svg
		bind:this={canvasRef}
		class="h-full w-full"
		viewBox="{viewBox.x} {viewBox.y} {viewBox.width / scale} {viewBox.height / scale}"
		onmousedown={handleMouseDown}
		onmousemove={handleMouseMove}
		onmouseup={handleMouseUp}
		onmouseleave={handleMouseUp}
		onwheel={handleWheel}
	>
		<!-- Grid Pattern -->
		<defs>
			<pattern id="grid" width={gridSize} height={gridSize} patternUnits="userSpaceOnUse">
				<path
					d="M {gridSize} 0 L 0 0 0 {gridSize}"
					fill="none"
					stroke="currentColor"
					stroke-width="0.5"
					class="text-muted-foreground/20"
				/>
			</pattern>
			<pattern id="gridLarge" width={gridSize * 5} height={gridSize * 5} patternUnits="userSpaceOnUse">
				<rect width={gridSize * 5} height={gridSize * 5} fill="url(#grid)" />
				<path
					d="M {gridSize * 5} 0 L 0 0 0 {gridSize * 5}"
					fill="none"
					stroke="currentColor"
					stroke-width="1"
					class="text-muted-foreground/30"
				/>
			</pattern>
			<!-- Arrow marker -->
			<marker
				id="arrowhead"
				markerWidth="10"
				markerHeight="7"
				refX="9"
				refY="3.5"
				orient="auto"
			>
				<polygon points="0 0, 10 3.5, 0 7" class="fill-muted-foreground" />
			</marker>
			<marker
				id="arrowhead-selected"
				markerWidth="10"
				markerHeight="7"
				refX="9"
				refY="3.5"
				orient="auto"
			>
				<polygon points="0 0, 10 3.5, 0 7" class="fill-primary" />
			</marker>
		</defs>

		<!-- Background with grid -->
		<rect
			class="canvas-bg"
			x={viewBox.x - 1000}
			y={viewBox.y - 1000}
			width={viewBox.width / scale + 2000}
			height={viewBox.height / scale + 2000}
			fill="url(#gridLarge)"
		/>

		<!-- Transitions (arrows) -->
		<g class="transitions">
			{#each transitionPaths as { transition, path } (transition.id)}
				<TransitionArrow
					{path}
					{transition}
					selected={selectedTransitionId === transition.id}
					onclick={() => handleTransitionClick(transition)}
				/>
			{/each}

			<!-- Connection being drawn -->
			{#if isDraggingConnection && connectionStart && connectionEnd}
				<path
					d="M {connectionStart.x + nodeWidth} {connectionStart.y + nodeHeight / 2} L {connectionEnd.x} {connectionEnd.y}"
					fill="none"
					stroke="currentColor"
					stroke-width="2"
					stroke-dasharray="5,5"
					class="text-primary"
					marker-end="url(#arrowhead)"
				/>
			{/if}
		</g>

		<!-- State Nodes -->
		<g class="states">
			{#each states as state (state.id)}
				{@const pos = getStatePosition(state)}
				<StateNode
					nodeState={state}
					x={pos.x}
					y={pos.y}
					selected={selectedStateId === state.id}
					{readonly}
					onclick={() => handleStateClick(state)}
					ondragstart={(e) => handleStateDragStart(state, e)}
					onconnectionstart={(x, y) => handleConnectionDragStart(state.id, x, y)}
				/>
			{/each}
		</g>
	</svg>

	<!-- Help text -->
	<div class="absolute bottom-2 right-2 text-xs text-muted-foreground">
		Shift+Drag or Middle-click to pan | Scroll to zoom | Drag connector to create transitions
	</div>
</div>

<style>
	.blueprint-canvas-container {
		cursor: default;
	}

	.blueprint-canvas-container :global(svg) {
		cursor: default;
	}

	.blueprint-canvas-container:active {
		cursor: grabbing;
	}
</style>

<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import { Input } from '$lib/components/ui/input';
	import { Search, ZoomIn, ZoomOut, Maximize2, Filter, Network } from 'lucide-svelte';
	import {
		loadGraphData,
		getNeighborhood,
		findPath,
		type GraphNode,
		type GraphEdge,
		type GraphData
	} from '$lib/api/graph';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import NodeDetailsPanel from './NodeDetailsPanel.svelte';

	interface Props {
		initialEntityType?: string | null;
		initialEntityId?: number | null;
	}

	let {
		initialEntityType = null,
		initialEntityId = null,
	}: Props = $props();

	let container = $state<HTMLDivElement>(null!);
	let canvas = $state<HTMLCanvasElement>(null!);
	let ctx = $state<CanvasRenderingContext2D | null>(null);

	let nodes = $state<GraphNode[]>([]);
	let edges = $state<GraphEdge[]>([]);
	let loading = $state(true);

	// Viewport state
	let scale = $state(1);
	let offsetX = $state(0);
	let offsetY = $state(0);
	let isDragging = $state(false);
	let dragStartX = $state(0);
	let dragStartY = $state(0);
	let lastOffsetX = $state(0);
	let lastOffsetY = $state(0);

	// Node positions (computed by force simulation)
	let nodePositions = $state<Map<string, { x: number; y: number }>>(new Map());

	// Selection state
	let selectedNode = $state<GraphNode | null>(null);
	let hoveredNode = $state<GraphNode | null>(null);

	// Filters
	let showContacts = $state(true);
	let showCompanies = $state(true);
	let showDeals = $state(true);
	let showUsers = $state(false);
	let searchQuery = $state('');

	// Path finding
	let pathFindingMode = $state(false);
	let pathStart = $state<GraphNode | null>(null);
	let pathEnd = $state<GraphNode | null>(null);
	let pathResult = $state<string[] | null>(null);

	onMount(async () => {
		ctx = canvas.getContext('2d');
		resizeCanvas();

		if (initialEntityType && initialEntityId) {
			await loadNeighborhood(initialEntityType, initialEntityId);
		} else {
			await loadFullGraph();
		}

		window.addEventListener('resize', resizeCanvas);
		requestAnimationFrame(render);
	});

	onDestroy(() => {
		window.removeEventListener('resize', resizeCanvas);
	});

	function resizeCanvas() {
		if (!container || !canvas) return;
		const rect = container.getBoundingClientRect();
		canvas.width = rect.width;
		canvas.height = rect.height;
	}

	async function loadFullGraph() {
		loading = true;
		const entityTypes: string[] = [];
		if (showContacts) entityTypes.push('contact');
		if (showCompanies) entityTypes.push('company');
		if (showDeals) entityTypes.push('deal');
		if (showUsers) entityTypes.push('user');

		const { data, error } = await tryCatch(loadGraphData({ entityTypes, limit: 200 }));
		loading = false;

		if (error) {
			toast.error('Failed to load graph data');
			return;
		}

		setGraphData(data);
	}

	async function loadNeighborhood(entityType: string, entityId: number) {
		loading = true;
		const { data, error } = await tryCatch(getNeighborhood(entityType, entityId, 2));
		loading = false;

		if (error) {
			toast.error('Failed to load neighborhood');
			return;
		}

		setGraphData(data);
	}

	function setGraphData(data: GraphData) {
		nodes = data.nodes;
		edges = data.edges;
		initializePositions();
		runForceSimulation();
	}

	function initializePositions() {
		nodePositions.clear();
		const centerX = canvas.width / 2;
		const centerY = canvas.height / 2;
		const radius = Math.min(centerX, centerY) * 0.7;

		nodes.forEach((node, i) => {
			const angle = (i / nodes.length) * 2 * Math.PI;
			nodePositions.set(node.id, {
				x: centerX + radius * Math.cos(angle),
				y: centerY + radius * Math.sin(angle)
			});
		});
	}

	function runForceSimulation() {
		// Simple force-directed layout simulation
		const iterations = 100;
		const k = Math.sqrt((canvas.width * canvas.height) / nodes.length); // Optimal distance

		for (let iter = 0; iter < iterations; iter++) {
			const temperature = 1 - iter / iterations;

			// Repulsive forces between all nodes
			for (let i = 0; i < nodes.length; i++) {
				for (let j = i + 1; j < nodes.length; j++) {
					const pos1 = nodePositions.get(nodes[i].id)!;
					const pos2 = nodePositions.get(nodes[j].id)!;
					const dx = pos2.x - pos1.x;
					const dy = pos2.y - pos1.y;
					const dist = Math.sqrt(dx * dx + dy * dy) || 1;
					const force = (k * k) / dist;
					const fx = (dx / dist) * force * temperature * 0.1;
					const fy = (dy / dist) * force * temperature * 0.1;
					pos1.x -= fx;
					pos1.y -= fy;
					pos2.x += fx;
					pos2.y += fy;
				}
			}

			// Attractive forces for connected nodes
			for (const edge of edges) {
				const pos1 = nodePositions.get(edge.source);
				const pos2 = nodePositions.get(edge.target);
				if (!pos1 || !pos2) continue;

				const dx = pos2.x - pos1.x;
				const dy = pos2.y - pos1.y;
				const dist = Math.sqrt(dx * dx + dy * dy) || 1;
				const force = (dist * dist) / k;
				const fx = (dx / dist) * force * temperature * 0.05;
				const fy = (dy / dist) * force * temperature * 0.05;
				pos1.x += fx;
				pos1.y += fy;
				pos2.x -= fx;
				pos2.y -= fy;
			}

			// Keep nodes in bounds
			const margin = 50;
			for (const node of nodes) {
				const pos = nodePositions.get(node.id)!;
				pos.x = Math.max(margin, Math.min(canvas.width - margin, pos.x));
				pos.y = Math.max(margin, Math.min(canvas.height - margin, pos.y));
			}
		}
	}

	function render() {
		if (!ctx) return;

		ctx.clearRect(0, 0, canvas.width, canvas.height);
		ctx.save();
		ctx.translate(offsetX, offsetY);
		ctx.scale(scale, scale);

		// Draw edges
		for (const edge of edges) {
			const from = nodePositions.get(edge.source);
			const to = nodePositions.get(edge.target);
			if (!from || !to) continue;

			const isInPath = pathResult?.includes(edge.source) && pathResult?.includes(edge.target);

			ctx.beginPath();
			ctx.moveTo(from.x, from.y);
			ctx.lineTo(to.x, to.y);
			ctx.strokeStyle = isInPath ? '#3b82f6' : '#94a3b8';
			ctx.lineWidth = isInPath ? 3 / scale : (edge.strength / 10 + 0.5) / scale;
			ctx.stroke();
		}

		// Draw nodes
		for (const node of nodes) {
			const pos = nodePositions.get(node.id);
			if (!pos) continue;

			if (searchQuery && !node.label.toLowerCase().includes(searchQuery.toLowerCase())) {
				continue;
			}

			const isSelected = selectedNode?.id === node.id;
			const isHovered = hoveredNode?.id === node.id;
			const isInPath = pathResult?.includes(node.id);

			// Node circle
			ctx.beginPath();
			ctx.arc(pos.x, pos.y, node.size / scale, 0, 2 * Math.PI);
			ctx.fillStyle = getNodeColor(node.entity_type, isSelected, isInPath ?? false);
			ctx.fill();

			if (isSelected || isHovered) {
				ctx.strokeStyle = '#3b82f6';
				ctx.lineWidth = 3 / scale;
				ctx.stroke();
			}

			// Node label
			ctx.fillStyle = '#1e293b';
			ctx.font = `${12 / scale}px system-ui`;
			ctx.textAlign = 'center';
			ctx.fillText(truncateLabel(node.label), pos.x, pos.y + node.size / scale + 14 / scale);
		}

		ctx.restore();
		requestAnimationFrame(render);
	}

	function getNodeColor(type: string, selected: boolean, inPath: boolean): string {
		if (inPath) return '#3b82f6';
		if (selected) return '#1d4ed8';

		switch (type) {
			case 'contact':
				return '#10b981';
			case 'company':
				return '#8b5cf6';
			case 'deal':
				return '#f59e0b';
			case 'user':
				return '#6366f1';
			default:
				return '#64748b';
		}
	}

	function truncateLabel(label: string, maxLength = 15): string {
		if (label.length <= maxLength) return label;
		return label.slice(0, maxLength - 1) + '...';
	}

	function handleMouseDown(e: MouseEvent) {
		if (e.button === 0) {
			isDragging = true;
			dragStartX = e.clientX;
			dragStartY = e.clientY;
			lastOffsetX = offsetX;
			lastOffsetY = offsetY;
		}
	}

	function handleMouseMove(e: MouseEvent) {
		const rect = canvas.getBoundingClientRect();
		const mouseX = (e.clientX - rect.left - offsetX) / scale;
		const mouseY = (e.clientY - rect.top - offsetY) / scale;

		// Check for hovered node
		hoveredNode = null;
		for (const node of nodes) {
			const pos = nodePositions.get(node.id);
			if (!pos) continue;

			const dx = mouseX - pos.x;
			const dy = mouseY - pos.y;
			const dist = Math.sqrt(dx * dx + dy * dy);

			if (dist < node.size / scale) {
				hoveredNode = node;
				canvas.style.cursor = 'pointer';
				break;
			}
		}

		if (!hoveredNode) {
			canvas.style.cursor = isDragging ? 'grabbing' : 'grab';
		}

		if (isDragging) {
			offsetX = lastOffsetX + (e.clientX - dragStartX);
			offsetY = lastOffsetY + (e.clientY - dragStartY);
		}
	}

	function handleMouseUp() {
		isDragging = false;
	}

	function handleClick(e: MouseEvent) {
		if (!hoveredNode) {
			selectedNode = null;
			pathResult = null;
			return;
		}

		if (pathFindingMode) {
			if (!pathStart) {
				pathStart = hoveredNode;
				toast.info(`Start: ${hoveredNode.label}. Now click the target node.`);
			} else {
				pathEnd = hoveredNode;
				findPathBetweenNodes();
			}
			return;
		}

		selectedNode = hoveredNode;
	}

	function handleWheel(e: WheelEvent) {
		e.preventDefault();
		const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
		scale = Math.max(0.1, Math.min(5, scale * zoomFactor));
	}

	function zoomIn() {
		scale = Math.min(5, scale * 1.2);
	}

	function zoomOut() {
		scale = Math.max(0.1, scale / 1.2);
	}

	function resetView() {
		scale = 1;
		offsetX = 0;
		offsetY = 0;
	}

	function togglePathFinding() {
		pathFindingMode = !pathFindingMode;
		if (pathFindingMode) {
			pathStart = null;
			pathEnd = null;
			pathResult = null;
			toast.info('Path finding mode: Click a start node');
		}
	}

	async function findPathBetweenNodes() {
		if (!pathStart || !pathEnd) return;

		loading = true;
		const { data, error } = await tryCatch(
			findPath(pathStart.entity_type, pathStart.entity_id, pathEnd.entity_type, pathEnd.entity_id)
		);
		loading = false;
		pathFindingMode = false;

		if (error || !data) {
			toast.error('No path found between these nodes');
			pathStart = null;
			pathEnd = null;
			return;
		}

		pathResult = data.path;
		toast.success(`Found path with ${data.hops} hop(s)`);
		pathStart = null;
		pathEnd = null;
	}

	function applyFilters() {
		loadFullGraph();
	}
</script>

<div class="flex h-full">
	<!-- Main graph area -->
	<div class="flex-1 flex flex-col">
		<!-- Toolbar -->
		<div class="flex items-center gap-2 p-2 border-b bg-background">
			<div class="relative flex-1 max-w-xs">
				<Search class="absolute left-2 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
				<Input
					bind:value={searchQuery}
					placeholder="Search nodes..."
					class="pl-8 h-8"
				/>
			</div>

			<div class="flex items-center gap-1 border-l pl-2">
				<Button variant="ghost" size="sm" onclick={zoomIn} title="Zoom in">
					<ZoomIn class="h-4 w-4" />
				</Button>
				<Button variant="ghost" size="sm" onclick={zoomOut} title="Zoom out">
					<ZoomOut class="h-4 w-4" />
				</Button>
				<Button variant="ghost" size="sm" onclick={resetView} title="Reset view">
					<Maximize2 class="h-4 w-4" />
				</Button>
			</div>

			<div class="flex items-center gap-1 border-l pl-2">
				<Button
					variant={pathFindingMode ? 'default' : 'ghost'}
					size="sm"
					onclick={togglePathFinding}
					title="Find path between nodes"
				>
					<Network class="h-4 w-4 mr-1" />
					Find Path
				</Button>
			</div>

			<div class="flex items-center gap-2 border-l pl-2 ml-auto">
				<label class="flex items-center gap-1 text-sm">
					<input type="checkbox" bind:checked={showContacts} onchange={applyFilters} class="rounded" />
					<span class="w-2 h-2 rounded-full bg-emerald-500"></span>
					Contacts
				</label>
				<label class="flex items-center gap-1 text-sm">
					<input type="checkbox" bind:checked={showCompanies} onchange={applyFilters} class="rounded" />
					<span class="w-2 h-2 rounded-full bg-violet-500"></span>
					Companies
				</label>
				<label class="flex items-center gap-1 text-sm">
					<input type="checkbox" bind:checked={showDeals} onchange={applyFilters} class="rounded" />
					<span class="w-2 h-2 rounded-full bg-amber-500"></span>
					Deals
				</label>
			</div>
		</div>

		<!-- Canvas -->
		<div bind:this={container} class="flex-1 relative bg-slate-50 dark:bg-slate-900">
			<canvas
				bind:this={canvas}
				class="absolute inset-0"
				onmousedown={handleMouseDown}
				onmousemove={handleMouseMove}
				onmouseup={handleMouseUp}
				onmouseleave={handleMouseUp}
				onclick={handleClick}
				onwheel={handleWheel}
			></canvas>

			{#if loading}
				<div class="absolute inset-0 flex items-center justify-center bg-background/50">
					<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
				</div>
			{/if}

			{#if nodes.length === 0 && !loading}
				<div class="absolute inset-0 flex items-center justify-center text-muted-foreground">
					<div class="text-center">
						<Network class="h-12 w-12 mx-auto mb-4 opacity-50" />
						<p>No data to display</p>
						<p class="text-sm mt-2">Add relationships to see the network graph</p>
					</div>
				</div>
			{/if}

			<!-- Legend -->
			<div class="absolute bottom-4 left-4 bg-background/90 backdrop-blur rounded-lg p-3 text-xs shadow-lg">
				<div class="font-medium mb-2">Legend</div>
				<div class="space-y-1">
					<div class="flex items-center gap-2">
						<span class="w-3 h-3 rounded-full bg-emerald-500"></span>
						Contact
					</div>
					<div class="flex items-center gap-2">
						<span class="w-3 h-3 rounded-full bg-violet-500"></span>
						Company
					</div>
					<div class="flex items-center gap-2">
						<span class="w-3 h-3 rounded-full bg-amber-500"></span>
						Deal
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Details panel -->
	{#if selectedNode}
		<div class="w-80 border-l bg-background">
			<NodeDetailsPanel node={selectedNode} onClose={() => (selectedNode = null)} />
		</div>
	{/if}
</div>

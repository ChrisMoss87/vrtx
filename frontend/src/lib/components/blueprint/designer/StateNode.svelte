<script lang="ts">
	import type { BlueprintState } from '$lib/api/blueprints';

	interface Props {
		nodeState: BlueprintState;
		x: number;
		y: number;
		selected?: boolean;
		readonly?: boolean;
		onclick?: () => void;
		ondragstart?: (e: MouseEvent) => void;
		onconnectionstart?: (x: number, y: number) => void;
	}

	let {
		nodeState,
		x,
		y,
		selected = false,
		readonly = false,
		onclick,
		ondragstart,
		onconnectionstart
	}: Props = $props();

	let isHovered = $state(false);

	const nodeWidth = 160;
	const nodeHeight = 60;

	function handleMouseDown(e: MouseEvent) {
		if (readonly) return;
		if (e.button !== 0) return;

		// Check if clicking on the connector handle
		const target = e.target as SVGElement;
		if (target.classList.contains('connector-handle')) {
			e.stopPropagation();
			onconnectionstart?.(x, y);
			return;
		}

		// Start drag - let the canvas handle the actual dragging
		e.stopPropagation();
		ondragstart?.(e);
	}

	function handleClick(e: MouseEvent) {
		e.stopPropagation();
		onclick?.();
	}

	function handleKeyDown(e: KeyboardEvent) {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			onclick?.();
		}
	}

	// Get color class based on state color
	function getColorClass(color: string | null): string {
		if (!color) return 'fill-muted stroke-muted-foreground';

		const colorMap: Record<string, string> = {
			'#22c55e': 'fill-green-100 stroke-green-500 dark:fill-green-900/30',
			'#3b82f6': 'fill-blue-100 stroke-blue-500 dark:fill-blue-900/30',
			'#f59e0b': 'fill-amber-100 stroke-amber-500 dark:fill-amber-900/30',
			'#ef4444': 'fill-red-100 stroke-red-500 dark:fill-red-900/30',
			'#8b5cf6': 'fill-violet-100 stroke-violet-500 dark:fill-violet-900/30',
			'#06b6d4': 'fill-cyan-100 stroke-cyan-500 dark:fill-cyan-900/30',
			'#ec4899': 'fill-pink-100 stroke-pink-500 dark:fill-pink-900/30',
			'#84cc16': 'fill-lime-100 stroke-lime-500 dark:fill-lime-900/30',
			gray: 'fill-gray-100 stroke-gray-500 dark:fill-gray-900/30',
			green: 'fill-green-100 stroke-green-500 dark:fill-green-900/30',
			blue: 'fill-blue-100 stroke-blue-500 dark:fill-blue-900/30',
			yellow: 'fill-amber-100 stroke-amber-500 dark:fill-amber-900/30',
			red: 'fill-red-100 stroke-red-500 dark:fill-red-900/30',
			purple: 'fill-violet-100 stroke-violet-500 dark:fill-violet-900/30'
		};

		return colorMap[color.toLowerCase()] || 'fill-muted stroke-muted-foreground';
	}
</script>

<g
	class="state-node cursor-pointer select-none"
	class:is-hovered={isHovered}
	transform="translate({x}, {y})"
	onmousedown={handleMouseDown}
	onclick={handleClick}
	onkeydown={handleKeyDown}
	onmouseenter={() => (isHovered = true)}
	onmouseleave={() => (isHovered = false)}
	role="button"
	tabindex="0"
>
	<!-- Main node rectangle -->
	<rect
		width={nodeWidth}
		height={nodeHeight}
		rx="8"
		ry="8"
		class="{getColorClass(nodeState.color)} {selected
			? 'stroke-primary stroke-[3]'
			: 'stroke-[2]'} transition-all hover:brightness-95"
		style={nodeState.color && !['gray', 'green', 'blue', 'yellow', 'red', 'purple'].includes(nodeState.color.toLowerCase())
			? `fill: ${nodeState.color}20; stroke: ${nodeState.color}`
			: ''}
	/>

	<!-- State type indicator -->
	<g transform="translate(12, {nodeHeight / 2})" class="text-muted-foreground">
		{#if nodeState.is_initial}
			<circle cx="0" cy="0" r="6" class="fill-primary stroke-none" />
			<circle cx="0" cy="0" r="3" class="fill-background stroke-none" />
		{:else if nodeState.is_terminal}
			<circle cx="0" cy="0" r="6" class="fill-green-500 stroke-none" />
			<path d="M -3 0 L -1 2 L 3 -2" class="fill-none stroke-background" stroke-width="2" />
		{:else}
			<circle cx="0" cy="0" r="5" class="fill-none stroke-current" stroke-width="2" />
		{/if}
	</g>

	<!-- State name -->
	<text
		x={nodeWidth / 2 + 8}
		y={nodeHeight / 2}
		text-anchor="middle"
		dominant-baseline="central"
		class="fill-foreground text-sm font-medium"
	>
		{nodeState.name.length > 18 ? nodeState.name.slice(0, 18) + '...' : nodeState.name}
	</text>

	<!-- Connection handle (right side) -->
	{#if !readonly}
		<circle
			cx={nodeWidth}
			cy={nodeHeight / 2}
			r="8"
			class="connector-handle cursor-crosshair fill-background stroke-muted-foreground stroke-2 transition-opacity hover:fill-primary hover:stroke-primary {isHovered ? 'opacity-100' : 'opacity-0'}"
		/>
		<circle
			cx={nodeWidth}
			cy={nodeHeight / 2}
			r="4"
			class="connector-handle pointer-events-none fill-muted-foreground {isHovered ? 'opacity-100' : 'opacity-0'}"
		/>
	{/if}

	<!-- Badges for initial/terminal states -->
	{#if nodeState.is_initial}
		<g transform="translate({nodeWidth - 50}, -8)">
			<rect width="50" height="16" rx="8" class="fill-primary" />
			<text x="25" y="8" text-anchor="middle" dominant-baseline="central" class="fill-primary-foreground text-[10px] font-medium">
				Start
			</text>
		</g>
	{/if}
	{#if nodeState.is_terminal}
		<g transform="translate({nodeWidth - 50}, {nodeHeight - 8})">
			<rect width="50" height="16" rx="8" class="fill-green-500" />
			<text x="25" y="8" text-anchor="middle" dominant-baseline="central" class="fill-white text-[10px] font-medium">
				End
			</text>
		</g>
	{/if}
</g>

<style>
	.state-node:hover .connector-handle {
		opacity: 1;
	}
</style>

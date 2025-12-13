<script lang="ts">
	import type { BlueprintTransition, BlueprintTransitionCondition } from '$lib/api/blueprints';

	interface Props {
		path: string;
		transition: BlueprintTransition;
		selected?: boolean;
		onclick?: () => void;
	}

	let { path, transition, selected = false, onclick }: Props = $props();

	// Get label position along the path
	function getLabelPosition(): { x: number; y: number } {
		// Parse the path to get a midpoint
		const parts = path.split(/[MLCQ]/);
		const coords = parts
			.filter((p) => p.trim())
			.map((p) => {
				const nums = p.trim().split(/[\s,]+/).map(Number);
				return nums;
			})
			.flat();

		if (coords.length >= 4) {
			// For bezier curves, approximate midpoint
			const startX = coords[0];
			const startY = coords[1];
			const endX = coords[coords.length - 2];
			const endY = coords[coords.length - 1];

			return {
				x: (startX + endX) / 2,
				y: (startY + endY) / 2 - 12
			};
		}

		return { x: 0, y: 0 };
	}

	const labelPos = $derived(getLabelPosition());

	// Generate a short summary of conditions for display
	function getConditionSummary(conditions: BlueprintTransitionCondition[] | undefined): string {
		if (!conditions || conditions.length === 0) return '';

		const operatorLabels: Record<string, string> = {
			eq: '=',
			ne: '≠',
			gt: '>',
			gte: '≥',
			lt: '<',
			lte: '≤',
			contains: '∋',
			not_contains: '∌',
			is_empty: 'empty',
			is_not_empty: 'not empty'
		};

		if (conditions.length === 1) {
			const c = conditions[0];
			const op = operatorLabels[c.operator] || c.operator;
			if (c.operator === 'is_empty' || c.operator === 'is_not_empty') {
				return `${c.field?.api_name || 'field'} ${op}`;
			}
			return `${c.field?.api_name || 'field'} ${op} ${c.value}`;
		}

		// Multiple conditions - show count
		return `${conditions.length} conditions`;
	}

	const conditionSummary = $derived(getConditionSummary(transition.conditions));
	const hasConditions = $derived(transition.conditions && transition.conditions.length > 0);

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
</script>

<g class="transition-arrow cursor-pointer" onclick={handleClick} onkeydown={handleKeyDown} role="button" tabindex="0">
	<!-- Invisible wider path for easier clicking -->
	<path d={path} fill="none" stroke="transparent" stroke-width="20" class="pointer-events-auto" />

	<!-- Visible path -->
	<path
		d={path}
		fill="none"
		stroke="currentColor"
		stroke-width={selected ? 3 : 2}
		class="{selected ? 'text-primary' : 'text-muted-foreground'} transition-colors"
		marker-end={selected ? 'url(#arrowhead-selected)' : 'url(#arrowhead)'}
	/>

	<!-- Transition label -->
	{#if path && transition.name}
		{@const displayName = transition.name.length > 20 ? transition.name.slice(0, 20) + '...' : transition.name}
		{@const labelWidth = Math.max(displayName.length * 7, conditionSummary.length * 5.5) + 24}
		{@const labelHeight = hasConditions ? 34 : 20}

		<g transform="translate({labelPos.x}, {labelPos.y})">
			<!-- Background -->
			<rect
				x={-labelWidth / 2}
				y={-labelHeight / 2}
				width={labelWidth}
				height={labelHeight}
				rx="4"
				class="{selected ? 'fill-primary' : 'fill-background'} stroke-muted-foreground/30"
				stroke-width="1"
			/>

			<!-- Main transition name -->
			<text
				x="0"
				y={hasConditions ? -6 : 0}
				text-anchor="middle"
				dominant-baseline="central"
				class="{selected ? 'fill-primary-foreground' : 'fill-foreground'} text-xs font-medium"
			>
				{displayName}
			</text>

			<!-- Condition summary below the name -->
			{#if hasConditions && conditionSummary}
				<text
					x="0"
					y="8"
					text-anchor="middle"
					dominant-baseline="central"
					class="{selected ? 'fill-primary-foreground/70' : 'fill-amber-600 dark:fill-amber-400'} text-[9px]"
				>
					{conditionSummary.length > 25 ? conditionSummary.slice(0, 25) + '...' : conditionSummary}
				</text>
			{/if}

			<!-- Status indicators (dots) -->
			{#if transition.requirements?.length || transition.actions?.length || transition.approval}
				{@const hasReqs = !!transition.requirements?.length}
				{@const hasActions = !!transition.actions?.length}
				{@const hasApproval = !!transition.approval}
				<g transform="translate({labelWidth / 2 - 8}, {hasConditions ? -6 : 0})">
					{#if hasReqs}
						<circle cx="0" cy="0" r="3" class="fill-blue-500">
							<title>{transition.requirements?.length} requirement(s)</title>
						</circle>
					{/if}
					{#if hasActions}
						<circle cx={hasReqs ? -8 : 0} cy="0" r="3" class="fill-green-500">
							<title>{transition.actions?.length} action(s)</title>
						</circle>
					{/if}
					{#if hasApproval}
						<circle cx={(hasReqs ? -8 : 0) + (hasActions ? -8 : 0)} cy="0" r="3" class="fill-purple-500">
							<title>Requires approval</title>
						</circle>
					{/if}
				</g>
			{/if}
		</g>
	{/if}
</g>

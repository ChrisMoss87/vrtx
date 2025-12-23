<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { ChevronRight, Home, X } from 'lucide-svelte';
	import {
		getDrillDownBreadcrumbs,
		goToDrillDownLevel,
		resetDrillDown,
		type DrillDownBreadcrumb
	} from '$lib/stores/dashboardNavigation.svelte';

	interface Props {
		widgetId: number;
		onLevelChange?: (filters: import('$lib/types/filters').FilterConfig[]) => void;
	}

	let { widgetId, onLevelChange }: Props = $props();

	const breadcrumbs = $derived(getDrillDownBreadcrumbs(widgetId));

	function handleLevelClick(level: number) {
		const filters = goToDrillDownLevel(widgetId, level);
		onLevelChange?.(filters);
	}

	function handleReset() {
		resetDrillDown(widgetId);
		onLevelChange?.([]);
	}
</script>

{#if breadcrumbs.length > 0}
	<div class="flex items-center gap-1 rounded-md bg-muted/50 px-2 py-1 text-sm">
		<Button
			variant="ghost"
			size="sm"
			class="h-6 gap-1 px-2 text-xs"
			onclick={handleReset}
		>
			<Home class="h-3 w-3" />
			<span>All</span>
		</Button>

		{#each breadcrumbs as crumb, index (crumb.level)}
			<ChevronRight class="h-3 w-3 text-muted-foreground" />
			<Button
				variant={index === breadcrumbs.length - 1 ? 'secondary' : 'ghost'}
				size="sm"
				class="h-6 px-2 text-xs"
				onclick={() => handleLevelClick(index)}
			>
				{crumb.label}
			</Button>
		{/each}

		<Button
			variant="ghost"
			size="sm"
			class="ml-1 h-5 w-5 p-0"
			onclick={handleReset}
		>
			<X class="h-3 w-3" />
		</Button>
	</div>
{/if}

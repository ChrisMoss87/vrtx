<script lang="ts">
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import { Filter, X } from 'lucide-svelte';
	import {
		isFilterSource,
		clearGlobalFilters,
		useGlobalFilters
	} from '$lib/stores/dashboardFilterContext.svelte';

	interface Props {
		widgetId: number;
	}

	let { widgetId }: Props = $props();

	const filterState = useGlobalFilters();

	const isSource = $derived(isFilterSource(widgetId));
	const isFiltered = $derived(
		filterState.hasActiveFilters && !isSource && filterState.sourceWidgetId !== null
	);
</script>

{#if isSource}
	<Badge variant="default" class="gap-1 text-xs">
		<Filter class="h-3 w-3" />
		Filtering dashboard
		<Button
			variant="ghost"
			size="sm"
			class="ml-1 h-4 w-4 p-0 hover:bg-primary-foreground/20"
			onclick={clearGlobalFilters}
		>
			<X class="h-3 w-3" />
		</Button>
	</Badge>
{:else if isFiltered}
	<Badge variant="secondary" class="gap-1 text-xs">
		<Filter class="h-3 w-3" />
		Filtered
	</Badge>
{/if}

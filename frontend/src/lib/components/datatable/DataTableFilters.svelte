<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Filter } from 'lucide-svelte';
	import type { TableContext } from './types';

	interface Props {
		moduleApiName: string;
		onToggle?: () => void;
		isOpen?: boolean;
	}

	let { moduleApiName, onToggle, isOpen = false }: Props = $props();

	const table = getContext<TableContext>('table');

	const filterCount = $derived(table.state.filters.length);
</script>

<Button
	variant={filterCount > 0 ? 'default' : 'outline'}
	size="sm"
	class="relative"
	aria-label={filterCount > 0 ? `${filterCount} filters active` : 'Add filters'}
	aria-expanded={isOpen}
	onclick={onToggle}
>
	<Filter class="mr-2 h-4 w-4" />
	Filters
	{#if filterCount > 0}
		<Badge
			variant="secondary"
			class="ml-2 h-5 rounded-full bg-background px-1.5 text-xs text-foreground"
		>
			{filterCount}
		</Badge>
	{/if}
</Button>

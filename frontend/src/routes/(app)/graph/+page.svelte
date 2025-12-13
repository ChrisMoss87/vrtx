<script lang="ts">
	import { RevenueGraph } from '$lib/components/revenue-graph';
	import { page } from '$app/stores';

	// Allow opening graph centered on a specific entity
	$: entityType = $page.url.searchParams.get('type');
	$: entityId = $page.url.searchParams.get('id');
</script>

<svelte:head>
	<title>Revenue Intelligence Graph | VRTX</title>
</svelte:head>

<div class="h-[calc(100vh-4rem)]">
	<RevenueGraph
		initialEntityType={entityType}
		initialEntityId={entityId ? parseInt(entityId) : null}
	/>
</div>

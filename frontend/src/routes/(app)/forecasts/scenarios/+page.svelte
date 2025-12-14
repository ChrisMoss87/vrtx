<script lang="ts">
	import { ScenarioPlanner } from '$lib/components/scenario-planner';
	import { page } from '$app/stores';

	const scenarioId = $derived($page.url.searchParams.get('id'));
</script>

<svelte:head>
	<title>Scenario Planner | VRTX</title>
</svelte:head>

<div class="h-[calc(100vh-4rem)]">
	<ScenarioPlanner initialScenarioId={scenarioId ? parseInt(scenarioId) : null} />
</div>

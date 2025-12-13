<script lang="ts">
	import { CompetitorList } from '$lib/components/battlecard';
</script>

<svelte:head>
	<title>Competitors | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<div>
		<h1 class="text-2xl font-bold">Competitor Battlecards</h1>
		<p class="text-muted-foreground">Track competitors and access battlecards to win more deals</p>
	</div>

	<CompetitorList />
</div>

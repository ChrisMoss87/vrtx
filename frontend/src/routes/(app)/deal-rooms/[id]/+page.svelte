<script lang="ts">
	import { page } from '$app/stores';
	import { DealRoom } from '$lib/components/deal-room';

	const roomId = $derived(parseInt($page.params.id ?? '0'));
</script>

<svelte:head>
	<title>Deal Room | VRTX</title>
</svelte:head>

{#if roomId}
	<DealRoom {roomId} />
{/if}

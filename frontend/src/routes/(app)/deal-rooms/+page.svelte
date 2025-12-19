<script lang="ts">
	import { DealRoomDashboard } from '$lib/components/deal-room';
	import { PluginGate } from '$lib/components/billing';
</script>

<svelte:head>
	<title>Deal Rooms | VRTX</title>
</svelte:head>

<PluginGate
	plugin="deal-rooms"
	title="Deal Rooms"
	description="Create collaborative spaces for complex deals with document sharing, action tracking, and buyer engagement analytics."
>
	<DealRoomDashboard />
</PluginGate>

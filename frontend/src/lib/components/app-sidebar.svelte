<script lang="ts">
	import { sidebarStyle } from '$lib/stores/sidebar';
	import { RailSidebar, CollapsibleSidebar } from '$lib/components/sidebar';
</script>

{#if $sidebarStyle === 'rail'}
	<RailSidebar />
{:else if $sidebarStyle === 'collapsible'}
	<CollapsibleSidebar />
{:else}
	<div class="w-12 bg-red-500 text-white p-2">No sidebar style: {$sidebarStyle}</div>
{/if}

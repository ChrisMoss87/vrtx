<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { MessageSquare } from 'lucide-svelte';

	interface Props {
		count: number;
		unresolvedCount?: number;
		onclick?: () => void;
	}

	let { count, unresolvedCount = 0, onclick }: Props = $props();
</script>

{#if count > 0}
	<Button
		variant="ghost"
		size="sm"
		class="h-7 gap-1 px-2"
		{onclick}
	>
		<MessageSquare class="h-3.5 w-3.5" />
		<span class="text-xs">{count}</span>
		{#if unresolvedCount > 0}
			<Badge variant="destructive" class="h-4 px-1 text-xs">
				{unresolvedCount}
			</Badge>
		{/if}
	</Button>
{:else}
	<Button
		variant="ghost"
		size="sm"
		class="h-7 w-7 p-0 opacity-0 group-hover:opacity-100 transition-opacity"
		{onclick}
	>
		<MessageSquare class="h-3.5 w-3.5 text-muted-foreground" />
	</Button>
{/if}

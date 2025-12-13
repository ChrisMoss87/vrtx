<script lang="ts">
	import { Check, MessageSquare } from 'lucide-svelte';
	import type { CompetitorNote } from '$lib/api/competitors';

	export let notes: CompetitorNote[];
	export let compact = false;

	function formatDate(dateStr: string): string {
		const date = new Date(dateStr);
		return date.toLocaleDateString('en-US', {
			month: 'short',
			day: 'numeric'
		});
	}
</script>

<div class="space-y-3">
	{#if notes.length === 0}
		<p class="text-sm text-muted-foreground text-center py-4">No notes yet</p>
	{:else}
		{#each notes as note (note.id)}
			<div class="flex gap-3 {compact ? 'text-sm' : ''}">
				<div class="w-6 h-6 rounded-full bg-muted flex items-center justify-center flex-shrink-0">
					<MessageSquare class="h-3 w-3 text-muted-foreground" />
				</div>
				<div class="flex-1 min-w-0">
					<p class="{compact ? 'text-sm' : ''}">{note.content}</p>
					<div class="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
						{#if note.is_verified}
							<span class="flex items-center gap-1 text-green-600 dark:text-green-400">
								<Check class="h-3 w-3" />
								Verified
							</span>
						{/if}
						<span>— {note.created_by}, {formatDate(note.created_at)}</span>
						{#if note.source}
							<span class="text-muted-foreground">via {note.source}</span>
						{/if}
					</div>
				</div>
			</div>
		{/each}
	{/if}
</div>

<script lang="ts">
	import { Cloud, CloudOff, Loader2, Check, AlertCircle } from 'lucide-svelte';

	interface Props {
		isSaving?: boolean;
		lastSaved?: Date | null;
		saveError?: string | null;
		draftId?: number | null;
	}

	let { isSaving = false, lastSaved = null, saveError = null, draftId = null }: Props = $props();

	function formatLastSaved(date: Date): string {
		const now = new Date();
		const diff = now.getTime() - date.getTime();
		const seconds = Math.floor(diff / 1000);
		const minutes = Math.floor(seconds / 60);
		const hours = Math.floor(minutes / 60);

		if (seconds < 10) return 'just now';
		if (seconds < 60) return `${seconds}s ago`;
		if (minutes < 60) return `${minutes}m ago`;
		if (hours < 24) return `${hours}h ago`;
		return date.toLocaleDateString();
	}
</script>

<div class="flex items-center gap-2 text-xs text-muted-foreground">
	{#if isSaving}
		<Loader2 class="h-3 w-3 animate-spin" />
		<span>Saving...</span>
	{:else if saveError}
		<AlertCircle class="h-3 w-3 text-destructive" />
		<span class="text-destructive">Save failed</span>
	{:else if lastSaved}
		<Check class="h-3 w-3 text-green-500" />
		<span>Saved {formatLastSaved(lastSaved)}</span>
	{:else if draftId}
		<Cloud class="h-3 w-3" />
		<span>Draft #{draftId}</span>
	{:else}
		<CloudOff class="h-3 w-3" />
		<span>Not saved</span>
	{/if}
</div>

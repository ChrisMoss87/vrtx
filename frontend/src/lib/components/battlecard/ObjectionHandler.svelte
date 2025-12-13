<script lang="ts">
	import { ChevronDown, ChevronUp, ThumbsUp, ThumbsDown } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { recordObjectionFeedback, type ObjectionHandler } from '$lib/api/competitors';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	export let objection: ObjectionHandler;
	export let competitorId: number;
	export let dealId: number | undefined = undefined;

	let expanded = false;
	let loading = false;

	function getEffectivenessColor(score: number | null): string {
		if (score === null) return 'text-muted-foreground';
		if (score >= 70) return 'text-green-600 dark:text-green-400';
		if (score >= 50) return 'text-amber-600 dark:text-amber-400';
		return 'text-red-600 dark:text-red-400';
	}

	async function handleFeedback(wasSuccessful: boolean) {
		loading = true;
		const { data, error } = await tryCatch(
			recordObjectionFeedback(competitorId, objection.id, wasSuccessful, dealId)
		);
		loading = false;

		if (error) {
			toast.error('Failed to record feedback');
			return;
		}

		toast.success(wasSuccessful ? 'Great! Thanks for the feedback' : 'Thanks, we\'ll improve this');

		// Update local state
		if (data) {
			objection.effectiveness_score = data.effectiveness_score;
			objection.use_count = data.use_count;
		}
	}
</script>

<div class="rounded-lg border p-3">
	<button
		class="w-full text-left"
		onclick={() => (expanded = !expanded)}
	>
		<div class="flex items-start justify-between gap-2">
			<div class="flex-1 min-w-0">
				<p class="font-medium text-sm">"{objection.objection}"</p>
				{#if !expanded}
					<p class="text-xs text-muted-foreground mt-1 line-clamp-1">
						Counter: {objection.counter_script}
					</p>
				{/if}
			</div>
			<div class="flex items-center gap-2 flex-shrink-0">
				{#if objection.effectiveness_score !== null && objection.use_count >= 3}
					<span class="text-xs {getEffectivenessColor(objection.effectiveness_score)}">
						{objection.effectiveness_score}% effective
					</span>
				{/if}
				{#if expanded}
					<ChevronUp class="h-4 w-4 text-muted-foreground" />
				{:else}
					<ChevronDown class="h-4 w-4 text-muted-foreground" />
				{/if}
			</div>
		</div>
	</button>

	{#if expanded}
		<div class="mt-3 pt-3 border-t">
			<div class="text-sm font-medium mb-2">Counter Script:</div>
			<p class="text-sm text-muted-foreground whitespace-pre-wrap">
				{objection.counter_script}
			</p>

			<!-- Feedback buttons -->
			<div class="flex items-center justify-between mt-4 pt-3 border-t">
				<span class="text-xs text-muted-foreground">
					Did this help? ({objection.use_count} uses)
				</span>
				<div class="flex items-center gap-2">
					<Button
						variant="outline"
						size="sm"
						onclick={() => handleFeedback(true)}
						disabled={loading}
						class="gap-1"
					>
						<ThumbsUp class="h-3.5 w-3.5" />
						Worked
					</Button>
					<Button
						variant="outline"
						size="sm"
						onclick={() => handleFeedback(false)}
						disabled={loading}
						class="gap-1"
					>
						<ThumbsDown class="h-3.5 w-3.5" />
						Didn't work
					</Button>
				</div>
			</div>
		</div>
	{/if}
</div>

<script lang="ts">
	import { Card, CardContent, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import type { DuplicateCandidate } from '$lib/api/duplicates';
	import { getRecordDisplayName, formatMatchScore, getMatchScoreBadgeVariant } from '$lib/api/duplicates';

	interface Props {
		candidate: DuplicateCandidate;
		primaryField?: string;
		onMerge?: () => void;
		onDismiss?: () => void;
		class?: string;
	}

	let { candidate, primaryField, onMerge, onDismiss, class: className = '' }: Props = $props();

	const recordAName = $derived(getRecordDisplayName(candidate.record_a.data, primaryField));
	const recordBName = $derived(getRecordDisplayName(candidate.record_b.data, primaryField));
</script>

<Card class={className}>
	<CardHeader class="pb-3">
		<div class="flex items-center justify-between">
			<CardTitle class="text-base">Potential Duplicate</CardTitle>
			<Badge variant={getMatchScoreBadgeVariant(candidate.match_score)}>
				{formatMatchScore(candidate.match_score)} match
			</Badge>
		</div>
	</CardHeader>
	<CardContent class="space-y-4">
		<div class="grid grid-cols-2 gap-4">
			<div class="space-y-1">
				<p class="text-xs text-muted-foreground">Record A</p>
				<p class="font-medium">{recordAName}</p>
				<p class="text-xs text-muted-foreground">
					Created {new Date(candidate.record_a.created_at).toLocaleDateString()}
				</p>
			</div>
			<div class="space-y-1">
				<p class="text-xs text-muted-foreground">Record B</p>
				<p class="font-medium">{recordBName}</p>
				<p class="text-xs text-muted-foreground">
					Created {new Date(candidate.record_b.created_at).toLocaleDateString()}
				</p>
			</div>
		</div>

		{#if candidate.matched_rules.length > 0}
			<div class="space-y-2">
				<p class="text-xs font-medium text-muted-foreground uppercase">Matched Rules</p>
				<div class="flex flex-wrap gap-1">
					{#each candidate.matched_rules as rule}
						<Badge variant="outline" class="text-xs">
							{rule.rule_name}
						</Badge>
					{/each}
				</div>
			</div>
		{/if}

		{#if candidate.status === 'pending'}
			<div class="flex gap-2 pt-2">
				<Button size="sm" class="flex-1" onclick={onMerge}>
					Merge
				</Button>
				<Button size="sm" variant="outline" class="flex-1" onclick={onDismiss}>
					Dismiss
				</Button>
			</div>
		{:else}
			<div class="pt-2">
				<Badge variant={candidate.status === 'merged' ? 'default' : 'secondary'}>
					{candidate.status === 'merged' ? 'Merged' : 'Dismissed'}
				</Badge>
				{#if candidate.reviewed_by}
					<span class="text-xs text-muted-foreground ml-2">
						by {candidate.reviewed_by.name}
					</span>
				{/if}
			</div>
		{/if}
	</CardContent>
</Card>

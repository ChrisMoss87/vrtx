<script lang="ts">
	import type { ComparisonField, ComparisonResult } from '$lib/api/time-machine';
	import { ArrowRight, Plus, Minus, RefreshCw } from 'lucide-svelte';

	export let comparison: ComparisonResult;
	export let showUnchanged = false;

	$: displayFields = showUnchanged
		? comparison.comparison
		: comparison.comparison.filter((f) => f.has_changed);

	function formatTimestamp(timestamp: string): string {
		return new Date(timestamp).toLocaleString('en-US', {
			month: 'short',
			day: 'numeric',
			year: 'numeric',
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function getChangeIcon(changeType: string | null) {
		switch (changeType) {
			case 'added':
				return Plus;
			case 'removed':
				return Minus;
			case 'modified':
				return RefreshCw;
			default:
				return null;
		}
	}

	function getChangeClass(changeType: string | null): string {
		switch (changeType) {
			case 'added':
				return 'bg-green-50 dark:bg-green-950 border-green-200 dark:border-green-800';
			case 'removed':
				return 'bg-red-50 dark:bg-red-950 border-red-200 dark:border-red-800';
			case 'modified':
				return 'bg-yellow-50 dark:bg-yellow-950 border-yellow-200 dark:border-yellow-800';
			default:
				return 'bg-background border-border';
		}
	}
</script>

<div class="space-y-4">
	<!-- Header with timestamps -->
	<div class="flex items-center justify-between text-sm text-muted-foreground">
		<div class="flex-1 text-center">
			<span class="font-medium">{formatTimestamp(comparison.from_timestamp)}</span>
		</div>
		<ArrowRight class="h-4 w-4 mx-4 flex-shrink-0" />
		<div class="flex-1 text-center">
			<span class="font-medium">{formatTimestamp(comparison.to_timestamp)}</span>
		</div>
	</div>

	<!-- Summary -->
	<div class="bg-muted/50 rounded-lg p-3 text-sm">
		<div class="flex items-center gap-4 flex-wrap">
			<span class="font-medium">{comparison.summary.total_fields_changed} fields changed</span>
			{#if comparison.summary.fields_added > 0}
				<span class="text-green-600 dark:text-green-400">
					+{comparison.summary.fields_added} added
				</span>
			{/if}
			{#if comparison.summary.fields_modified > 0}
				<span class="text-yellow-600 dark:text-yellow-400">
					{comparison.summary.fields_modified} modified
				</span>
			{/if}
			{#if comparison.summary.fields_removed > 0}
				<span class="text-red-600 dark:text-red-400">
					-{comparison.summary.fields_removed} removed
				</span>
			{/if}
		</div>

		{#if comparison.summary.significant_changes.length > 0}
			<div class="mt-2 text-muted-foreground">
				Key changes:
				{#each comparison.summary.significant_changes as change, i}
					<span class="font-medium text-foreground">{change.field}</span>
					{change.from} → {change.to}{i < comparison.summary.significant_changes.length - 1
						? ', '
						: ''}
				{/each}
			</div>
		{/if}
	</div>

	<!-- Toggle unchanged fields -->
	<label class="flex items-center gap-2 text-sm cursor-pointer">
		<input
			type="checkbox"
			bind:checked={showUnchanged}
			class="rounded border-input"
		/>
		Show unchanged fields
	</label>

	<!-- Comparison table -->
	<div class="border rounded-lg overflow-hidden">
		<table class="w-full text-sm">
			<thead>
				<tr class="bg-muted">
					<th class="px-4 py-2 text-left font-medium">Field</th>
					<th class="px-4 py-2 text-left font-medium">Before</th>
					<th class="px-4 py-2 text-center font-medium w-10"></th>
					<th class="px-4 py-2 text-left font-medium">After</th>
				</tr>
			</thead>
			<tbody>
				{#each displayFields as field}
					{@const Icon = getChangeIcon(field.change_type)}
					<tr class="border-t {getChangeClass(field.change_type)}">
						<td class="px-4 py-2 font-medium">
							{field.field_label}
						</td>
						<td class="px-4 py-2 {field.change_type === 'removed' ? 'line-through text-muted-foreground' : ''}">
							{field.from_display}
						</td>
						<td class="px-4 py-2 text-center">
							{#if Icon}
								<svelte:component this={Icon} class="h-4 w-4 mx-auto text-muted-foreground" />
							{/if}
						</td>
						<td class="px-4 py-2 {field.change_type === 'added' ? 'font-medium' : ''}">
							{field.to_display}
						</td>
					</tr>
				{/each}
			</tbody>
		</table>
	</div>
</div>

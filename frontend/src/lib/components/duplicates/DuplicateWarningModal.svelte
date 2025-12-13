<script lang="ts">
	import type { DuplicateMatch } from '$lib/api/duplicates';
	import { getRecordDisplayName, formatMatchScore, getMatchScoreBadgeVariant } from '$lib/api/duplicates';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Dialog from '$lib/components/ui/dialog';
	import { AlertTriangle, ExternalLink, GitMerge } from 'lucide-svelte';

	interface Props {
		open?: boolean;
		duplicates: DuplicateMatch[];
		shouldBlock: boolean;
		primaryField?: string;
		onClose?: () => void;
		onCreateAnyway?: () => void;
		onMergeWith?: (recordId: number) => void;
		onViewRecord?: (recordId: number) => void;
	}

	let {
		open = $bindable(false),
		duplicates,
		shouldBlock,
		primaryField,
		onClose,
		onCreateAnyway,
		onMergeWith,
		onViewRecord
	}: Props = $props();

	function handleOpenChange(isOpen: boolean) {
		open = isOpen;
		if (!isOpen) {
			onClose?.();
		}
	}

	function handleCreateAnyway() {
		onCreateAnyway?.();
		open = false;
	}

	function handleMerge(recordId: number) {
		onMergeWith?.(recordId);
		open = false;
	}

	function handleView(recordId: number) {
		onViewRecord?.(recordId);
	}
</script>

<Dialog.Root {open} onOpenChange={handleOpenChange}>
	<Dialog.Content class="sm:max-w-lg">
		<Dialog.Header>
			<Dialog.Title class="flex items-center gap-2">
				<AlertTriangle class="h-5 w-5 text-yellow-500" />
				{shouldBlock ? 'Duplicate Record Blocked' : 'Possible Duplicate Detected'}
			</Dialog.Title>
			<Dialog.Description>
				{#if shouldBlock}
					This record cannot be created because it matches existing records.
				{:else}
					The record you're creating may be a duplicate of an existing record.
				{/if}
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-3 py-4 max-h-[400px] overflow-y-auto">
			{#each duplicates as duplicate}
				<div class="rounded-lg border p-4 space-y-2">
					<div class="flex items-start justify-between">
						<div class="flex-1 min-w-0">
							<p class="font-medium truncate">
								{getRecordDisplayName(duplicate.record.data, primaryField)}
							</p>
							<div class="flex flex-wrap gap-2 mt-1 text-sm text-muted-foreground">
								{#each Object.entries(duplicate.record.data).slice(0, 3) as [key, value]}
									{#if value && key !== primaryField}
										<span class="truncate max-w-[200px]">{key}: {value}</span>
									{/if}
								{/each}
							</div>
							<p class="text-xs text-muted-foreground mt-1">
								Created: {new Date(duplicate.record.created_at).toLocaleDateString()}
							</p>
						</div>
						<Badge variant={getMatchScoreBadgeVariant(duplicate.match_score)}>
							{formatMatchScore(duplicate.match_score)} match
						</Badge>
					</div>

					<div class="flex items-center gap-2 pt-2 border-t">
						<Button variant="outline" size="sm" onclick={() => handleView(duplicate.record_id)}>
							<ExternalLink class="h-3 w-3 mr-1" />
							View
						</Button>
						{#if !shouldBlock && onMergeWith}
							<Button variant="secondary" size="sm" onclick={() => handleMerge(duplicate.record_id)}>
								<GitMerge class="h-3 w-3 mr-1" />
								Merge with this
							</Button>
						{/if}
					</div>
				</div>
			{/each}
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => handleOpenChange(false)}>
				Cancel
			</Button>
			{#if !shouldBlock}
				<Button onclick={handleCreateAnyway}>Create Anyway</Button>
			{/if}
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

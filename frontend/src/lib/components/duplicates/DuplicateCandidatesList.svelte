<script lang="ts">
	import type { DuplicateCandidate, CandidateStatus } from '$lib/api/duplicates';
	import {
		getDuplicateCandidates,
		dismissCandidate,
		getRecordDisplayName,
		formatMatchScore,
		getMatchScoreBadgeVariant
	} from '$lib/api/duplicates';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Card, CardContent, CardHeader, CardTitle } from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Loader2, GitMerge, X, RefreshCw, ArrowRight } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';

	interface Props {
		moduleId: number;
		primaryField?: string;
		onMerge?: (recordAId: number, recordBId: number) => void;
		onViewRecord?: (recordId: number) => void;
	}

	let { moduleId, primaryField, onMerge, onViewRecord }: Props = $props();

	let loading = $state(true);
	let candidates = $state<DuplicateCandidate[]>([]);
	let status = $state<CandidateStatus | 'all'>('pending');
	let currentPage = $state(1);
	let totalPages = $state(1);
	let total = $state(0);

	async function loadCandidates() {
		loading = true;
		try {
			const result = await getDuplicateCandidates({
				module_id: moduleId,
				status: status === 'all' ? undefined : status,
				per_page: 10,
				page: currentPage
			});
			candidates = result.data;
			totalPages = result.meta.last_page;
			total = result.meta.total;
		} catch {
			toast.error('Failed to load duplicate candidates');
		} finally {
			loading = false;
		}
	}

	async function handleDismiss(candidate: DuplicateCandidate) {
		try {
			await dismissCandidate(candidate.id);
			toast.success('Duplicate dismissed');
			loadCandidates();
		} catch {
			toast.error('Failed to dismiss duplicate');
		}
	}

	function handleMerge(candidate: DuplicateCandidate) {
		onMerge?.(candidate.record_a.id, candidate.record_b.id);
	}

	function handleStatusChange(value: string | undefined) {
		if (value) {
			status = value as CandidateStatus | 'all';
			currentPage = 1;
			loadCandidates();
		}
	}

	$effect(() => {
		loadCandidates();
	});
</script>

<Card>
	<CardHeader class="flex flex-row items-center justify-between space-y-0 pb-4">
		<CardTitle class="text-lg font-medium">Duplicate Candidates</CardTitle>
		<div class="flex items-center gap-2">
			<Select.Root type="single" value={status} onValueChange={handleStatusChange}>
				<Select.Trigger class="w-[140px]">
					<span class="capitalize">{status === 'all' ? 'All Statuses' : status}</span>
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="all">All Statuses</Select.Item>
					<Select.Item value="pending">Pending</Select.Item>
					<Select.Item value="merged">Merged</Select.Item>
					<Select.Item value="dismissed">Dismissed</Select.Item>
				</Select.Content>
			</Select.Root>
			<Button variant="outline" size="icon" onclick={loadCandidates} disabled={loading}>
				<RefreshCw class="h-4 w-4 {loading ? 'animate-spin' : ''}" />
			</Button>
		</div>
	</CardHeader>
	<CardContent>
		{#if loading && candidates.length === 0}
			<div class="flex items-center justify-center py-8">
				<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
			</div>
		{:else if candidates.length === 0}
			<div class="text-center py-8 text-muted-foreground">
				No duplicate candidates found
			</div>
		{:else}
			<div class="space-y-3">
				{#each candidates as candidate (candidate.id)}
					<div class="rounded-lg border p-4">
						<div class="flex items-center justify-between mb-3">
							<Badge variant={getMatchScoreBadgeVariant(candidate.match_score)}>
								{formatMatchScore(candidate.match_score)} match
							</Badge>
							<Badge variant={candidate.status === 'pending' ? 'outline' : 'secondary'}>
								{candidate.status}
							</Badge>
						</div>

						<div class="grid grid-cols-2 gap-4">
							<!-- Record A -->
							<div
								class="p-3 rounded-md bg-muted cursor-pointer hover:bg-muted/80"
								role="button"
								tabindex="0"
								onclick={() => onViewRecord?.(candidate.record_a.id)}
								onkeypress={(e) => e.key === 'Enter' && onViewRecord?.(candidate.record_a.id)}
							>
								<p class="font-medium text-sm truncate">
									{getRecordDisplayName(candidate.record_a.data, primaryField)}
								</p>
								<p class="text-xs text-muted-foreground mt-1">
									ID: {candidate.record_a.id}
								</p>
							</div>

							<!-- Arrow -->
							<div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 hidden md:block">
								<ArrowRight class="h-4 w-4 text-muted-foreground" />
							</div>

							<!-- Record B -->
							<div
								class="p-3 rounded-md bg-muted cursor-pointer hover:bg-muted/80"
								role="button"
								tabindex="0"
								onclick={() => onViewRecord?.(candidate.record_b.id)}
								onkeypress={(e) => e.key === 'Enter' && onViewRecord?.(candidate.record_b.id)}
							>
								<p class="font-medium text-sm truncate">
									{getRecordDisplayName(candidate.record_b.data, primaryField)}
								</p>
								<p class="text-xs text-muted-foreground mt-1">
									ID: {candidate.record_b.id}
								</p>
							</div>
						</div>

						{#if candidate.status === 'pending'}
							<div class="flex items-center gap-2 mt-3 pt-3 border-t">
								<Button
									variant="default"
									size="sm"
									onclick={() => handleMerge(candidate)}
								>
									<GitMerge class="h-3 w-3 mr-1" />
									Merge
								</Button>
								<Button
									variant="outline"
									size="sm"
									onclick={() => handleDismiss(candidate)}
								>
									<X class="h-3 w-3 mr-1" />
									Not a Duplicate
								</Button>
							</div>
						{:else if candidate.reviewed_by}
							<div class="mt-3 pt-3 border-t text-xs text-muted-foreground">
								Reviewed by {candidate.reviewed_by.name} on{' '}
								{candidate.reviewed_at ? new Date(candidate.reviewed_at).toLocaleString() : ''}
								{#if candidate.dismiss_reason}
									<br />Reason: {candidate.dismiss_reason}
								{/if}
							</div>
						{/if}
					</div>
				{/each}
			</div>

			<!-- Pagination -->
			{#if totalPages > 1}
				<div class="flex items-center justify-between mt-4 pt-4 border-t">
					<p class="text-sm text-muted-foreground">
						Showing page {currentPage} of {totalPages} ({total} total)
					</p>
					<div class="flex items-center gap-2">
						<Button
							variant="outline"
							size="sm"
							disabled={currentPage === 1}
							onclick={() => {
								currentPage--;
								loadCandidates();
							}}
						>
							Previous
						</Button>
						<Button
							variant="outline"
							size="sm"
							disabled={currentPage >= totalPages}
							onclick={() => {
								currentPage++;
								loadCandidates();
							}}
						>
							Next
						</Button>
					</div>
				</div>
			{/if}
		{/if}
	</CardContent>
</Card>

<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { getRottingDeals, type RottingDeal, type RotStatus } from '$lib/api/rotting';
	import RottingIndicator from './RottingIndicator.svelte';
	import { goto } from '$app/navigation';

	interface Props {
		pipelineId?: number;
		limit?: number;
		class?: string;
	}

	let { pipelineId, limit = 5, class: className = '' }: Props = $props();

	let deals = $state<RottingDeal[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);

	async function loadDeals() {
		loading = true;
		error = null;
		try {
			const response = await getRottingDeals({
				pipeline_id: pipelineId,
				status: 'rotting',
				per_page: limit
			});
			deals = response.data;
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to load rotting deals';
		} finally {
			loading = false;
		}
	}

	$effect(() => {
		loadDeals();
	});

	function getDealName(deal: RottingDeal): string {
		const data = deal.record.data;
		return (data.name as string) || (data.title as string) || `Deal #${deal.record.id}`;
	}

	function navigateToDeal(deal: RottingDeal) {
		goto(`/deals/${deal.record.id}`);
	}
</script>

<Card class={className}>
	<CardHeader class="pb-3">
		<div class="flex items-center justify-between">
			<div>
				<CardTitle class="text-base">Rotting Deals</CardTitle>
				<CardDescription>Deals needing immediate attention</CardDescription>
			</div>
			<Button variant="ghost" size="sm" href="/deals/rotting">View All</Button>
		</div>
	</CardHeader>
	<CardContent>
		{#if loading}
			<div class="space-y-3">
				{#each Array(3) as _}
					<div class="flex items-center gap-3">
						<Skeleton class="h-8 w-8 rounded-full" />
						<div class="flex-1 space-y-1">
							<Skeleton class="h-4 w-3/4" />
							<Skeleton class="h-3 w-1/2" />
						</div>
					</div>
				{/each}
			</div>
		{:else if error}
			<div class="text-center py-4 text-muted-foreground">
				<p class="text-sm">{error}</p>
				<Button variant="ghost" size="sm" onclick={loadDeals} class="mt-2">Retry</Button>
			</div>
		{:else if deals.length === 0}
			<div class="text-center py-6 text-muted-foreground">
				<p class="text-sm">No rotting deals found</p>
				<p class="text-xs mt-1">All your deals are looking healthy!</p>
			</div>
		{:else}
			<div class="space-y-3">
				{#each deals as deal}
					<button
						class="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-muted/50 transition-colors text-left"
						onclick={() => navigateToDeal(deal)}
					>
						<RottingIndicator status={deal.rot_status} size="lg" showDays={false} showTooltip={false} />
						<div class="flex-1 min-w-0">
							<p class="text-sm font-medium truncate">{getDealName(deal)}</p>
							<p class="text-xs text-muted-foreground">
								{deal.stage.name} &bull; {deal.rot_status.days_inactive} days inactive
							</p>
						</div>
						<div class="text-xs text-muted-foreground">
							{deal.pipeline.name}
						</div>
					</button>
				{/each}
			</div>
		{/if}
	</CardContent>
</Card>

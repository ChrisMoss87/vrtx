<script lang="ts">
	import { DollarSign, GripVertical, CheckCircle2 } from 'lucide-svelte';
	import type { ScenarioDeal } from '$lib/api/scenarios';

	export let deals: ScenarioDeal[];
	export let stageBreakdown: Array<{
		stage_id: number | null;
		stage_name: string;
		deal_count: number;
		total_amount: number;
		weighted_amount: number;
	}>;
	export let onDealSelect: (deal: ScenarioDeal) => void;
	export let onDealMove: (deal: ScenarioDeal, newStageId: number) => void;

	let draggedDeal: ScenarioDeal | null = null;
	let dragOverStageId: number | null = null;

	// Group deals by stage
	$: dealsByStage = deals.reduce(
		(acc, deal) => {
			const stageId = deal.stage_id ?? 0;
			if (!acc[stageId]) {
				acc[stageId] = [];
			}
			acc[stageId].push(deal);
			return acc;
		},
		{} as Record<number, ScenarioDeal[]>
	);

	// Create stage columns from breakdown, ensuring all stages are shown
	$: stages = stageBreakdown.map((s) => ({
		id: s.stage_id ?? 0,
		name: s.stage_name,
		deals: dealsByStage[s.stage_id ?? 0] ?? [],
		totalAmount: s.total_amount,
		weightedAmount: s.weighted_amount
	}));

	function formatCurrency(value: number): string {
		if (value >= 1000000) {
			return `$${(value / 1000000).toFixed(1)}M`;
		}
		if (value >= 1000) {
			return `$${(value / 1000).toFixed(0)}K`;
		}
		return `$${value.toFixed(0)}`;
	}

	function handleDragStart(e: DragEvent, deal: ScenarioDeal) {
		draggedDeal = deal;
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', deal.id.toString());
		}
	}

	function handleDragEnd() {
		draggedDeal = null;
		dragOverStageId = null;
	}

	function handleDragOver(e: DragEvent, stageId: number) {
		e.preventDefault();
		dragOverStageId = stageId;
	}

	function handleDragLeave() {
		dragOverStageId = null;
	}

	function handleDrop(e: DragEvent, stageId: number) {
		e.preventDefault();
		if (draggedDeal && draggedDeal.stage_id !== stageId) {
			onDealMove(draggedDeal, stageId);
		}
		draggedDeal = null;
		dragOverStageId = null;
	}

	function getProbabilityColor(probability: number): string {
		if (probability >= 75) return 'text-green-600';
		if (probability >= 50) return 'text-amber-600';
		if (probability >= 25) return 'text-orange-600';
		return 'text-red-600';
	}
</script>

<div class="flex gap-4 pb-4 overflow-x-auto">
	{#each stages as stage}
		<div
			class="flex-shrink-0 w-72 rounded-lg border bg-muted/30 {dragOverStageId === stage.id
				? 'ring-2 ring-primary'
				: ''}"
			ondragover={(e) => handleDragOver(e, stage.id)}
			ondragleave={handleDragLeave}
			ondrop={(e) => handleDrop(e, stage.id)}
			role="region"
			aria-label="{stage.name} column"
		>
			<!-- Stage Header -->
			<div class="p-3 border-b">
				<div class="flex items-center justify-between">
					<h3 class="font-medium">{stage.name}</h3>
					<span class="text-xs text-muted-foreground">{stage.deals.length} deals</span>
				</div>
				<div class="mt-1 flex items-center gap-3 text-sm text-muted-foreground">
					<span>{formatCurrency(stage.totalAmount)}</span>
					<span class="text-xs">({formatCurrency(stage.weightedAmount)} weighted)</span>
				</div>
			</div>

			<!-- Deals List -->
			<div class="p-2 space-y-2 min-h-[200px]">
				{#each stage.deals as deal}
					<button
						class="w-full text-left rounded-lg border bg-background p-3 cursor-pointer hover:shadow-md transition-shadow {draggedDeal?.id ===
						deal.id
							? 'opacity-50'
							: ''} {deal.has_changes ? 'ring-1 ring-amber-400' : ''}"
						draggable="true"
						ondragstart={(e) => handleDragStart(e, deal)}
						ondragend={handleDragEnd}
						onclick={() => onDealSelect(deal)}
					>
						<div class="flex items-start gap-2">
							<GripVertical class="h-4 w-4 text-muted-foreground mt-0.5 cursor-grab" />
							<div class="flex-1 min-w-0">
								<div class="flex items-center gap-1">
									{#if deal.is_committed}
										<CheckCircle2 class="h-3.5 w-3.5 text-green-500 flex-shrink-0" />
									{/if}
									<span class="font-medium text-sm truncate">{deal.name}</span>
								</div>
								<div class="mt-1 flex items-center gap-2 text-sm">
									<span class="font-semibold">{formatCurrency(deal.amount)}</span>
									<span class={getProbabilityColor(deal.probability)}>
										{deal.probability}%
									</span>
								</div>
								{#if deal.close_date}
									<div class="mt-1 text-xs text-muted-foreground">
										Close: {new Date(deal.close_date).toLocaleDateString()}
									</div>
								{/if}
								{#if deal.has_changes}
									<div class="mt-1 text-xs text-amber-600">Modified from actual</div>
								{/if}
							</div>
						</div>
					</button>
				{/each}

				{#if stage.deals.length === 0}
					<div class="flex items-center justify-center h-24 text-sm text-muted-foreground">
						No deals in this stage
					</div>
				{/if}
			</div>
		</div>
	{/each}

	{#if stages.length === 0}
		<div class="flex-1 flex items-center justify-center text-muted-foreground">
			<div class="text-center">
				<DollarSign class="mx-auto h-12 w-12 opacity-50 mb-2" />
				<p>No deals in this scenario</p>
				<p class="text-sm mt-1">Deals from the selected period will appear here</p>
			</div>
		</div>
	{/if}
</div>

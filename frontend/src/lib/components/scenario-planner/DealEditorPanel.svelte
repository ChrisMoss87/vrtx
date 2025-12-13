<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Slider } from '$lib/components/ui/slider';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { X, RotateCcw, CheckCircle2, TrendingUp, ArrowRight } from 'lucide-svelte';
	import type { ScenarioDeal } from '$lib/api/scenarios';

	export let deal: ScenarioDeal;
	export let onUpdate: (changes: Partial<ScenarioDeal>) => void;
	export let onClose: () => void;

	let amount = deal.amount;
	let probability = deal.probability;
	let closeDate = deal.close_date ?? '';
	let isCommitted = deal.is_committed;

	function formatCurrency(value: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(value);
	}

	function handleApplyChanges() {
		onUpdate({
			amount,
			probability,
			close_date: closeDate || undefined,
			is_committed: isCommitted
		});
	}

	function handleReset() {
		if (deal.original_data) {
			amount = deal.original_data.amount;
			probability = deal.original_data.probability ?? 50;
			closeDate = deal.original_data.close_date ?? '';
		}
		onUpdate({
			amount: deal.original_data?.amount,
			probability: deal.original_data?.probability ?? undefined,
			close_date: deal.original_data?.close_date ?? undefined
		});
	}

	function handleCommit() {
		isCommitted = true;
		probability = 100;
		onUpdate({ is_committed: true, probability: 100 });
	}

	$: weightedAmount = amount * (probability / 100);
	$: originalWeighted = (deal.original_data?.amount ?? 0) * ((deal.original_data?.probability ?? 50) / 100);
	$: impactDelta = weightedAmount - originalWeighted;
</script>

<div class="flex h-full flex-col">
	<!-- Header -->
	<div class="flex items-center justify-between border-b p-4">
		<div>
			<h3 class="font-semibold">Edit Deal</h3>
			<p class="text-sm text-muted-foreground truncate max-w-[200px]">{deal.name}</p>
		</div>
		<Button variant="ghost" size="icon" onclick={onClose}>
			<X class="h-4 w-4" />
		</Button>
	</div>

	<!-- Content -->
	<div class="flex-1 overflow-y-auto p-4 space-y-6">
		<!-- Amount -->
		<div class="space-y-2">
			<Label for="amount">Amount</Label>
			<Input
				id="amount"
				type="number"
				bind:value={amount}
				min={0}
				step={1000}
			/>
			{#if deal.original_data && amount !== deal.original_data.amount}
				<p class="text-xs text-muted-foreground">
					Actual: {formatCurrency(deal.original_data.amount)}
				</p>
			{/if}
		</div>

		<!-- Probability Slider -->
		<div class="space-y-3">
			<div class="flex items-center justify-between">
				<Label>Probability</Label>
				<span class="text-sm font-medium">{probability}%</span>
			</div>
			<Slider
				type="single"
				bind:value={probability}
				min={0}
				max={100}
				step={5}
			/>
			<div class="flex justify-between text-xs text-muted-foreground">
				<span>0%</span>
				<span>50%</span>
				<span>100%</span>
			</div>
			{#if deal.original_data?.probability !== undefined && probability !== deal.original_data.probability}
				<p class="text-xs text-muted-foreground">
					Actual: {deal.original_data.probability}%
				</p>
			{/if}
		</div>

		<!-- Close Date -->
		<div class="space-y-2">
			<Label for="close-date">Close Date</Label>
			<Input
				id="close-date"
				type="date"
				bind:value={closeDate}
			/>
			{#if deal.original_data?.close_date && closeDate !== deal.original_data.close_date}
				<p class="text-xs text-muted-foreground">
					Actual: {new Date(deal.original_data.close_date).toLocaleDateString()}
				</p>
			{/if}
		</div>

		<!-- Committed Checkbox -->
		<div class="flex items-center gap-2">
			<Checkbox id="committed" bind:checked={isCommitted} />
			<Label for="committed" class="text-sm cursor-pointer">
				Mark as committed
			</Label>
		</div>

		<!-- Impact Summary -->
		<div class="rounded-lg border bg-muted/30 p-4 space-y-3">
			<h4 class="text-sm font-medium flex items-center gap-2">
				<TrendingUp class="h-4 w-4" />
				Impact Summary
			</h4>

			<div class="space-y-2 text-sm">
				<div class="flex justify-between">
					<span class="text-muted-foreground">Weighted Value</span>
					<span class="font-medium">{formatCurrency(weightedAmount)}</span>
				</div>

				{#if deal.original_data}
					<div class="flex justify-between">
						<span class="text-muted-foreground">Original Weighted</span>
						<span>{formatCurrency(originalWeighted)}</span>
					</div>

					<div class="flex justify-between border-t pt-2">
						<span class="text-muted-foreground">Change</span>
						<span class="font-medium {impactDelta >= 0 ? 'text-green-600' : 'text-red-600'}">
							{impactDelta >= 0 ? '+' : ''}{formatCurrency(impactDelta)}
						</span>
					</div>
				{/if}
			</div>
		</div>

		<!-- Changes Summary -->
		{#if deal.has_changes && Object.keys(deal.changes).length > 0}
			<div class="rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-950/20 p-4">
				<h4 class="text-sm font-medium text-amber-700 dark:text-amber-400 mb-2">
					Changes from Actual
				</h4>
				<div class="space-y-1 text-xs">
					{#each Object.entries(deal.changes) as [field, change]}
						<div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
							<span class="capitalize">{field.replace('_', ' ')}:</span>
							<span>{change.from}</span>
							<ArrowRight class="h-3 w-3" />
							<span>{change.to}</span>
						</div>
					{/each}
				</div>
			</div>
		{/if}
	</div>

	<!-- Footer -->
	<div class="border-t p-4 space-y-2">
		{#if !isCommitted}
			<Button class="w-full" variant="outline" onclick={handleCommit}>
				<CheckCircle2 class="mr-2 h-4 w-4" />
				Commit Deal (100%)
			</Button>
		{/if}

		<div class="flex gap-2">
			<Button
				variant="outline"
				class="flex-1"
				onclick={handleReset}
				disabled={!deal.has_changes}
			>
				<RotateCcw class="mr-1 h-4 w-4" />
				Reset
			</Button>
			<Button class="flex-1" onclick={handleApplyChanges}>
				Apply Changes
			</Button>
		</div>
	</div>
</div>

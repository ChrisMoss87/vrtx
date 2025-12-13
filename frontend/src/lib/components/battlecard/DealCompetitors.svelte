<script lang="ts">
	import { onMount } from 'svelte';
	import { Plus, Building2, Star, X } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import {
		getDealCompetitors,
		getCompetitors,
		addCompetitorToDeal,
		removeCompetitorFromDeal,
		type DealCompetitor,
		type Competitor
	} from '$lib/api/competitors';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import { goto } from '$app/navigation';

	let { dealId }: { dealId: number } = $props();

	let dealCompetitors = $state<DealCompetitor[]>([]);
	let allCompetitors = $state<Competitor[]>([]);
	let loading = $state(true);
	let adding = $state(false);
	let selectedCompetitorId = $state<string>('');

	onMount(async () => {
		await Promise.all([loadDealCompetitors(), loadAllCompetitors()]);
	});

	async function loadDealCompetitors() {
		loading = true;
		const { data, error } = await tryCatch(getDealCompetitors(dealId));
		loading = false;

		if (error) {
			toast.error('Failed to load competitors');
			return;
		}

		dealCompetitors = data ?? [];
	}

	async function loadAllCompetitors() {
		const { data } = await tryCatch(getCompetitors());
		allCompetitors = data ?? [];
	}

	async function handleAdd() {
		if (!selectedCompetitorId) return;

		adding = true;
		const { data, error } = await tryCatch(
			addCompetitorToDeal(dealId, parseInt(selectedCompetitorId))
		);
		adding = false;

		if (error) {
			toast.error('Failed to add competitor');
			return;
		}

		if (data) {
			dealCompetitors = [...dealCompetitors, data];
		}
		selectedCompetitorId = '';
		toast.success('Competitor added');
	}

	async function handleRemove(competitorId: number) {
		const { error } = await tryCatch(removeCompetitorFromDeal(dealId, competitorId));

		if (error) {
			toast.error('Failed to remove competitor');
			return;
		}

		dealCompetitors = dealCompetitors.filter((c) => c.competitor_id !== competitorId);
		toast.success('Competitor removed');
	}

	const availableCompetitors = $derived(
		allCompetitors.filter((c) => !dealCompetitors.some((dc) => dc.competitor_id === c.id))
	);

	const primaryCompetitor = $derived(dealCompetitors.find((c) => c.is_primary));
</script>

<div class="space-y-4">
	<h3 class="font-semibold">Competitors on this Deal</h3>

	{#if loading}
		<div class="flex items-center justify-center py-8">
			<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
		</div>
	{:else}
		<!-- Existing competitors -->
		{#if dealCompetitors.length > 0}
			<div class="flex flex-wrap gap-2">
				{#each dealCompetitors as dc (dc.id)}
					<div class="flex items-center gap-2 px-3 py-2 rounded-lg border bg-card group">
						{#if dc.competitor_logo}
							<img
								src={dc.competitor_logo}
								alt={dc.competitor_name}
								class="w-5 h-5 object-contain"
							/>
						{:else}
							<Building2 class="h-4 w-4 text-muted-foreground" />
						{/if}
						<button
							class="text-sm font-medium hover:text-primary"
							onclick={() => goto(`/competitors/${dc.competitor_id}`)}
						>
							{dc.competitor_name}
						</button>
						{#if dc.is_primary}
							<Star class="h-3 w-3 text-amber-500 fill-amber-500" />
						{/if}
						{#if dc.win_rate !== null}
							<span class="text-xs text-muted-foreground">
								{dc.win_rate}%
							</span>
						{/if}
						<Button
							variant="ghost"
							size="icon"
							class="h-5 w-5 opacity-0 group-hover:opacity-100"
							onclick={() => handleRemove(dc.competitor_id)}
						>
							<X class="h-3 w-3" />
						</Button>
					</div>
				{/each}
			</div>
		{:else}
			<p class="text-sm text-muted-foreground">No competitors added yet</p>
		{/if}

		<!-- Add competitor -->
		{#if availableCompetitors.length > 0}
			<div class="flex items-center gap-2">
				<div class="w-48">
					<Select.Root
						type="single"
						bind:value={selectedCompetitorId}
					>
						<Select.Trigger>
							{selectedCompetitorId
								? availableCompetitors.find((c) => c.id.toString() === selectedCompetitorId)?.name ?? 'Select competitor'
								: 'Add competitor...'}
						</Select.Trigger>
						<Select.Content>
							{#each availableCompetitors as competitor}
								<Select.Item value={competitor.id.toString()}>{competitor.name}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
				<Button onclick={handleAdd} disabled={!selectedCompetitorId || adding} size="sm">
					<Plus class="h-4 w-4 mr-1" />
					Add
				</Button>
			</div>
		{/if}

		<!-- Quick tip -->
		{#if primaryCompetitor}
			<div class="text-sm text-muted-foreground bg-muted/50 rounded p-3">
				<strong>Quick tip:</strong> Against {primaryCompetitor.competitor_name}, focus on your
				unique value proposition and pricing flexibility.
			</div>
		{/if}
	{/if}
</div>

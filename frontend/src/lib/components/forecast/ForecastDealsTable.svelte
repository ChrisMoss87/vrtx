<script lang="ts">
	import type { ForecastDeal, ForecastCategory } from '$lib/api/forecasts';
	import { formatCurrency, getCategoryLabel, getCategoryColor } from '$lib/api/forecasts';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import {
		Table,
		TableBody,
		TableCell,
		TableHead,
		TableHeader,
		TableRow
	} from '$lib/components/ui/table';
	import DealForecastEditor from './DealForecastEditor.svelte';
	import { cn } from '$lib/utils';
	import { Pencil, ExternalLink } from 'lucide-svelte';

	interface Props {
		deals: ForecastDeal[];
		category?: ForecastCategory | 'all';
		showCategory?: boolean;
		onDealUpdate?: (deal: ForecastDeal) => void;
	}

	let { deals, category = 'all', showCategory = true, onDealUpdate }: Props = $props();

	let editingDeal = $state<ForecastDeal | null>(null);
	let editorOpen = $state(false);

	const filteredDeals = $derived(
		category === 'all' ? deals : deals.filter((d) => d.forecast_category === category)
	);

	function openEditor(deal: ForecastDeal) {
		editingDeal = deal;
		editorOpen = true;
	}

	function handleUpdate(updatedDeal: ForecastDeal) {
		onDealUpdate?.(updatedDeal);
		editingDeal = null;
	}

	function getCategoryBadgeVariant(cat: ForecastCategory | null): 'default' | 'secondary' | 'destructive' | 'outline' {
		switch (cat) {
			case 'commit':
				return 'default';
			case 'best_case':
				return 'secondary';
			case 'omitted':
				return 'destructive';
			default:
				return 'outline';
		}
	}
</script>

<div class="rounded-md border">
	<Table>
		<TableHeader>
			<TableRow>
				<TableHead>Deal</TableHead>
				<TableHead class="text-right">Amount</TableHead>
				{#if showCategory}
					<TableHead>Category</TableHead>
				{/if}
				<TableHead>Stage</TableHead>
				<TableHead>Close Date</TableHead>
				<TableHead class="w-[100px]">Actions</TableHead>
			</TableRow>
		</TableHeader>
		<TableBody>
			{#each filteredDeals as deal (deal.id)}
				<TableRow>
					<TableCell>
						<div class="flex items-center gap-2">
							<a
								href="/records/{deal.id}"
								class="font-medium hover:underline"
							>
								{deal.name}
							</a>
						</div>
					</TableCell>
					<TableCell class="text-right font-mono">
						{#if deal.forecast_override}
							<span class="text-muted-foreground line-through mr-2">
								{formatCurrency(deal.amount)}
							</span>
							{formatCurrency(deal.forecast_override)}
						{:else}
							{formatCurrency(deal.amount)}
						{/if}
					</TableCell>
					{#if showCategory}
						<TableCell>
							<Badge variant={getCategoryBadgeVariant(deal.forecast_category)}>
								{getCategoryLabel(deal.forecast_category)}
							</Badge>
						</TableCell>
					{/if}
					<TableCell>
						{#if deal.stage_field_value}
							<div class="flex items-center gap-2">
								<span>{deal.stage_field_value}</span>
								<span class="text-xs text-muted-foreground">({deal.probability}%)</span>
							</div>
						{:else}
							<span class="text-muted-foreground">-</span>
						{/if}
					</TableCell>
					<TableCell>
						{#if deal.expected_close_date}
							{new Date(deal.expected_close_date).toLocaleDateString()}
						{:else}
							<span class="text-muted-foreground">-</span>
						{/if}
					</TableCell>
					<TableCell>
						<div class="flex items-center gap-1">
							<Button
								variant="ghost"
								size="icon"
								class="h-8 w-8"
								onclick={() => openEditor(deal)}
							>
								<Pencil class="h-4 w-4" />
								<span class="sr-only">Edit forecast</span>
							</Button>
							<Button
								variant="ghost"
								size="icon"
								class="h-8 w-8"
								href="/records/{deal.id}"
							>
								<ExternalLink class="h-4 w-4" />
								<span class="sr-only">View deal</span>
							</Button>
						</div>
					</TableCell>
				</TableRow>
			{:else}
				<TableRow>
					<TableCell colspan={showCategory ? 6 : 5} class="text-center py-8 text-muted-foreground">
						No deals found
					</TableCell>
				</TableRow>
			{/each}
		</TableBody>
	</Table>
</div>

{#if editingDeal}
	<DealForecastEditor
		deal={editingDeal}
		bind:open={editorOpen}
		onClose={() => (editingDeal = null)}
		onUpdate={handleUpdate}
	/>
{/if}

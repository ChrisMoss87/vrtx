<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { Plus, Trophy, Trash2, Play, Pause } from 'lucide-svelte';
	import type { VariantComparison } from '$lib/api/landing-pages';

	interface Props {
		variants: VariantComparison[];
		isAbTestingEnabled: boolean;
		onCreateVariant: () => void;
		onDeleteVariant: (id: number) => void;
		onToggleVariant: (id: number, isActive: boolean) => void;
		onDeclareWinner: (id: number) => void;
		onUpdateTraffic: (id: number, percentage: number) => void;
	}

	let {
		variants,
		isAbTestingEnabled,
		onCreateVariant,
		onDeleteVariant,
		onToggleVariant,
		onDeclareWinner,
		onUpdateTraffic
	}: Props = $props();

	const totalTraffic = $derived(variants.reduce((sum, v) => sum + v.traffic_percentage, 0));

	function formatPercentage(value: number): string {
		return (value * 100).toFixed(1) + '%';
	}
</script>

<Card.Root>
	<Card.Header>
		<div class="flex items-center justify-between">
			<div>
				<Card.Title>A/B Testing</Card.Title>
				<Card.Description>
					{#if isAbTestingEnabled}
						Testing is active with {variants.length} variant{variants.length !== 1 ? 's' : ''}
					{:else}
						Create variants to test different versions of your page
					{/if}
				</Card.Description>
			</div>
			<Button onclick={onCreateVariant}>
				<Plus class="mr-1 h-4 w-4" />
				Add Variant
			</Button>
		</div>
	</Card.Header>
	<Card.Content>
		{#if variants.length === 0}
			<div class="py-8 text-center">
				<p class="text-muted-foreground mb-4">No variants yet</p>
				<Button variant="outline" onclick={onCreateVariant}>
					<Plus class="mr-1 h-4 w-4" />
					Create First Variant
				</Button>
			</div>
		{:else}
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Variant</Table.Head>
						<Table.Head class="text-center">Traffic</Table.Head>
						<Table.Head class="text-center">Views</Table.Head>
						<Table.Head class="text-center">Conversions</Table.Head>
						<Table.Head class="text-center">Conv. Rate</Table.Head>
						<Table.Head class="text-center">Status</Table.Head>
						<Table.Head class="text-right">Actions</Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each variants as variant}
						<Table.Row>
							<Table.Cell>
								<div class="flex items-center gap-2">
									<span class="bg-primary/10 text-primary rounded px-2 py-1 font-mono text-sm font-bold">
										{variant.variant_code}
									</span>
									<span class="font-medium">{variant.name}</span>
									{#if variant.is_winner}
										<Badge variant="default" class="gap-1 bg-amber-500">
											<Trophy class="h-3 w-3" />
											Winner
										</Badge>
									{/if}
								</div>
							</Table.Cell>
							<Table.Cell class="text-center">
								<Input
									type="number"
									min="1"
									max="100"
									value={variant.traffic_percentage}
									class="mx-auto w-20 text-center"
									onchange={(e) =>
										onUpdateTraffic(variant.id, parseInt(e.currentTarget.value) || 50)}
								/>
							</Table.Cell>
							<Table.Cell class="text-center font-medium">
								{variant.views.toLocaleString()}
							</Table.Cell>
							<Table.Cell class="text-center font-medium">
								{variant.conversions.toLocaleString()}
							</Table.Cell>
							<Table.Cell class="text-center">
								<span
									class={variant.conversion_rate > 0.05 ? 'text-green-600' : 'text-muted-foreground'}
								>
									{formatPercentage(variant.conversion_rate)}
								</span>
							</Table.Cell>
							<Table.Cell class="text-center">
								{#if variant.is_active}
									<Badge variant="default">Active</Badge>
								{:else}
									<Badge variant="secondary">Paused</Badge>
								{/if}
							</Table.Cell>
							<Table.Cell class="text-right">
								<div class="flex items-center justify-end gap-1">
									<Button
										variant="ghost"
										size="sm"
										onclick={() => onToggleVariant(variant.id, !variant.is_active)}
										title={variant.is_active ? 'Pause variant' : 'Resume variant'}
									>
										{#if variant.is_active}
											<Pause class="h-4 w-4" />
										{:else}
											<Play class="h-4 w-4" />
										{/if}
									</Button>
									{#if !variant.is_winner}
										<Button
											variant="ghost"
											size="sm"
											onclick={() => onDeclareWinner(variant.id)}
											title="Declare winner"
										>
											<Trophy class="h-4 w-4" />
										</Button>
										<Button
											variant="ghost"
											size="sm"
											onclick={() => onDeleteVariant(variant.id)}
											title="Delete variant"
										>
											<Trash2 class="h-4 w-4 text-red-500" />
										</Button>
									{/if}
								</div>
							</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>

			{#if totalTraffic !== 100}
				<div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
					Traffic split totals {totalTraffic}%. Adjust percentages to total 100% for accurate
					testing.
				</div>
			{/if}
		{/if}
	</Card.Content>
</Card.Root>

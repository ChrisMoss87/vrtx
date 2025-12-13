<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Progress } from '$lib/components/ui/progress';
	import * as Table from '$lib/components/ui/table';
	import {
		Trophy,
		TrendingUp,
		TrendingDown,
		Minus,
		AlertTriangle,
		CheckCircle2,
		Users,
		MousePointerClick,
		Mail,
		Target
	} from 'lucide-svelte';
	import type { TestStatistics, VariantStatistics, AbTestGoal } from '$lib/api/ab-tests';

	interface Props {
		statistics: TestStatistics;
		goal: AbTestGoal;
		minSampleSize: number;
		confidenceLevel: number;
		onDeclareWinner?: (variantId: number) => void;
	}

	let { statistics, goal, minSampleSize, confidenceLevel, onDeclareWinner }: Props = $props();

	const controlVariant = $derived(statistics.variants.find((v) => v.is_control));
	const totalImpressions = $derived(
		statistics.variants.reduce((sum, v) => sum + v.impressions, 0)
	);

	function getMetricValue(variant: VariantStatistics): number {
		switch (goal) {
			case 'conversion':
				return variant.conversion_rate;
			case 'click_rate':
				return variant.click_rate;
			case 'open_rate':
				return variant.open_rate;
			default:
				return variant.conversion_rate;
		}
	}

	function getMetricLabel(): string {
		switch (goal) {
			case 'conversion':
				return 'Conversion Rate';
			case 'click_rate':
				return 'Click Rate';
			case 'open_rate':
				return 'Open Rate';
			default:
				return 'Rate';
		}
	}

	function getImprovement(variant: VariantStatistics): number {
		if (!controlVariant || variant.is_control) return 0;
		const controlRate = getMetricValue(controlVariant);
		if (controlRate === 0) return 0;
		return ((getMetricValue(variant) - controlRate) / controlRate) * 100;
	}

	function formatPercent(value: number): string {
		return value.toFixed(2) + '%';
	}

	function formatNumber(value: number): string {
		return value.toLocaleString();
	}

	const sampleProgress = $derived(
		Math.min(100, (totalImpressions / (minSampleSize * statistics.variants.length)) * 100)
	);
</script>

<div class="space-y-6">
	<!-- Summary Cards -->
	<div class="grid gap-4 md:grid-cols-4">
		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-muted-foreground">Total Impressions</p>
						<p class="text-2xl font-bold">{formatNumber(totalImpressions)}</p>
					</div>
					<Users class="h-8 w-8 text-muted-foreground" />
				</div>
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-muted-foreground">Total Conversions</p>
						<p class="text-2xl font-bold">
							{formatNumber(statistics.variants.reduce((sum, v) => sum + v.conversions, 0))}
						</p>
					</div>
					<Target class="h-8 w-8 text-muted-foreground" />
				</div>
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-muted-foreground">Sample Progress</p>
						<p class="text-2xl font-bold">{formatPercent(sampleProgress)}</p>
					</div>
					{#if sampleProgress >= 100}
						<CheckCircle2 class="h-8 w-8 text-green-500" />
					{:else}
						<AlertTriangle class="h-8 w-8 text-yellow-500" />
					{/if}
				</div>
				<Progress value={sampleProgress} class="mt-2" />
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-muted-foreground">Statistical Significance</p>
						<p class="text-2xl font-bold">
							{statistics.significance.is_significant ? 'Yes' : 'No'}
						</p>
					</div>
					{#if statistics.significance.is_significant}
						<CheckCircle2 class="h-8 w-8 text-green-500" />
					{:else}
						<Minus class="h-8 w-8 text-muted-foreground" />
					{/if}
				</div>
			</Card.Content>
		</Card.Root>
	</div>

	<!-- Significance Alert -->
	{#if statistics.significance.is_significant && statistics.recommended_winner}
		{@const winner = statistics.variants.find((v) => v.id === statistics.recommended_winner)}
		<Card.Root class="border-green-200 bg-green-50">
			<Card.Content class="pt-6">
				<div class="flex items-start gap-4">
					<div class="rounded-full bg-green-100 p-2">
						<Trophy class="h-6 w-6 text-green-600" />
					</div>
					<div class="flex-1">
						<h3 class="font-semibold text-green-900">
							Statistical Significance Reached!
						</h3>
						<p class="text-green-800">
							<strong>{winner?.name}</strong> is outperforming the control by{' '}
							<strong>{formatPercent(statistics.significance.improvement)}</strong>
							with {confidenceLevel}% confidence.
						</p>
						{#if onDeclareWinner && !statistics.has_winner}
							<Button
								class="mt-3"
								onclick={() => onDeclareWinner?.(statistics.recommended_winner!)}
							>
								<Trophy class="mr-2 h-4 w-4" />
								Declare Winner
							</Button>
						{/if}
					</div>
				</div>
			</Card.Content>
		</Card.Root>
	{:else if !statistics.significance.is_significant && sampleProgress >= 100}
		<Card.Root class="border-yellow-200 bg-yellow-50">
			<Card.Content class="pt-6">
				<div class="flex items-start gap-4">
					<div class="rounded-full bg-yellow-100 p-2">
						<AlertTriangle class="h-6 w-6 text-yellow-600" />
					</div>
					<div>
						<h3 class="font-semibold text-yellow-900">No Clear Winner Yet</h3>
						<p class="text-yellow-800">
							Despite reaching the minimum sample size, results are not statistically
							significant. Consider running the test longer or checking for external factors.
						</p>
					</div>
				</div>
			</Card.Content>
		</Card.Root>
	{/if}

	<!-- Variant Comparison Table -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Variant Performance</Card.Title>
			<Card.Description>
				Comparing variants by {getMetricLabel().toLowerCase()}
			</Card.Description>
		</Card.Header>
		<Card.Content>
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Variant</Table.Head>
						<Table.Head class="text-right">Impressions</Table.Head>
						<Table.Head class="text-right">Conversions</Table.Head>
						<Table.Head class="text-right">Clicks</Table.Head>
						<Table.Head class="text-right">{getMetricLabel()}</Table.Head>
						<Table.Head class="text-right">vs Control</Table.Head>
						<Table.Head></Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each statistics.variants as variant}
						{@const improvement = getImprovement(variant)}
						<Table.Row class={variant.is_winner ? 'bg-green-50' : ''}>
							<Table.Cell>
								<div class="flex items-center gap-2">
									<span class="font-medium">{variant.name}</span>
									{#if variant.is_control}
										<Badge variant="secondary">Control</Badge>
									{/if}
									{#if variant.is_winner}
										<Badge class="bg-green-100 text-green-800">
											<Trophy class="mr-1 h-3 w-3" />
											Winner
										</Badge>
									{/if}
								</div>
								<span class="text-xs text-muted-foreground">
									Code: {variant.variant_code} | Traffic: {variant.traffic_percentage}%
								</span>
							</Table.Cell>
							<Table.Cell class="text-right font-mono">
								{formatNumber(variant.impressions)}
							</Table.Cell>
							<Table.Cell class="text-right font-mono">
								{formatNumber(variant.conversions)}
							</Table.Cell>
							<Table.Cell class="text-right font-mono">
								{formatNumber(variant.clicks)}
							</Table.Cell>
							<Table.Cell class="text-right font-mono font-medium">
								{formatPercent(getMetricValue(variant))}
							</Table.Cell>
							<Table.Cell class="text-right">
								{#if variant.is_control}
									<span class="text-muted-foreground">Baseline</span>
								{:else}
									<div class="flex items-center justify-end gap-1">
										{#if improvement > 0}
											<TrendingUp class="h-4 w-4 text-green-500" />
											<span class="text-green-600">+{formatPercent(improvement)}</span>
										{:else if improvement < 0}
											<TrendingDown class="h-4 w-4 text-red-500" />
											<span class="text-red-600">{formatPercent(improvement)}</span>
										{:else}
											<Minus class="h-4 w-4 text-muted-foreground" />
											<span class="text-muted-foreground">0%</span>
										{/if}
									</div>
								{/if}
							</Table.Cell>
							<Table.Cell>
								{#if onDeclareWinner && !statistics.has_winner && !variant.is_control}
									<Button
										variant="ghost"
										size="sm"
										onclick={() => onDeclareWinner?.(variant.id)}
									>
										<Trophy class="h-4 w-4" />
									</Button>
								{/if}
							</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
		</Card.Content>
	</Card.Root>

	<!-- Additional Metrics -->
	<div class="grid gap-4 md:grid-cols-2">
		<Card.Root>
			<Card.Header>
				<Card.Title class="text-base">Click-Through Rate</Card.Title>
			</Card.Header>
			<Card.Content>
				<div class="space-y-3">
					{#each statistics.variants as variant}
						<div>
							<div class="flex justify-between text-sm">
								<span>{variant.name}</span>
								<span class="font-mono">{formatPercent(variant.click_rate)}</span>
							</div>
							<Progress value={variant.click_rate} max={100} class="h-2" />
						</div>
					{/each}
				</div>
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Header>
				<Card.Title class="text-base">Open Rate</Card.Title>
			</Card.Header>
			<Card.Content>
				<div class="space-y-3">
					{#each statistics.variants as variant}
						<div>
							<div class="flex justify-between text-sm">
								<span>{variant.name}</span>
								<span class="font-mono">{formatPercent(variant.open_rate)}</span>
							</div>
							<Progress value={variant.open_rate} max={100} class="h-2" />
						</div>
					{/each}
				</div>
			</Card.Content>
		</Card.Root>
	</div>
</div>

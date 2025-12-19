<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Progress } from '$lib/components/ui/progress';
	import * as Table from '$lib/components/ui/table';
	import {
		Users,
		TrendingUp,
		Building2,
		MapPin,
		Zap,
		Download,
		ExternalLink
	} from 'lucide-svelte';
	import type { LookalikeMatch, LookalikeAudience, ExportDestination } from '$lib/api/lookalike';
	import { getScoreLabel, getCriteriaLabel } from '$lib/api/lookalike';

	interface Props {
		audience: LookalikeAudience;
		matches: LookalikeMatch[];
		onExport?: (destination: ExportDestination) => void;
		onViewContact?: (contactId: number) => void;
	}

	let { audience, matches, onExport, onViewContact }: Props = $props();

	const topMatches = $derived(matches.slice(0, 10));

	const scoreDistribution = $derived.by(() => {
		const ranges = { excellent: 0, veryGood: 0, good: 0, fair: 0, low: 0 };
		matches.forEach((m) => {
			if (m.similarity_score >= 90) ranges.excellent++;
			else if (m.similarity_score >= 80) ranges.veryGood++;
			else if (m.similarity_score >= 70) ranges.good++;
			else if (m.similarity_score >= 60) ranges.fair++;
			else ranges.low++;
		});
		return ranges;
	});

	const avgScore = $derived(
		matches.length > 0
			? matches.reduce((sum, m) => sum + m.similarity_score, 0) / matches.length
			: 0
	);

	const exportDestinations: { value: ExportDestination; label: string; icon: typeof Download }[] = [
		{ value: 'google_ads', label: 'Google Ads', icon: Download },
		{ value: 'facebook', label: 'Facebook Ads', icon: Download },
		{ value: 'linkedin', label: 'LinkedIn Ads', icon: Download },
		{ value: 'csv', label: 'CSV Download', icon: Download }
	];
</script>

<div class="space-y-6">
	<!-- Summary Stats -->
	<div class="grid gap-4 md:grid-cols-4">
		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-muted-foreground">Total Matches</p>
						<p class="text-2xl font-bold">{matches.length.toLocaleString()}</p>
					</div>
					<Users class="h-8 w-8 text-muted-foreground" />
				</div>
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-muted-foreground">Average Score</p>
						<p class="text-2xl font-bold">{avgScore.toFixed(1)}%</p>
					</div>
					<TrendingUp class="h-8 w-8 text-muted-foreground" />
				</div>
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-muted-foreground">Source Records</p>
						<p class="text-2xl font-bold">{audience.source_count.toLocaleString()}</p>
					</div>
					<Building2 class="h-8 w-8 text-muted-foreground" />
				</div>
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-muted-foreground">Expansion Rate</p>
						<p class="text-2xl font-bold">
							{audience.source_count > 0
								? ((matches.length / audience.source_count) * 100).toFixed(0)
								: 0}%
						</p>
					</div>
					<Zap class="h-8 w-8 text-muted-foreground" />
				</div>
			</Card.Content>
		</Card.Root>
	</div>

	<!-- Score Distribution -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Score Distribution</Card.Title>
			<Card.Description>Breakdown of match quality</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-3">
			<div class="space-y-2">
				<div class="flex justify-between text-sm">
					<span class="flex items-center gap-2">
						<Badge class="bg-green-100 text-green-800">Excellent (90%+)</Badge>
					</span>
					<span class="font-mono">{scoreDistribution.excellent.toLocaleString()}</span>
				</div>
				<Progress value={(scoreDistribution.excellent / Math.max(1, matches.length)) * 100} class="h-2" />
			</div>

			<div class="space-y-2">
				<div class="flex justify-between text-sm">
					<span class="flex items-center gap-2">
						<Badge class="bg-emerald-100 text-emerald-800">Very Good (80-89%)</Badge>
					</span>
					<span class="font-mono">{scoreDistribution.veryGood.toLocaleString()}</span>
				</div>
				<Progress value={(scoreDistribution.veryGood / Math.max(1, matches.length)) * 100} class="h-2" />
			</div>

			<div class="space-y-2">
				<div class="flex justify-between text-sm">
					<span class="flex items-center gap-2">
						<Badge class="bg-blue-100 text-blue-800">Good (70-79%)</Badge>
					</span>
					<span class="font-mono">{scoreDistribution.good.toLocaleString()}</span>
				</div>
				<Progress value={(scoreDistribution.good / Math.max(1, matches.length)) * 100} class="h-2" />
			</div>

			<div class="space-y-2">
				<div class="flex justify-between text-sm">
					<span class="flex items-center gap-2">
						<Badge class="bg-yellow-100 text-yellow-800">Fair (60-69%)</Badge>
					</span>
					<span class="font-mono">{scoreDistribution.fair.toLocaleString()}</span>
				</div>
				<Progress value={(scoreDistribution.fair / Math.max(1, matches.length)) * 100} class="h-2" />
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Top Matches -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Top Matches</Card.Title>
			<Card.Description>Highest scoring lookalike contacts</Card.Description>
		</Card.Header>
		<Card.Content>
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Contact ID</Table.Head>
						<Table.Head class="text-right">Similarity</Table.Head>
						<Table.Head>Top Factors</Table.Head>
						<Table.Head>Status</Table.Head>
						<Table.Head></Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each topMatches as match}
						{@const scoreInfo = getScoreLabel(match.similarity_score)}
						<Table.Row>
							<Table.Cell class="font-mono">#{match.contact_id}</Table.Cell>
							<Table.Cell class="text-right">
								<Badge class={scoreInfo.class}>{match.similarity_score.toFixed(1)}%</Badge>
							</Table.Cell>
							<Table.Cell>
								<div class="flex flex-wrap gap-1">
									{#each Object.entries(match.match_factors).slice(0, 3) as [factor, score]}
										<Badge variant="outline" class="text-xs">
											{getCriteriaLabel(factor as any)}: {(score as number).toFixed(0)}%
										</Badge>
									{/each}
								</div>
							</Table.Cell>
							<Table.Cell>
								{#if match.exported}
									<Badge variant="secondary">Exported</Badge>
								{:else}
									<Badge variant="outline">New</Badge>
								{/if}
							</Table.Cell>
							<Table.Cell>
								{#if onViewContact}
									<Button variant="ghost" size="icon" onclick={() => onViewContact?.(match.contact_id)}>
										<ExternalLink class="h-4 w-4" />
									</Button>
								{/if}
							</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
		</Card.Content>
	</Card.Root>

	<!-- Export Options -->
	{#if onExport && matches.length > 0}
		<Card.Root>
			<Card.Header>
				<Card.Title class="flex items-center gap-2">
					<Download class="h-5 w-5" />
					Export Audience
				</Card.Title>
				<Card.Description>Export matches to ad platforms or download as CSV</Card.Description>
			</Card.Header>
			<Card.Content>
				<div class="flex flex-wrap gap-3">
					{#each exportDestinations as dest}
						<Button variant="outline" onclick={() => onExport?.(dest.value)}>
							<dest.icon class="mr-2 h-4 w-4" />
							{dest.label}
						</Button>
					{/each}
				</div>
			</Card.Content>
		</Card.Root>
	{/if}
</div>

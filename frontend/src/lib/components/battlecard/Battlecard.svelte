<script lang="ts">
	import { onMount } from 'svelte';
	import {
		TrendingUp, TrendingDown, Shield, Target, AlertTriangle,
		MessageSquare, ExternalLink, Edit, Building2
	} from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { getBattlecard, type Battlecard as BattlecardType } from '$lib/api/competitors';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import ObjectionHandler from './ObjectionHandler.svelte';
	import CompetitorNotes from './CompetitorNotes.svelte';

	interface Props {
		competitorId: number;
		compact?: boolean;
		onEdit?: () => void;
	}

	let {
		competitorId,
		compact = false,
		onEdit = undefined,
	}: Props = $props();

	let battlecard = $state<BattlecardType | null>(null);
	let loading = $state(true);

	onMount(async () => {
		await loadBattlecard();
	});

	async function loadBattlecard() {
		loading = true;
		const { data, error } = await tryCatch(getBattlecard(competitorId));
		loading = false;

		if (error) {
			toast.error('Failed to load battlecard');
			return;
		}

		battlecard = data;
	}

	function getSectionIcon(type: string) {
		switch (type) {
			case 'strengths': return AlertTriangle;
			case 'weaknesses': return Target;
			case 'our_advantages': return Shield;
			default: return MessageSquare;
		}
	}

	function getSectionColor(type: string): string {
		switch (type) {
			case 'strengths': return 'text-amber-600 dark:text-amber-400';
			case 'weaknesses': return 'text-red-600 dark:text-red-400';
			case 'our_advantages': return 'text-green-600 dark:text-green-400';
			default: return 'text-muted-foreground';
		}
	}

	function getWinRateColor(rate: number | null): string {
		if (rate === null) return 'text-muted-foreground';
		if (rate >= 60) return 'text-green-600 dark:text-green-400';
		if (rate >= 40) return 'text-amber-600 dark:text-amber-400';
		return 'text-red-600 dark:text-red-400';
	}
</script>

{#if loading}
	<div class="flex items-center justify-center py-12">
		<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
	</div>
{:else if battlecard}
	<div class="space-y-6">
		<!-- Header -->
		<div class="flex items-start justify-between">
			<div class="flex items-center gap-4">
				<div class="w-16 h-16 rounded-lg bg-muted flex items-center justify-center">
					{#if battlecard.logo_url}
						<img
							src={battlecard.logo_url}
							alt={battlecard.name}
							class="w-12 h-12 object-contain"
						/>
					{:else}
						<Building2 class="h-8 w-8 text-muted-foreground" />
					{/if}
				</div>
				<div>
					<h2 class="text-xl font-bold">{battlecard.name}</h2>
					{#if battlecard.website}
						<a
							href={battlecard.website}
							target="_blank"
							rel="noopener noreferrer"
							class="text-sm text-muted-foreground hover:text-primary flex items-center gap-1"
						>
							{battlecard.website}
							<ExternalLink class="h-3 w-3" />
						</a>
					{/if}
				</div>
			</div>
			{#if onEdit}
				<Button variant="outline" size="sm" onclick={onEdit}>
					<Edit class="h-4 w-4 mr-2" />
					Edit
				</Button>
			{/if}
		</div>

		<!-- Win Rate Summary -->
		<div class="rounded-lg border p-4 bg-card">
			<div class="flex items-center justify-between">
				<div>
					<span class="text-sm text-muted-foreground">Your Win Rate</span>
					<div class="flex items-center gap-2 mt-1">
						<span class="text-3xl font-bold {getWinRateColor(battlecard.win_rate)}">
							{battlecard.win_rate !== null ? `${battlecard.win_rate}%` : '-'}
						</span>
						{#if battlecard.win_rate !== null}
							{#if battlecard.win_rate >= 50}
								<TrendingUp class="h-5 w-5 text-green-500" />
							{:else}
								<TrendingDown class="h-5 w-5 text-red-500" />
							{/if}
						{/if}
					</div>
				</div>
				<div class="text-right text-sm">
					<div class="text-muted-foreground">{battlecard.total_deals} competitive deals</div>
					<div>
						<span class="text-green-600 dark:text-green-400">{battlecard.won_deals} won</span>
						<span class="text-muted-foreground mx-1">/</span>
						<span class="text-red-600 dark:text-red-400">{battlecard.lost_deals} lost</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Sections -->
		{#if !compact}
			<div class="grid gap-4 md:grid-cols-2">
				{#each battlecard.sections as section (section.id)}
					{#if section.content || (section.content_lines && section.content_lines.length > 0)}
						<div class="rounded-lg border p-4">
							<h3 class="font-semibold flex items-center gap-2 mb-3 {getSectionColor(section.type)}">
								<svelte:component this={getSectionIcon(section.type)} class="h-4 w-4" />
								{section.type_label}
							</h3>
							<ul class="space-y-2">
								{#each section.content_lines || section.content.split('\n').filter(Boolean) as line}
									<li class="text-sm flex items-start gap-2">
										<span class="text-muted-foreground">•</span>
										<span>{line}</span>
									</li>
								{/each}
							</ul>
						</div>
					{/if}
				{/each}
			</div>
		{/if}

		<!-- Objection Handlers -->
		{#if battlecard.objections.length > 0}
			<div>
				<h3 class="font-semibold mb-3 flex items-center gap-2">
					<Target class="h-4 w-4" />
					Counter-Objections
				</h3>
				<div class="space-y-3">
					{#each battlecard.objections as objection (objection.id)}
						<ObjectionHandler {objection} {competitorId} />
					{/each}
				</div>
			</div>
		{/if}

		<!-- Recent Notes -->
		{#if !compact && battlecard.recent_notes.length > 0}
			<div>
				<h3 class="font-semibold mb-3 flex items-center gap-2">
					<MessageSquare class="h-4 w-4" />
					Latest Intel
				</h3>
				<CompetitorNotes notes={battlecard.recent_notes} compact />
			</div>
		{/if}
	</div>
{/if}

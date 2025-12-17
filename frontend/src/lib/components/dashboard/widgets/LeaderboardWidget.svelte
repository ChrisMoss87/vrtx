<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Trophy, TrendingUp, TrendingDown, Minus } from 'lucide-svelte';
	import { Avatar, AvatarFallback, AvatarImage } from '$lib/components/ui/avatar';

	interface LeaderboardItem {
		id: number;
		name: string;
		value: number;
		rank: number;
		previous_rank?: number;
		avatar_url?: string;
		subtitle?: string;
	}

	interface Props {
		title: string;
		data: {
			items: LeaderboardItem[];
			label?: string;
			value_label?: string;
		} | null;
		loading?: boolean;
	}

	let { title, data, loading = false }: Props = $props();

	function formatValue(value: number): string {
		if (value >= 1000000) {
			return (value / 1000000).toFixed(1) + 'M';
		} else if (value >= 1000) {
			return (value / 1000).toFixed(1) + 'K';
		}
		return value.toLocaleString();
	}

	function getRankChange(item: LeaderboardItem): 'up' | 'down' | 'same' | null {
		if (item.previous_rank === undefined) return null;
		if (item.rank < item.previous_rank) return 'up';
		if (item.rank > item.previous_rank) return 'down';
		return 'same';
	}

	function getRankBadgeColor(rank: number): string {
		switch (rank) {
			case 1:
				return 'bg-yellow-500 text-yellow-950';
			case 2:
				return 'bg-gray-400 text-gray-950';
			case 3:
				return 'bg-amber-700 text-amber-50';
			default:
				return 'bg-muted text-muted-foreground';
		}
	}

	function getInitials(name: string): string {
		return name
			.split(' ')
			.map((n) => n[0])
			.join('')
			.toUpperCase()
			.slice(0, 2);
	}
</script>

<Card.Root class="flex h-full flex-col">
	<Card.Header class="pb-2">
		<div class="flex items-center gap-2">
			<Trophy class="h-4 w-4 text-muted-foreground" />
			<Card.Title class="text-sm font-medium">{title}</Card.Title>
		</div>
		{#if data?.value_label}
			<p class="text-xs text-muted-foreground">{data.value_label}</p>
		{/if}
	</Card.Header>
	<Card.Content class="flex-1 overflow-auto">
		{#if loading}
			<div class="space-y-3">
				{#each [1, 2, 3, 4, 5] as _}
					<div class="flex animate-pulse items-center gap-3">
						<div class="h-8 w-8 rounded-full bg-muted"></div>
						<div class="flex-1">
							<div class="h-4 w-24 rounded bg-muted"></div>
							<div class="mt-1 h-3 w-16 rounded bg-muted"></div>
						</div>
						<div class="h-4 w-12 rounded bg-muted"></div>
					</div>
				{/each}
			</div>
		{:else if data?.items && data.items.length > 0}
			<div class="space-y-2">
				{#each data.items as item (item.id)}
					{@const rankChange = getRankChange(item)}
					<div
						class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-muted/50"
					>
						<!-- Rank badge -->
						<div
							class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold {getRankBadgeColor(
								item.rank
							)}"
						>
							{item.rank}
						</div>

						<!-- Avatar -->
						<Avatar class="h-8 w-8">
							{#if item.avatar_url}
								<AvatarImage src={item.avatar_url} alt={item.name} />
							{/if}
							<AvatarFallback class="text-xs">{getInitials(item.name)}</AvatarFallback>
						</Avatar>

						<!-- Name & subtitle -->
						<div class="min-w-0 flex-1">
							<div class="truncate text-sm font-medium">{item.name}</div>
							{#if item.subtitle}
								<div class="truncate text-xs text-muted-foreground">{item.subtitle}</div>
							{/if}
						</div>

						<!-- Value & change indicator -->
						<div class="flex items-center gap-2">
							<span class="text-sm font-semibold">{formatValue(item.value)}</span>
							{#if rankChange === 'up'}
								<TrendingUp class="h-4 w-4 text-green-500" />
							{:else if rankChange === 'down'}
								<TrendingDown class="h-4 w-4 text-red-500" />
							{:else if rankChange === 'same'}
								<Minus class="h-4 w-4 text-muted-foreground" />
							{/if}
						</div>
					</div>
				{/each}
			</div>
		{:else}
			<div class="py-8 text-center text-sm text-muted-foreground">No data available</div>
		{/if}
	</Card.Content>
</Card.Root>

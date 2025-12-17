<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Target, TrendingUp, TrendingDown } from 'lucide-svelte';

	interface Props {
		title: string;
		data: {
			value: number;
			target: number;
			label?: string;
			change_percent?: number | null;
			change_type?: 'increase' | 'decrease' | 'no_change' | null;
		} | null;
		loading?: boolean;
	}

	let { title, data, loading = false }: Props = $props();

	const progress = $derived(() => {
		if (!data || data.target === 0) return 0;
		return Math.min(100, Math.round((data.value / data.target) * 100));
	});

	const progressColor = $derived(() => {
		const pct = progress();
		if (pct >= 100) return 'bg-green-500';
		if (pct >= 75) return 'bg-yellow-500';
		if (pct >= 50) return 'bg-orange-500';
		return 'bg-red-500';
	});

	function formatValue(value: number): string {
		if (value >= 1000000) {
			return (value / 1000000).toFixed(1) + 'M';
		} else if (value >= 1000) {
			return (value / 1000).toFixed(1) + 'K';
		}
		return value.toLocaleString();
	}
</script>

<Card.Root class="h-full">
	<Card.Header class="pb-2">
		<div class="flex items-center gap-2">
			<Target class="h-4 w-4 text-muted-foreground" />
			<Card.Title class="text-sm font-medium">{title}</Card.Title>
		</div>
	</Card.Header>
	<Card.Content>
		{#if loading}
			<div class="flex animate-pulse flex-col items-center py-4">
				<div class="h-10 w-24 rounded bg-muted"></div>
				<div class="mt-2 h-4 w-16 rounded bg-muted"></div>
			</div>
		{:else if data}
			<div class="space-y-3">
				<div class="flex items-baseline justify-between">
					<span class="text-3xl font-bold">{formatValue(data.value)}</span>
					<span class="text-sm text-muted-foreground">/ {formatValue(data.target)}</span>
				</div>

				<!-- Progress bar -->
				<div class="space-y-1">
					<div class="h-2 w-full overflow-hidden rounded-full bg-muted">
						<div class="h-full transition-all {progressColor()}" style="width: {progress()}%"></div>
					</div>
					<div class="flex items-center justify-between text-xs">
						<span class="text-muted-foreground">{progress()}% of goal</span>
						{#if data.change_percent !== null && data.change_percent !== undefined}
							<span
								class="flex items-center gap-1 {data.change_type === 'increase'
									? 'text-green-600'
									: data.change_type === 'decrease'
										? 'text-red-600'
										: 'text-muted-foreground'}"
							>
								{#if data.change_type === 'increase'}
									<TrendingUp class="h-3 w-3" />
								{:else if data.change_type === 'decrease'}
									<TrendingDown class="h-3 w-3" />
								{/if}
								{data.change_percent >= 0 ? '+' : ''}{data.change_percent.toFixed(1)}%
							</span>
						{/if}
					</div>
				</div>
			</div>
		{:else}
			<div class="py-4 text-center text-sm text-muted-foreground">No data available</div>
		{/if}
	</Card.Content>
</Card.Root>

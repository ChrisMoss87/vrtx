<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { TrendingUp, Flag } from 'lucide-svelte';

	interface Props {
		title: string;
		data: {
			current: number;
			goal: number;
			label?: string;
			unit?: string;
			color?: 'default' | 'success' | 'warning' | 'danger';
		} | null;
		loading?: boolean;
	}

	let { title, data, loading = false }: Props = $props();

	const progress = $derived(() => {
		if (!data || data.goal === 0) return 0;
		return Math.min(100, Math.round((data.current / data.goal) * 100));
	});

	const progressColor = $derived(() => {
		if (data?.color) {
			switch (data.color) {
				case 'success':
					return 'bg-green-500';
				case 'warning':
					return 'bg-yellow-500';
				case 'danger':
					return 'bg-red-500';
				default:
					return 'bg-primary';
			}
		}

		// Auto color based on progress
		const pct = progress();
		if (pct >= 100) return 'bg-green-500';
		if (pct >= 75) return 'bg-blue-500';
		if (pct >= 50) return 'bg-yellow-500';
		return 'bg-red-500';
	});

	const trackColor = $derived(() => {
		if (data?.color) {
			switch (data.color) {
				case 'success':
					return 'bg-green-100 dark:bg-green-950';
				case 'warning':
					return 'bg-yellow-100 dark:bg-yellow-950';
				case 'danger':
					return 'bg-red-100 dark:bg-red-950';
				default:
					return 'bg-primary/10';
			}
		}
		return 'bg-muted';
	});

	function formatValue(value: number, unit?: string): string {
		let formatted: string;
		if (value >= 1000000) {
			formatted = (value / 1000000).toFixed(1) + 'M';
		} else if (value >= 1000) {
			formatted = (value / 1000).toFixed(1) + 'K';
		} else {
			formatted = value.toLocaleString();
		}
		return unit ? `${formatted} ${unit}` : formatted;
	}
</script>

<Card.Root class="h-full">
	<Card.Header class="pb-2">
		<div class="flex items-center gap-2">
			<TrendingUp class="h-4 w-4 text-muted-foreground" />
			<Card.Title class="text-sm font-medium">{title}</Card.Title>
		</div>
	</Card.Header>
	<Card.Content>
		{#if loading}
			<div class="space-y-3">
				<div class="h-4 w-full animate-pulse rounded-full bg-muted"></div>
				<div class="flex justify-between">
					<div class="h-4 w-16 animate-pulse rounded bg-muted"></div>
					<div class="h-4 w-16 animate-pulse rounded bg-muted"></div>
				</div>
			</div>
		{:else if data}
			<div class="space-y-3">
				<!-- Progress bar -->
				<div class="relative">
					<div class="h-4 w-full overflow-hidden rounded-full {trackColor()}">
						<div
							class="h-full rounded-full transition-all duration-500 {progressColor()}"
							style="width: {progress()}%"
						></div>
					</div>

					<!-- Goal marker -->
					<div
						class="absolute top-1/2 -translate-y-1/2"
						style="left: 100%"
					>
						<Flag class="h-3 w-3 -translate-x-1/2 text-muted-foreground" />
					</div>
				</div>

				<!-- Values -->
				<div class="flex items-end justify-between">
					<div>
						<div class="text-2xl font-bold">{formatValue(data.current, data.unit)}</div>
						{#if data.label}
							<div class="text-xs text-muted-foreground">{data.label}</div>
						{/if}
					</div>
					<div class="text-right">
						<div class="flex items-center gap-1 text-sm text-muted-foreground">
							<Flag class="h-3 w-3" />
							<span>{formatValue(data.goal, data.unit)}</span>
						</div>
						<div class="text-lg font-semibold {progress() >= 100 ? 'text-green-600' : ''}">
							{progress()}%
						</div>
					</div>
				</div>
			</div>
		{:else}
			<div class="py-4 text-center text-sm text-muted-foreground">No data available</div>
		{/if}
	</Card.Content>
</Card.Root>

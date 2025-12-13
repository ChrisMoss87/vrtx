<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Hash, TrendingUp, TrendingDown, Minus } from 'lucide-svelte';

	interface Props {
		title: string;
		data: {
			value: number | string;
			label?: string;
			change_percent?: number | null;
			change_type?: 'increase' | 'decrease' | 'no_change' | null;
		} | null;
		loading?: boolean;
	}

	let { title, data, loading = false }: Props = $props();

	function formatValue(value: number | string): string {
		if (typeof value === 'number') {
			if (value >= 1000000) {
				return (value / 1000000).toFixed(1) + 'M';
			} else if (value >= 1000) {
				return (value / 1000).toFixed(1) + 'K';
			}
			return value.toLocaleString();
		}
		return String(value || '0');
	}

	const changeIcon = $derived(() => {
		if (!data?.change_type) return null;
		switch (data.change_type) {
			case 'increase':
				return TrendingUp;
			case 'decrease':
				return TrendingDown;
			default:
				return Minus;
		}
	});

	const changeColor = $derived(() => {
		if (!data?.change_type) return 'text-muted-foreground';
		switch (data.change_type) {
			case 'increase':
				return 'text-green-600';
			case 'decrease':
				return 'text-red-600';
			default:
				return 'text-muted-foreground';
		}
	});
</script>

<Card.Root class="h-full">
	<Card.Header class="pb-2">
		<div class="flex items-center gap-2">
			<Hash class="h-4 w-4 text-muted-foreground" />
			<Card.Title class="text-sm font-medium">{title}</Card.Title>
		</div>
	</Card.Header>
	<Card.Content>
		{#if loading}
			<div class="flex animate-pulse flex-col items-center py-4">
				<div class="h-10 w-24 rounded bg-muted"></div>
				<div class="mt-2 h-4 w-16 rounded bg-muted"></div>
			</div>
		{:else}
			<div class="text-center">
				<div class="text-3xl font-bold">
					{formatValue(data?.value ?? 0)}
				</div>
				{#if data?.change_percent !== null && data?.change_percent !== undefined}
					{@const Icon = changeIcon()}
					<div class="mt-1 flex items-center justify-center gap-1 text-sm {changeColor()}">
						{#if Icon}
							<Icon class="h-4 w-4" />
						{/if}
						<span>
							{data.change_percent >= 0 ? '+' : ''}{data.change_percent.toFixed(1)}%
						</span>
					</div>
				{/if}
			</div>
		{/if}
	</Card.Content>
</Card.Root>

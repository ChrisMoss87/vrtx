<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Filter } from 'lucide-svelte';

	interface FunnelStage {
		id: string | number;
		label: string;
		value: number;
		color?: string;
	}

	interface Props {
		title: string;
		data: {
			stages: FunnelStage[];
			show_conversion?: boolean;
		} | null;
		loading?: boolean;
	}

	let { title, data, loading = false }: Props = $props();

	const maxValue = $derived(() => {
		if (!data?.stages || data.stages.length === 0) return 0;
		return Math.max(...data.stages.map((s) => s.value));
	});

	function formatValue(value: number): string {
		if (value >= 1000000) {
			return (value / 1000000).toFixed(1) + 'M';
		} else if (value >= 1000) {
			return (value / 1000).toFixed(1) + 'K';
		}
		return value.toLocaleString();
	}

	function getWidthPercent(value: number): number {
		const max = maxValue();
		if (max === 0) return 100;
		return Math.max(20, (value / max) * 100);
	}

	function getConversionRate(currentValue: number, previousValue: number): string {
		if (previousValue === 0) return '0%';
		return ((currentValue / previousValue) * 100).toFixed(1) + '%';
	}

	const defaultColors = [
		'bg-blue-500',
		'bg-indigo-500',
		'bg-violet-500',
		'bg-purple-500',
		'bg-fuchsia-500',
		'bg-pink-500'
	];

	function getStageColor(index: number, customColor?: string): string {
		if (customColor) return customColor;
		return defaultColors[index % defaultColors.length];
	}
</script>

<Card.Root class="flex h-full flex-col">
	<Card.Header class="pb-2">
		<div class="flex items-center gap-2">
			<Filter class="h-4 w-4 text-muted-foreground" />
			<Card.Title class="text-sm font-medium">{title}</Card.Title>
		</div>
	</Card.Header>
	<Card.Content class="flex flex-1 flex-col justify-center">
		{#if loading}
			<div class="space-y-3">
				{#each [100, 80, 60, 40, 30] as width}
					<div class="flex items-center gap-3">
						<div
							class="h-10 animate-pulse rounded bg-muted"
							style="width: {width}%"
						></div>
					</div>
				{/each}
			</div>
		{:else if data?.stages && data.stages.length > 0}
			<div class="space-y-2">
				{#each data.stages as stage, index (stage.id)}
					{@const widthPercent = getWidthPercent(stage.value)}
					{@const prevValue = index > 0 ? data.stages[index - 1].value : null}
					<div class="group relative">
						<div class="flex items-center gap-3">
							<!-- Funnel bar -->
							<div
								class="relative h-10 transition-all duration-300 {getStageColor(index, stage.color)} rounded"
								style="width: {widthPercent}%"
							>
								<div
									class="absolute inset-0 flex items-center justify-between px-3 text-white"
								>
									<span class="truncate text-sm font-medium">{stage.label}</span>
									<span class="text-sm font-bold">{formatValue(stage.value)}</span>
								</div>
							</div>

							<!-- Conversion rate -->
							{#if data.show_conversion !== false && prevValue !== null}
								<div
									class="hidden w-16 text-right text-xs text-muted-foreground group-hover:block"
								>
									{getConversionRate(stage.value, prevValue)}
								</div>
							{/if}
						</div>

						<!-- Conversion arrow (shown between stages) -->
						{#if index < data.stages.length - 1}
							<div class="absolute -bottom-1 left-1/4 hidden text-muted-foreground group-hover:block">
								<svg
									xmlns="http://www.w3.org/2000/svg"
									width="16"
									height="16"
									viewBox="0 0 24 24"
									fill="none"
									stroke="currentColor"
									stroke-width="2"
									stroke-linecap="round"
									stroke-linejoin="round"
								>
									<path d="M12 5v14M19 12l-7 7-7-7" />
								</svg>
							</div>
						{/if}
					</div>
				{/each}
			</div>

			<!-- Summary -->
			{#if data.stages.length > 1}
				<div class="mt-4 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground">
					<span>Overall conversion</span>
					<span class="font-medium">
						{getConversionRate(data.stages[data.stages.length - 1].value, data.stages[0].value)}
					</span>
				</div>
			{/if}
		{:else}
			<div class="py-8 text-center text-sm text-muted-foreground">No funnel data available</div>
		{/if}
	</Card.Content>
</Card.Root>

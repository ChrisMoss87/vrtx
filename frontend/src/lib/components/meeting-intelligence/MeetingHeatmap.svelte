<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import type { MeetingHeatmap } from '$lib/api/meetings';

	interface Props {
		heatmap: MeetingHeatmap;
	}

	let { heatmap }: Props = $props();

	function getIntensity(value: number): string {
		if (value === 0) return 'bg-muted';
		const ratio = value / heatmap.max_value;
		if (ratio <= 0.25) return 'bg-green-200 dark:bg-green-900';
		if (ratio <= 0.5) return 'bg-green-400 dark:bg-green-700';
		if (ratio <= 0.75) return 'bg-green-500 dark:bg-green-600';
		return 'bg-green-600 dark:bg-green-500';
	}

	function formatHour(hour: number): string {
		if (hour === 12) return '12 PM';
		if (hour > 12) return `${hour - 12} PM`;
		return `${hour} AM`;
	}
</script>

<Card>
	<CardHeader>
		<CardTitle>Meeting Heatmap</CardTitle>
		<CardDescription>
			Your meeting patterns over the past {heatmap.hours.length > 0 ? '4 weeks' : 'period'}
		</CardDescription>
	</CardHeader>
	<CardContent>
		<div class="overflow-x-auto">
			<div class="min-w-[400px]">
				<!-- Header row with days -->
				<div class="flex items-center gap-1 mb-2">
					<div class="w-16 text-xs text-muted-foreground"></div>
					{#each heatmap.days as day}
						<div class="flex-1 text-center text-xs font-medium text-muted-foreground">
							{day}
						</div>
					{/each}
				</div>

				<!-- Heatmap grid -->
				<div class="space-y-1">
					{#each heatmap.hours as hour}
						<div class="flex items-center gap-1">
							<div class="w-16 text-xs text-muted-foreground text-right pr-2">
								{formatHour(hour)}
							</div>
							{#each heatmap.days as day}
								{@const value = heatmap.data[hour]?.[day] ?? 0}
								<div
									class="flex-1 h-6 rounded {getIntensity(value)} transition-colors"
									title="{value} meeting{value !== 1 ? 's' : ''} on {day} at {formatHour(hour)}"
								>
									{#if value > 0}
										<span class="flex items-center justify-center h-full text-xs font-medium">
											{value}
										</span>
									{/if}
								</div>
							{/each}
						</div>
					{/each}
				</div>

				<!-- Legend -->
				<div class="flex items-center justify-between mt-4 pt-4 border-t">
					<div class="flex items-center gap-2">
						<span class="text-xs text-muted-foreground">Less</span>
						<div class="w-4 h-4 rounded bg-muted"></div>
						<div class="w-4 h-4 rounded bg-green-200 dark:bg-green-900"></div>
						<div class="w-4 h-4 rounded bg-green-400 dark:bg-green-700"></div>
						<div class="w-4 h-4 rounded bg-green-500 dark:bg-green-600"></div>
						<div class="w-4 h-4 rounded bg-green-600 dark:bg-green-500"></div>
						<span class="text-xs text-muted-foreground">More</span>
					</div>

					{#if heatmap.peak_times.length > 0}
						<div class="text-xs text-muted-foreground">
							Peak: {heatmap.peak_times.map(p => `${p.day} ${formatHour(p.hour)}`).join(', ')}
						</div>
					{/if}
				</div>
			</div>
		</div>
	</CardContent>
</Card>

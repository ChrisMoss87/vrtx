<script lang="ts">
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Button } from '$lib/components/ui/button';
	import * as Collapsible from '$lib/components/ui/collapsible';
	import { ChevronDown, RefreshCw, Loader2 } from 'lucide-svelte';
	import type { DashboardWidget } from '$lib/api/dashboards';

	// Import widget components
	import KPIWidget from './widgets/KPIWidget.svelte';
	import GoalKPIWidget from './widgets/GoalKPIWidget.svelte';
	import ChartWidget from './widgets/ChartWidget.svelte';
	import TableWidget from './widgets/TableWidget.svelte';
	import FunnelWidget from './widgets/FunnelWidget.svelte';
	import LeaderboardWidget from './widgets/LeaderboardWidget.svelte';

	interface Props {
		widgets: DashboardWidget[];
		widgetData: Record<number, unknown>;
		loading?: boolean;
		onRefresh?: () => void;
	}

	let { widgets, widgetData, loading = false, onRefresh }: Props = $props();

	let collapsedWidgets = $state<Set<number>>(new Set());
	let refreshing = $state(false);
	let pullStartY = $state<number | null>(null);
	let pullDistance = $state(0);

	// Sort widgets by y position then x position
	const sortedWidgets = $derived(
		[...widgets].sort((a, b) => {
			const posA = a.grid_position;
			const posB = b.grid_position;
			if (posA.y !== posB.y) return posA.y - posB.y;
			return posA.x - posB.x;
		})
	);

	function toggleCollapse(widgetId: number) {
		const newSet = new Set(collapsedWidgets);
		if (newSet.has(widgetId)) {
			newSet.delete(widgetId);
		} else {
			newSet.add(widgetId);
		}
		collapsedWidgets = newSet;
	}

	function handleTouchStart(event: TouchEvent) {
		if (window.scrollY === 0) {
			pullStartY = event.touches[0].clientY;
		}
	}

	function handleTouchMove(event: TouchEvent) {
		if (pullStartY !== null) {
			const currentY = event.touches[0].clientY;
			const distance = currentY - pullStartY;
			if (distance > 0) {
				pullDistance = Math.min(distance, 100);
			}
		}
	}

	async function handleTouchEnd() {
		if (pullDistance > 60 && onRefresh) {
			refreshing = true;
			try {
				await onRefresh();
			} finally {
				refreshing = false;
			}
		}
		pullStartY = null;
		pullDistance = 0;
	}

	function getWidgetComponent(type: string) {
		switch (type) {
			case 'kpi':
				return KPIWidget;
			case 'goal_kpi':
				return GoalKPIWidget;
			case 'chart':
				return ChartWidget;
			case 'table':
				return TableWidget;
			case 'funnel':
				return FunnelWidget;
			case 'leaderboard':
				return LeaderboardWidget;
			default:
				return null;
		}
	}
</script>

<div
	class="min-h-screen bg-background"
	ontouchstart={handleTouchStart}
	ontouchmove={handleTouchMove}
	ontouchend={handleTouchEnd}
>
	<!-- Pull to refresh indicator -->
	{#if pullDistance > 0 || refreshing}
		<div
			class="flex items-center justify-center py-4 transition-all"
			style="height: {refreshing ? 60 : pullDistance}px"
		>
			{#if refreshing}
				<Loader2 class="h-6 w-6 animate-spin text-primary" />
			{:else}
				<RefreshCw
					class="h-6 w-6 text-muted-foreground transition-transform"
					style="transform: rotate({pullDistance * 2}deg)"
				/>
			{/if}
		</div>
	{/if}

	<!-- Widget stack -->
	<div class="space-y-4 p-4">
		{#each sortedWidgets as widget (widget.id)}
			{@const WidgetComponent = getWidgetComponent(widget.type)}
			{@const data = widgetData[widget.id]}
			{@const isCollapsed = collapsedWidgets.has(widget.id)}

			{#if WidgetComponent}
				<Collapsible.Root open={!isCollapsed}>
					<div class="rounded-lg border bg-card overflow-hidden">
						<!-- Collapsible header -->
						<Collapsible.Trigger
							class="w-full flex items-center justify-between p-3 hover:bg-muted/50"
							onclick={() => toggleCollapse(widget.id)}
						>
							<span class="font-medium text-sm">{widget.title}</span>
							<ChevronDown
								class="h-4 w-4 transition-transform {isCollapsed ? '' : 'rotate-180'}"
							/>
						</Collapsible.Trigger>

						<!-- Widget content -->
						<Collapsible.Content>
							<div class="p-3 pt-0">
								{#if widget.type === 'kpi'}
									<KPIWidget
										title=""
										data={data as any}
										config={widget.config}
										loading={loading}
									/>
								{:else if widget.type === 'goal_kpi'}
									<GoalKPIWidget
										title=""
										data={data as any}
										config={widget.config}
										loading={loading}
									/>
								{:else if widget.type === 'chart'}
									<ChartWidget
										title=""
										data={data as any}
										config={widget.config}
										chartType={widget.config?.chart_type}
										loading={loading}
									/>
								{:else if widget.type === 'table'}
									<TableWidget
										title=""
										data={data as any}
										config={widget.config}
										loading={loading}
										maxRows={5}
									/>
								{:else if widget.type === 'funnel'}
									<FunnelWidget
										title=""
										data={data as any}
										config={widget.config}
										loading={loading}
									/>
								{:else if widget.type === 'leaderboard'}
									<LeaderboardWidget
										title=""
										data={data as any}
										config={widget.config}
										loading={loading}
									/>
								{/if}
							</div>
						</Collapsible.Content>
					</div>
				</Collapsible.Root>
			{/if}
		{/each}
	</div>
</div>

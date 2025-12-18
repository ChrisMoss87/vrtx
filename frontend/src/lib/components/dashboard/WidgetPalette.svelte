<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import {
		X,
		GripVertical,
		BarChart2,
		Hash,
		Table,
		Activity,
		CheckSquare,
		FileText,
		Target,
		Trophy,
		Filter,
		TrendingUp,
		Clock,
		Grid3x3,
		Link,
		Globe,
		PieChart
	} from 'lucide-svelte';
	import type { WidgetType } from '$lib/api/dashboards';

	interface WidgetTypeInfo {
		value: WidgetType;
		label: string;
		icon: typeof BarChart2;
		description: string;
		category: string;
	}

	interface Props {
		open?: boolean;
		onClose?: () => void;
		onWidgetSelect?: (type: WidgetType) => void;
	}

	let { open = false, onClose, onWidgetSelect }: Props = $props();

	const widgetTypes: WidgetTypeInfo[] = [
		// Analytics
		{
			value: 'kpi',
			label: 'KPI Card',
			icon: Hash,
			description: 'Single metric with comparison',
			category: 'Analytics'
		},
		{
			value: 'goal_kpi',
			label: 'Goal KPI',
			icon: Target,
			description: 'KPI with target & progress',
			category: 'Analytics'
		},
		{
			value: 'chart',
			label: 'Chart',
			icon: BarChart2,
			description: 'Visualize data with charts',
			category: 'Analytics'
		},
		{
			value: 'funnel',
			label: 'Funnel',
			icon: Filter,
			description: 'Sales/conversion funnel',
			category: 'Analytics'
		},
		{
			value: 'forecast',
			label: 'Sales Forecast',
			icon: PieChart,
			description: 'Pipeline categories & quota',
			category: 'Analytics'
		},
		{
			value: 'table',
			label: 'Data Table',
			icon: Table,
			description: 'Display data in table format',
			category: 'Analytics'
		},
		{
			value: 'report',
			label: 'Report',
			icon: FileText,
			description: 'Embed a saved report',
			category: 'Analytics'
		},
		// Performance
		{
			value: 'leaderboard',
			label: 'Leaderboard',
			icon: Trophy,
			description: 'Ranked list of items',
			category: 'Performance'
		},
		{
			value: 'progress',
			label: 'Progress Bar',
			icon: TrendingUp,
			description: 'Progress toward a goal',
			category: 'Performance'
		},
		// Activity
		{
			value: 'activity',
			label: 'Activity Feed',
			icon: Activity,
			description: 'Recent activity stream',
			category: 'Activity'
		},
		{
			value: 'tasks',
			label: 'Tasks',
			icon: CheckSquare,
			description: 'Your pending tasks',
			category: 'Activity'
		},
		{
			value: 'recent_records',
			label: 'Recent Records',
			icon: Clock,
			description: 'Latest module records',
			category: 'Activity'
		},
		// Visualization
		{
			value: 'heatmap',
			label: 'Heatmap',
			icon: Grid3x3,
			description: 'Activity density grid',
			category: 'Visualization'
		},
		// Content
		{
			value: 'text',
			label: 'Text/HTML',
			icon: FileText,
			description: 'Custom text or HTML',
			category: 'Content'
		},
		{
			value: 'quick_links',
			label: 'Quick Links',
			icon: Link,
			description: 'Navigation shortcuts',
			category: 'Content'
		},
		{
			value: 'embed',
			label: 'Embed',
			icon: Globe,
			description: 'External URL, video, or form',
			category: 'Content'
		}
	];

	const widgetCategories = $derived(() => {
		const categories = new Map<string, WidgetTypeInfo[]>();
		widgetTypes.forEach((type) => {
			if (!categories.has(type.category)) {
				categories.set(type.category, []);
			}
			categories.get(type.category)!.push(type);
		});
		return categories;
	});

	function handleWidgetClick(type: WidgetType) {
		onWidgetSelect?.(type);
	}

	function handleDragStart(event: DragEvent, type: WidgetType) {
		if (event.dataTransfer) {
			event.dataTransfer.setData('widget-type', type);
			event.dataTransfer.effectAllowed = 'copy';
		}
	}
</script>

{#if open}
	<div
		class="fixed right-0 top-0 z-50 h-full w-80 border-l bg-background shadow-lg transition-transform"
		class:translate-x-0={open}
		class:translate-x-full={!open}
	>
		<div class="flex h-full flex-col">
			<!-- Header -->
			<div class="flex items-center justify-between border-b p-4">
				<h2 class="text-lg font-semibold">Add Widgets</h2>
				<Button variant="ghost" size="icon" onclick={onClose}>
					<X class="h-4 w-4" />
				</Button>
			</div>

			<!-- Widget List -->
			<ScrollArea class="flex-1">
				<div class="space-y-6 p-4">
					{#each [...widgetCategories().entries()] as [category, types]}
						<div class="space-y-2">
							<h3 class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
								{category}
							</h3>
							<div class="space-y-2">
								{#each types as type}
									{@const Icon = type.icon}
									<button
										type="button"
										class="widget-palette-item group flex w-full items-center gap-3 rounded-lg border bg-card p-3 text-left transition-all hover:border-primary hover:shadow-sm"
										draggable="true"
										ondragstart={(e) => handleDragStart(e, type.value)}
										onclick={() => handleWidgetClick(type.value)}
									>
										<div
											class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted"
										>
											<Icon class="h-5 w-5 text-muted-foreground" />
										</div>
										<div class="min-w-0 flex-1">
											<div class="font-medium">{type.label}</div>
											<div class="truncate text-xs text-muted-foreground">
												{type.description}
											</div>
										</div>
										<GripVertical
											class="h-4 w-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
										/>
									</button>
								{/each}
							</div>
						</div>
					{/each}
				</div>
			</ScrollArea>

			<!-- Footer hint -->
			<div class="border-t p-4">
				<p class="text-center text-xs text-muted-foreground">
					Click a widget to add it, or drag and drop onto the dashboard
				</p>
			</div>
		</div>
	</div>

	<!-- Backdrop -->
	<button
		type="button"
		class="fixed inset-0 z-40 bg-black/20 transition-opacity"
		onclick={onClose}
		onkeydown={(e) => e.key === 'Escape' && onClose?.()}
		aria-label="Close widget palette"
	></button>
{/if}

<style>
	.widget-palette-item {
		cursor: grab;
	}

	.widget-palette-item:active {
		cursor: grabbing;
	}
</style>

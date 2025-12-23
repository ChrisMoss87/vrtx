<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import {
		ArrowLeft,
		Plus,
		Settings,
		Save,
		RefreshCw,
		BarChart2,
		Hash,
		Table,
		Activity,
		CheckSquare,
		FileText,
		LayoutDashboard,
		Star,
		Users,
		Target,
		Trophy,
		Filter,
		TrendingUp,
		Clock,
		PanelRightOpen,
		Grid3x3,
		Link,
		Globe,
		Share2,
		Play,
		Bell,
		MessageSquare
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import QuickFiltersBar from '$lib/components/dashboard/QuickFiltersBar.svelte';
	import ShareDashboardDialog from '$lib/components/dashboard/ShareDashboardDialog.svelte';
	import AlertHistoryPanel from '$lib/components/dashboard/AlertHistoryPanel.svelte';
	import { onFilterChange, clearGlobalFilters, buildFilterQueryParams } from '$lib/stores/dashboardFilterContext.svelte';
	import {
		dashboardsApi,
		type Dashboard,
		type DashboardWidget,
		type WidgetType,
		type WidgetConfig,
		getDefaultGridPosition
	} from '$lib/api/dashboards';
	import { reportsApi, type Report } from '$lib/api/reports';
	import {
		KPIWidget,
		TableWidget,
		ChartWidget,
		TextWidget,
		ActivityWidget,
		TasksWidget,
		GoalKPIWidget,
		LeaderboardWidget,
		FunnelWidget,
		ProgressWidget,
		RecentRecordsWidget,
		HeatmapWidget,
		QuickLinksWidget,
		EmbedWidget,
		ForecastWidget
	} from '$lib/components/dashboard/widgets';
	import DashboardGrid from '$lib/components/dashboard/DashboardGrid.svelte';
	import WidgetPalette from '$lib/components/dashboard/WidgetPalette.svelte';
	import KPIConfigForm from '$lib/components/dashboard/KPIConfigForm.svelte';

	let dashboard = $state<Dashboard | null>(null);
	let widgetData = $state<Record<number, any>>({});
	let loading = $state(true);
	let refreshing = $state(false);
	let editMode = $state(false);
	let widgetPaletteOpen = $state(false);
	let addWidgetDialogOpen = $state(false);
	let editWidgetDialogOpen = $state(false);
	let selectedWidget = $state<DashboardWidget | null>(null);
	let savingLayout = $state(false);

	// Widget form state
	let widgetTitle = $state('');
	let widgetType = $state<WidgetType>('kpi');
	let selectedReportId = $state<string>('');
	let widgetConfig = $state<WidgetConfig>({});
	let textContent = $state('');
	let reports = $state<Report[]>([]);

	// Dashboard feature dialogs
	let shareDialogOpen = $state(false);
	let alertPanelOpen = $state(false);
	let showQuickFilters = $state(true);

	const dashboardId = $derived(Number($page.params.id));

	const widgetTypes: {
		value: WidgetType;
		label: string;
		icon: typeof BarChart2;
		description: string;
		category: string;
	}[] = [
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
			icon: BarChart2,
			description: 'Pipeline & quota tracking',
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
		const categories = new Map<string, typeof widgetTypes>();
		widgetTypes.forEach((type) => {
			if (!categories.has(type.category)) {
				categories.set(type.category, []);
			}
			categories.get(type.category)!.push(type);
		});
		return categories;
	});

	let unsubscribeFilters: (() => void) | null = null;

	onMount(() => {
		const urlParams = new URLSearchParams(window.location.search);
		if (urlParams.get('edit') === 'true') {
			editMode = true;
		}

		loadDashboard();
		loadReports();

		// Subscribe to filter changes for cross-widget filtering
		unsubscribeFilters = onFilterChange(() => {
			loadWidgetData();
		});

		return () => {
			unsubscribeFilters?.();
			clearGlobalFilters();
		};
	});

	async function loadDashboard() {
		loading = true;
		try {
			dashboard = await dashboardsApi.get(dashboardId);
			await loadWidgetData();
		} catch (error) {
			console.error('Failed to load dashboard:', error);
			toast.error('Failed to load dashboard');
			goto('/dashboards');
		} finally {
			loading = false;
		}
	}

	async function loadWidgetData() {
		if (!dashboard) return;

		try {
			// Build filter params from global filter context
			const filterParams = buildFilterQueryParams();
			widgetData = await dashboardsApi.getAllWidgetData(dashboard.id, filterParams);
		} catch (error) {
			console.error('Failed to load widget data:', error);
		}
	}

	function handleFiltersChange() {
		loadWidgetData();
	}

	function handlePresentationMode() {
		goto(`/dashboards/${dashboardId}/present`);
	}

	async function loadReports() {
		try {
			const response = await reportsApi.list({ per_page: 100 });
			reports = response.data;
		} catch (error) {
			console.error('Failed to load reports:', error);
		}
	}

	async function handleRefresh() {
		refreshing = true;
		try {
			await loadWidgetData();
			toast.success('Dashboard refreshed');
		} catch (error) {
			console.error('Failed to refresh:', error);
			toast.error('Failed to refresh dashboard');
		} finally {
			refreshing = false;
		}
	}

	async function handleLayoutChange(
		positions: { id: number; x: number; y: number; w: number; h: number }[]
	) {
		if (!dashboard || savingLayout) return;

		savingLayout = true;
		try {
			await dashboardsApi.widgets.updatePositions(dashboard.id, positions);

			// Update local state
			if (dashboard.widgets) {
				dashboard = {
					...dashboard,
					widgets: dashboard.widgets.map((w) => {
						const pos = positions.find((p) => p.id === w.id);
						if (pos) {
							return {
								...w,
								grid_position: { ...w.grid_position, x: pos.x, y: pos.y, w: pos.w, h: pos.h }
							};
						}
						return w;
					})
				};
			}
		} catch (error) {
			console.error('Failed to save layout:', error);
			toast.error('Failed to save widget positions');
		} finally {
			savingLayout = false;
		}
	}

	async function handleAddWidget() {
		if (!dashboard || !widgetTitle.trim()) {
			toast.error('Please enter a widget title');
			return;
		}

		// Build config based on widget type
		let config: WidgetConfig = { ...widgetConfig };
		if (widgetType === 'text') {
			config.content = textContent;
		}

		try {
			const widget = await dashboardsApi.widgets.add(dashboard.id, {
				title: widgetTitle.trim(),
				type: widgetType,
				report_id: selectedReportId ? Number(selectedReportId) : undefined,
				config,
				grid_position: getDefaultGridPosition(widgetType)
			});

			dashboard = {
				...dashboard,
				widgets: [...(dashboard.widgets || []), widget]
			};

			// Load data for the new widget
			try {
				const data = await dashboardsApi.widgets.getData(dashboard.id, widget.id);
				widgetData = { ...widgetData, [widget.id]: data };
			} catch {}

			resetWidgetForm();
			addWidgetDialogOpen = false;
			toast.success('Widget added');
		} catch (error) {
			console.error('Failed to add widget:', error);
			toast.error('Failed to add widget');
		}
	}

	async function handleUpdateWidget() {
		if (!dashboard || !selectedWidget || !widgetTitle.trim()) {
			return;
		}

		let config: WidgetConfig = { ...widgetConfig };
		if (widgetType === 'text') {
			config.content = textContent;
		}

		try {
			const updated = await dashboardsApi.widgets.update(dashboard.id, selectedWidget.id, {
				title: widgetTitle.trim(),
				type: widgetType,
				report_id: selectedReportId ? Number(selectedReportId) : undefined,
				config
			});

			dashboard = {
				...dashboard,
				widgets: dashboard.widgets?.map((w) => (w.id === updated.id ? updated : w))
			};

			// Refresh data for updated widget
			try {
				const data = await dashboardsApi.widgets.getData(dashboard.id, updated.id);
				widgetData = { ...widgetData, [updated.id]: data };
			} catch {}

			resetWidgetForm();
			editWidgetDialogOpen = false;
			toast.success('Widget updated');
		} catch (error) {
			console.error('Failed to update widget:', error);
			toast.error('Failed to update widget');
		}
	}

	async function handleDeleteWidget(widget: DashboardWidget) {
		if (!dashboard) return;

		try {
			await dashboardsApi.widgets.remove(dashboard.id, widget.id);
			dashboard = {
				...dashboard,
				widgets: dashboard.widgets?.filter((w) => w.id !== widget.id)
			};
			delete widgetData[widget.id];
			widgetData = { ...widgetData };
			toast.success('Widget removed');
		} catch (error) {
			console.error('Failed to remove widget:', error);
			toast.error('Failed to remove widget');
		}
	}

	function openEditWidget(widget: DashboardWidget) {
		selectedWidget = widget;
		widgetTitle = widget.title;
		widgetType = widget.type;
		selectedReportId = widget.report_id ? String(widget.report_id) : '';
		widgetConfig = widget.config || {};
		textContent = widget.config?.content || '';
		editWidgetDialogOpen = true;
	}

	function resetWidgetForm() {
		widgetTitle = '';
		widgetType = 'kpi';
		selectedReportId = '';
		widgetConfig = {};
		textContent = '';
		selectedWidget = null;
	}

	function handleWidgetPaletteSelect(type: WidgetType) {
		resetWidgetForm();
		widgetType = type;
		const typeInfo = widgetTypes.find((t) => t.value === type);
		widgetTitle = typeInfo?.label || '';
		widgetPaletteOpen = false;
		addWidgetDialogOpen = true;
	}

	function getWidgetIcon(type: WidgetType) {
		const typeConfig = widgetTypes.find((t) => t.value === type);
		return typeConfig?.icon || LayoutDashboard;
	}
</script>

<svelte:head>
	<title>{dashboard?.name || 'Dashboard'} | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	{#if loading}
		<div class="space-y-4">
			<div class="flex items-center gap-4">
				<Skeleton class="h-8 w-8" />
				<Skeleton class="h-8 w-64" />
			</div>
			<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
				{#each [1, 2, 3, 4, 5, 6] as _}
					<Skeleton class="h-48" />
				{/each}
			</div>
		</div>
	{:else if dashboard}
		<!-- Header -->
		<div class="mb-6 flex items-start justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => goto('/dashboards')}>
					<ArrowLeft class="h-4 w-4" />
				</Button>
				<div>
					<div class="flex items-center gap-2">
						<h1 class="text-2xl font-bold">{dashboard.name}</h1>
						{#if dashboard.is_default}
							<Badge variant="default">
								<Star class="mr-1 h-3 w-3" />
								Default
							</Badge>
						{/if}
						{#if dashboard.is_public}
							<Badge variant="outline">
								<Users class="mr-1 h-3 w-3" />
								Public
							</Badge>
						{/if}
					</div>
					{#if dashboard.description}
						<p class="text-muted-foreground">{dashboard.description}</p>
					{/if}
				</div>
			</div>

			<div class="flex items-center gap-2">
				{#if savingLayout}
					<span class="text-sm text-muted-foreground">Saving...</span>
				{/if}

				<!-- Alert History Button -->
				<Button variant="outline" size="icon" onclick={() => (alertPanelOpen = true)} title="View Alerts">
					<Bell class="h-4 w-4" />
				</Button>

				<!-- Presentation Mode Button -->
				<Button variant="outline" size="icon" onclick={handlePresentationMode} title="Presentation Mode">
					<Play class="h-4 w-4" />
				</Button>

				<!-- Share Button -->
				<Button variant="outline" size="icon" onclick={() => (shareDialogOpen = true)} title="Share Dashboard">
					<Share2 class="h-4 w-4" />
				</Button>

				<Button variant="outline" onclick={handleRefresh} disabled={refreshing}>
					<RefreshCw class="mr-2 h-4 w-4 {refreshing ? 'animate-spin' : ''}" />
					Refresh
				</Button>

				{#if editMode}
					<Button variant="outline" onclick={() => (widgetPaletteOpen = true)}>
						<PanelRightOpen class="mr-2 h-4 w-4" />
						Widget Palette
					</Button>
					<Button variant="outline" onclick={() => (addWidgetDialogOpen = true)}>
						<Plus class="mr-2 h-4 w-4" />
						Add Widget
					</Button>
					<Button onclick={() => (editMode = false)}>
						<Save class="mr-2 h-4 w-4" />
						Done Editing
					</Button>
				{:else}
					<Button variant="outline" onclick={() => (editMode = true)}>
						<Settings class="mr-2 h-4 w-4" />
						Edit Dashboard
					</Button>
				{/if}
			</div>
		</div>

		<!-- Quick Filters Bar -->
		{#if showQuickFilters}
			<div class="mb-4">
				<QuickFiltersBar onFiltersChange={handleFiltersChange} />
			</div>
		{/if}

		<!-- Widgets Grid -->
		{#if !dashboard.widgets || dashboard.widgets.length === 0}
			<Card.Root>
				<Card.Content class="flex flex-col items-center justify-center py-12">
					<LayoutDashboard class="mb-4 h-12 w-12 text-muted-foreground" />
					<h3 class="mb-2 text-lg font-medium">No widgets yet</h3>
					<p class="mb-4 text-muted-foreground">Add widgets to visualize your data</p>
					<Button
						onclick={() => {
							editMode = true;
							addWidgetDialogOpen = true;
						}}
					>
						<Plus class="mr-2 h-4 w-4" />
						Add Widget
					</Button>
				</Card.Content>
			</Card.Root>
		{:else}
			<DashboardGrid
				widgets={dashboard.widgets}
				{editMode}
				onLayoutChange={handleLayoutChange}
				onWidgetEdit={openEditWidget}
				onWidgetDelete={handleDeleteWidget}
			>
				{#snippet children(widget, data)}
					{@const widgetDataItem = widgetData[widget.id]}
					{#if widget.type === 'kpi'}
						<KPIWidget title={widget.title} data={widgetDataItem} config={widget.config} />
					{:else if widget.type === 'goal_kpi'}
						<GoalKPIWidget title={widget.title} data={widgetDataItem} config={widget.config} />
					{:else if widget.type === 'table' || widget.type === 'report'}
						<TableWidget title={widget.title} data={widgetDataItem} config={widget.config} />
					{:else if widget.type === 'chart'}
						<ChartWidget
							title={widget.title}
							data={widgetDataItem}
							config={widget.config}
							chartType={widgetDataItem?.chart_type || 'bar'}
						/>
					{:else if widget.type === 'funnel'}
						<FunnelWidget title={widget.title} data={widgetDataItem} config={widget.config} />
					{:else if widget.type === 'leaderboard'}
						<LeaderboardWidget title={widget.title} data={widgetDataItem} config={widget.config} />
					{:else if widget.type === 'progress'}
						<ProgressWidget title={widget.title} data={widgetDataItem} config={widget.config} />
					{:else if widget.type === 'recent_records'}
						<RecentRecordsWidget title={widget.title} data={widgetDataItem} />
					{:else if widget.type === 'heatmap'}
						<HeatmapWidget title={widget.title} data={widgetDataItem} config={widget.config} />
					{:else if widget.type === 'text'}
						<TextWidget title={widget.title} content={widget.config?.content || ''} />
					{:else if widget.type === 'quick_links'}
						<QuickLinksWidget title={widget.title} data={widget.config?.links ? { links: widget.config.links, columns: widget.config.columns } : widgetDataItem} />
					{:else if widget.type === 'embed'}
						<EmbedWidget title={widget.title} data={widgetDataItem} config={widget.config} />
					{:else if widget.type === 'activity'}
						<ActivityWidget title={widget.title} data={widgetDataItem} />
					{:else if widget.type === 'tasks'}
						<TasksWidget title={widget.title} data={widgetDataItem} />
					{:else if widget.type === 'forecast'}
						<ForecastWidget title={widget.title} config={widget.config} data={widgetDataItem} />
					{:else}
						<Card.Root class="h-full">
							<Card.Header class="pb-2">
								{@const WidgetIcon = getWidgetIcon(widget.type)}
								<div class="flex items-center gap-2">
									<WidgetIcon class="h-4 w-4 text-muted-foreground" />
									<Card.Title class="text-sm font-medium">{widget.title}</Card.Title>
								</div>
							</Card.Header>
							<Card.Content>
								<p class="py-4 text-center text-sm text-muted-foreground">
									{widget.type} widget
								</p>
							</Card.Content>
						</Card.Root>
					{/if}
				{/snippet}
			</DashboardGrid>
		{/if}
	{/if}
</div>

<!-- Add Widget Dialog -->
<Dialog.Root bind:open={addWidgetDialogOpen}>
	<Dialog.Content class="max-w-2xl">
		<Dialog.Header>
			<Dialog.Title>Add Widget</Dialog.Title>
			<Dialog.Description>Choose a widget type and configure it</Dialog.Description>
		</Dialog.Header>

		<Tabs.Root value="type" class="w-full">
			<Tabs.List class="grid w-full grid-cols-2">
				<Tabs.Trigger value="type">Widget Type</Tabs.Trigger>
				<Tabs.Trigger value="config">Configuration</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="type" class="space-y-4 pt-4">
				<div class="space-y-2">
					<Label>Widget Title</Label>
					<Input placeholder="Enter widget title" bind:value={widgetTitle} />
				</div>

				<div class="space-y-4">
					{#each [...widgetCategories().entries()] as [category, types]}
						<div class="space-y-2">
							<Label class="text-xs uppercase text-muted-foreground">{category}</Label>
							<div class="grid grid-cols-3 gap-2">
								{#each types as type}
									{@const Icon = type.icon}
									<button
										type="button"
										class="flex items-start gap-2 rounded-lg border p-3 text-left transition-colors hover:bg-muted {widgetType ===
										type.value
											? 'border-primary bg-primary/5'
											: ''}"
										onclick={() => (widgetType = type.value)}
									>
										<Icon class="mt-0.5 h-4 w-4 shrink-0" />
										<div>
											<div class="text-sm font-medium">{type.label}</div>
											<div class="text-xs text-muted-foreground">{type.description}</div>
										</div>
									</button>
								{/each}
							</div>
						</div>
					{/each}
				</div>
			</Tabs.Content>

			<Tabs.Content value="config" class="space-y-4 pt-4">
				{#if widgetType === 'kpi' || widgetType === 'goal_kpi'}
					<KPIConfigForm config={widgetConfig} onUpdate={(c) => (widgetConfig = c)} />
					{#if widgetType === 'goal_kpi'}
						<div class="space-y-2">
							<Label>Target Value</Label>
							<Input
								type="number"
								placeholder="Enter target value"
								value={widgetConfig.target || ''}
								oninput={(e) =>
									(widgetConfig = { ...widgetConfig, target: Number(e.currentTarget.value) })}
							/>
						</div>
					{/if}
				{:else if widgetType === 'report' || widgetType === 'chart' || widgetType === 'table'}
					<div class="space-y-2">
						<Label>Select Report</Label>
						<Select.Root type="single" bind:value={selectedReportId}>
							<Select.Trigger class="w-full">
								{selectedReportId
									? reports.find((r) => String(r.id) === selectedReportId)?.name || 'Select report'
									: 'Select a report'}
							</Select.Trigger>
							<Select.Content>
								{#each reports as report}
									<Select.Item value={String(report.id)}>{report.name}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
						<p class="text-xs text-muted-foreground">
							Choose a saved report to display in this widget
						</p>
					</div>
				{:else if widgetType === 'text'}
					<div class="space-y-2">
						<Label>Content (HTML supported)</Label>
						<Textarea
							placeholder="Enter text or HTML content..."
							bind:value={textContent}
							rows={6}
						/>
						<p class="text-xs text-muted-foreground">You can use basic HTML for formatting</p>
					</div>
				{:else if widgetType === 'progress'}
					<div class="grid gap-4 sm:grid-cols-2">
						<div class="space-y-2">
							<Label>Current Value</Label>
							<Input
								type="number"
								placeholder="0"
								value={widgetConfig.current_value || ''}
								oninput={(e) =>
									(widgetConfig = { ...widgetConfig, current_value: Number(e.currentTarget.value) })}
							/>
						</div>
						<div class="space-y-2">
							<Label>Goal Value</Label>
							<Input
								type="number"
								placeholder="100"
								value={widgetConfig.goal_value || ''}
								oninput={(e) =>
									(widgetConfig = { ...widgetConfig, goal_value: Number(e.currentTarget.value) })}
							/>
						</div>
					</div>
				{:else}
					<p class="py-4 text-center text-sm text-muted-foreground">
						No additional configuration needed for this widget type
					</p>
				{/if}
			</Tabs.Content>
		</Tabs.Root>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (addWidgetDialogOpen = false)}>Cancel</Button>
			<Button onclick={handleAddWidget} disabled={!widgetTitle.trim()}>Add Widget</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Edit Widget Dialog -->
<Dialog.Root bind:open={editWidgetDialogOpen}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>Edit Widget</Dialog.Title>
			<Dialog.Description>Modify the widget settings</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label>Widget Title</Label>
				<Input placeholder="Enter widget title" bind:value={widgetTitle} />
			</div>

			<div class="space-y-2">
				<Label>Widget Type</Label>
				<div class="grid grid-cols-4 gap-2">
					{#each widgetTypes as type}
						{@const Icon = type.icon}
						<button
							type="button"
							class="flex flex-col items-center gap-1 rounded-lg border p-2 text-center transition-colors hover:bg-muted {widgetType ===
							type.value
								? 'border-primary bg-primary/5'
								: ''}"
							onclick={() => (widgetType = type.value)}
						>
							<Icon class="h-4 w-4" />
							<span class="text-[10px]">{type.label}</span>
						</button>
					{/each}
				</div>
			</div>

			{#if widgetType === 'kpi' || widgetType === 'goal_kpi'}
				<KPIConfigForm config={widgetConfig} onUpdate={(c) => (widgetConfig = c)} />
			{:else if widgetType === 'report' || widgetType === 'chart' || widgetType === 'table'}
				<div class="space-y-2">
					<Label>Select Report</Label>
					<Select.Root type="single" bind:value={selectedReportId}>
						<Select.Trigger class="w-full">
							{selectedReportId
								? reports.find((r) => String(r.id) === selectedReportId)?.name || 'Select report'
								: 'Select a report'}
						</Select.Trigger>
						<Select.Content>
							{#each reports as report}
								<Select.Item value={String(report.id)}>{report.name}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			{:else if widgetType === 'text'}
				<div class="space-y-2">
					<Label>Content (HTML supported)</Label>
					<Textarea placeholder="Enter text or HTML content..." bind:value={textContent} rows={6} />
				</div>
			{/if}
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (editWidgetDialogOpen = false)}>Cancel</Button>
			<Button onclick={handleUpdateWidget} disabled={!widgetTitle.trim()}>Save Changes</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Widget Palette Sidebar -->
<WidgetPalette
	open={widgetPaletteOpen}
	onClose={() => (widgetPaletteOpen = false)}
	onWidgetSelect={handleWidgetPaletteSelect}
/>

<!-- Share Dashboard Dialog -->
{#if dashboard}
	<ShareDashboardDialog
		dashboardId={dashboard.id}
		bind:open={shareDialogOpen}
	/>
{/if}

<!-- Alert History Panel -->
{#if dashboard}
	<AlertHistoryPanel
		dashboardId={dashboard.id}
		bind:open={alertPanelOpen}
	/>
{/if}

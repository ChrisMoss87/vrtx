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
		Trash2,
		Edit2,
		BarChart2,
		Hash,
		Table,
		Activity,
		CheckSquare,
		FileText,
		LayoutDashboard,
		Star,
		Users,
		Globe
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		dashboardsApi,
		type Dashboard,
		type DashboardWidget,
		type WidgetType,
		type WidgetConfig,
		getDefaultWidgetSize
	} from '$lib/api/dashboards';
	import { reportsApi, type Report } from '$lib/api/reports';
	import {
		KPIWidget,
		TableWidget,
		ChartWidget,
		TextWidget,
		ActivityWidget,
		TasksWidget
	} from '$lib/components/dashboard/widgets';
	import KPIConfigForm from '$lib/components/dashboard/KPIConfigForm.svelte';

	let dashboard = $state<Dashboard | null>(null);
	let widgetData = $state<Record<number, any>>({});
	let loading = $state(true);
	let refreshing = $state(false);
	let editMode = $state(false);
	let addWidgetDialogOpen = $state(false);
	let editWidgetDialogOpen = $state(false);
	let selectedWidget = $state<DashboardWidget | null>(null);

	// Widget form state
	let widgetTitle = $state('');
	let widgetType = $state<WidgetType>('kpi');
	let selectedReportId = $state<string>('');
	let widgetConfig = $state<WidgetConfig>({});
	let textContent = $state('');
	let reports = $state<Report[]>([]);

	const dashboardId = $derived(Number($page.params.id));

	const widgetTypes: { value: WidgetType; label: string; icon: typeof BarChart2; description: string }[] = [
		{ value: 'kpi', label: 'KPI Card', icon: Hash, description: 'Show a single metric with optional comparison' },
		{ value: 'chart', label: 'Chart', icon: BarChart2, description: 'Visualize data with charts' },
		{ value: 'table', label: 'Data Table', icon: Table, description: 'Display data in a table format' },
		{ value: 'report', label: 'Report', icon: FileText, description: 'Embed a saved report' },
		{ value: 'activity', label: 'Activity Feed', icon: Activity, description: 'Show recent activity' },
		{ value: 'tasks', label: 'Tasks', icon: CheckSquare, description: 'Display your tasks' },
		{ value: 'text', label: 'Text/HTML', icon: FileText, description: 'Add custom text or HTML content' }
	];

	onMount(async () => {
		const urlParams = new URLSearchParams(window.location.search);
		if (urlParams.get('edit') === 'true') {
			editMode = true;
		}

		await loadDashboard();
		await loadReports();
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
			widgetData = await dashboardsApi.getAllWidgetData(dashboard.id);
		} catch (error) {
			console.error('Failed to load widget data:', error);
		}
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
				size: getDefaultWidgetSize(widgetType)
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

	function getWidgetIcon(type: WidgetType) {
		const typeConfig = widgetTypes.find((t) => t.value === type);
		return typeConfig?.icon || LayoutDashboard;
	}

	function getWidgetColSpan(widget: DashboardWidget): string {
		const w = widget.size?.w || 1;
		// Map widget logical width to CSS grid column spans
		// Grid is: 1 col on mobile, 2 on md, 3 on lg, 4 on xl
		if (w >= 12) return 'col-span-full'; // Full width at all sizes
		if (w >= 6) return 'md:col-span-2 lg:col-span-3 xl:col-span-4'; // Full width on xl
		if (w >= 4) return 'md:col-span-2 lg:col-span-2 xl:col-span-3';
		if (w >= 3) return 'md:col-span-1 lg:col-span-2 xl:col-span-2';
		if (w >= 2) return 'md:col-span-1 lg:col-span-1 xl:col-span-2';
		return ''; // w:1 = single column
	}

	function getWidgetRowSpan(widget: DashboardWidget): string {
		const h = widget.size?.h || 1;
		if (h >= 4) return 'row-span-4';
		if (h >= 3) return 'row-span-3';
		if (h >= 2) return 'row-span-2';
		return '';
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
				<Button variant="outline" onclick={handleRefresh} disabled={refreshing}>
					<RefreshCw class="mr-2 h-4 w-4 {refreshing ? 'animate-spin' : ''}" />
					Refresh
				</Button>

				{#if editMode}
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

		<!-- Widgets Grid -->
		{#if !dashboard.widgets || dashboard.widgets.length === 0}
			<Card.Root>
				<Card.Content class="flex flex-col items-center justify-center py-12">
					<LayoutDashboard class="mb-4 h-12 w-12 text-muted-foreground" />
					<h3 class="mb-2 text-lg font-medium">No widgets yet</h3>
					<p class="mb-4 text-muted-foreground">Add widgets to visualize your data</p>
					<Button onclick={() => { editMode = true; addWidgetDialogOpen = true; }}>
						<Plus class="mr-2 h-4 w-4" />
						Add Widget
					</Button>
				</Card.Content>
			</Card.Root>
		{:else}
			<div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
				{#each dashboard.widgets as widget (widget.id)}
					{@const data = widgetData[widget.id]}
					<div class="relative {getWidgetColSpan(widget)} {getWidgetRowSpan(widget)}">
						{#if editMode}
							<div class="absolute right-2 top-2 z-10 flex gap-1">
								<Button
									variant="secondary"
									size="icon"
									class="h-7 w-7"
									onclick={() => openEditWidget(widget)}
								>
									<Edit2 class="h-3.5 w-3.5" />
								</Button>
								<Button
									variant="secondary"
									size="icon"
									class="h-7 w-7 text-destructive hover:text-destructive"
									onclick={() => handleDeleteWidget(widget)}
								>
									<Trash2 class="h-3.5 w-3.5" />
								</Button>
							</div>
						{/if}

						{#if widget.type === 'kpi'}
							<KPIWidget title={widget.title} {data} />
						{:else if widget.type === 'table' || widget.type === 'report'}
							<TableWidget title={widget.title} {data} />
						{:else if widget.type === 'chart'}
							<ChartWidget title={widget.title} {data} chartType={data?.chart_type || 'bar'} />
						{:else if widget.type === 'text'}
							<TextWidget title={widget.title} content={widget.config?.content || ''} />
						{:else if widget.type === 'activity'}
							<ActivityWidget title={widget.title} {data} />
						{:else if widget.type === 'tasks'}
							<TasksWidget title={widget.title} {data} />
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
					</div>
				{/each}
			</div>
		{/if}
	{/if}
</div>

<!-- Add Widget Dialog -->
<Dialog.Root bind:open={addWidgetDialogOpen}>
	<Dialog.Content class="max-w-lg">
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

				<div class="space-y-2">
					<Label>Widget Type</Label>
					<div class="grid grid-cols-2 gap-2">
						{#each widgetTypes as type}
							{@const Icon = type.icon}
							<button
								type="button"
								class="flex items-start gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-muted {widgetType === type.value ? 'border-primary bg-primary/5' : ''}"
								onclick={() => (widgetType = type.value)}
							>
								<Icon class="mt-0.5 h-5 w-5 shrink-0" />
								<div>
									<div class="text-sm font-medium">{type.label}</div>
									<div class="text-xs text-muted-foreground">{type.description}</div>
								</div>
							</button>
						{/each}
					</div>
				</div>
			</Tabs.Content>

			<Tabs.Content value="config" class="space-y-4 pt-4">
				{#if widgetType === 'kpi'}
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
						<p class="text-xs text-muted-foreground">
							You can use basic HTML for formatting
						</p>
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
				<div class="grid grid-cols-3 gap-2">
					{#each widgetTypes as type}
						{@const Icon = type.icon}
						<button
							type="button"
							class="flex flex-col items-center gap-1 rounded-lg border p-3 text-center transition-colors hover:bg-muted {widgetType === type.value ? 'border-primary bg-primary/5' : ''}"
							onclick={() => (widgetType = type.value)}
						>
							<Icon class="h-5 w-5" />
							<span class="text-xs">{type.label}</span>
						</button>
					{/each}
				</div>
			</div>

			{#if widgetType === 'kpi'}
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

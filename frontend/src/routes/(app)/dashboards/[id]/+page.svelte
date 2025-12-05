<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { Label } from '$lib/components/ui/label';
	import {
		ArrowLeft,
		Plus,
		Settings,
		Save,
		RefreshCw,
		MoreVertical,
		Trash2,
		Edit2,
		BarChart2,
		Hash,
		Table,
		Activity,
		CheckSquare,
		FileText,
		LayoutDashboard,
		Maximize2,
		Minimize2,
		Star,
		Users
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		dashboardsApi,
		type Dashboard,
		type DashboardWidget,
		type WidgetType,
		getDefaultWidgetSize
	} from '$lib/api/dashboards';
	import { reportsApi, type Report, type ReportResult } from '$lib/api/reports';

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
	let reports = $state<Report[]>([]);

	const dashboardId = $derived(Number($page.params.id));

	const widgetTypes: { value: WidgetType; label: string; icon: typeof BarChart2 }[] = [
		{ value: 'kpi', label: 'KPI Card', icon: Hash },
		{ value: 'chart', label: 'Chart', icon: BarChart2 },
		{ value: 'table', label: 'Data Table', icon: Table },
		{ value: 'report', label: 'Report', icon: FileText },
		{ value: 'activity', label: 'Activity Feed', icon: Activity },
		{ value: 'tasks', label: 'Tasks', icon: CheckSquare }
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

		try {
			const widget = await dashboardsApi.widgets.add(dashboard.id, {
				title: widgetTitle.trim(),
				type: widgetType,
				report_id: selectedReportId ? Number(selectedReportId) : undefined,
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

		try {
			const updated = await dashboardsApi.widgets.update(dashboard.id, selectedWidget.id, {
				title: widgetTitle.trim(),
				type: widgetType,
				report_id: selectedReportId ? Number(selectedReportId) : undefined
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
		editWidgetDialogOpen = true;
	}

	function resetWidgetForm() {
		widgetTitle = '';
		widgetType = 'kpi';
		selectedReportId = '';
		selectedWidget = null;
	}

	function getWidgetIcon(type: WidgetType) {
		const typeConfig = widgetTypes.find((t) => t.value === type);
		return typeConfig?.icon || LayoutDashboard;
	}

	function formatValue(value: any): string {
		if (typeof value === 'number') {
			if (value >= 1000000) {
				return (value / 1000000).toFixed(1) + 'M';
			} else if (value >= 1000) {
				return (value / 1000).toFixed(1) + 'K';
			}
			return value.toLocaleString();
		}
		return String(value || '-');
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
			<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
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
					<p class="mb-4 text-muted-foreground">
						Add widgets to visualize your data
					</p>
					<Button onclick={() => (addWidgetDialogOpen = true)}>
						<Plus class="mr-2 h-4 w-4" />
						Add Widget
					</Button>
				</Card.Content>
			</Card.Root>
		{:else}
			<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
				{#each dashboard.widgets as widget (widget.id)}
					{@const WidgetIcon = getWidgetIcon(widget.type)}
					{@const data = widgetData[widget.id]}
					<Card.Root
						class="relative {widget.type === 'kpi'
							? ''
							: widget.size.w >= 6
								? 'md:col-span-2'
								: ''}"
					>
						{#if editMode}
							<div class="absolute right-2 top-2 z-10 flex gap-1">
								<Button
									variant="ghost"
									size="icon"
									class="h-7 w-7"
									onclick={() => openEditWidget(widget)}
								>
									<Edit2 class="h-3.5 w-3.5" />
								</Button>
								<Button
									variant="ghost"
									size="icon"
									class="h-7 w-7 text-destructive"
									onclick={() => handleDeleteWidget(widget)}
								>
									<Trash2 class="h-3.5 w-3.5" />
								</Button>
							</div>
						{/if}

						<Card.Header class="pb-2">
							<div class="flex items-center gap-2">
								<WidgetIcon class="h-4 w-4 text-muted-foreground" />
								<Card.Title class="text-sm font-medium">{widget.title}</Card.Title>
							</div>
						</Card.Header>
						<Card.Content>
							{#if widget.type === 'kpi'}
								<!-- KPI Widget -->
								<div class="text-center">
									<div class="text-3xl font-bold">
										{formatValue(data?.value ?? 0)}
									</div>
									{#if data?.change_percent !== null && data?.change_percent !== undefined}
										<div
											class="mt-1 text-sm {data.change_type === 'increase'
												? 'text-green-600'
												: data.change_type === 'decrease'
													? 'text-red-600'
													: 'text-muted-foreground'}"
										>
											{data.change_percent >= 0 ? '+' : ''}{data.change_percent.toFixed(1)}%
										</div>
									{/if}
								</div>
							{:else if widget.type === 'table' || widget.type === 'report'}
								<!-- Table/Report Widget -->
								{#if data?.data && Array.isArray(data.data)}
									<div class="max-h-48 overflow-auto">
										<table class="w-full text-sm">
											<tbody>
												{#each data.data.slice(0, 5) as row, i}
													<tr class="border-b last:border-0">
														<td class="py-2 font-medium">
															{Object.values(row)[0] || '-'}
														</td>
														<td class="py-2 text-right text-muted-foreground">
															{formatValue(Object.values(row)[1])}
														</td>
													</tr>
												{/each}
											</tbody>
										</table>
										{#if data.data.length > 5}
											<p class="mt-2 text-center text-xs text-muted-foreground">
												+{data.data.length - 5} more
											</p>
										{/if}
									</div>
								{:else}
									<p class="py-4 text-center text-muted-foreground">No data</p>
								{/if}
							{:else if widget.type === 'activity'}
								<!-- Activity Feed Widget -->
								<p class="py-4 text-center text-muted-foreground">
									Activity feed coming soon
								</p>
							{:else if widget.type === 'tasks'}
								<!-- Tasks Widget -->
								<p class="py-4 text-center text-muted-foreground">
									Tasks widget coming soon
								</p>
							{:else}
								<!-- Generic/Chart Widget -->
								<p class="py-4 text-center text-muted-foreground">
									{widget.type} widget
								</p>
							{/if}
						</Card.Content>
					</Card.Root>
				{/each}
			</div>
		{/if}
	{/if}
</div>

<!-- Add Widget Dialog -->
<Dialog.Root bind:open={addWidgetDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Add Widget</Dialog.Title>
			<Dialog.Description>Choose a widget type and configure it</Dialog.Description>
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
							class="flex flex-col items-center gap-1 rounded-lg border p-3 text-center transition-colors hover:bg-muted {widgetType ===
							type.value
								? 'border-primary bg-primary/5'
								: ''}"
							onclick={() => (widgetType = type.value)}
						>
							<Icon class="h-5 w-5" />
							<span class="text-xs">{type.label}</span>
						</button>
					{/each}
				</div>
			</div>

			{#if widgetType === 'report' || widgetType === 'chart' || widgetType === 'table'}
				<div class="space-y-2">
					<Label>Select Report</Label>
					<Select.Root type="single" bind:value={selectedReportId}>
						<Select.Trigger>
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
			{/if}
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (addWidgetDialogOpen = false)}>Cancel</Button>
			<Button onclick={handleAddWidget} disabled={!widgetTitle.trim()}>Add Widget</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Edit Widget Dialog -->
<Dialog.Root bind:open={editWidgetDialogOpen}>
	<Dialog.Content>
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
							class="flex flex-col items-center gap-1 rounded-lg border p-3 text-center transition-colors hover:bg-muted {widgetType ===
							type.value
								? 'border-primary bg-primary/5'
								: ''}"
							onclick={() => (widgetType = type.value)}
						>
							<Icon class="h-5 w-5" />
							<span class="text-xs">{type.label}</span>
						</button>
					{/each}
				</div>
			</div>

			{#if widgetType === 'report' || widgetType === 'chart' || widgetType === 'table'}
				<div class="space-y-2">
					<Label>Select Report</Label>
					<Select.Root type="single" bind:value={selectedReportId}>
						<Select.Trigger>
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
			{/if}
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (editWidgetDialogOpen = false)}>Cancel</Button>
			<Button onclick={handleUpdateWidget} disabled={!widgetTitle.trim()}>Save Changes</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

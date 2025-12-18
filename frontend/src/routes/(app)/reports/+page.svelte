<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import * as Tabs from '$lib/components/ui/tabs';
	import {
		Plus,
		Search,
		MoreVertical,
		Star,
		StarOff,
		Copy,
		Trash2,
		Play,
		Download,
		BarChart2,
		PieChart,
		LineChart,
		Table,
		LayoutGrid,
		FileText,
		Clock,
		Edit,
		Users
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { reportsApi, type Report, type ReportType } from '$lib/api/reports';
	import { modulesApi, type Module } from '$lib/api/modules';

	let reports = $state<Report[]>([]);
	let modules = $state<Module[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let selectedModule = $state<string>('all');
	let selectedType = $state<string>('all');
	let activeTab = $state('all');
	let deleteDialogOpen = $state(false);
	let reportToDelete = $state<Report | null>(null);

	const typeIcons: Record<ReportType, typeof Table> = {
		table: Table,
		chart: BarChart2,
		summary: FileText,
		matrix: LayoutGrid,
		pivot: LayoutGrid,
		cohort: Users
	};

	const chartIcons: Record<string, typeof BarChart2> = {
		bar: BarChart2,
		line: LineChart,
		pie: PieChart,
		doughnut: PieChart,
		area: LineChart
	};

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [reportsData, modulesData] = await Promise.all([
				reportsApi.list({ per_page: 100 }),
				modulesApi.getActive()
			]);
			reports = reportsData.data;
			modules = modulesData;
		} catch (error) {
			console.error('Failed to load reports:', error);
			toast.error('Failed to load reports');
		} finally {
			loading = false;
		}
	}

	const filteredReports = $derived(() => {
		let result = reports;

		// Filter by tab
		if (activeTab === 'favorites') {
			result = result.filter((r) => r.is_favorite);
		} else if (activeTab === 'my') {
			result = result.filter((r) => !r.is_public);
		} else if (activeTab === 'shared') {
			result = result.filter((r) => r.is_public);
		}

		// Filter by search
		if (searchQuery) {
			const query = searchQuery.toLowerCase();
			result = result.filter(
				(r) =>
					r.name.toLowerCase().includes(query) ||
					r.description?.toLowerCase().includes(query) ||
					r.module?.name.toLowerCase().includes(query)
			);
		}

		// Filter by module
		if (selectedModule !== 'all') {
			result = result.filter((r) => String(r.module_id) === selectedModule);
		}

		// Filter by type
		if (selectedType !== 'all') {
			result = result.filter((r) => r.type === selectedType);
		}

		return result;
	});

	async function handleToggleFavorite(report: Report) {
		try {
			const { is_favorite } = await reportsApi.toggleFavorite(report.id);
			reports = reports.map((r) => (r.id === report.id ? { ...r, is_favorite } : r));
			toast.success(is_favorite ? 'Added to favorites' : 'Removed from favorites');
		} catch (error) {
			console.error('Failed to toggle favorite:', error);
			toast.error('Failed to update favorite status');
		}
	}

	async function handleDuplicate(report: Report) {
		try {
			const duplicated = await reportsApi.duplicate(report.id);
			reports = [...reports, duplicated];
			toast.success('Report duplicated');
		} catch (error) {
			console.error('Failed to duplicate report:', error);
			toast.error('Failed to duplicate report');
		}
	}

	async function handleDelete() {
		if (!reportToDelete) return;

		try {
			await reportsApi.delete(reportToDelete.id);
			reports = reports.filter((r) => r.id !== reportToDelete!.id);
			toast.success('Report deleted');
			deleteDialogOpen = false;
			reportToDelete = null;
		} catch (error) {
			console.error('Failed to delete report:', error);
			toast.error('Failed to delete report');
		}
	}

	async function handleExport(report: Report, format: 'csv' | 'json') {
		try {
			const blob = await reportsApi.export(report.id, format);
			const url = window.URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = `${report.name}.${format}`;
			document.body.appendChild(a);
			a.click();
			window.URL.revokeObjectURL(url);
			document.body.removeChild(a);
			toast.success('Report exported');
		} catch (error) {
			console.error('Failed to export report:', error);
			toast.error('Failed to export report');
		}
	}

	function formatDate(dateString: string | null): string {
		if (!dateString) return 'Never';
		return new Date(dateString).toLocaleDateString();
	}

	function getReportIcon(report: Report) {
		if (report.type === 'chart' && report.chart_type) {
			return chartIcons[report.chart_type] || BarChart2;
		}
		return typeIcons[report.type] || FileText;
	}
</script>

<svelte:head>
	<title>Reports | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Reports</h1>
			<p class="text-muted-foreground">Create and manage your reports</p>
		</div>
		<Button onclick={() => goto('/reports/new')}>
			<Plus class="mr-2 h-4 w-4" />
			Create Report
		</Button>
	</div>

	<!-- Tabs -->
	<Tabs.Root bind:value={activeTab} class="mb-6">
		<Tabs.List>
			<Tabs.Trigger value="all">All Reports</Tabs.Trigger>
			<Tabs.Trigger value="favorites">
				<Star class="mr-1 h-3.5 w-3.5" />
				Favorites
			</Tabs.Trigger>
			<Tabs.Trigger value="my">My Reports</Tabs.Trigger>
			<Tabs.Trigger value="shared">Shared</Tabs.Trigger>
		</Tabs.List>
	</Tabs.Root>

	<!-- Filters -->
	<div class="mb-6 flex flex-wrap items-center gap-4">
		<div class="relative flex-1">
			<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
			<Input
				type="search"
				placeholder="Search reports..."
				class="pl-9"
				bind:value={searchQuery}
			/>
		</div>

		<Select.Root type="single" bind:value={selectedModule}>
			<Select.Trigger class="w-[180px]">
				{selectedModule === 'all'
					? 'All Modules'
					: modules.find((m) => String(m.id) === selectedModule)?.name || 'Select Module'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="all">All Modules</Select.Item>
				{#each modules as module}
					<Select.Item value={String(module.id)}>{module.name}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>

		<Select.Root type="single" bind:value={selectedType}>
			<Select.Trigger class="w-[150px]">
				{selectedType === 'all'
					? 'All Types'
					: selectedType.charAt(0).toUpperCase() + selectedType.slice(1)}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="all">All Types</Select.Item>
				<Select.Item value="table">Table</Select.Item>
				<Select.Item value="chart">Chart</Select.Item>
				<Select.Item value="summary">Summary</Select.Item>
				<Select.Item value="matrix">Matrix</Select.Item>
				<Select.Item value="pivot">Pivot</Select.Item>
				<Select.Item value="cohort">Cohort</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Reports Grid -->
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading reports...</div>
		</div>
	{:else if filteredReports().length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<BarChart2 class="mb-4 h-12 w-12 text-muted-foreground" />
				<h3 class="mb-2 text-lg font-medium">No reports found</h3>
				<p class="mb-4 text-muted-foreground">
					{searchQuery || selectedModule !== 'all' || selectedType !== 'all'
						? 'Try adjusting your filters'
						: 'Create your first report to analyze your data'}
				</p>
				{#if !searchQuery && selectedModule === 'all' && selectedType === 'all'}
					<Button onclick={() => goto('/reports/new')}>
						<Plus class="mr-2 h-4 w-4" />
						Create Report
					</Button>
				{/if}
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each filteredReports() as report (report.id)}
				{@const ReportIcon = getReportIcon(report)}
				<Card.Root class="transition-shadow hover:shadow-md">
					<Card.Header class="pb-3">
						<div class="flex items-start justify-between">
							<div class="flex items-center gap-3">
								<div
									class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10"
								>
									<ReportIcon class="h-5 w-5 text-primary" />
								</div>
								<div>
									<Card.Title class="text-base">
										<a href="/reports/{report.id}" class="hover:underline">
											{report.name}
										</a>
									</Card.Title>
									{#if report.module}
										<p class="text-sm text-muted-foreground">{report.module.name}</p>
									{/if}
								</div>
							</div>
							<div class="flex items-center gap-1">
								<Button
									variant="ghost"
									size="icon"
									class="h-8 w-8"
									onclick={() => handleToggleFavorite(report)}
								>
									{#if report.is_favorite}
										<Star class="h-4 w-4 fill-yellow-400 text-yellow-400" />
									{:else}
										<StarOff class="h-4 w-4 text-muted-foreground" />
									{/if}
								</Button>
								<DropdownMenu.Root>
									<DropdownMenu.Trigger>
										{#snippet child({ props })}
											<Button variant="ghost" size="icon" class="h-8 w-8" {...props}>
												<MoreVertical class="h-4 w-4" />
											</Button>
										{/snippet}
									</DropdownMenu.Trigger>
									<DropdownMenu.Content align="end">
										<DropdownMenu.Item onclick={() => goto(`/reports/${report.id}`)}>
											<Play class="mr-2 h-4 w-4" />
											Run Report
										</DropdownMenu.Item>
										<DropdownMenu.Item onclick={() => goto(`/reports/${report.id}?edit=true`)}>
											<Edit class="mr-2 h-4 w-4" />
											Edit
										</DropdownMenu.Item>
										<DropdownMenu.Separator />
										<DropdownMenu.Item onclick={() => handleDuplicate(report)}>
											<Copy class="mr-2 h-4 w-4" />
											Duplicate
										</DropdownMenu.Item>
										<DropdownMenu.Sub>
											<DropdownMenu.SubTrigger>
												<Download class="mr-2 h-4 w-4" />
												Export
											</DropdownMenu.SubTrigger>
											<DropdownMenu.SubContent>
												<DropdownMenu.Item onclick={() => handleExport(report, 'csv')}>
													Export as CSV
												</DropdownMenu.Item>
												<DropdownMenu.Item onclick={() => handleExport(report, 'json')}>
													Export as JSON
												</DropdownMenu.Item>
											</DropdownMenu.SubContent>
										</DropdownMenu.Sub>
										<DropdownMenu.Separator />
										<DropdownMenu.Item
											class="text-destructive focus:text-destructive"
											onclick={() => {
												reportToDelete = report;
												deleteDialogOpen = true;
											}}
										>
											<Trash2 class="mr-2 h-4 w-4" />
											Delete
										</DropdownMenu.Item>
									</DropdownMenu.Content>
								</DropdownMenu.Root>
							</div>
						</div>
					</Card.Header>
					<Card.Content>
						{#if report.description}
							<p class="mb-3 text-sm text-muted-foreground line-clamp-2">
								{report.description}
							</p>
						{/if}
						<div class="flex flex-wrap items-center gap-2">
							<Badge variant="secondary">
								{report.type.charAt(0).toUpperCase() + report.type.slice(1)}
							</Badge>
							{#if report.chart_type}
								<Badge variant="outline">{report.chart_type}</Badge>
							{/if}
							{#if report.is_public}
								<Badge variant="outline" class="text-green-600">Public</Badge>
							{/if}
						</div>
						<div class="mt-3 flex items-center gap-4 text-xs text-muted-foreground">
							<span class="flex items-center gap-1">
								<Clock class="h-3 w-3" />
								Last run: {formatDate(report.last_run_at)}
							</span>
						</div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{/if}
</div>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Report</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete "{reportToDelete?.name}"? This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action onclick={handleDelete}>Delete</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>

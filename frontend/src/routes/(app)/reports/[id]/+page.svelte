<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import {
		ArrowLeft,
		Play,
		RefreshCw,
		Download,
		Edit,
		Star,
		StarOff,
		Copy,
		Trash2,
		MoreVertical,
		Clock,
		BarChart2,
		LineChart,
		PieChart,
		Table,
		FileText,
		Settings
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		reportsApi,
		type Report,
		type ReportResult,
		type ReportType
	} from '$lib/api/reports';
	import ReportBuilder from '$lib/components/reporting/ReportBuilder.svelte';
	import ReportPreview from '$lib/components/reporting/ReportPreview.svelte';

	let report = $state<Report | null>(null);
	let result = $state<ReportResult | null>(null);
	let loading = $state(true);
	let executing = $state(false);
	let cached = $state(false);
	let lastRunAt = $state<string | null>(null);
	let editMode = $state(false);
	let activeTab = $state('results');

	const reportId = $derived(Number($page.params.id));

	onMount(async () => {
		// Check if edit mode was requested via query param
		const urlParams = new URLSearchParams(window.location.search);
		if (urlParams.get('edit') === 'true') {
			editMode = true;
		}

		await loadReport();
	});

	async function loadReport() {
		loading = true;
		try {
			report = await reportsApi.get(reportId);
			// Auto-execute the report
			await executeReport();
		} catch (error) {
			console.error('Failed to load report:', error);
			toast.error('Failed to load report');
			goto('/reports');
		} finally {
			loading = false;
		}
	}

	async function executeReport(refresh = false) {
		if (!report) return;

		executing = true;
		try {
			const response = await reportsApi.execute(report.id, refresh);
			result = response.data;
			cached = response.cached;
			lastRunAt = response.last_run_at;

			if (refresh) {
				toast.success('Report refreshed');
			}
		} catch (error) {
			console.error('Failed to execute report:', error);
			toast.error('Failed to execute report');
		} finally {
			executing = false;
		}
	}

	async function handleToggleFavorite() {
		if (!report) return;

		try {
			const { is_favorite } = await reportsApi.toggleFavorite(report.id);
			report = { ...report, is_favorite };
			toast.success(is_favorite ? 'Added to favorites' : 'Removed from favorites');
		} catch (error) {
			console.error('Failed to toggle favorite:', error);
			toast.error('Failed to update favorite status');
		}
	}

	async function handleDuplicate() {
		if (!report) return;

		try {
			const duplicated = await reportsApi.duplicate(report.id);
			toast.success('Report duplicated');
			goto(`/reports/${duplicated.id}`);
		} catch (error) {
			console.error('Failed to duplicate report:', error);
			toast.error('Failed to duplicate report');
		}
	}

	async function handleExport(format: 'csv' | 'json') {
		if (!report) return;

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

	async function handleSave(updatedReport: Report) {
		report = updatedReport;
		editMode = false;
		toast.success('Report updated');
		await executeReport(true);
	}

	function handleCancelEdit() {
		editMode = false;
	}

	function formatDate(dateString: string | null): string {
		if (!dateString) return 'Never';
		return new Date(dateString).toLocaleString();
	}

	function getReportIcon(type: ReportType) {
		const icons = {
			table: Table,
			chart: BarChart2,
			summary: FileText,
			matrix: Table,
			pivot: Table
		};
		return icons[type] || FileText;
	}
</script>

<svelte:head>
	<title>{report?.name || 'Report'} | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	{#if loading}
		<!-- Loading State -->
		<div class="space-y-4">
			<div class="flex items-center gap-4">
				<Skeleton class="h-8 w-8" />
				<Skeleton class="h-8 w-64" />
			</div>
			<Skeleton class="h-96 w-full" />
		</div>
	{:else if report}
		{#if editMode}
			<!-- Edit Mode -->
			<div class="mb-6 flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => (editMode = false)}>
					<ArrowLeft class="h-4 w-4" />
				</Button>
				<div>
					<h1 class="text-2xl font-bold">Edit Report</h1>
					<p class="text-muted-foreground">Modify your report configuration</p>
				</div>
			</div>
			<ReportBuilder {report} onSave={handleSave} onCancel={handleCancelEdit} />
		{:else}
			<!-- View Mode -->
			<!-- Header -->
			<div class="mb-6 flex items-start justify-between">
				<div class="flex items-center gap-4">
					<Button variant="ghost" size="icon" onclick={() => goto('/reports')}>
						<ArrowLeft class="h-4 w-4" />
					</Button>
					<div>
						<div class="flex items-center gap-2">
							<h1 class="text-2xl font-bold">{report.name}</h1>
							{#if report.is_public}
								<Badge variant="outline" class="text-green-600">Public</Badge>
							{/if}
							{#if cached}
								<Badge variant="secondary">Cached</Badge>
							{/if}
						</div>
						{#if report.description}
							<p class="text-muted-foreground">{report.description}</p>
						{/if}
						<div class="mt-1 flex items-center gap-4 text-sm text-muted-foreground">
							{#if report.module}
								<span>Module: {report.module.name}</span>
							{/if}
							<span class="flex items-center gap-1">
								<Clock class="h-3.5 w-3.5" />
								Last run: {formatDate(lastRunAt)}
							</span>
						</div>
					</div>
				</div>

				<div class="flex items-center gap-2">
					<Button variant="outline" onclick={() => executeReport(true)} disabled={executing}>
						<RefreshCw class="mr-2 h-4 w-4 {executing ? 'animate-spin' : ''}" />
						Refresh
					</Button>

					<Button variant="outline" onclick={handleToggleFavorite}>
						{#if report.is_favorite}
							<Star class="mr-2 h-4 w-4 fill-yellow-400 text-yellow-400" />
							Favorited
						{:else}
							<StarOff class="mr-2 h-4 w-4" />
							Favorite
						{/if}
					</Button>

					<Button onclick={() => (editMode = true)}>
						<Edit class="mr-2 h-4 w-4" />
						Edit
					</Button>

					<DropdownMenu.Root>
						<DropdownMenu.Trigger>
							{#snippet child({ props })}
								<Button variant="outline" size="icon" {...props}>
									<MoreVertical class="h-4 w-4" />
								</Button>
							{/snippet}
						</DropdownMenu.Trigger>
						<DropdownMenu.Content align="end">
							<DropdownMenu.Item onclick={handleDuplicate}>
								<Copy class="mr-2 h-4 w-4" />
								Duplicate
							</DropdownMenu.Item>
							<DropdownMenu.Sub>
								<DropdownMenu.SubTrigger>
									<Download class="mr-2 h-4 w-4" />
									Export
								</DropdownMenu.SubTrigger>
								<DropdownMenu.SubContent>
									<DropdownMenu.Item onclick={() => handleExport('csv')}>
										Export as CSV
									</DropdownMenu.Item>
									<DropdownMenu.Item onclick={() => handleExport('json')}>
										Export as JSON
									</DropdownMenu.Item>
								</DropdownMenu.SubContent>
							</DropdownMenu.Sub>
							<DropdownMenu.Separator />
							<DropdownMenu.Item onclick={() => goto(`/reports/${report!.id}/schedule`)}>
								<Settings class="mr-2 h-4 w-4" />
								Schedule
							</DropdownMenu.Item>
						</DropdownMenu.Content>
					</DropdownMenu.Root>
				</div>
			</div>

			<!-- Report Content -->
			<Tabs.Root bind:value={activeTab}>
				<Tabs.List class="mb-4">
					<Tabs.Trigger value="results">Results</Tabs.Trigger>
					<Tabs.Trigger value="config">Configuration</Tabs.Trigger>
				</Tabs.List>

				<Tabs.Content value="results">
					{#if executing && !result}
						<Card.Root>
							<Card.Content class="flex items-center justify-center py-12">
								<RefreshCw class="mr-2 h-5 w-5 animate-spin" />
								<span>Executing report...</span>
							</Card.Content>
						</Card.Root>
					{:else if result}
						<ReportPreview {result} reportType={report.type} chartType={report.chart_type} />
					{:else}
						<Card.Root>
							<Card.Content class="flex flex-col items-center justify-center py-12">
								<BarChart2 class="mb-4 h-12 w-12 text-muted-foreground" />
								<h3 class="mb-2 text-lg font-medium">No results</h3>
								<p class="mb-4 text-muted-foreground">
									Run the report to see results
								</p>
								<Button onclick={() => executeReport()}>
									<Play class="mr-2 h-4 w-4" />
									Run Report
								</Button>
							</Card.Content>
						</Card.Root>
					{/if}
				</Tabs.Content>

				<Tabs.Content value="config">
					<Card.Root>
						<Card.Header>
							<Card.Title>Report Configuration</Card.Title>
						</Card.Header>
						<Card.Content>
							<div class="grid gap-4 md:grid-cols-2">
								<div>
									<h4 class="mb-2 font-medium">Type</h4>
									<Badge variant="secondary">
										{report.type.charAt(0).toUpperCase() + report.type.slice(1)}
									</Badge>
									{#if report.chart_type}
										<Badge variant="outline" class="ml-2">{report.chart_type}</Badge>
									{/if}
								</div>

								{#if report.filters && report.filters.length > 0}
									<div>
										<h4 class="mb-2 font-medium">Filters</h4>
										<div class="flex flex-wrap gap-1">
											{#each report.filters as filter}
												<Badge variant="outline">
													{filter.field} {filter.operator} {filter.value}
												</Badge>
											{/each}
										</div>
									</div>
								{/if}

								{#if report.grouping && report.grouping.length > 0}
									<div>
										<h4 class="mb-2 font-medium">Grouping</h4>
										<div class="flex flex-wrap gap-1">
											{#each report.grouping as group}
												<Badge variant="outline">{group.field}</Badge>
											{/each}
										</div>
									</div>
								{/if}

								{#if report.aggregations && report.aggregations.length > 0}
									<div>
										<h4 class="mb-2 font-medium">Aggregations</h4>
										<div class="flex flex-wrap gap-1">
											{#each report.aggregations as agg}
												<Badge variant="outline">
													{agg.function}({agg.field})
												</Badge>
											{/each}
										</div>
									</div>
								{/if}

								{#if report.sorting && report.sorting.length > 0}
									<div>
										<h4 class="mb-2 font-medium">Sorting</h4>
										<div class="flex flex-wrap gap-1">
											{#each report.sorting as sort}
												<Badge variant="outline">
													{sort.field} ({sort.direction})
												</Badge>
											{/each}
										</div>
									</div>
								{/if}

								{#if report.date_range && report.date_range.type}
									<div>
										<h4 class="mb-2 font-medium">Date Range</h4>
										<Badge variant="outline">
											{report.date_range.type.replace(/_/g, ' ')}
										</Badge>
									</div>
								{/if}
							</div>
						</Card.Content>
					</Card.Root>
				</Tabs.Content>
			</Tabs.Root>
		{/if}
	{/if}
</div>

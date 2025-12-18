<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import { Separator } from '$lib/components/ui/separator';
	import {
		BarChart2,
		LineChart,
		PieChart,
		Table,
		Filter,
		Group,
		Calculator,
		Calendar,
		Save,
		Play,
		Download,
		X,
		Plus,
		Trash2,
		ArrowUpDown,
		RefreshCw
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { modulesApi } from '$lib/api/modules';
	import {
		reportsApi,
		type Report,
		type ReportType,
		type ChartType,
		type ReportGrouping,
		type ReportAggregation,
		type ReportSorting,
		type ReportDateRange,
		type ModuleField,
		type ReportResult,
		type CreateReportRequest,
		type UpdateReportRequest
	} from '$lib/api/reports';
	import type { FilterConfig } from '$lib/types/filters';
	import ReportFilterBuilder from './ReportFilterBuilder.svelte';
	import ReportPreview from './ReportPreview.svelte';

	interface Props {
		report?: Report;
		onSave?: (report: Report) => void;
		onCancel?: () => void;
	}

	let { report, onSave, onCancel }: Props = $props();

	// Form state
	let name = $state(report?.name || '');
	let description = $state(report?.description || '');
	let moduleId = $state<number | null>(report?.module_id || null);
	let reportType = $state<ReportType>(report?.type || 'table');
	let chartType = $state<ChartType | null>(report?.chart_type || null);
	let isPublic = $state(report?.is_public || false);
	let filters = $state<FilterConfig[]>(report?.filters || []);
	let grouping = $state<ReportGrouping[]>(report?.grouping || []);
	let aggregations = $state<ReportAggregation[]>(report?.aggregations || []);
	let sorting = $state<ReportSorting[]>(report?.sorting || []);
	let dateRange = $state<ReportDateRange>(report?.date_range || {});

	// UI state
	let modules = $state<{ id: number; name: string; api_name: string }[]>([]);
	let fields = $state<ModuleField[]>([]);
	let reportTypes = $state<Record<string, string>>({});
	let chartTypes = $state<Record<string, string>>({});
	let aggregationTypes = $state<Record<string, string>>({});
	let loading = $state(false);
	let previewLoading = $state(false);
	let previewResult = $state<ReportResult | null>(null);
	let activeTab = $state('config');

	onMount(async () => {
		await loadInitialData();
	});

	async function loadInitialData() {
		try {
			// Load modules
			const modulesResponse = await modulesApi.getAll();
			modules = modulesResponse.map((m) => ({ id: m.id, name: m.name, api_name: m.api_name }));

			// Load report types
			const types = await reportsApi.getTypes();
			reportTypes = types.report_types;
			chartTypes = types.chart_types;
			aggregationTypes = types.aggregations;

			// Load fields if module is already selected
			if (moduleId) {
				await loadModuleFields();
			}
		} catch (error) {
			console.error('Failed to load initial data:', error);
			toast.error('Failed to load report builder data');
		}
	}

	async function loadModuleFields() {
		if (!moduleId) {
			fields = [];
			return;
		}

		try {
			fields = await reportsApi.getFields(moduleId);
		} catch (error) {
			console.error('Failed to load module fields:', error);
			toast.error('Failed to load module fields');
		}
	}

	async function handleModuleChange(value: string | undefined) {
		if (value) {
			moduleId = parseInt(value);
			await loadModuleFields();
			// Reset dependent settings
			filters = [];
			grouping = [];
			aggregations = [];
			sorting = [];
		}
	}

	function addGrouping() {
		if (fields.length === 0) return;
		grouping = [...grouping, { field: fields[0].name }];
	}

	function removeGrouping(index: number) {
		grouping = grouping.filter((_, i) => i !== index);
	}

	function addAggregation() {
		aggregations = [...aggregations, { function: 'count', field: '*' }];
	}

	function removeAggregation(index: number) {
		aggregations = aggregations.filter((_, i) => i !== index);
	}

	function addSorting() {
		if (fields.length === 0) return;
		sorting = [...sorting, { field: fields[0].name, direction: 'asc' }];
	}

	function removeSorting(index: number) {
		sorting = sorting.filter((_, i) => i !== index);
	}

	async function runPreview() {
		previewLoading = true;
		try {
			previewResult = await reportsApi.preview({
				module_id: moduleId || undefined,
				type: reportType,
				chart_type: chartType || undefined,
				filters,
				grouping,
				aggregations,
				sorting,
				date_range: dateRange
			});
			activeTab = 'preview';
		} catch (error: any) {
			console.error('Preview failed:', error);
			toast.error(error.response?.data?.message || 'Failed to preview report');
		} finally {
			previewLoading = false;
		}
	}

	async function handleSave() {
		if (!name.trim()) {
			toast.error('Please enter a report name');
			return;
		}

		loading = true;
		try {
			const data: CreateReportRequest = {
				name,
				description: description || undefined,
				module_id: moduleId || undefined,
				type: reportType,
				chart_type: reportType === 'chart' && chartType ? chartType : undefined,
				is_public: isPublic,
				filters,
				grouping,
				aggregations,
				sorting,
				date_range: dateRange
			};

			let savedReport: Report;
			if (report?.id) {
				savedReport = await reportsApi.update(report.id, data as UpdateReportRequest);
				toast.success('Report updated successfully');
			} else {
				savedReport = await reportsApi.create(data);
				toast.success('Report created successfully');
			}

			onSave?.(savedReport);
		} catch (error: any) {
			console.error('Save failed:', error);
			toast.error(error.response?.data?.message || 'Failed to save report');
		} finally {
			loading = false;
		}
	}

	const chartTypeOptions = [
		{ value: 'bar', label: 'Bar Chart', icon: BarChart2 },
		{ value: 'line', label: 'Line Chart', icon: LineChart },
		{ value: 'pie', label: 'Pie Chart', icon: PieChart },
		{ value: 'doughnut', label: 'Doughnut', icon: PieChart },
		{ value: 'area', label: 'Area Chart', icon: LineChart },
		{ value: 'funnel', label: 'Funnel', icon: Filter },
		{ value: 'kpi', label: 'KPI Card', icon: Calculator }
	];

	const dateRangeOptions = [
		{ value: 'today', label: 'Today' },
		{ value: 'yesterday', label: 'Yesterday' },
		{ value: 'this_week', label: 'This Week' },
		{ value: 'last_week', label: 'Last Week' },
		{ value: 'this_month', label: 'This Month' },
		{ value: 'last_month', label: 'Last Month' },
		{ value: 'this_quarter', label: 'This Quarter' },
		{ value: 'last_quarter', label: 'Last Quarter' },
		{ value: 'this_year', label: 'This Year' },
		{ value: 'last_year', label: 'Last Year' },
		{ value: 'last_7_days', label: 'Last 7 Days' },
		{ value: 'last_30_days', label: 'Last 30 Days' },
		{ value: 'last_90_days', label: 'Last 90 Days' },
		{ value: 'custom', label: 'Custom Range' }
	];
</script>

<div class="flex h-full flex-col">
	<!-- Header -->
	<div class="flex items-center justify-between border-b px-6 py-4">
		<div>
			<h2 class="text-lg font-semibold">{report?.id ? 'Edit Report' : 'Create Report'}</h2>
			<p class="text-sm text-muted-foreground">
				Configure your report settings and preview the results
			</p>
		</div>
		<div class="flex items-center gap-2">
			<Button variant="outline" onclick={onCancel} disabled={loading}>
				<X class="mr-2 h-4 w-4" />
				Cancel
			</Button>
			<Button variant="outline" onclick={runPreview} disabled={previewLoading || !moduleId}>
				{#if previewLoading}
					<RefreshCw class="mr-2 h-4 w-4 animate-spin" />
				{:else}
					<Play class="mr-2 h-4 w-4" />
				{/if}
				Preview
			</Button>
			<Button onclick={handleSave} disabled={loading}>
				<Save class="mr-2 h-4 w-4" />
				{report?.id ? 'Update' : 'Save'} Report
			</Button>
		</div>
	</div>

	<!-- Content -->
	<Tabs.Root bind:value={activeTab} class="flex-1">
		<div class="border-b px-6">
			<Tabs.List>
				<Tabs.Trigger value="config">Configuration</Tabs.Trigger>
				<Tabs.Trigger value="filters">Filters</Tabs.Trigger>
				<Tabs.Trigger value="preview">Preview</Tabs.Trigger>
			</Tabs.List>
		</div>

		<div class="flex-1 overflow-auto p-6">
			<Tabs.Content value="config" class="mt-0">
				<div class="grid gap-6 lg:grid-cols-2">
					<!-- Basic Settings -->
					<Card.Root>
						<Card.Header>
							<Card.Title class="text-base">Basic Settings</Card.Title>
						</Card.Header>
						<Card.Content class="space-y-4">
							<div class="space-y-2">
								<Label for="name">Report Name</Label>
								<Input id="name" bind:value={name} placeholder="Enter report name" />
							</div>

							<div class="space-y-2">
								<Label for="description">Description</Label>
								<Textarea
									id="description"
									bind:value={description}
									placeholder="Optional description"
									rows={2}
								/>
							</div>

							<div class="space-y-2">
								<Label>Data Source</Label>
								<Select.Root
									type="single"
									value={moduleId?.toString()}
									onValueChange={handleModuleChange}
								>
									<Select.Trigger>
										<span>
											{moduleId
												? modules.find((m) => m.id === moduleId)?.name
												: 'Select a module'}
										</span>
									</Select.Trigger>
									<Select.Content>
										{#each modules as module}
											<Select.Item value={module.id.toString()}>{module.name}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>

							<div class="flex items-center justify-between">
								<div class="space-y-0.5">
									<Label>Public Report</Label>
									<p class="text-xs text-muted-foreground">Make this report visible to all users</p>
								</div>
								<Switch bind:checked={isPublic} />
							</div>
						</Card.Content>
					</Card.Root>

					<!-- Report Type -->
					<Card.Root>
						<Card.Header>
							<Card.Title class="text-base">Report Type</Card.Title>
						</Card.Header>
						<Card.Content class="space-y-4">
							<div class="space-y-2">
								<Label>Type</Label>
								<Select.Root
									type="single"
									value={reportType}
									onValueChange={(v) => {
										if (v) reportType = v as ReportType;
									}}
								>
									<Select.Trigger>
										<span>{reportTypes[reportType] || reportType}</span>
									</Select.Trigger>
									<Select.Content>
										{#each Object.entries(reportTypes) as [value, label]}
											<Select.Item {value}>{label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>

							{#if reportType === 'chart'}
								<div class="space-y-2">
									<Label>Chart Type</Label>
									<div class="grid grid-cols-4 gap-2">
										{#each chartTypeOptions as option}
											{@const IconComponent = option.icon}
											<Button
												variant={chartType === option.value ? 'default' : 'outline'}
												size="sm"
												class="flex-col gap-1 h-auto py-2"
												onclick={() => (chartType = option.value as ChartType)}
											>
												<IconComponent class="h-4 w-4" />
												<span class="text-xs">{option.label}</span>
											</Button>
										{/each}
									</div>
								</div>
							{/if}

							<Separator />

							<!-- Date Range -->
							<div class="space-y-2">
								<Label>Date Range</Label>
								<Select.Root
									type="single"
									value={dateRange.type || ''}
									onValueChange={(v) => {
										if (v) dateRange = { ...dateRange, type: v as any };
									}}
								>
									<Select.Trigger>
										<Calendar class="mr-2 h-4 w-4" />
										<span>
											{dateRange.type
												? dateRangeOptions.find((o) => o.value === dateRange.type)?.label
												: 'All time'}
										</span>
									</Select.Trigger>
									<Select.Content>
										<Select.Item value="">All Time</Select.Item>
										{#each dateRangeOptions as option}
											<Select.Item value={option.value}>{option.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>
						</Card.Content>
					</Card.Root>

					<!-- Grouping -->
					<Card.Root>
						<Card.Header>
							<Card.Title class="flex items-center justify-between text-base">
								<span class="flex items-center gap-2">
									<Group class="h-4 w-4" />
									Group By
								</span>
								<Button variant="outline" size="sm" onclick={addGrouping} disabled={!moduleId}>
									<Plus class="mr-1 h-3 w-3" />
									Add
								</Button>
							</Card.Title>
						</Card.Header>
						<Card.Content>
							{#if grouping.length === 0}
								<p class="text-sm text-muted-foreground">No grouping configured</p>
							{:else}
								<div class="space-y-2">
									{#each grouping as group, index}
										<div class="flex items-center gap-2">
											<Select.Root
												type="single"
												value={group.field}
												onValueChange={(v) => {
													if (v) {
														grouping[index] = { ...group, field: v };
													}
												}}
											>
												<Select.Trigger class="flex-1">
													<span>
														{fields.find((f) => f.name === group.field)?.label || group.field}
													</span>
												</Select.Trigger>
												<Select.Content>
													{#each fields as field}
														<Select.Item value={field.name}>{field.label}</Select.Item>
													{/each}
												</Select.Content>
											</Select.Root>
											<Button
												variant="ghost"
												size="icon"
												onclick={() => removeGrouping(index)}
											>
												<Trash2 class="h-4 w-4" />
											</Button>
										</div>
									{/each}
								</div>
							{/if}
						</Card.Content>
					</Card.Root>

					<!-- Aggregations -->
					<Card.Root>
						<Card.Header>
							<Card.Title class="flex items-center justify-between text-base">
								<span class="flex items-center gap-2">
									<Calculator class="h-4 w-4" />
									Aggregations
								</span>
								<Button variant="outline" size="sm" onclick={addAggregation}>
									<Plus class="mr-1 h-3 w-3" />
									Add
								</Button>
							</Card.Title>
						</Card.Header>
						<Card.Content>
							{#if aggregations.length === 0}
								<p class="text-sm text-muted-foreground">No aggregations configured (defaults to count)</p>
							{:else}
								<div class="space-y-2">
									{#each aggregations as agg, index}
										<div class="flex items-center gap-2">
											<Select.Root
												type="single"
												value={agg.function}
												onValueChange={(v) => {
													if (v) {
														aggregations[index] = { ...agg, function: v as any };
													}
												}}
											>
												<Select.Trigger class="w-32">
													<span>{aggregationTypes[agg.function] || agg.function}</span>
												</Select.Trigger>
												<Select.Content>
													{#each Object.entries(aggregationTypes) as [value, label]}
														<Select.Item {value}>{label}</Select.Item>
													{/each}
												</Select.Content>
											</Select.Root>

											<Select.Root
												type="single"
												value={agg.field}
												onValueChange={(v) => {
													if (v) {
														aggregations[index] = { ...agg, field: v };
													}
												}}
											>
												<Select.Trigger class="flex-1">
													<span>
														{agg.field === '*'
															? 'All Records'
															: fields.find((f) => f.name === agg.field)?.label || agg.field}
													</span>
												</Select.Trigger>
												<Select.Content>
													<Select.Item value="*">All Records</Select.Item>
													{#each fields.filter((f) => ['number', 'decimal', 'currency', 'percent'].includes(f.type)) as field}
														<Select.Item value={field.name}>{field.label}</Select.Item>
													{/each}
												</Select.Content>
											</Select.Root>

											<Button
												variant="ghost"
												size="icon"
												onclick={() => removeAggregation(index)}
											>
												<Trash2 class="h-4 w-4" />
											</Button>
										</div>
									{/each}
								</div>
							{/if}
						</Card.Content>
					</Card.Root>

					<!-- Sorting -->
					<Card.Root class="lg:col-span-2">
						<Card.Header>
							<Card.Title class="flex items-center justify-between text-base">
								<span class="flex items-center gap-2">
									<ArrowUpDown class="h-4 w-4" />
									Sorting
								</span>
								<Button variant="outline" size="sm" onclick={addSorting} disabled={!moduleId}>
									<Plus class="mr-1 h-3 w-3" />
									Add
								</Button>
							</Card.Title>
						</Card.Header>
						<Card.Content>
							{#if sorting.length === 0}
								<p class="text-sm text-muted-foreground">No sorting configured</p>
							{:else}
								<div class="flex flex-wrap gap-2">
									{#each sorting as sort, index}
										<Badge variant="secondary" class="flex items-center gap-2 py-1 pr-1">
											<Select.Root
												type="single"
												value={sort.field}
												onValueChange={(v) => {
													if (v) {
														sorting[index] = { ...sort, field: v };
													}
												}}
											>
												<Select.Trigger class="h-auto border-0 bg-transparent p-0 text-sm">
													<span>
														{fields.find((f) => f.name === sort.field)?.label || sort.field}
													</span>
												</Select.Trigger>
												<Select.Content>
													{#each fields as field}
														<Select.Item value={field.name}>{field.label}</Select.Item>
													{/each}
												</Select.Content>
											</Select.Root>

											<Button
												variant="ghost"
												size="sm"
												class="h-auto px-1 py-0"
												onclick={() => {
													sorting[index] = {
														...sort,
														direction: sort.direction === 'asc' ? 'desc' : 'asc'
													};
												}}
											>
												{sort.direction === 'asc' ? '↑' : '↓'}
											</Button>

											<Button
												variant="ghost"
												size="sm"
												class="h-auto px-1 py-0"
												onclick={() => removeSorting(index)}
											>
												<X class="h-3 w-3" />
											</Button>
										</Badge>
									{/each}
								</div>
							{/if}
						</Card.Content>
					</Card.Root>
				</div>
			</Tabs.Content>

			<Tabs.Content value="filters" class="mt-0">
				<ReportFilterBuilder {fields} bind:filters />
			</Tabs.Content>

			<Tabs.Content value="preview" class="mt-0">
				<ReportPreview
					result={previewResult}
					{reportType}
					{chartType}
					loading={previewLoading}
					onRefresh={runPreview}
				/>
			</Tabs.Content>
		</div>
	</Tabs.Root>
</div>

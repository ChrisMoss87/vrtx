<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import * as Card from '$lib/components/ui/card';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import {
		FileText,
		Search,
		MoreVertical,
		Trash2,
		Play,
		Users,
		Lock,
		ArrowLeft,
		Loader2
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { reportTemplatesApi, type ReportTemplate } from '$lib/api/report-templates';

	let templates = $state<ReportTemplate[]>([]);
	let loading = $state(true);
	let search = $state('');
	let deleting = $state<number | null>(null);
	let deleteConfirmOpen = $state(false);
	let templateToDelete = $state<ReportTemplate | null>(null);

	const filteredTemplates = $derived(
		templates.filter(
			(t) =>
				t.name.toLowerCase().includes(search.toLowerCase()) ||
				(t.description?.toLowerCase().includes(search.toLowerCase()) ?? false)
		)
	);

	onMount(async () => {
		await loadTemplates();
	});

	async function loadTemplates() {
		loading = true;
		try {
			const response = await reportTemplatesApi.list({ per_page: 100 });
			templates = response.data;
		} catch (error) {
			console.error('Failed to load templates:', error);
			toast.error('Failed to load templates');
		} finally {
			loading = false;
		}
	}

	async function handleApplyTemplate(template: ReportTemplate) {
		try {
			const report = await reportTemplatesApi.apply(template.id, {
				name: `${template.name} Report`,
				description: template.description || undefined,
				is_public: false
			});
			toast.success('Report created from template');
			goto(`/reports/${report.id}`);
		} catch (error) {
			console.error('Failed to apply template:', error);
			toast.error('Failed to create report from template');
		}
	}

	function confirmDelete(template: ReportTemplate) {
		templateToDelete = template;
		deleteConfirmOpen = true;
	}

	async function handleDelete() {
		if (!templateToDelete) return;

		deleting = templateToDelete.id;
		try {
			await reportTemplatesApi.delete(templateToDelete.id);
			templates = templates.filter((t) => t.id !== templateToDelete!.id);
			toast.success('Template deleted');
		} catch (error) {
			console.error('Failed to delete template:', error);
			toast.error('Failed to delete template');
		} finally {
			deleting = null;
			deleteConfirmOpen = false;
			templateToDelete = null;
		}
	}

	function getTypeLabel(type: string): string {
		const labels: Record<string, string> = {
			table: 'Table',
			chart: 'Chart',
			summary: 'Summary',
			matrix: 'Matrix',
			pivot: 'Pivot'
		};
		return labels[type] || type;
	}

	function getChartTypeLabel(type: string | null): string {
		if (!type) return '';
		const labels: Record<string, string> = {
			bar: 'Bar',
			line: 'Line',
			pie: 'Pie',
			doughnut: 'Doughnut',
			area: 'Area',
			funnel: 'Funnel',
			kpi: 'KPI'
		};
		return labels[type] || type;
	}
</script>

<svelte:head>
	<title>Report Templates</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" href="/reports">
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<h1 class="text-2xl font-bold">Report Templates</h1>
				<p class="text-muted-foreground">
					Manage your report templates for quick report creation
				</p>
			</div>
		</div>
	</div>

	<!-- Search -->
	<div class="relative max-w-md">
		<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
		<Input placeholder="Search templates..." class="pl-9" bind:value={search} />
	</div>

	<!-- Templates Grid -->
	{#if loading}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each Array(6) as _}
				<Card.Root>
					<Card.Header>
						<Skeleton class="h-5 w-32" />
						<Skeleton class="h-4 w-full" />
					</Card.Header>
					<Card.Content>
						<div class="flex gap-2">
							<Skeleton class="h-5 w-16" />
							<Skeleton class="h-5 w-20" />
						</div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{:else if filteredTemplates.length === 0}
		<div class="flex flex-col items-center justify-center py-16 text-center">
			<FileText class="mb-4 h-16 w-16 text-muted-foreground" />
			<h2 class="mb-2 text-xl font-semibold">No templates found</h2>
			<p class="mb-4 text-muted-foreground max-w-md">
				{search
					? 'Try a different search term'
					: 'Save a report as a template to create reusable report configurations'}
			</p>
			<Button href="/reports/new">Create a Report</Button>
		</div>
	{:else}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each filteredTemplates as template}
				<Card.Root class="relative group">
					<Card.Header>
						<div class="flex items-start justify-between">
							<div class="flex-1">
								<Card.Title class="flex items-center gap-2">
									<span>{template.name}</span>
									{#if template.is_public}
										<Badge variant="secondary" class="text-xs">
											<Users class="mr-1 h-3 w-3" />
											Public
										</Badge>
									{:else}
										<Lock class="h-3 w-3 text-muted-foreground" />
									{/if}
								</Card.Title>
								{#if template.description}
									<Card.Description class="line-clamp-2">
										{template.description}
									</Card.Description>
								{/if}
							</div>
							<DropdownMenu.Root>
								<DropdownMenu.Trigger>
									<Button variant="ghost" size="icon" class="h-8 w-8">
										<MoreVertical class="h-4 w-4" />
									</Button>
								</DropdownMenu.Trigger>
								<DropdownMenu.Content align="end">
									<DropdownMenu.Item onclick={() => handleApplyTemplate(template)}>
										<Play class="mr-2 h-4 w-4" />
										Create Report
									</DropdownMenu.Item>
									<DropdownMenu.Separator />
									<DropdownMenu.Item
										class="text-destructive"
										onclick={() => confirmDelete(template)}
									>
										<Trash2 class="mr-2 h-4 w-4" />
										Delete
									</DropdownMenu.Item>
								</DropdownMenu.Content>
							</DropdownMenu.Root>
						</div>
					</Card.Header>
					<Card.Content>
						<div class="flex flex-wrap gap-2">
							<Badge variant="outline">{getTypeLabel(template.type)}</Badge>
							{#if template.chart_type}
								<Badge variant="outline">{getChartTypeLabel(template.chart_type)}</Badge>
							{/if}
							{#if template.module}
								<Badge variant="secondary">{template.module.name}</Badge>
							{/if}
						</div>
						{#if template.user}
							<p class="mt-3 text-xs text-muted-foreground">
								Created by {template.user.name}
							</p>
						{/if}
					</Card.Content>
					<Card.Footer>
						<Button
							variant="outline"
							class="w-full"
							onclick={() => handleApplyTemplate(template)}
						>
							<Play class="mr-2 h-4 w-4" />
							Use Template
						</Button>
					</Card.Footer>
				</Card.Root>
			{/each}
		</div>
	{/if}
</div>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root bind:open={deleteConfirmOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Template</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete "{templateToDelete?.name}"? This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
				onclick={handleDelete}
				disabled={deleting !== null}
			>
				{#if deleting !== null}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Delete
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>

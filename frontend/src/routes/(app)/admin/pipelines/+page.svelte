<script lang="ts">
	import { onMount } from 'svelte';
	import type { Pipeline } from '$lib/api/pipelines';
	import { getPipelines, deletePipeline } from '$lib/api/pipelines';
	import { getModules } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Spinner } from '$lib/components/ui/spinner';
	import * as Table from '$lib/components/ui/table';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { toast } from 'svelte-sonner';
	import Plus from 'lucide-svelte/icons/plus';
	import Pencil from 'lucide-svelte/icons/pencil';
	import Trash2 from 'lucide-svelte/icons/trash-2';
	import LayoutGrid from 'lucide-svelte/icons/layout-grid';
	import { goto } from '$app/navigation';

	let loading = $state(true);
	let pipelines = $state<Pipeline[]>([]);
	let modules = $state<Array<{ id: number; name: string; api_name: string }>>([]);
	let deleteConfirmOpen = $state(false);
	let pipelineToDelete = $state<Pipeline | null>(null);
	let deleting = $state(false);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		try {
			loading = true;
			const [pipelinesData, modulesData] = await Promise.all([getPipelines(), getModules()]);
			pipelines = pipelinesData;
			modules = modulesData;
		} catch (error: any) {
			console.error('Failed to load data:', error);
			toast.error('Failed to load pipelines');
		} finally {
			loading = false;
		}
	}

	function getModuleName(moduleId: number): string {
		return modules.find((m) => m.id === moduleId)?.name || 'Unknown';
	}

	function confirmDelete(pipeline: Pipeline) {
		pipelineToDelete = pipeline;
		deleteConfirmOpen = true;
	}

	async function handleDelete() {
		if (!pipelineToDelete) return;

		deleting = true;
		try {
			await deletePipeline(pipelineToDelete.id);
			pipelines = pipelines.filter((p) => p.id !== pipelineToDelete!.id);
			toast.success('Pipeline deleted successfully');
			deleteConfirmOpen = false;
			pipelineToDelete = null;
		} catch (error: any) {
			console.error('Failed to delete pipeline:', error);
			toast.error('Failed to delete pipeline');
		} finally {
			deleting = false;
		}
	}
</script>

<svelte:head>
	<title>Pipelines | Admin</title>
</svelte:head>

<div class="container mx-auto py-6">
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Pipelines</h1>
			<p class="text-muted-foreground">Manage sales pipelines and kanban boards</p>
		</div>
		<Button href="/admin/pipelines/create">
			<Plus class="mr-2 h-4 w-4" />
			Create Pipeline
		</Button>
	</div>

	{#if loading}
		<div class="flex h-64 items-center justify-center">
			<Spinner class="h-8 w-8" />
		</div>
	{:else if pipelines.length === 0}
		<div class="rounded-lg border border-dashed p-12 text-center">
			<LayoutGrid class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
			<h3 class="mb-2 text-lg font-semibold">No pipelines yet</h3>
			<p class="mb-4 text-muted-foreground">Create your first pipeline to visualize your deals</p>
			<Button href="/admin/pipelines/create">
				<Plus class="mr-2 h-4 w-4" />
				Create Pipeline
			</Button>
		</div>
	{:else}
		<div class="rounded-md border">
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Name</Table.Head>
						<Table.Head>Module</Table.Head>
						<Table.Head>Stages</Table.Head>
						<Table.Head>Status</Table.Head>
						<Table.Head class="w-[100px]">Actions</Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each pipelines as pipeline}
						<Table.Row>
							<Table.Cell class="font-medium">{pipeline.name}</Table.Cell>
							<Table.Cell>{getModuleName(pipeline.module_id)}</Table.Cell>
							<Table.Cell>
								<div class="flex items-center gap-1">
									{#if pipeline.stages}
										{#each pipeline.stages.slice(0, 5) as stage}
											<div
												class="h-3 w-3 rounded-full"
												style="background-color: {stage.color}"
												title={stage.name}
											></div>
										{/each}
										{#if pipeline.stages.length > 5}
											<span class="text-xs text-muted-foreground">
												+{pipeline.stages.length - 5}
											</span>
										{/if}
									{:else}
										<span class="text-sm text-muted-foreground">No stages</span>
									{/if}
								</div>
							</Table.Cell>
							<Table.Cell>
								<Badge variant={pipeline.is_active ? 'default' : 'secondary'}>
									{pipeline.is_active ? 'Active' : 'Inactive'}
								</Badge>
							</Table.Cell>
							<Table.Cell>
								<div class="flex items-center gap-1">
									<Button variant="ghost" size="sm" href="/admin/pipelines/{pipeline.id}/edit">
										<Pencil class="h-4 w-4" />
									</Button>
									<Button
										variant="ghost"
										size="sm"
										class="text-destructive hover:text-destructive"
										onclick={() => confirmDelete(pipeline)}
									>
										<Trash2 class="h-4 w-4" />
									</Button>
								</div>
							</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
		</div>
	{/if}
</div>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root bind:open={deleteConfirmOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Pipeline</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete "{pipelineToDelete?.name}"? This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel disabled={deleting}>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				class="text-destructive-foreground bg-destructive hover:bg-destructive/90"
				onclick={handleDelete}
				disabled={deleting}
			>
				{#if deleting}
					<Spinner class="mr-2 h-4 w-4" />
				{/if}
				Delete
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>

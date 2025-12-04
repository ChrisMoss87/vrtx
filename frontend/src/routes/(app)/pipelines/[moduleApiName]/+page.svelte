<script lang="ts">
	import { page } from '$app/stores';
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { modulesApi, type Module } from '$lib/api/modules';
	import { getPipelinesForModule, type Pipeline } from '$lib/api/pipelines';
	import { Button } from '$lib/components/ui/button';
	import { ArrowLeft, Plus, Settings, LayoutGrid, Table } from 'lucide-svelte';
	import * as Card from '$lib/components/ui/card';
	import { Skeleton } from '$lib/components/ui/skeleton';

	const moduleApiName = $derived($page.params.moduleApiName as string);

	let module = $state<Module | null>(null);
	let pipelines = $state<Pipeline[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		error = null;
		try {
			[module, pipelines] = await Promise.all([
				modulesApi.getByApiName(moduleApiName),
				getPipelinesForModule(moduleApiName)
			]);
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load data';
		} finally {
			loading = false;
		}
	}

	function openPipeline(pipelineId: number) {
		goto(`/pipelines/${moduleApiName}/${pipelineId}`);
	}

	function createPipeline() {
		goto(`/pipelines/${moduleApiName}/create`);
	}

	function goToDataTable() {
		goto(`/records/${moduleApiName}`);
	}
</script>

<div class="container mx-auto py-8">
	{#if loading}
		<div class="space-y-6">
			<Skeleton class="h-12 w-64" />
			<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
				{#each [1, 2, 3] as _}
					<Skeleton class="h-40 w-full" />
				{/each}
			</div>
		</div>
	{:else if error}
		<div class="rounded-lg border border-destructive p-6">
			<p class="text-destructive">{error}</p>
			<Button variant="outline" class="mt-4" onclick={loadData}>Try Again</Button>
		</div>
	{:else if module}
		<div class="mb-6 flex items-center justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => goto('/dashboard')}>
					<ArrowLeft class="h-4 w-4" />
				</Button>
				<div>
					<h1 class="text-3xl font-bold">{module.name} Pipelines</h1>
					<p class="text-muted-foreground mt-1">
						Manage sales pipelines for {module.name.toLowerCase()}
					</p>
				</div>
			</div>
			<div class="flex gap-2">
				<Button variant="outline" onclick={goToDataTable}>
					<Table class="mr-2 h-4 w-4" />
					Table View
				</Button>
				<Button onclick={createPipeline}>
					<Plus class="mr-2 h-4 w-4" />
					New Pipeline
				</Button>
			</div>
		</div>

		{#if pipelines.length === 0}
			<Card.Root class="p-12">
				<div class="text-center">
					<LayoutGrid class="text-muted-foreground mx-auto h-12 w-12" />
					<h3 class="mt-4 text-lg font-medium">No Pipelines</h3>
					<p class="text-muted-foreground mt-2">
						Create your first pipeline to visualize your {module.name.toLowerCase()} as a kanban board.
					</p>
					<Button class="mt-6" onclick={createPipeline}>
						<Plus class="mr-2 h-4 w-4" />
						Create Pipeline
					</Button>
				</div>
			</Card.Root>
		{:else}
			<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
				{#each pipelines as pipeline}
					<Card.Root
						class="cursor-pointer transition-shadow hover:shadow-md"
						onclick={() => openPipeline(pipeline.id)}
					>
						<Card.Header>
							<div class="flex items-start justify-between">
								<div>
									<Card.Title>{pipeline.name}</Card.Title>
									<Card.Description>
										{pipeline.stages?.length || 0} stages
									</Card.Description>
								</div>
								<Button
									variant="ghost"
									size="icon"
									onclick={(e) => {
										e.stopPropagation();
										goto(`/pipelines/${moduleApiName}/${pipeline.id}/settings`);
									}}
								>
									<Settings class="h-4 w-4" />
								</Button>
							</div>
						</Card.Header>
						<Card.Content>
							{#if pipeline.stages && pipeline.stages.length > 0}
								<div class="flex gap-1">
									{#each pipeline.stages.sort((a, b) => a.display_order - b.display_order) as stage}
										<div
											class="h-2 flex-1 rounded-full"
											style="background-color: {stage.color}"
											title="{stage.name} ({stage.probability}%)"
										></div>
									{/each}
								</div>
								<div class="text-muted-foreground mt-2 flex justify-between text-xs">
									<span>{pipeline.stages[0].name}</span>
									<span>{pipeline.stages[pipeline.stages.length - 1].name}</span>
								</div>
							{/if}
						</Card.Content>
					</Card.Root>
				{/each}
			</div>
		{/if}
	{/if}
</div>

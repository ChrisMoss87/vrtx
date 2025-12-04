<script lang="ts">
	import { onMount } from 'svelte';
	import type { Pipeline } from '$lib/api/pipelines';
	import { getPipelines } from '$lib/api/pipelines';
	import { getModules, type Module } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Spinner } from '$lib/components/ui/spinner';
	import * as Card from '$lib/components/ui/card';
	import { toast } from 'svelte-sonner';
	import LayoutGrid from 'lucide-svelte/icons/layout-grid';
	import Settings from 'lucide-svelte/icons/settings';

	let loading = $state(true);
	let pipelines = $state<Pipeline[]>([]);
	let modules = $state<Module[]>([]);

	// Group pipelines by module
	let pipelinesByModule = $derived(() => {
		const grouped = new Map<number, { module: Module | undefined; pipelines: Pipeline[] }>();
		for (const pipeline of pipelines) {
			if (!grouped.has(pipeline.module_id)) {
				grouped.set(pipeline.module_id, {
					module: modules.find((m) => m.id === pipeline.module_id),
					pipelines: []
				});
			}
			grouped.get(pipeline.module_id)!.pipelines.push(pipeline);
		}
		return grouped;
	});

	onMount(async () => {
		try {
			const [pipelinesData, modulesData] = await Promise.all([
				getPipelines({ active: true }),
				getModules()
			]);
			pipelines = pipelinesData;
			modules = modulesData;
		} catch (error: any) {
			console.error('Failed to load pipelines:', error);
			toast.error('Failed to load pipelines');
		} finally {
			loading = false;
		}
	});

	function getPipelineHref(pipeline: Pipeline): string {
		const module = modules.find((m) => m.id === pipeline.module_id);
		if (module) {
			return `/pipelines/${module.api_name}/${pipeline.id}`;
		}
		return `/admin/pipelines/${pipeline.id}/edit`;
	}
</script>

<svelte:head>
	<title>Pipelines</title>
</svelte:head>

<div class="container mx-auto py-6">
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Pipelines</h1>
			<p class="text-muted-foreground">View and manage your sales pipelines</p>
		</div>
		<Button variant="outline" href="/admin/pipelines">
			<Settings class="mr-2 h-4 w-4" />
			Manage Pipelines
		</Button>
	</div>

	{#if loading}
		<div class="flex h-64 items-center justify-center">
			<Spinner class="h-8 w-8" />
		</div>
	{:else if pipelines.length === 0}
		<div class="rounded-lg border border-dashed p-12 text-center">
			<LayoutGrid class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
			<h3 class="mb-2 text-lg font-semibold">No pipelines available</h3>
			<p class="mb-4 text-muted-foreground">Create a pipeline to start tracking deals</p>
			<Button href="/admin/pipelines/create">Create Pipeline</Button>
		</div>
	{:else}
		<div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
			{#each pipelines as pipeline}
				<a href={getPipelineHref(pipeline)} class="group">
					<Card.Root class="transition-all hover:border-primary hover:shadow-md">
						<Card.Header>
							<Card.Title class="flex items-center justify-between">
								<span>{pipeline.name}</span>
								{#if pipeline.module}
									<Badge variant="secondary">{pipeline.module.name}</Badge>
								{/if}
							</Card.Title>
							<Card.Description>
								{pipeline.stages?.length || 0} stages
							</Card.Description>
						</Card.Header>
						<Card.Content>
							<!-- Stage preview -->
							{#if pipeline.stages && pipeline.stages.length > 0}
								<div class="flex items-center gap-1">
									{#each pipeline.stages as stage}
										<div class="flex-1">
											<div
												class="h-2 rounded-full"
												style="background-color: {stage.color}"
												title={stage.name}
											></div>
										</div>
									{/each}
								</div>
								<div class="mt-2 flex justify-between text-xs text-muted-foreground">
									<span>{pipeline.stages[0].name}</span>
									<span>{pipeline.stages[pipeline.stages.length - 1].name}</span>
								</div>
							{/if}
						</Card.Content>
					</Card.Root>
				</a>
			{/each}
		</div>
	{/if}
</div>

<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/state';
	import { goto } from '$app/navigation';
	import type { Pipeline } from '$lib/api/pipelines';
	import { getPipeline } from '$lib/api/pipelines';
	import { getModules } from '$lib/api/modules';
	import { PipelineBuilder } from '$lib/components/pipeline-builder';
	import { Spinner } from '$lib/components/ui/spinner';
	import { Button } from '$lib/components/ui/button';
	import ArrowLeft from 'lucide-svelte/icons/arrow-left';
	import { toast } from 'svelte-sonner';

	interface Module {
		id: number;
		name: string;
		api_name: string;
		fields?: Array<{ api_name: string; label: string; type: string }>;
	}

	let loading = $state(true);
	let pipeline = $state<Pipeline | null>(null);
	let modules = $state<Module[]>([]);

	let pipelineId = $derived(parseInt(page.params.id || '0'));

	onMount(async () => {
		try {
			const [pipelineData, modulesData] = await Promise.all([
				getPipeline(pipelineId),
				getModules()
			]);
			pipeline = pipelineData;
			modules = modulesData;
		} catch (error: any) {
			console.error('Failed to load data:', error);
			toast.error('Failed to load pipeline');
			goto('/admin/pipelines');
		} finally {
			loading = false;
		}
	});

	function handleSave() {
		goto('/admin/pipelines');
	}

	function handleCancel() {
		goto('/admin/pipelines');
	}
</script>

<svelte:head>
	<title>Edit Pipeline | Admin</title>
</svelte:head>

<div class="container mx-auto max-w-4xl py-6">
	<div class="mb-6">
		<Button variant="ghost" href="/admin/pipelines" class="mb-4">
			<ArrowLeft class="mr-2 h-4 w-4" />
			Back to Pipelines
		</Button>
		<h1 class="text-2xl font-bold">Edit Pipeline</h1>
		<p class="text-muted-foreground">Update pipeline settings and stages</p>
	</div>

	{#if loading}
		<div class="flex h-64 items-center justify-center">
			<Spinner class="h-8 w-8" />
		</div>
	{:else if pipeline}
		<div class="rounded-lg border bg-card p-6">
			<PipelineBuilder {pipeline} {modules} onSave={handleSave} onCancel={handleCancel} />
		</div>
	{/if}
</div>

<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { getModules } from '$lib/api/modules';
	import { PipelineBuilder } from '$lib/components/pipeline-builder';
	import { Spinner } from '$lib/components/ui/spinner';
	import { Button } from '$lib/components/ui/button';
	import ArrowLeft from 'lucide-svelte/icons/arrow-left';

	interface Module {
		id: number;
		name: string;
		api_name: string;
		fields?: Array<{ api_name: string; label: string; type: string }>;
	}

	let loading = $state(true);
	let modules = $state<Module[]>([]);

	onMount(async () => {
		try {
			modules = await getModules();
		} catch (error) {
			console.error('Failed to load modules:', error);
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
	<title>Create Pipeline | Admin</title>
</svelte:head>

<div class="container mx-auto max-w-4xl py-6">
	<div class="mb-6">
		<Button variant="ghost" href="/admin/pipelines" class="mb-4">
			<ArrowLeft class="mr-2 h-4 w-4" />
			Back to Pipelines
		</Button>
		<h1 class="text-2xl font-bold">Create Pipeline</h1>
		<p class="text-muted-foreground">Create a new sales pipeline with stages</p>
	</div>

	{#if loading}
		<div class="flex h-64 items-center justify-center">
			<Spinner class="h-8 w-8" />
		</div>
	{:else}
		<div class="rounded-lg border bg-card p-6">
			<PipelineBuilder {modules} onSave={handleSave} onCancel={handleCancel} />
		</div>
	{/if}
</div>
